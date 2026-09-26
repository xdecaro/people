<?php

namespace xdecaro\Component\People\Administrator\Service;
defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\Database\DatabaseInterface;
use RuntimeException;
use Throwable;
use ZipArchive;

final class RestoreService
{
    private const REQUIRED_ENTRIES = ['manifest.json', 'data.json', 'SHA256SUMS.txt'];

    public function __construct(
        private DatabaseInterface $db,
        private MaintenanceLogService $maintenanceLog,
        private ?int $maxUploadMb = null,
        private ?BackupService $backupService = null
    ) {}

    public function preview(string $zipPath, int $actorUserId): array
    {
        $result = $this->emptyResult();
        $subjectUuid = null;
        try {
            if (!is_file($zipPath)) { $result['blocking_errors'][] = 'file_missing'; return $this->finishPreview($result, null, $actorUserId); }
            $limitMb = $this->maxUploadMb ?? (int) ComponentHelper::getParams('com_xdecaropeople')->get('backup_max_upload_mb', 64);
            $size = filesize($zipPath);
            if ($size === false || $size > max(1, $limitMb) * 1024 * 1024) { $result['blocking_errors'][] = 'file_too_large'; return $this->finishPreview($result, null, $actorUserId); }

            $zip = new ZipArchive();
            if ($zip->open($zipPath) !== true) { $result['blocking_errors'][] = 'invalid_zip'; return $this->finishPreview($result, null, $actorUserId); }
            try {
                $entries=[]; for($i=0;$i<$zip->numFiles;$i++) $entries[]=(string)$zip->getNameIndex($i);
                sort($entries,SORT_STRING); $expected=self::REQUIRED_ENTRIES; sort($expected,SORT_STRING);
                if($entries!==$expected){$result['blocking_errors'][]='unexpected_archive_entries';return $this->finishPreview($result,null,$actorUserId);}
                $manifestJson=$zip->getFromName('manifest.json');$dataJson=$zip->getFromName('data.json');$sums=$zip->getFromName('SHA256SUMS.txt');
            } finally { $zip->close(); }
            if(!is_string($manifestJson)||!is_string($dataJson)||!is_string($sums)){$result['blocking_errors'][]='missing_archive_entry';return $this->finishPreview($result,null,$actorUserId);}
            $dataSha=hash('sha256',$dataJson);$manifestSha=hash('sha256',$manifestJson);$sumMap=$this->parseChecksums($sums);
            if(!isset($sumMap['data.json'],$sumMap['manifest.json'])||!hash_equals($dataSha,$sumMap['data.json'])||!hash_equals($manifestSha,$sumMap['manifest.json'])){$result['blocking_errors'][]='checksum_mismatch';return $this->finishPreview($result,null,$actorUserId);}

            $manifest=json_decode($manifestJson,true,512,JSON_THROW_ON_ERROR);$data=json_decode($dataJson,true,512,JSON_THROW_ON_ERROR);
            if(!is_array($manifest)||!is_array($data)){$result['blocking_errors'][]='invalid_json';return $this->finishPreview($result,null,$actorUserId);}
            $subjectUuid=is_string($manifest['backup_uuid']??null)?strtolower((string)$manifest['backup_uuid']):null;$result['manifest']=$manifest;$result['payload_sha256']=$dataSha;
            if(($manifest['format']??null)!==BackupService::FORMAT||(int)($manifest['format_version']??0)!==BackupService::FORMAT_VERSION)$result['blocking_errors'][]='unsupported_format';
            if(($manifest['schema_version']??null)!==BackupService::SCHEMA_VERSION)$result['blocking_errors'][]='unsupported_schema';
            if(!hash_equals($dataSha,(string)($manifest['payload_sha256']??'')))$result['blocking_errors'][]='payload_hash_mismatch';
            if(isset($manifest['component_version'])&&version_compare((string)$manifest['component_version'],BackupService::SCHEMA_VERSION,'<'))$result['warnings'][]='older_component_version';

            $tables=$data['tables']??null;
            if(!is_array($tables)||array_keys($tables)!==BackupService::BACKUP_TABLES){$result['blocking_errors'][]='invalid_table_whitelist';}
            else{
                $counts=[];foreach(BackupService::BACKUP_TABLES as $table){if(!is_array($tables[$table])){$result['blocking_errors'][]='invalid_table_rows';continue;}$counts[$table]=count($tables[$table]);}$result['counts']=$counts;
                $manifestCounts=$manifest['table_counts']??[];foreach($counts as $table=>$count){if(!isset($manifestCounts[$table])||(int)$manifestCounts[$table]!==$count){$result['blocking_errors'][]='count_mismatch';break;}}
                $active=0;$trashed=0;foreach($tables['#__xdecaropeople_people'] as $row){if(!is_array($row)||!$this->isUuid((string)($row['uuid']??''))){$result['blocking_errors'][]='invalid_person_uuid';break;}$state=(int)($row['state']??0);if($state===-2)$trashed++;elseif($state>=0)$active++;}$result['people_active']=$active;$result['people_trashed']=$trashed;
                foreach($tables['#__xdecaropeople_merges'] as $row){if(!is_array($row)||!$this->isUuid((string)($row['source_uuid']??''))||!$this->isUuid((string)($row['target_uuid']??''))){$result['blocking_errors'][]='invalid_merge_uuid';break;}}
            }
        } catch(Throwable){$result['blocking_errors'][]='invalid_backup';}
        $result['blocking_errors']=array_values(array_unique($result['blocking_errors']));$result['warnings']=array_values(array_unique($result['warnings']));$result['compatible']=$result['blocking_errors']===[];
        return $this->finishPreview($result,$subjectUuid,$actorUserId);
    }

    public function restoreFull(string $zipPath, int $actorUserId): array
    {
        $preview=$this->preview($zipPath,$actorUserId);
        if(($preview['compatible']??false)!==true) throw new RuntimeException('People backup is not compatible with this restore operation.');
        if(!$this->backupService) throw new RuntimeException('People backup service is unavailable for restore safety backup.');

        $safety=$this->backupService->create($actorUserId,'pre_restore');
        $tables=$this->readPayloadTables($zipPath);
        $restored=[];
        $this->db->transactionStart();
        try{
            foreach(array_reverse(BackupService::BACKUP_TABLES) as $table){$query=$this->db->getQuery(true)->delete($this->db->quoteName($table));$this->db->setQuery($query)->execute();}
            foreach(BackupService::BACKUP_TABLES as $table){
                foreach($tables[$table] as $row){if(!is_array($row))throw new RuntimeException('Invalid People restore row.');$object=(object)$row;$this->db->insertObject($table,$object);}
                $query=$this->db->getQuery(true)->select('COUNT(*)')->from($this->db->quoteName($table));$restored[$table]=(int)$this->db->setQuery($query)->loadResult();
                if($restored[$table]!==count($tables[$table]))throw new RuntimeException('People restore row-count verification failed.');
            }
            $this->db->transactionCommit();
        }catch(Throwable $e){$this->db->transactionRollback();throw $e;}

        $backupUuid=(string)($preview['manifest']['backup_uuid']??'');
        $this->maintenanceLog->log('restore_full',$this->isUuid($backupUuid)?$backupUuid:null,$actorUserId,[
            'safety_backup_uuid'=>(string)$safety['uuid'],'restored_counts'=>$restored,'payload_sha256'=>(string)($preview['payload_sha256']??'')
        ]);
        return ['safety_backup_uuid'=>(string)$safety['uuid'],'restored_counts'=>$restored,'integrity_ok'=>true,'warnings'=>$preview['warnings']??[]];
    }

    private function readPayloadTables(string $zipPath): array
    {
        $zip=new ZipArchive();if($zip->open($zipPath)!==true)throw new RuntimeException('Unable to open People backup ZIP.');
        try{$dataJson=$zip->getFromName('data.json');}finally{$zip->close();}
        if(!is_string($dataJson))throw new RuntimeException('People backup data.json is unavailable.');
        $data=json_decode($dataJson,true,512,JSON_THROW_ON_ERROR);$tables=$data['tables']??null;
        if(!is_array($tables)||array_keys($tables)!==BackupService::BACKUP_TABLES)throw new RuntimeException('People backup table whitelist is invalid.');
        return $tables;
    }

    private function emptyResult():array{return ['compatible'=>false,'blocking_errors'=>[],'warnings'=>[],'manifest'=>[],'counts'=>[],'people_active'=>0,'people_trashed'=>0,'payload_sha256'=>''];}
    private function finishPreview(array $result,?string $subjectUuid,int $actorUserId):array{$result['blocking_errors']=array_values(array_unique($result['blocking_errors']??[]));$result['warnings']=array_values(array_unique($result['warnings']??[]));$result['compatible']=$result['blocking_errors']===[];try{$this->maintenanceLog->log('restore_preview',$this->isUuid((string)$subjectUuid)?$subjectUuid:null,$actorUserId,['compatible'=>(bool)$result['compatible'],'blocking_errors'=>$result['blocking_errors'],'warnings'=>$result['warnings'],'people_count'=>array_sum([(int)($result['people_active']??0),(int)($result['people_trashed']??0)]),'payload_sha256'=>(string)($result['payload_sha256']??'')]);}catch(Throwable){}return $result;}
    private function parseChecksums(string $contents):array{$result=[];foreach(preg_split('/\R/',trim($contents))?:[] as $line){if(preg_match('/^([0-9a-f]{64})\s{2}([^\s]+)$/i',trim($line),$m))$result[$m[2]]=strtolower($m[1]);}return $result;}
    private function isUuid(string $value):bool{return(bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',trim($value));}
}

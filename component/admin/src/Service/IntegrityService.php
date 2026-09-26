<?php

namespace xdecaro\Component\People\Administrator\Service;
defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;

final class IntegrityService
{
    public function __construct(private DatabaseInterface $db, private BackupStorageService $storage, private MaintenanceLogService $log) {}

    public function check(int $actorUserId = 0, bool $writeLog = true): array
    {
        $tables = array_flip($this->db->getTableList());
        $required = ['#__xdecaropeople_people','#__xdecaropeople_history','#__xdecaropeople_duplicate_ignores','#__xdecaropeople_merges','#__xdecaropeople_backups','#__xdecaropeople_maintenance_log'];
        $tableState = [];
        foreach ($required as $table) $tableState[$table] = isset($tables[$this->db->replacePrefix($table)]);

        $uuidMissing = $this->scalar("SELECT COUNT(*) FROM #__xdecaropeople_people WHERE uuid IS NULL OR uuid = ''");
        $uuidDuplicates = $this->scalar("SELECT COUNT(*) FROM (SELECT uuid FROM #__xdecaropeople_people WHERE uuid IS NOT NULL AND uuid <> '' GROUP BY uuid HAVING COUNT(*) > 1) d");
        $userDuplicates = $this->scalar("SELECT COUNT(*) FROM (SELECT user_id FROM #__xdecaropeople_people WHERE user_id IS NOT NULL GROUP BY user_id HAVING COUNT(*) > 1) d");
        $orphanHistory = $this->scalar("SELECT COUNT(*) FROM #__xdecaropeople_history h LEFT JOIN #__xdecaropeople_people p ON p.id=h.person_id WHERE p.id IS NULL");
        $brokenMerges = $this->scalar("SELECT COUNT(*) FROM #__xdecaropeople_merges m LEFT JOIN #__xdecaropeople_people t ON t.uuid=m.target_uuid WHERE t.uuid IS NULL");
        $storage = $this->storage->isHealthy();
        $zipOk = class_exists(\ZipArchive::class);
        $jsonOk = function_exists('json_encode') && function_exists('json_decode');
        $ok = !in_array(false,$tableState,true) && $uuidMissing===0 && $uuidDuplicates===0 && $userDuplicates===0 && $orphanHistory===0 && $brokenMerges===0 && !empty($storage['ok']) && $zipOk && $jsonOk;

        $result = [
            'ok'=>$ok,
            'tables'=>$tableState,
            'uuid_missing'=>$uuidMissing,
            'uuid_duplicates'=>$uuidDuplicates,
            'user_id_duplicates'=>$userDuplicates,
            'orphan_history'=>$orphanHistory,
            'broken_merges'=>$brokenMerges,
            'backup_storage'=>['ok'=>(bool)($storage['ok']??false),'message'=>(string)($storage['message']??'')],
            'php_zip'=>$zipOk,
            'php_json'=>$jsonOk,
        ];
        if ($writeLog) $this->log->log('integrity_check',null,$actorUserId,['ok'=>$ok,'uuid_missing'=>$uuidMissing,'uuid_duplicates'=>$uuidDuplicates,'user_id_duplicates'=>$userDuplicates,'orphan_history'=>$orphanHistory,'broken_merges'=>$brokenMerges,'backup_storage'=>(bool)($storage['ok']??false)]);
        return $result;
    }

    private function scalar(string $sql): int
    {
        try { return (int)$this->db->setQuery($sql)->loadResult(); } catch (\Throwable) { return -1; }
    }
}

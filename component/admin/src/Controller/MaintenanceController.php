<?php

namespace xdecaro\Component\People\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use RuntimeException;
use xdecaro\Component\People\Administrator\Extension\PeopleComponent;

final class MaintenanceController extends BaseController
{
    protected $option = 'com_xdecaropeople';

    public function createBackup(): void
    {
        $this->checkToken();
        $user = $this->app->getIdentity();
        $this->requirePermission('people.backup');
        $backup = $this->component()->getBackupService()->create((int) $user->id, 'manual');
        $this->redirectInformation('Backup creato: ' . ($backup['filename'] ?? $backup['uuid'] ?? ''), 'success');
    }

    public function downloadBackup(): void
    {
        $this->checkToken('get');
        $this->requirePermission('people.backup');
        $uuid = strtolower(trim($this->input->getString('backup_uuid')));
        $backup = $this->component()->getBackupService()->resolveDownload($uuid);
        $path = (string) ($backup['path'] ?? '');
        if ($path === '' || !is_file($path)) {
            throw new RuntimeException('Backup non disponibile.', 404);
        }
        $name = basename((string) ($backup['filename'] ?? basename($path)));
        $this->app->setHeader('Content-Type', 'application/zip', true);
        $this->app->setHeader('Content-Disposition', 'attachment; filename="' . addslashes($name) . '"', true);
        $this->app->setHeader('Content-Length', (string) filesize($path), true);
        $this->app->sendHeaders();
        readfile($path);
        $this->app->close();
    }

    public function deleteBackup(): void
    {
        $this->checkToken();
        $this->requirePermission('people.backup');
        $uuid = strtolower(trim($this->input->getString('backup_uuid')));
        if (!$this->input->getBool('confirm_delete_backup')) {
            throw new RuntimeException('La cancellazione del backup richiede conferma esplicita.', 400);
        }
        $this->component()->getBackupService()->delete($uuid, (int) $this->app->getIdentity()->id);
        $this->redirectInformation('Backup eliminato.', 'success');
    }

    public function previewRestore(): void
    {
        $this->checkToken();
        $this->requirePermission('people.restore');

        try {
            $path = $this->restorePath();
            $preview = $this->component()->getRestoreService()->preview($path, (int) $this->app->getIdentity()->id);
        } catch (RuntimeException $e) {
            $this->redirectInformation($this->restoreFailureMessage($e), 'warning');
            return;
        }

        $summary = sprintf(
            'Backup valido: %d persone (%d attive, %d nel cestino). Versione People %s.',
            (int) ($preview['counts']['#__xdecaropeople_people'] ?? 0),
            (int) ($preview['people_active'] ?? 0),
            (int) ($preview['people_trashed'] ?? 0),
            (string) ($preview['manifest']['component_version'] ?? '-')
        );
        $this->redirectInformation($summary, 'success');
    }

    public function restoreFull(): void
    {
        $this->checkToken();
        $this->requirePermission('people.restore');
        if (!$this->input->getBool('confirm_restore')) {
            throw new RuntimeException('Il ripristino completo richiede conferma esplicita.', 400);
        }

        try {
            $result = $this->component()->getRestoreService()->restoreFull(
                $this->restorePath(),
                (int) $this->app->getIdentity()->id
            );
        } catch (RuntimeException $e) {
            $this->redirectInformation($this->restoreFailureMessage($e), 'warning');
            return;
        }

        $this->redirectInformation('Ripristino completo terminato. È stato creato automaticamente un backup di sicurezza ' . ($result['safety_backup_uuid'] ?? '') . '.', 'success');
    }

    public function restorePerson(): void
    {
        $this->checkToken();
        $this->requirePermission('people.restore');
        $uuid = strtolower(trim($this->input->getString('person_uuid')));
        if ($uuid === '') {
            throw new RuntimeException('UUID persona obbligatorio.', 400);
        }

        try {
            $result = $this->component()->getRestoreService()->restorePerson(
                $this->restorePath(),
                $uuid,
                (int) $this->app->getIdentity()->id,
                $this->input->getBool('overwrite_person')
            );
        } catch (RuntimeException $e) {
            $this->redirectInformation($this->restoreFailureMessage($e), 'warning');
            return;
        }

        $this->redirectInformation('Persona ripristinata: ' . ($result['uuid'] ?? $uuid) . '.', 'success');
    }

    public function restoreTrash(): void
    {
        $this->checkToken();
        $this->requirePermission('core.edit.state');
        $id = $this->input->getInt('person_id');
        $count = $this->component()->getPersonTrashService()->restore([$id], (int) $this->app->getIdentity()->id);
        $this->redirectInformation($count === 1 ? 'Persona ripristinata dal cestino.' : 'Nessuna persona ripristinata.', $count === 1 ? 'success' : 'warning');
    }

    public function purgePerson(): void
    {
        $this->checkToken();
        $this->requirePermission('core.delete');
        if (!$this->input->getBool('confirm_purge')) {
            throw new RuntimeException('L’eliminazione definitiva richiede conferma esplicita.', 400);
        }
        $id = $this->input->getInt('person_id');
        $count = $this->component()->getPersonTrashService()->purge([$id], (int) $this->app->getIdentity()->id);
        $this->redirectInformation($count === 1 ? 'Persona eliminata definitivamente.' : 'Nessuna persona eliminata.', $count === 1 ? 'success' : 'warning');
    }

    public function checkIntegrity(): void
    {
        $this->checkToken();
        $this->requirePermission('core.manage');
        $result = $this->component()->getIntegrityService()->check((int) $this->app->getIdentity()->id, true);
        $this->redirectInformation($result['ok'] ? 'Controllo integrità completato: nessuna anomalia.' : 'Controllo integrità completato: sono presenti elementi da verificare.', $result['ok'] ? 'success' : 'warning');
    }

    private function restorePath(): string
    {
        $backupUuid = strtolower(trim($this->input->getString('backup_uuid')));
        if ($backupUuid !== '') {
            $backup = $this->component()->getBackupService()->resolveDownload($backupUuid);
            return (string) ($backup['path'] ?? '');
        }
        $upload = $this->input->files->get('restore_file', null, 'raw');
        $path = is_array($upload) ? (string) ($upload['tmp_name'] ?? '') : '';
        if ($path === '' || !is_uploaded_file($path)) {
            throw new RuntimeException('Seleziona un backup People valido.', 400);
        }
        return $path;
    }

    private function restoreFailureMessage(RuntimeException $e): string
    {
        $this->app->getLanguage()->load('com_xdecaropeople.maintenance', JPATH_ADMINISTRATOR, null, true);

        return Text::_('COM_XDECAROPEOPLE_RESTORE_INVALID_BACKUP');
    }

    private function requirePermission(string $action): void
    {
        if (!$this->app->getIdentity()->authorise($action, 'com_xdecaropeople')) {
            throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }

    private function component(): PeopleComponent
    {
        $component = $this->app->bootComponent('com_xdecaropeople');
        if (!$component instanceof PeopleComponent) {
            throw new RuntimeException('People component service is unavailable.');
        }
        return $component;
    }

    private function redirectInformation(string $message, string $type = 'message'): void
    {
        $this->app->enqueueMessage($message, $type);
        $this->setRedirect('index.php?option=com_xdecaropeople&view=information');
    }
}

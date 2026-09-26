<?php

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

$d = $this->diagnostics;
$db = $this->databaseSummary;
$integrity = (array) ($d['integrity'] ?? []);
$missing = (array) ($db['missing'] ?? []);
$token = Session::getFormToken();
$fmtBytes = static function (int $bytes): string {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return number_format($bytes / 1024, 1, ',', '.') . ' KB';
    return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
};
$actionLabel = static function (string $action): string {
    return match ($action) {
        'backup_create' => 'Backup creato',
        'backup_download' => 'Backup scaricato',
        'backup_delete' => 'Backup eliminato',
        'restore_preview' => 'Anteprima restore',
        'restore_full' => 'Restore completo',
        'restore_person' => 'Restore persona',
        'trash_person' => 'Persona cestinata',
        'restore_trash_person' => 'Persona ripristinata',
        'purge_person' => 'Persona eliminata definitivamente',
        'integrity_check' => 'Controllo integrità',
        default => $action,
    };
};
?>
<div class="xdecaro-scope xdecaro-information">
    <section class="xdecaro-info-grid xdecaro-info-grid--top" aria-label="Prodotto e ambiente">
        <article class="xdecaro-info-card">
            <div class="xdecaro-info-card__head"><h2>Prodotto e ambiente</h2><span class="badge bg-primary">People <?php echo $this->escape((string) ($d['component_version'] ?? '-')); ?></span></div>
            <dl class="xdecaro-info-dl">
                <div><dt>People</dt><dd><?php echo $this->escape((string) ($d['component_version'] ?? '-')); ?></dd></div>
                <div><dt>Joomla</dt><dd><?php echo $this->escape((string) ($d['joomla_version'] ?? '-')); ?></dd></div>
                <div><dt>PHP</dt><dd><?php echo $this->escape((string) ($d['php_version'] ?? '-')); ?></dd></div>
                <div><dt>Core</dt><dd><?php echo $this->escape((string) ($d['core_version'] ?? '-')); ?></dd></div>
            </dl>
        </article>

        <article class="xdecaro-info-card">
            <div class="xdecaro-info-card__head"><h2>Database People</h2><span class="badge <?php echo !empty($d['table_ok']) ? 'bg-success' : 'bg-warning text-dark'; ?>"><?php echo !empty($d['table_ok']) ? 'OK' : 'Attenzione'; ?></span></div>
            <div class="xdecaro-stat-row">
                <div><strong><?php echo (int) ($db['total'] ?? 0); ?></strong><span>Totale</span></div>
                <div><strong><?php echo (int) ($db['active'] ?? 0); ?></strong><span>Pubblicate</span></div>
                <div><strong><?php echo (int) ($db['unpublished'] ?? 0); ?></strong><span>Sospese</span></div>
                <div><strong><?php echo (int) ($db['trashed'] ?? 0); ?></strong><span>Cestinate</span></div>
            </div>
            <h3 class="xdecaro-info-subtitle">Dati mancanti</h3>
            <div class="xdecaro-mini-grid">
                <span>Nascita <b><?php echo (int) ($missing['birth_date'] ?? 0); ?></b></span>
                <span>Sesso <b><?php echo (int) ($missing['sex'] ?? 0); ?></b></span>
                <span>TIN <b><?php echo (int) ($missing['tax_identifier'] ?? 0); ?></b></span>
                <span>Luogo nascita <b><?php echo (int) ($missing['birth_place'] ?? 0); ?></b></span>
                <span>Indirizzo <b><?php echo (int) ($missing['address_line'] ?? 0); ?></b></span>
                <span>Email <b><?php echo (int) ($missing['email'] ?? 0); ?></b></span>
                <span>Telefono <b><?php echo (int) ($missing['phone'] ?? 0); ?></b></span>
                <span>Nazionalità <b><?php echo (int) ($missing['nationality_code'] ?? 0); ?></b></span>
            </div>
        </article>
    </section>

    <section class="xdecaro-info-card">
        <div class="xdecaro-info-card__head"><h2>Componenti collegati</h2><span class="text-muted">Ecosistema xdecaro</span></div>
        <div class="xdecaro-components-grid">
            <?php foreach ($this->connectedComponents as $component): ?>
                <a class="xdecaro-component-tile <?php echo !empty($component['enabled']) ? 'is-ok' : 'is-off'; ?>" href="<?php echo Route::_((string) $component['url']); ?>">
                    <strong><?php echo $this->escape((string) $component['name']); ?></strong>
                    <span><?php echo !empty($component['enabled']) ? 'Collegato' : (!empty($component['installed']) ? 'Disabilitato' : 'Non disponibile'); ?></span>
                    <?php if (!empty($component['version'])): ?><small>v<?php echo $this->escape((string) $component['version']); ?></small><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="xdecaro-info-card">
        <div class="xdecaro-info-card__head">
            <div><h2>Diagnostica e integrità</h2><p class="mb-0 text-muted">Controlli tecnici sui dati People e sullo storage privato dei backup.</p></div>
            <span class="badge <?php echo !empty($integrity['ok']) ? 'bg-success' : 'bg-warning text-dark'; ?>"><?php echo !empty($integrity['ok']) ? 'Tutto corretto' : 'Da verificare'; ?></span>
        </div>
        <div class="xdecaro-integrity-grid">
            <div><span>UUID mancanti</span><strong><?php echo (int) ($integrity['uuid_missing'] ?? -1); ?></strong></div>
            <div><span>UUID duplicati</span><strong><?php echo (int) ($integrity['uuid_duplicates'] ?? -1); ?></strong></div>
            <div><span>User ID duplicati</span><strong><?php echo (int) ($integrity['user_id_duplicates'] ?? -1); ?></strong></div>
            <div><span>Storico orfano</span><strong><?php echo (int) ($integrity['orphan_history'] ?? -1); ?></strong></div>
            <div><span>Merge non validi</span><strong><?php echo (int) ($integrity['broken_merges'] ?? -1); ?></strong></div>
            <div><span>Backup storage</span><strong><?php echo !empty($integrity['backup_storage']['ok']) ? 'OK' : 'Errore'; ?></strong></div>
            <div><span>PHP ZIP</span><strong><?php echo !empty($integrity['php_zip']) ? 'OK' : 'Manca'; ?></strong></div>
            <div><span>JSON</span><strong><?php echo !empty($integrity['php_json']) ? 'OK' : 'Manca'; ?></strong></div>
        </div>
        <form method="post" action="<?php echo Route::_('index.php?option=com_xdecaropeople&task=maintenance.checkIntegrity'); ?>" class="mt-3">
            <button type="submit" class="btn btn-outline-primary">Ricontrolla integrità</button>
            <?php echo HTMLHelper::_('form.token'); ?>
        </form>
    </section>

    <section class="xdecaro-info-card xdecaro-info-card--database">
        <div class="xdecaro-info-card__head"><div><h2>Gestione database</h2><p class="mb-0 text-muted">Backup People, restore controllato e recupero delle persone cancellate.</p></div></div>
        <div class="xdecaro-management-grid">
            <div class="xdecaro-management-panel">
                <h3>Backup</h3>
                <p>Crea un archivio completo delle quattro tabelle funzionali People. Non è un export CSV/Excel.</p>
                <?php if ($this->canBackup): ?>
                    <form method="post" action="<?php echo Route::_('index.php?option=com_xdecaropeople&task=maintenance.createBackup'); ?>">
                        <button type="submit" class="btn btn-primary">Crea backup adesso</button>
                        <?php echo HTMLHelper::_('form.token'); ?>
                    </form>
                <?php endif; ?>
            </div>
            <div class="xdecaro-management-panel">
                <h3>Restore</h3>
                <p>Prima puoi verificare il file. Il restore completo crea automaticamente un backup di sicurezza.</p>
                <?php if ($this->canRestore): ?>
                    <form method="post" enctype="multipart/form-data" action="<?php echo Route::_('index.php?option=com_xdecaropeople&task=maintenance.previewRestore'); ?>" class="xdecaro-restore-form">
                        <input type="file" class="form-control" name="restore_file" accept=".zip,application/zip" required>
                        <button type="submit" class="btn btn-outline-primary">Controlla backup</button>
                        <?php echo HTMLHelper::_('form.token'); ?>
                    </form>
                    <form method="post" enctype="multipart/form-data" action="<?php echo Route::_('index.php?option=com_xdecaropeople&task=maintenance.restoreFull'); ?>" class="xdecaro-restore-form mt-2" onsubmit="return confirm('Ripristinare tutto People? Verrà creato prima un backup di sicurezza.');">
                        <input type="file" class="form-control" name="restore_file" accept=".zip,application/zip" required>
                        <input type="hidden" name="confirm_restore" value="1">
                        <button type="submit" class="btn btn-danger">Ripristina tutto</button>
                        <?php echo HTMLHelper::_('form.token'); ?>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="xdecaro-info-card">
        <div class="xdecaro-info-card__head"><h2>Backup disponibili</h2><span class="badge bg-secondary"><?php echo count($this->backups); ?></span></div>
        <?php if (!$this->backups): ?>
            <p class="text-muted mb-0">Nessun backup disponibile.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Data</th><th>Persone</th><th>Versione</th><th>Dimensione</th><th>SHA256</th><th class="text-end">Azioni</th></tr></thead>
                    <tbody>
                    <?php foreach ($this->backups as $backup): ?>
                        <tr>
                            <td><?php echo $this->escape((string) ($backup['created'] ?? '-')); ?></td>
                            <td><?php echo (int) ($backup['people_count'] ?? 0); ?></td>
                            <td><?php echo $this->escape((string) ($backup['component_version'] ?? '-')); ?></td>
                            <td><?php echo $fmtBytes((int) ($backup['size_bytes'] ?? 0)); ?></td>
                            <td><code class="xdecaro-hash"><?php echo $this->escape(substr((string) ($backup['sha256'] ?? ''), 0, 12)); ?>…</code></td>
                            <td class="text-end xdecaro-actions-cell">
                                <?php if ($this->canBackup): ?>
                                    <a class="btn btn-sm btn-outline-primary" href="<?php echo Route::_('index.php?option=com_xdecaropeople&task=maintenance.downloadBackup&backup_uuid=' . urlencode((string) $backup['uuid']) . '&' . $token . '=1'); ?>">Scarica</a>
                                    <form method="post" action="<?php echo Route::_('index.php?option=com_xdecaropeople&task=maintenance.deleteBackup'); ?>" onsubmit="return confirm('Eliminare questo backup?');">
                                        <input type="hidden" name="backup_uuid" value="<?php echo $this->escape((string) $backup['uuid']); ?>">
                                        <input type="hidden" name="confirm_delete_backup" value="1">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Elimina</button>
                                        <?php echo HTMLHelper::_('form.token'); ?>
                                    </form>
                                <?php endif; ?>
                                <?php if ($this->canRestore): ?>
                                    <form method="post" action="<?php echo Route::_('index.php?option=com_xdecaropeople&task=maintenance.previewRestore'); ?>">
                                        <input type="hidden" name="backup_uuid" value="<?php echo $this->escape((string) $backup['uuid']); ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">Anteprima</button>
                                        <?php echo HTMLHelper::_('form.token'); ?>
                                    </form>
                                    <form method="post" action="<?php echo Route::_('index.php?option=com_xdecaropeople&task=maintenance.restoreFull'); ?>" onsubmit="return confirm('Ripristinare questo backup completo?');">
                                        <input type="hidden" name="backup_uuid" value="<?php echo $this->escape((string) $backup['uuid']); ?>">
                                        <input type="hidden" name="confirm_restore" value="1">
                                        <button type="submit" class="btn btn-sm btn-outline-warning">Ripristina</button>
                                        <?php echo HTMLHelper::_('form.token'); ?>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="xdecaro-info-card">
        <div class="xdecaro-info-card__head"><div><h2>Cancellati</h2><p class="mb-0 text-muted">Le persone nel cestino restano recuperabili e mantengono ID e UUID.</p></div><span class="badge bg-secondary"><?php echo count($this->recentTrashed); ?></span></div>
        <?php if (!$this->recentTrashed): ?>
            <p class="text-muted mb-0">Nessuna persona nel cestino.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Persona</th><th>UUID</th><th>Cestinata il</th><th class="text-end">Azioni</th></tr></thead>
                    <tbody>
                    <?php foreach ($this->recentTrashed as $person): ?>
                        <tr>
                            <td><strong><?php echo $this->escape((string) ($person['display_name'] ?? '-')); ?></strong><br><small>ID <?php echo (int) ($person['id'] ?? 0); ?></small></td>
                            <td><code class="xdecaro-hash"><?php echo $this->escape((string) ($person['uuid'] ?? '-')); ?></code></td>
                            <td><?php echo $this->escape((string) ($person['trashed_at'] ?? '-')); ?></td>
                            <td class="text-end xdecaro-actions-cell">
                                <?php if ($this->canEditState): ?>
                                    <form method="post" action="<?php echo Route::_('index.php?option=com_xdecaropeople&task=maintenance.restoreTrash'); ?>">
                                        <input type="hidden" name="person_id" value="<?php echo (int) $person['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-success">Ripristina</button>
                                        <?php echo HTMLHelper::_('form.token'); ?>
                                    </form>
                                <?php endif; ?>
                                <?php if ($this->canDelete): ?>
                                    <form method="post" action="<?php echo Route::_('index.php?option=com_xdecaropeople&task=maintenance.purgePerson'); ?>" onsubmit="return confirm('Eliminare definitivamente questa persona? Questa operazione non è annullabile dal cestino.');">
                                        <input type="hidden" name="person_id" value="<?php echo (int) $person['id']; ?>">
                                        <input type="hidden" name="confirm_purge" value="1">
                                        <button type="submit" class="btn btn-sm btn-danger">Elimina definitivamente</button>
                                        <?php echo HTMLHelper::_('form.token'); ?>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="xdecaro-info-card">
        <div class="xdecaro-info-card__head"><h2>Attività manutenzione</h2><span class="badge bg-secondary"><?php echo count($this->maintenanceActivity); ?></span></div>
        <?php if (!$this->maintenanceActivity): ?>
            <p class="text-muted mb-0">Nessuna attività registrata.</p>
        <?php else: ?>
            <div class="xdecaro-activity-list">
                <?php foreach ($this->maintenanceActivity as $event): ?>
                    <div class="xdecaro-activity-item">
                        <div><strong><?php echo $this->escape($actionLabel((string) ($event['action'] ?? ''))); ?></strong><?php if (!empty($event['subject_uuid'])): ?><small><?php echo $this->escape((string) $event['subject_uuid']); ?></small><?php endif; ?></div>
                        <span><?php echo $this->escape((string) ($event['created'] ?? '-')); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php

namespace xdecaro\Component\People\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;
use RuntimeException;
use Throwable;

final class PersonTrashService
{
    public function __construct(
        private DatabaseInterface $db,
        private MaintenanceLogService $maintenanceLog
    ) {}

    public function trash(array $ids, int $actorUserId): int
    {
        $rows = $this->loadRows($ids);
        if ($rows === []) {
            return 0;
        }

        $count = 0;
        $this->db->transactionStart();
        try {
            foreach ($rows as $row) {
                $id = (int) $row['id'];
                $state = (int) $row['state'];
                if ($state === -2) {
                    continue;
                }

                $this->maintenanceLog->log('trash_person', (string) $row['uuid'], $actorUserId, [
                    'person_id' => $id,
                    'previous_state' => $state,
                ]);

                $query = $this->db->getQuery(true)
                    ->update($this->db->quoteName('#__xdecaropeople_people'))
                    ->set($this->db->quoteName('state') . ' = -2')
                    ->where($this->db->quoteName('id') . ' = ' . $id);
                $this->db->setQuery($query)->execute();
                $count++;
            }
            $this->db->transactionCommit();
        } catch (Throwable $e) {
            $this->db->transactionRollback();
            throw $e;
        }

        return $count;
    }

    public function restore(array $ids, int $actorUserId): int
    {
        $rows = $this->loadRows($ids);
        if ($rows === []) {
            return 0;
        }

        $count = 0;
        $this->db->transactionStart();
        try {
            foreach ($rows as $row) {
                if ((int) $row['state'] !== -2) {
                    continue;
                }

                $log = $this->maintenanceLog->latestForSubject((string) $row['uuid'], 'trash_person');
                $previousState = (int) (($log['metadata']['previous_state'] ?? 1));
                if ($previousState < 0) {
                    $previousState = 1;
                }

                $id = (int) $row['id'];
                $query = $this->db->getQuery(true)
                    ->update($this->db->quoteName('#__xdecaropeople_people'))
                    ->set($this->db->quoteName('state') . ' = ' . $previousState)
                    ->where($this->db->quoteName('id') . ' = ' . $id);
                $this->db->setQuery($query)->execute();

                $this->maintenanceLog->log('restore_trash_person', (string) $row['uuid'], $actorUserId, [
                    'person_id' => $id,
                    'restored_state' => $previousState,
                ]);
                $count++;
            }
            $this->db->transactionCommit();
        } catch (Throwable $e) {
            $this->db->transactionRollback();
            throw $e;
        }

        return $count;
    }

    public function purge(array $ids, int $actorUserId): int
    {
        $rows = $this->loadRows($ids);
        if ($rows === []) {
            return 0;
        }

        foreach ($rows as $row) {
            if ((int) $row['state'] !== -2) {
                throw new RuntimeException('Only trashed People records may be permanently deleted.');
            }
        }

        $count = 0;
        $this->db->transactionStart();
        try {
            foreach ($rows as $row) {
                $id = (int) $row['id'];
                $this->maintenanceLog->log('purge_person', (string) $row['uuid'], $actorUserId, [
                    'person_id' => $id,
                    'display_name' => (string) ($row['display_name'] ?? ''),
                    'reference_status' => 'unknown',
                ]);

                $query = $this->db->getQuery(true)
                    ->delete($this->db->quoteName('#__xdecaropeople_people'))
                    ->where($this->db->quoteName('id') . ' = ' . $id);
                $this->db->setQuery($query)->execute();
                $count++;
            }
            $this->db->transactionCommit();
        } catch (Throwable $e) {
            $this->db->transactionRollback();
            throw $e;
        }

        return $count;
    }

    public function getRecentTrashed(int $limit = 10): array
    {
        $limit = max(1, min(100, $limit));
        $query = $this->db->getQuery(true)
            ->select(['p.id', 'p.uuid', 'p.display_name', 'p.state'])
            ->from($this->db->quoteName('#__xdecaropeople_people', 'p'))
            ->where($this->db->quoteName('p.state') . ' = -2')
            ->order($this->db->quoteName('p.id') . ' DESC');

        $rows = array_values((array) $this->db->setQuery($query, 0, $limit)->loadAssocList());
        foreach ($rows as &$row) {
            $log = $this->maintenanceLog->latestForSubject((string) $row['uuid'], 'trash_person');
            $row['trashed_at'] = $log['created'] ?? null;
            $row['trashed_by'] = isset($log['actor_user_id']) ? (int) $log['actor_user_id'] : 0;
        }
        unset($row);

        return $rows;
    }

    private function loadRows(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }

        $query = $this->db->getQuery(true)
            ->select(['id', 'uuid', 'display_name', 'state'])
            ->from($this->db->quoteName('#__xdecaropeople_people'))
            ->where($this->db->quoteName('id') . ' IN (' . implode(',', $ids) . ')')
            ->order($this->db->quoteName('id') . ' ASC');

        return array_values((array) $this->db->setQuery($query)->loadAssocList());
    }
}

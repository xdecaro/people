<?php

namespace xdecaro\Component\People\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use RuntimeException;

final class PersonTrashService
{
    public function __construct(private DatabaseInterface $db, private MaintenanceLogService $log) {}

    public function trash(array $ids, int $actorUserId): int
    {
        $ids = $this->normalizeIds($ids);
        $affected = 0;

        foreach ($ids as $id) {
            $row = $this->loadPerson($id);
            if (!$row || (int) $row['state'] === -2) {
                continue;
            }

            $previousState = (int) $row['state'];
            $query = $this->db->getQuery(true)
                ->update($this->db->quoteName('#__xdecaropeople_people'))
                ->set($this->db->quoteName('state') . ' = -2')
                ->where($this->db->quoteName('id') . ' = :id')
                ->bind(':id', $id, ParameterType::INTEGER);
            $this->db->setQuery($query)->execute();

            $this->log->log('trash_person', (string) $row['uuid'], $actorUserId, [
                'person_id' => $id,
                'previous_state' => $previousState,
            ]);
            $affected++;
        }

        return $affected;
    }

    public function restore(array $ids, int $actorUserId): int
    {
        $ids = $this->normalizeIds($ids);
        $affected = 0;

        foreach ($ids as $id) {
            $row = $this->loadPerson($id);
            if (!$row || (int) $row['state'] !== -2) {
                continue;
            }

            $latestTrash = $this->log->latestForSubject((string) $row['uuid'], 'trash_person');
            $previousState = (int) (($latestTrash['metadata']['previous_state'] ?? 1));
            if ($previousState === -2) {
                $previousState = 1;
            }

            $query = $this->db->getQuery(true)
                ->update($this->db->quoteName('#__xdecaropeople_people'))
                ->set($this->db->quoteName('state') . ' = :state')
                ->where($this->db->quoteName('id') . ' = :id')
                ->bind(':state', $previousState, ParameterType::INTEGER)
                ->bind(':id', $id, ParameterType::INTEGER);
            $this->db->setQuery($query)->execute();

            $this->log->log('restore_trash_person', (string) $row['uuid'], $actorUserId, [
                'person_id' => $id,
                'restored_state' => $previousState,
            ]);
            $affected++;
        }

        return $affected;
    }

    public function purge(array $ids, int $actorUserId): int
    {
        $ids = $this->normalizeIds($ids);
        $rows = [];

        foreach ($ids as $id) {
            $row = $this->loadPerson($id);
            if (!$row) {
                continue;
            }
            if ((int) $row['state'] !== -2) {
                throw new RuntimeException('Only trashed people can be permanently deleted.', 409);
            }
            $rows[] = $row;
        }

        $affected = 0;
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $uuid = (string) $row['uuid'];

            $this->log->log('purge_person', $uuid, $actorUserId, [
                'person_id' => $id,
            ]);

            $query = $this->db->getQuery(true)
                ->delete($this->db->quoteName('#__xdecaropeople_people'))
                ->where($this->db->quoteName('id') . ' = :id')
                ->bind(':id', $id, ParameterType::INTEGER);
            $this->db->setQuery($query)->execute();
            $affected++;
        }

        return $affected;
    }

    public function getRecentTrashed(int $limit = 10): array
    {
        $limit = max(1, min(100, $limit));
        $query = $this->db->getQuery(true)
            ->select(['id', 'uuid', 'display_name', 'state'])
            ->from($this->db->quoteName('#__xdecaropeople_people'))
            ->where($this->db->quoteName('state') . ' = -2')
            ->order($this->db->quoteName('id') . ' DESC');
        $rows = (array) $this->db->setQuery($query, 0, $limit)->loadAssocList();

        foreach ($rows as &$row) {
            $event = $this->log->latestForSubject((string) $row['uuid'], 'trash_person');
            $row['trashed_at'] = $event['created'] ?? null;
            $row['trashed_by'] = isset($event['actor_user_id']) ? (int) $event['actor_user_id'] : null;
        }
        unset($row);

        return array_values($rows);
    }

    private function normalizeIds(array $ids): array
    {
        $normalized = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $normalized[$id] = $id;
            }
        }
        return array_values($normalized);
    }

    private function loadPerson(int $id): ?array
    {
        $query = $this->db->getQuery(true)
            ->select(['id', 'uuid', 'display_name', 'state'])
            ->from($this->db->quoteName('#__xdecaropeople_people'))
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);
        $row = $this->db->setQuery($query, 0, 1)->loadAssoc();
        return $row ?: null;
    }
}

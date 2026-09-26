<?php

namespace xdecaro\Component\People\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

final class MaintenanceLogService
{
    public function __construct(private DatabaseInterface $db) {}

    public function log(string $action, ?string $subjectUuid, int $actorUserId, array $metadata = []): void
    {
        $action = trim($action);
        if ($action === '') {
            return;
        }

        $record = (object) [
            'action' => $action,
            'subject_uuid' => $subjectUuid !== null && trim($subjectUuid) !== '' ? strtolower(trim($subjectUuid)) : null,
            'actor_user_id' => max(0, $actorUserId),
            'created' => Factory::getDate()->toSql(),
            'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ];

        $this->db->insertObject('#__xdecaropeople_maintenance_log', $record);
    }

    public function recent(int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        $query = $this->db->getQuery(true)
            ->select(['id', 'action', 'subject_uuid', 'actor_user_id', 'created', 'metadata'])
            ->from($this->db->quoteName('#__xdecaropeople_maintenance_log'))
            ->order($this->db->quoteName('id') . ' DESC');

        $rows = array_values((array) $this->db->setQuery($query, 0, $limit)->loadAssocList());
        foreach ($rows as &$row) {
            $row['metadata'] = $this->decodeMetadata($row['metadata'] ?? null);
        }
        unset($row);

        return $rows;
    }

    public function latestForSubject(string $subjectUuid, string $action): ?array
    {
        $subjectUuid = strtolower(trim($subjectUuid));
        $action = trim($action);
        if ($subjectUuid === '' || $action === '') {
            return null;
        }

        $query = $this->db->getQuery(true)
            ->select(['id', 'action', 'subject_uuid', 'actor_user_id', 'created', 'metadata'])
            ->from($this->db->quoteName('#__xdecaropeople_maintenance_log'))
            ->where($this->db->quoteName('subject_uuid') . ' = :subjectUuid')
            ->where($this->db->quoteName('action') . ' = :action')
            ->order($this->db->quoteName('id') . ' DESC')
            ->bind(':subjectUuid', $subjectUuid)
            ->bind(':action', $action);

        $row = $this->db->setQuery($query, 0, 1)->loadAssoc();
        if (!$row) {
            return null;
        }
        $row['metadata'] = $this->decodeMetadata($row['metadata'] ?? null);
        return $row;
    }

    private function decodeMetadata(mixed $value): array
    {
        if (!is_string($value) || trim($value) === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}

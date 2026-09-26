<?php

namespace xdecaro\Component\People\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use JsonException;

final class MaintenanceLogService
{
    public function __construct(private DatabaseInterface $db) {}

    public function log(string $action, ?string $subjectUuid, int $actorUserId, array $metadata = []): void
    {
        $action = trim($action);
        if ($action === '') {
            throw new \InvalidArgumentException('Maintenance action is required.');
        }

        $record = (object) [
            'action' => $action,
            'subject_uuid' => $subjectUuid !== null && trim($subjectUuid) !== '' ? strtolower(trim($subjectUuid)) : null,
            'actor_user_id' => max(0, $actorUserId),
            'created' => Factory::getDate()->toSql(),
            'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ];

        $this->db->insertObject('#__xdecaropeople_maintenance_log', $record, 'id');
    }

    public function recent(int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        $query = $this->db->getQuery(true)
            ->select(['id', 'action', 'subject_uuid', 'actor_user_id', 'created', 'metadata'])
            ->from($this->db->quoteName('#__xdecaropeople_maintenance_log'))
            ->order($this->db->quoteName('id') . ' DESC');

        $rows = (array) $this->db->setQuery($query, 0, $limit)->loadAssocList();
        foreach ($rows as &$row) {
            $row['metadata'] = $this->decodeMetadata($row['metadata'] ?? null);
        }
        unset($row);

        return array_values($rows);
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
            ->where($this->db->quoteName('subject_uuid') . ' = :uuid')
            ->where($this->db->quoteName('action') . ' = :action')
            ->order($this->db->quoteName('id') . ' DESC')
            ->bind(':uuid', $subjectUuid)
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

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (JsonException) {
            return [];
        }
    }
}

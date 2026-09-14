<?php

namespace xdecaro\Component\People\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use RuntimeException;

final class DuplicateService
{
    public function __construct(private DatabaseInterface $db)
    {
    }

    public function find(int $limit = 100): array
    {
        $user = Factory::getApplication()->getIdentity();
        if (!$user->authorise('core.manage', 'com_xdecaropeople') && !$user->authorise('core.admin', 'com_xdecaropeople')) {
            throw new RuntimeException('Not authorised.', 403);
        }

        $limit = max(1, min(500, $limit));
        $canSensitive = $user->authorise('people.view_sensitive', 'com_xdecaropeople')
            || $user->authorise('core.admin', 'com_xdecaropeople');

        $buckets = [];
        foreach ($this->loadCandidates($canSensitive) as $record) {
            foreach ($this->criteriaForRecord($record, $canSensitive) as $criterion) {
                $bucketKey = $criterion['type'] . '|' . $criterion['key'];
                if (!isset($buckets[$bucketKey])) {
                    $buckets[$bucketKey] = [
                        'type' => $criterion['type'],
                        'strength' => $criterion['strength'],
                        'key' => $criterion['key'],
                        'value' => $criterion['value'],
                        'records' => [],
                    ];
                }

                $id = (int) ($record['id'] ?? 0);
                if ($id > 0) {
                    $buckets[$bucketKey]['records'][$id] = $this->recordSummary($record);
                }
            }
        }

        $groups = [];
        foreach ($buckets as $bucket) {
            if (count($bucket['records']) < 2) {
                continue;
            }

            $bucket['records'] = array_values($bucket['records']);
            $bucket['count'] = count($bucket['records']);
            $groups[] = $bucket;
        }

        usort($groups, static function (array $a, array $b): int {
            $strengthA = ($a['strength'] ?? '') === 'strong' ? 0 : 1;
            $strengthB = ($b['strength'] ?? '') === 'strong' ? 0 : 1;

            return [$strengthA, (string) ($a['type'] ?? ''), (string) ($a['value'] ?? '')]
                <=> [$strengthB, (string) ($b['type'] ?? ''), (string) ($b['value'] ?? '')];
        });

        return array_slice($groups, 0, $limit);
    }

    public function hasMatch(array $data, int $excludeId = 0): bool
    {
        $user = Factory::getApplication()->getIdentity();
        $canSensitive = $user->authorise('people.view_sensitive', 'com_xdecaropeople')
            || $user->authorise('core.admin', 'com_xdecaropeople');

        $wanted = [];
        foreach ($this->criteriaForRecord($data, $canSensitive) as $criterion) {
            $wanted[$criterion['type'] . '|' . $criterion['key']] = true;
        }

        if ($wanted === []) {
            return false;
        }

        foreach ($this->loadCandidates($canSensitive, $excludeId) as $record) {
            foreach ($this->criteriaForRecord($record, $canSensitive) as $criterion) {
                if (isset($wanted[$criterion['type'] . '|' . $criterion['key']])) {
                    return true;
                }
            }
        }

        return false;
    }

    private function loadCandidates(bool $canSensitive, int $excludeId = 0): array
    {
        $columns = [
            $this->db->quoteName('id'),
            $this->db->quoteName('display_name'),
            $this->db->quoteName('first_name'),
            $this->db->quoteName('last_name'),
            $this->db->quoteName('email'),
            $this->db->quoteName('phone'),
            $this->db->quoteName('whatsapp'),
        ];

        if ($canSensitive) {
            $columns[] = $this->db->quoteName('birth_date');
            $columns[] = $this->db->quoteName('tax_identifier');
        }

        $query = $this->db->getQuery(true)
            ->select($columns)
            ->from($this->db->quoteName('#__xdecaropeople_people'))
            ->where($this->db->quoteName('state') . ' >= 0')
            ->order($this->db->quoteName('id') . ' ASC');

        if ($excludeId > 0) {
            $query->where($this->db->quoteName('id') . ' <> :excludeId')
                ->bind(':excludeId', $excludeId, ParameterType::INTEGER);
        }

        return (array) $this->db->setQuery($query)->loadAssocList();
    }

    private function criteriaForRecord(array $record, bool $canSensitive): array
    {
        $criteria = [];

        $email = self::normalizeText($record['email'] ?? null);
        if ($email !== '') {
            $criteria[] = $this->criterion('email', 'strong', $email, $email);
        }

        if ($canSensitive) {
            $tin = self::normalizeTin($record['tax_identifier'] ?? null);
            if ($tin !== '') {
                $criteria[] = $this->criterion('tax_identifier', 'strong', $tin, $tin);
            }
        }

        $firstName = self::normalizeText($record['first_name'] ?? null);
        $lastName = self::normalizeText($record['last_name'] ?? null);
        $displayName = trim((string) ($record['display_name'] ?? ''));
        if ($displayName === '') {
            $displayName = trim((string) ($record['first_name'] ?? '') . ' ' . (string) ($record['last_name'] ?? ''));
        }

        $nameKey = $firstName !== '' && $lastName !== '' ? $firstName . '|' . $lastName : '';
        if ($canSensitive && $nameKey !== '') {
            $birthDate = self::normalizeBirthDate($record['birth_date'] ?? null);
            if ($birthDate !== '') {
                $criteria[] = $this->criterion(
                    'name_birth',
                    'strong',
                    $nameKey . '|' . $birthDate,
                    trim($displayName . ' · ' . $birthDate)
                );
            }
        }

        $phone = self::normalizePhone($record['phone'] ?? null);
        if ($phone !== '') {
            $criteria[] = $this->criterion('phone', 'strong', $phone, $phone);
        }

        $whatsapp = self::normalizePhone($record['whatsapp'] ?? null);
        if ($whatsapp !== '') {
            $criteria[] = $this->criterion('whatsapp', 'strong', $whatsapp, $whatsapp);
        }

        if ($nameKey !== '') {
            $criteria[] = $this->criterion('name', 'possible', $nameKey, $displayName);
        }

        return $criteria;
    }

    private function criterion(string $type, string $strength, string $key, string $value): array
    {
        return [
            'type' => $type,
            'strength' => $strength,
            'key' => $key,
            'value' => $value,
        ];
    }

    private function recordSummary(array $record): array
    {
        $displayName = trim((string) ($record['display_name'] ?? ''));
        if ($displayName === '') {
            $displayName = trim((string) ($record['first_name'] ?? '') . ' ' . (string) ($record['last_name'] ?? ''));
        }

        return [
            'id' => (int) ($record['id'] ?? 0),
            'display_name' => $displayName,
            'email' => trim((string) ($record['email'] ?? '')),
            'phone' => self::normalizePhone($record['phone'] ?? null),
        ];
    }

    private static function normalizeText(mixed $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    private static function normalizeTin(mixed $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        return function_exists('mb_strtoupper') ? mb_strtoupper($value, 'UTF-8') : strtoupper($value);
    }

    private static function normalizePhone(mixed $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        return preg_replace('/\s+/u', '', $value) ?? $value;
    }

    private static function normalizeBirthDate(mixed $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $matches)) {
            return $matches[3] . '-' . $matches[2] . '-' . $matches[1];
        }

        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $matches)) {
            return $matches[1];
        }

        return $value;
    }
}

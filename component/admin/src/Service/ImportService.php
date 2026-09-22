<?php

namespace xdecaro\Component\People\Administrator\Service;

defined('_JEXEC') or die;

use DateTimeImmutable;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
use Throwable;

/**
 * Bulk importer for People-owned person master data.
 *
 * Domain-specific Membership, disability/health and payment fields are
 * intentionally outside this service.
 */
final class ImportService
{
    public const MAX_BATCH_SIZE = 150;

    public function __construct(private DatabaseInterface $db)
    {
    }

    public function analyzeCandidates(array $rows): array
    {
        $rows = array_slice(array_values($rows), 0, 5000);
        [$taxSet, $fallbackSet] = $this->existingIdentitySets();
        $existingRows = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $rowNumber = (int) ($row['_row'] ?? 0);
            $taxIdentifier = $this->normalizeTaxIdentifier($row['tax_identifier'] ?? null);
            $fallbackKey = $this->fallbackKey(
                $row['first_name'] ?? null,
                $row['last_name'] ?? null,
                $this->normalizeDate($row['birth_date'] ?? null)
            );

            $exists = $taxIdentifier !== '' && isset($taxSet[$taxIdentifier]);
            if (!$exists && $taxIdentifier === '' && $fallbackKey !== '' && isset($fallbackSet[$fallbackKey])) {
                $exists = true;
            }

            if ($exists && $rowNumber > 0) {
                $existingRows[] = $rowNumber;
            }
        }

        return array_values(array_unique($existingRows));
    }

    public function importBatch(array $rows, int $userId, string $profile = 'generic'): array
    {
        $rows = array_slice(array_values($rows), 0, self::MAX_BATCH_SIZE);
        [$taxSet, $fallbackSet] = $this->existingIdentitySets();

        $summary = [
            'inserted' => 0,
            'existing' => 0,
            'invalid' => 0,
            'errors' => 0,
            'warnings' => 0,
            'results' => [],
        ];

        foreach ($rows as $row) {
            $row = is_array($row) ? $row : [];
            $rowNumber = max(0, (int) ($row['_row'] ?? 0));

            [$record, $errors, $warnings] = $this->normalizeRecord($row, $profile);

            if ($errors !== []) {
                $summary['invalid']++;
                $summary['warnings'] += count($warnings);
                $summary['results'][] = [
                    'row' => $rowNumber,
                    'status' => 'invalid',
                    'message' => implode(' ', $errors),
                    'warnings' => $warnings,
                ];
                continue;
            }

            $taxIdentifier = (string) ($record['tax_identifier'] ?? '');
            $fallbackKey = $this->fallbackKey(
                $record['first_name'] ?? null,
                $record['last_name'] ?? null,
                $record['birth_date'] ?? null
            );

            $exists = $taxIdentifier !== '' && isset($taxSet[$taxIdentifier]);
            if (!$exists && $taxIdentifier === '' && $fallbackKey !== '' && isset($fallbackSet[$fallbackKey])) {
                $exists = true;
            }

            if ($exists) {
                $summary['existing']++;
                $summary['warnings'] += count($warnings);
                $summary['results'][] = [
                    'row' => $rowNumber,
                    'status' => 'existing',
                    'message' => 'existing_person',
                    'warnings' => $warnings,
                ];
                continue;
            }

            $transactionStarted = false;

            try {
                $this->db->transactionStart();
                $transactionStarted = true;

                $person = (object) array_merge($record, [
                    'uuid' => self::uuidV4(),
                    'display_name' => trim($record['first_name'] . ' ' . $record['last_name']),
                    'user_id' => null,
                    'preferred_name' => null,
                    'birth_place_id' => null,
                    'residence_place_id' => null,
                    'disability_status' => null,
                    'disability_types' => null,
                    'disability_other' => null,
                    'accessibility_needs' => null,
                    'accessibility_other' => null,
                    'nationality_code' => null,
                    'nationality_codes' => null,
                    'birth_country_code' => null,
                    'whatsapp' => null,
                    'preferred_contact' => null,
                    'additional_addresses' => null,
                    'relations_data' => null,
                    'language' => null,
                    'social_instagram' => null,
                    'social_facebook' => null,
                    'social_linkedin' => null,
                    'social_tiktok' => null,
                    'social_telegram' => null,
                    'social_x' => null,
                    'social_youtube' => null,
                    'website_url' => null,
                    'profile_document_uuid' => null,
                    'person_status' => 'active',
                    'source_component' => 'com_xdecaropeople.import',
                    'notes' => null,
                    'state' => 1,
                    'access' => 1,
                    'created' => Factory::getDate()->toSql(),
                    'created_by' => $userId,
                    'modified' => null,
                    'modified_by' => 0,
                ]);

                $this->db->insertObject('#__xdecaropeople_people', $person, 'id');

                $personId = (int) ($person->id ?? 0);
                if ($personId < 1) {
                    throw new \RuntimeException('Imported person did not return a database id.');
                }

                $history = (object) [
                    'person_id' => $personId,
                    'action' => 'import',
                    'changed_fields' => json_encode(['created', 'import'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'actor_user_id' => $userId,
                    'created' => Factory::getDate()->toSql(),
                ];
                $this->db->insertObject('#__xdecaropeople_history', $history);
                $this->db->transactionCommit();
                $transactionStarted = false;

                if ($taxIdentifier !== '') {
                    $taxSet[$taxIdentifier] = true;
                }
                if ($fallbackKey !== '') {
                    $fallbackSet[$fallbackKey] = true;
                }

                $summary['inserted']++;
                $summary['warnings'] += count($warnings);
                $summary['results'][] = [
                    'row' => $rowNumber,
                    'status' => 'inserted',
                    'uuid' => (string) $person->uuid,
                    'message' => 'imported',
                    'warnings' => $warnings,
                ];
            } catch (Throwable $exception) {
                if ($transactionStarted) {
                    try {
                        $this->db->transactionRollback();
                    } catch (Throwable $rollbackException) {
                        Log::add(
                            'People CSV import rollback failed: ' . $rollbackException->getMessage(),
                            Log::ERROR,
                            'com_xdecaropeople'
                        );
                    }
                }

                Log::add(
                    'People CSV import row ' . $rowNumber . ' failed: ' . $exception->getMessage(),
                    Log::ERROR,
                    'com_xdecaropeople'
                );
                $summary['errors']++;
                $summary['warnings'] += count($warnings);
                $summary['results'][] = [
                    'row' => $rowNumber,
                    'status' => 'error',
                    'message' => 'database_error',
                    'warnings' => $warnings,
                ];
            }
        }

        return $summary;
    }

    private function normalizeRecord(array $row, string $profile): array
    {
        $errors = [];
        $warnings = [];

        $firstName = $this->cleanString($row['first_name'] ?? null, 150);
        $lastName = $this->cleanString($row['last_name'] ?? null, 150);

        if ($firstName === null) {
            $errors[] = 'missing_first_name';
        }
        if ($lastName === null) {
            $errors[] = 'missing_last_name';
        }

        $taxIdentifier = $this->normalizeTaxIdentifier($row['tax_identifier'] ?? null);
        if ($profile === 'ens_soci_2026') {
            if ($taxIdentifier === '') {
                $errors[] = 'missing_tax_identifier';
            } elseif (!preg_match('/^[A-Z0-9]{16}$/', $taxIdentifier)) {
                $errors[] = 'invalid_tax_identifier';
            }
        }

        $birthDateRaw = trim((string) ($row['birth_date'] ?? ''));
        $birthDate = $this->normalizeDate($birthDateRaw);
        if ($birthDateRaw !== '' && $birthDateRaw !== '0000-00-00' && $birthDate === null) {
            $warnings[] = 'invalid_birth_date_removed';
        }

        $sex = strtoupper(trim((string) ($row['sex'] ?? '')));
        if ($sex !== '' && !in_array($sex, ['M', 'F'], true)) {
            $warnings[] = 'invalid_sex_removed';
            $sex = '';
        }

        $email = strtolower(trim((string) ($row['email'] ?? '')));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $warnings[] = 'invalid_email_removed';
            $email = '';
        }

        $countryCode = strtoupper(trim((string) ($row['country_code'] ?? '')));
        if ($countryCode !== '' && !preg_match('/^[A-Z]{2}$/', $countryCode)) {
            $warnings[] = 'invalid_country_code_removed';
            $countryCode = '';
        }

        $record = [
            'first_name' => $firstName ?? '',
            'last_name' => $lastName ?? '',
            'tax_identifier' => $taxIdentifier !== '' ? $taxIdentifier : null,
            'birth_date' => $birthDate,
            'sex' => $sex !== '' ? $sex : null,
            'birth_place' => $this->cleanString($row['birth_place'] ?? null, 190),
            'birth_region' => $this->cleanString($row['birth_region'] ?? null, 190),
            'address_line' => $this->cleanString($row['address_line'] ?? null, 255),
            'address_number' => $this->cleanString($row['address_number'] ?? null, 32),
            'postal_code' => $this->cleanString($row['postal_code'] ?? null, 32),
            'city' => $this->cleanString($row['city'] ?? null, 190),
            'region' => $this->cleanString($row['region'] ?? null, 190),
            'country_code' => $countryCode !== '' ? $countryCode : null,
            'phone' => $this->normalizePhone($row['phone'] ?? null),
            'email' => $email !== '' ? $email : null,
        ];

        return [$record, $errors, $warnings];
    }

    private function existingIdentitySets(): array
    {
        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('tax_identifier'),
                $this->db->quoteName('first_name'),
                $this->db->quoteName('last_name'),
                $this->db->quoteName('birth_date'),
            ])
            ->from($this->db->quoteName('#__xdecaropeople_people'));

        $rows = (array) $this->db->setQuery($query)->loadAssocList();
        $taxSet = [];
        $fallbackSet = [];

        foreach ($rows as $row) {
            $tax = $this->normalizeTaxIdentifier($row['tax_identifier'] ?? null);
            if ($tax !== '') {
                $taxSet[$tax] = true;
            }

            $key = $this->fallbackKey(
                $row['first_name'] ?? null,
                $row['last_name'] ?? null,
                $this->normalizeDate($row['birth_date'] ?? null)
            );
            if ($key !== '') {
                $fallbackSet[$key] = true;
            }
        }

        return [$taxSet, $fallbackSet];
    }

    private function fallbackKey(mixed $firstName, mixed $lastName, ?string $birthDate): string
    {
        if ($birthDate === null) {
            return '';
        }

        $first = $this->foldName($firstName);
        $last = $this->foldName($lastName);

        return $first !== '' && $last !== '' ? $first . '|' . $last . '|' . $birthDate : '';
    }

    private function foldName(mixed $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim((string) $value)) ?? '';
        return mb_strtoupper($value, 'UTF-8');
    }

    private function normalizeTaxIdentifier(mixed $value): string
    {
        $value = mb_strtoupper(trim((string) $value), 'UTF-8');
        return preg_replace('/\s+/u', '', $value) ?? '';
    }

    private function normalizeDate(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '0000-00-00') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!' . $format, $value);
            $errors = DateTimeImmutable::getLastErrors();
            if ($date instanceof DateTimeImmutable && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    private function normalizePhone(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $prefix = str_starts_with($value, '+') ? '+' : '';
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        return $digits !== '' ? $prefix . $digits : null;
    }

    private function cleanString(mixed $value, int $maxLength): ?string
    {
        $value = preg_replace('/\s+/u', ' ', trim((string) $value)) ?? '';
        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $maxLength, 'UTF-8');
    }

    private static function uuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

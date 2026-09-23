<?php

namespace xdecaro\Component\People\Administrator\Service;

defined('_JEXEC') or die;

use DateTimeImmutable;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use RuntimeException;
use ZipArchive;

final class ExportService
{
    private const PDF_COLUMNS_PER_PAGE = 8;
    private const PDF_ROWS_PER_PAGE = 28;
    private const PDF_TABLE_WIDTH = 770.0;

    public function __construct(private DatabaseInterface $db)
    {
    }

    public function resolveColumns(array $requested, bool $canIdentityDetails, bool $canSensitive): array
    {
        $allowed = $this->allowedColumns($canIdentityDetails, $canSensitive);
        $requested = array_values(array_unique(array_filter(
            array_map(static fn ($value): string => trim((string) $value), $requested),
            static fn (string $value): bool => $value !== ''
        )));

        if ($requested === []) {
            $requested = $canIdentityDetails
                ? ['display_name', 'birth_date', 'birth_place', 'email', 'phone', 'state']
                : ['display_name', 'email', 'phone', 'state'];
        }

        $columns = [];

        foreach ($requested as $key) {
            if (isset($allowed[$key])) {
                $columns[] = $allowed[$key];
            }
        }

        if ($columns === []) {
            throw new RuntimeException(Text::_('COM_XDECAROPEOPLE_EXPORT_NO_COLUMNS'), 400);
        }

        return $columns;
    }

    public function loadRows(
        string $scope,
        array $ids,
        string $search,
        string $state,
        bool $canIdentityDetails,
        bool $canSensitive
    ): array {
        $select = [
            'a.display_name',
            'a.first_name',
            'a.last_name',
            'a.email',
            'a.phone',
            'a.state',
        ];

        if ($canSensitive) {
            $select[] = 'a.tax_identifier';
        }

        if ($canIdentityDetails) {
            array_push(
                $select,
                'a.birth_date',
                'a.sex',
                'a.birth_place',
                'a.birth_region',
                'a.address_line',
                'a.address_number',
                'a.postal_code',
                'a.city',
                'a.region',
                'a.country_code'
            );
        }

        $query = $this->db->getQuery(true)
            ->select($select)
            ->from($this->db->quoteName('#__xdecaropeople_people', 'a'));

        if ($scope === 'selected') {
            $ids = array_values(array_unique(array_filter(
                array_map('intval', $ids),
                static fn (int $id): bool => $id > 0
            )));

            if ($ids === []) {
                return [];
            }

            $query->where($this->db->quoteName('a.id') . ' IN (' . implode(',', $ids) . ')');
        } elseif ($scope === 'filtered') {
            if ($state !== '') {
                $stateValue = (int) $state;
                $query->where($this->db->quoteName('a.state') . ' = :exportState')
                    ->bind(':exportState', $stateValue, ParameterType::INTEGER);
            } else {
                $query->where($this->db->quoteName('a.state') . ' >= 0');
            }

            $search = trim($search);

            if ($search !== '') {
                $like = '%' . str_replace(' ', '%', $search) . '%';
                $conditions = [
                    $this->db->quoteName('a.display_name') . ' LIKE :exportSearch1',
                    $this->db->quoteName('a.first_name') . ' LIKE :exportSearch2',
                    $this->db->quoteName('a.last_name') . ' LIKE :exportSearch3',
                    $this->db->quoteName('a.email') . ' LIKE :exportSearch4',
                ];

                if ($canIdentityDetails) {
                    $conditions[] = $this->db->quoteName('a.birth_place') . ' LIKE :exportSearch5';
                }

                $query->where('(' . implode(' OR ', $conditions) . ')')
                    ->bind(':exportSearch1', $like)
                    ->bind(':exportSearch2', $like)
                    ->bind(':exportSearch3', $like)
                    ->bind(':exportSearch4', $like);

                if ($canIdentityDetails) {
                    $query->bind(':exportSearch5', $like);
                }
            }
        } else {
            $query->where($this->db->quoteName('a.state') . ' >= 0');
        }

        $query->order([
            $this->db->quoteName('a.last_name') . ' ASC',
            $this->db->quoteName('a.first_name') . ' ASC',
            $this->db->quoteName('a.display_name') . ' ASC',
        ]);

        return (array) $this->db->setQuery($query)->loadAssocList();
    }

    public function toCsv(array $rows, array $columns): string
    {
        $stream = fopen('php://temp', 'w+b');

        if ($stream === false) {
            throw new RuntimeException(Text::_('COM_XDECAROPEOPLE_EXPORT_ERROR_CREATE'));
        }

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, array_column($columns, 'label'), ';', '"', '\\');

        foreach ($rows as $row) {
            fputcsv($stream, $this->exportRow($row, $columns), ';', '"', '\\');
        }

        rewind($stream);
        $result = stream_get_contents($stream);
        fclose($stream);

        if ($result === false) {
            throw new RuntimeException(Text::_('COM_XDECAROPEOPLE_EXPORT_ERROR_CREATE'));
        }

        return $result;
    }

    public function toXlsx(array $rows, array $columns): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException(Text::_('COM_XDECAROPEOPLE_EXPORT_ERROR_XLSX'));
        }

        $table = [array_column($columns, 'label')];

        foreach ($rows as $row) {
            $table[] = $this->exportRow($row, $columns);
        }

        $temporary = tempnam(sys_get_temp_dir(), 'xdecaro-people-xlsx-');

        if ($temporary === false) {
            throw new RuntimeException(Text::_('COM_XDECAROPEOPLE_EXPORT_ERROR_CREATE'));
        }

        $zip = new ZipArchive();

        if ($zip->open($temporary, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($temporary);
            throw new RuntimeException(Text::_('COM_XDECAROPEOPLE_EXPORT_ERROR_CREATE'));
        }

        $zip->addFromString('[Content_Types].xml', $this->xlsxContentTypes());
        $zip->addFromString('_rels/.rels', $this->xlsxRootRelations());
        $zip->addFromString('docProps/core.xml', $this->xlsxCoreProperties());
        $zip->addFromString('docProps/app.xml', $this->xlsxAppProperties());
        $zip->addFromString('xl/workbook.xml', $this->xlsxWorkbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->xlsxWorkbookRelations());
        $zip->addFromString('xl/styles.xml', $this->xlsxStyles());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->xlsxWorksheet($table, $columns));
        $zip->close();

        $result = file_get_contents($temporary);
        @unlink($temporary);

        if ($result === false) {
            throw new RuntimeException(Text::_('COM_XDECAROPEOPLE_EXPORT_ERROR_CREATE'));
        }

        return $result;
    }

    public function toPdf(array $rows, array $columns): string
    {
        $pageSpecs = [];

        foreach (array_chunk($columns, self::PDF_COLUMNS_PER_PAGE) as $columnGroup) {
            $pdfColumns = $this->withPdfWidths($columnGroup);
            $prepared = [];

            foreach ($rows as $row) {
                $values = $this->exportRow($row, $pdfColumns);
                $prepared[] = array_map(
                    fn (string $value, int $index): string => $this->fitPdfText(
                        $value,
                        (float) $pdfColumns[$index]['width']
                    ),
                    $values,
                    array_keys($values)
                );
            }

            $rowPages = array_chunk($prepared, self::PDF_ROWS_PER_PAGE);

            if ($rowPages === []) {
                $rowPages = [[]];
            }

            foreach ($rowPages as $pageRows) {
                $pageSpecs[] = [
                    'columns' => $pdfColumns,
                    'rows' => $pageRows,
                ];
            }
        }

        if ($pageSpecs === []) {
            throw new RuntimeException(Text::_('COM_XDECAROPEOPLE_EXPORT_NO_COLUMNS'), 400);
        }

        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        $pageObjectIds = [];
        $nextObject = 5;
        $pageCount = count($pageSpecs);
        $exportedAt = Factory::getDate()->format('d/m/Y H:i');

        foreach ($pageSpecs as $pageIndex => $pageSpec) {
            $pageObjectId = $nextObject++;
            $contentObjectId = $nextObject++;
            $pageObjectIds[] = $pageObjectId;

            $pageContent = $this->pdfPageContent(
                $pageSpec['rows'],
                $pageSpec['columns'],
                $pageIndex + 1,
                $pageCount,
                $exportedAt
            );

            $objects[$pageObjectId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 841.89 595.28] '
                . '/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $contentObjectId . ' 0 R >>';
            $objects[$contentObjectId] = '<< /Length ' . strlen($pageContent) . " >>\nstream\n"
                . $pageContent . "\nendstream";
        }

        $objects[2] = '<< /Type /Pages /Kids ['
            . implode(' ', array_map(static fn (int $id): string => $id . ' 0 R', $pageObjectIds))
            . '] /Count ' . count($pageObjectIds) . ' >>';

        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        $maxObject = max(array_keys($objects));

        for ($id = 1; $id <= $maxObject; $id++) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . ($objects[$id] ?? '<< >>') . "\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . ($maxObject + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($id = 1; $id <= $maxObject; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }

        $pdf .= "trailer\n<< /Size " . ($maxObject + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $xrefOffset . "\n%%EOF";

        return $pdf;
    }

    private function allowedColumns(bool $canIdentityDetails, bool $canSensitive): array
    {
        $columns = [
            'display_name' => [
                'key' => 'display_name',
                'label' => Text::_('COM_XDECAROPEOPLE_FIELD_DISPLAY_NAME'),
                'pdf_weight' => 1.6,
            ],
            'first_name' => [
                'key' => 'first_name',
                'label' => Text::_('COM_XDECAROPEOPLE_FIELD_FIRST_NAME'),
                'pdf_weight' => 1.2,
            ],
            'last_name' => [
                'key' => 'last_name',
                'label' => Text::_('COM_XDECAROPEOPLE_FIELD_LAST_NAME'),
                'pdf_weight' => 1.2,
            ],
        ];

        if ($canSensitive) {
            $columns['tax_identifier'] = [
                'key' => 'tax_identifier',
                'label' => Text::_('COM_XDECAROPEOPLE_EXPORT_COLUMN_TAX_IDENTIFIER'),
                'pdf_weight' => 1.25,
            ];
        }

        if ($canIdentityDetails) {
            $columns += [
                'birth_date' => [
                    'key' => 'birth_date',
                    'label' => Text::_('COM_XDECAROPEOPLE_FIELD_BIRTH_DATE'),
                    'pdf_weight' => 0.9,
                ],
                'sex' => [
                    'key' => 'sex',
                    'label' => Text::_('COM_XDECAROPEOPLE_FIELD_SEX'),
                    'pdf_weight' => 0.55,
                ],
                'birth_place' => [
                    'key' => 'birth_place',
                    'label' => Text::_('COM_XDECAROPEOPLE_FIELD_BIRTH_PLACE'),
                    'pdf_weight' => 1.25,
                ],
                'birth_region' => [
                    'key' => 'birth_region',
                    'label' => Text::_('COM_XDECAROPEOPLE_FIELD_BIRTH_REGION'),
                    'pdf_weight' => 1.1,
                ],
                'address_line' => [
                    'key' => 'address_line',
                    'label' => Text::_('COM_XDECAROPEOPLE_FIELD_ADDRESS'),
                    'pdf_weight' => 1.55,
                ],
                'address_number' => [
                    'key' => 'address_number',
                    'label' => Text::_('COM_XDECAROPEOPLE_FIELD_ADDRESS_NUMBER'),
                    'pdf_weight' => 0.75,
                ],
                'postal_code' => [
                    'key' => 'postal_code',
                    'label' => Text::_('COM_XDECAROPEOPLE_FIELD_POSTAL_CODE'),
                    'pdf_weight' => 0.7,
                ],
                'city' => [
                    'key' => 'city',
                    'label' => Text::_('COM_XDECAROPEOPLE_FIELD_CITY'),
                    'pdf_weight' => 1.15,
                ],
                'region' => [
                    'key' => 'region',
                    'label' => Text::_('COM_XDECAROPEOPLE_FIELD_REGION'),
                    'pdf_weight' => 1.15,
                ],
                'country_code' => [
                    'key' => 'country_code',
                    'label' => Text::_('COM_XDECAROPEOPLE_FIELD_COUNTRY'),
                    'pdf_weight' => 0.8,
                ],
            ];
        }

        $columns += [
            'email' => [
                'key' => 'email',
                'label' => Text::_('JGLOBAL_EMAIL'),
                'pdf_weight' => 1.8,
            ],
            'phone' => [
                'key' => 'phone',
                'label' => Text::_('COM_XDECAROPEOPLE_FIELD_PHONE'),
                'pdf_weight' => 1.05,
            ],
            'state' => [
                'key' => 'state',
                'label' => Text::_('JSTATUS'),
                'pdf_weight' => 0.85,
            ],
        ];

        return $columns;
    }

    private function exportRow(array $row, array $columns): array
    {
        $values = [];

        foreach ($columns as $column) {
            $values[] = $this->formatValue((string) $column['key'], $row[$column['key']] ?? '');
        }

        return $values;
    }

    private function formatValue(string $key, mixed $value): string
    {
        if ($key === 'birth_date') {
            return $this->formatDate((string) $value);
        }

        if ($key === 'state') {
            return $this->stateLabel((int) $value);
        }

        return trim((string) $value);
    }

    private function formatDate(string $value): string
    {
        $value = trim($value);

        if ($value === '' || $value === '0000-00-00') {
            return '';
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date instanceof DateTimeImmutable ? $date->format('d/m/Y') : $value;
    }

    private function stateLabel(int $state): string
    {
        return match ($state) {
            1 => Text::_('JPUBLISHED'),
            -2 => Text::_('COM_XDECAROPEOPLE_FILTER_TRASHED'),
            default => Text::_('JUNPUBLISHED'),
        };
    }

    private function withPdfWidths(array $columns): array
    {
        $totalWeight = array_sum(array_map(
            static fn (array $column): float => (float) ($column['pdf_weight'] ?? 1.0),
            $columns
        ));

        if ($totalWeight <= 0) {
            $totalWeight = (float) max(1, count($columns));
        }

        return array_map(
            static function (array $column) use ($totalWeight): array {
                $column['width'] = self::PDF_TABLE_WIDTH
                    * ((float) ($column['pdf_weight'] ?? 1.0) / $totalWeight);

                return $column;
            },
            $columns
        );
    }

    private function xlsxWorksheet(array $rows, array $columns): string
    {
        $lastColumn = $this->xlsxColumnName(max(1, count($columns)));
        $lastRow = max(1, count($rows));
        $sheetRows = [];

        foreach ($rows as $rowIndex => $row) {
            $cells = [];

            foreach (array_values($row) as $columnIndex => $value) {
                $reference = $this->xlsxColumnName($columnIndex + 1) . ($rowIndex + 1);
                $style = $rowIndex === 0 ? ' s="1"' : '';
                $cells[] = '<c r="' . $reference . '" t="inlineStr"' . $style . '><is><t xml:space="preserve">'
                    . $this->xml((string) $value) . '</t></is></c>';
            }

            $sheetRows[] = '<row r="' . ($rowIndex + 1) . '">' . implode('', $cells) . '</row>';
        }

        $columnXml = [];

        foreach ($columns as $index => $column) {
            $maxLength = mb_strlen((string) $column['label'], 'UTF-8');

            foreach ($rows as $row) {
                $maxLength = max($maxLength, mb_strlen((string) ($row[$index] ?? ''), 'UTF-8'));
            }

            $width = min(45, max(12, $maxLength + 2));
            $columnXml[] = '<col min="' . ($index + 1) . '" max="' . ($index + 1)
                . '" width="' . $width . '" customWidth="1"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<dimension ref="A1:' . $lastColumn . $lastRow . '"/>'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<cols>' . implode('', $columnXml) . '</cols>'
            . '<sheetData>' . implode('', $sheetRows) . '</sheetData>'
            . '<autoFilter ref="A1:' . $lastColumn . $lastRow . '"/>'
            . '</worksheet>';
    }

    private function xlsxColumnName(int $index): string
    {
        $name = '';

        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)) . $name;
            $index = intdiv($index, 26);
        }

        return $name;
    }

    private function xlsxContentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . '</Types>';
    }

    private function xlsxRootRelations(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
    }

    private function xlsxWorkbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="People" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private function xlsxWorkbookRelations(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private function xlsxStyles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
            . '</styleSheet>';
    }

    private function xlsxCoreProperties(): string
    {
        $created = gmdate('Y-m-d\TH:i:s\Z');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
            . 'xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" '
            . 'xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:title>People export</dc:title><dc:creator>Xdecaro People</dc:creator>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $created
            . '</dcterms:created></cp:coreProperties>';
    }

    private function xlsxAppProperties(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" '
            . 'xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            . '<Application>Xdecaro People</Application></Properties>';
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function pdfPageContent(
        array $rows,
        array $columns,
        int $page,
        int $pageCount,
        string $exportedAt
    ): string {
        $commands = [];
        $commands[] = 'BT /F2 15 Tf 36 557 Td ('
            . $this->pdfText(Text::_('COM_XDECAROPEOPLE_EXPORT_PDF_TITLE')) . ') Tj ET';
        $commands[] = 'BT /F1 8 Tf 36 540 Td ('
            . $this->pdfText(Text::sprintf('COM_XDECAROPEOPLE_EXPORT_PDF_EXPORTED_AT', $exportedAt))
            . ') Tj ET';

        $x = 36.0;
        $headerY = 516.0;

        foreach ($columns as $column) {
            $commands[] = 'BT /F2 8 Tf ' . $this->pdfNumber($x) . ' '
                . $this->pdfNumber($headerY) . ' Td ('
                . $this->pdfText($this->fitPdfText((string) $column['label'], (float) $column['width']))
                . ') Tj ET';
            $x += (float) $column['width'];
        }

        $commands[] = '0.6 w 36 510 m 806 510 l S';
        $y = 494.0;

        foreach ($rows as $row) {
            $x = 36.0;

            foreach ($columns as $index => $column) {
                $commands[] = 'BT /F1 7.5 Tf ' . $this->pdfNumber($x) . ' '
                    . $this->pdfNumber($y) . ' Td ('
                    . $this->pdfText((string) ($row[$index] ?? '')) . ') Tj ET';
                $x += (float) $column['width'];
            }

            $commands[] = '0.2 w 36 ' . $this->pdfNumber($y - 4)
                . ' m 806 ' . $this->pdfNumber($y - 4) . ' l S';
            $y -= 16.0;
        }

        if ($rows === []) {
            $commands[] = 'BT /F1 9 Tf 36 492 Td ('
                . $this->pdfText(Text::_('COM_XDECAROPEOPLE_EXPORT_EMPTY')) . ') Tj ET';
        }

        $footer = Text::sprintf('COM_XDECAROPEOPLE_EXPORT_PDF_PAGE', $page, $pageCount);
        $commands[] = 'BT /F1 8 Tf 710 24 Td (' . $this->pdfText($footer) . ') Tj ET';

        return implode("\n", $commands);
    }

    private function fitPdfText(string $value, float $width): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? '';
        $maxChars = max(5, (int) floor($width / 4.3));

        if (mb_strlen($value, 'UTF-8') <= $maxChars) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, max(1, $maxChars - 3), 'UTF-8')) . '...';
    }

    private function pdfText(string $value): string
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value);

        if ($encoded === false) {
            $encoded = preg_replace('/[^\x20-\x7E]/', '?', $value) ?? '';
        }

        $encoded = str_replace(["\\", '(', ')', "\r", "\n"], ["\\\\", '\\(', '\\)', ' ', ' '], $encoded);

        return preg_replace('/[\x00-\x1F]/', ' ', $encoded) ?? '';
    }

    private function pdfNumber(float $number): string
    {
        return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }
}

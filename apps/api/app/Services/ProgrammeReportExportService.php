<?php

namespace App\Services;

use RuntimeException;

class ProgrammeReportExportService
{
    public function __construct(
        private readonly InclusiveImpactService $impact,
        private readonly ProgrammeDeliveryService $delivery,
        private readonly CommercialInsightsService $commercial,
    ) {}

    public function reportData(int $programmeId, bool $includeCommercial = false): array
    {
        $operations = $this->delivery->operationsSummary($programmeId);
        $aggregateOperations = [
            'programme_id' => $operations['programme_id'],
            'active_enrolments' => $operations['active_enrolments'],
            'scheduled' => $operations['scheduled'],
            'due_next_7_days' => $operations['due_next_7_days'],
            'overdue' => $operations['overdue'],
            'completed' => $operations['completed'],
            'consent_exceptions' => $operations['consent_exceptions'],
            'baseline_missing' => $operations['baseline_missing'],
            'data_quality' => $operations['data_quality'],
        ];

        $data = [
            'generated_at' => now()->toIso8601String(),
            'programme_id' => $programmeId,
            'outcomes' => $this->impact->programmeOutcomes($programmeId),
            'operations' => $aggregateOperations,
            'graduation' => $this->commercial->graduationSummary($programmeId),
            'privacy_notice' => 'This export is aggregate-only. Individual participant records and suppressed small-cohort values are not exported.',
            'causality_notice' => 'Measured change must not be represented as programme-caused unless the evaluation design supports causal attribution.',
        ];

        if ($includeCommercial) {
            $data['commercial'] = $this->commercial->dashboard(null, null, null, $programmeId);
        }

        return $data;
    }

    public function csv(int $programmeId): string
    {
        $data = $this->reportData($programmeId);
        $rows = $this->indicatorRows($data);

        $handle = fopen('php://temp', 'w+');
        if ($handle === false) {
            throw new RuntimeException('Unable to create programme export.');
        }

        fputcsv($handle, [
            'indicator_code',
            'indicator_name',
            'outcome_domain',
            'participant_count',
            'observation_count',
            'institutional_observation_count',
            'suppressed',
            'average_numeric',
            'institutional_latest_numeric',
            'latest_observed_at',
        ]);

        foreach ($rows as $row) {
            fputcsv($handle, array_values($row));
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return (string) $content;
    }

    public function xlsx(int $programmeId): string
    {
        $data = $this->reportData($programmeId);
        $rows = $this->indicatorRows($data);
        $headers = [
            'Indicator code',
            'Indicator name',
            'Outcome domain',
            'Participant count',
            'Observation count',
            'Institutional observation count',
            'Suppressed',
            'Average numeric',
            'Institutional latest numeric',
            'Latest observed at',
        ];

        $sheetRows = [$headers];
        foreach ($rows as $row) {
            $sheetRows[] = array_values($row);
        }

        return $this->zip([
            '[Content_Types].xml' => $this->contentTypesXml(),
            '_rels/.rels' => $this->rootRelsXml(),
            'xl/workbook.xml' => $this->workbookXml(),
            'xl/_rels/workbook.xml.rels' => $this->workbookRelsXml(),
            'xl/worksheets/sheet1.xml' => $this->sheetXml($sheetRows),
        ]);
    }

    public function reportPack(int $programmeId, bool $includeCommercial = false): string
    {
        $data = $this->reportData($programmeId, $includeCommercial);

        return $this->zip([
            'programme-outcomes.csv' => $this->csv($programmeId),
            'programme-report.json' => json_encode(
                $data,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ),
            'README.txt' => implode("\n", [
                'OpFin Programme Report Pack',
                '',
                'This package contains aggregate programme evidence only.',
                'Small participant cohorts remain suppressed.',
                'The package does not contain individual participant records.',
                'Measured outcomes must not be described as causal programme impact unless the evaluation design supports that claim.',
                '',
                'Generated: '.$data['generated_at'],
            ]),
        ]);
    }

    private function indicatorRows(array $data): array
    {
        return collect($data['outcomes']['indicator_summaries'] ?? [])
            ->map(function (array $summary) {
                $indicator = $summary['indicator'] ?? [];

                return [
                    'indicator_code' => $indicator['code'] ?? '',
                    'indicator_name' => $indicator['name'] ?? '',
                    'outcome_domain' => $indicator['outcome_domain'] ?? '',
                    'participant_count' => $summary['participant_count'],
                    'observation_count' => $summary['observation_count'] ?? 0,
                    'institutional_observation_count' => $summary['institutional_observation_count'] ?? 0,
                    'suppressed' => ($summary['suppressed'] ?? false) ? 'true' : 'false',
                    'average_numeric' => $summary['average_numeric'] ?? null,
                    'institutional_latest_numeric' => $summary['institutional_latest_numeric'] ?? null,
                    'latest_observed_at' => $summary['latest_observed_at'] ?? null,
                ];
            })
            ->values()
            ->all();
    }

    private function sheetXml(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 1;
            $xml .= '<row r="'.$excelRow.'">';
            foreach (array_values($row) as $columnIndex => $value) {
                $cell = $this->columnName($columnIndex + 1).$excelRow;
                if (is_int($value) || is_float($value)) {
                    $xml .= '<c r="'.$cell.'" t="n"><v>'.$value.'</v></c>';
                } else {
                    $text = htmlspecialchars((string) ($value ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');
                    $xml .= '<c r="'.$cell.'" t="inlineStr"><is><t>'.$text.'</t></is></c>';
                }
            }
            $xml .= '</row>';
        }

        return $xml.'</sheetData></worksheet>';
    }

    private function columnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)).$name;
            $number = intdiv($number, 26);
        }

        return $name;
    }

    /**
     * Create a standards-compliant ZIP archive using the uncompressed STORE
     * method so XLSX/report-pack generation does not depend on ext-zip.
     *
     * @param  array<string, string>  $files
     */
    private function zip(array $files): string
    {
        $body = '';
        $central = '';
        $offset = 0;
        [$dosTime, $dosDate] = $this->dosDateTime();

        foreach ($files as $name => $contents) {
            $nameBytes = (string) $name;
            $data = (string) $contents;
            $crc = crc32($data);
            if ($crc < 0) {
                $crc += 4294967296;
            }
            $size = strlen($data);
            $nameLength = strlen($nameBytes);

            $local = pack(
                'VvvvvvVVVvv',
                0x04034b50,
                20,
                0,
                0,
                $dosTime,
                $dosDate,
                $crc,
                $size,
                $size,
                $nameLength,
                0,
            ).$nameBytes.$data;

            $central .= pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50,
                20,
                20,
                0,
                0,
                $dosTime,
                $dosDate,
                $crc,
                $size,
                $size,
                $nameLength,
                0,
                0,
                0,
                0,
                0,
                $offset,
            ).$nameBytes;

            $body .= $local;
            $offset += strlen($local);
        }

        $centralOffset = strlen($body);
        $centralSize = strlen($central);
        $count = count($files);

        $end = pack(
            'VvvvvVVv',
            0x06054b50,
            0,
            0,
            $count,
            $count,
            $centralSize,
            $centralOffset,
            0,
        );

        return $body.$central.$end;
    }

    private function dosDateTime(): array
    {
        $now = now();
        $year = max(1980, min(2107, (int) $now->format('Y')));
        $dosTime = ((int) $now->format('H') << 11)
            | ((int) $now->format('i') << 5)
            | intdiv((int) $now->format('s'), 2);
        $dosDate = (($year - 1980) << 9)
            | ((int) $now->format('n') << 5)
            | (int) $now->format('j');

        return [$dosTime, $dosDate];
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'</Types>';
    }

    private function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Programme outcomes" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private function workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'</Relationships>';
    }
}

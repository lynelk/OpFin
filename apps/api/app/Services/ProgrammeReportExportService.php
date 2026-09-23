<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

class ProgrammeReportExportService
{
    public function __construct(
        private readonly InclusiveImpactService $impact,
        private readonly ProgrammeDeliveryService $delivery,
        private readonly CommercialInsightsService $commercial,
    ) {}

    public function reportData(int $programmeId): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'programme_id' => $programmeId,
            'outcomes' => $this->impact->programmeOutcomes($programmeId),
            'operations' => $this->delivery->operationsSummary($programmeId),
            'graduation' => $this->commercial->graduationSummary($programmeId),
            'commercial' => $this->commercial->dashboard(null, null, null, $programmeId),
            'privacy_notice' => 'This export is aggregate-only. Individual participant records and suppressed small-cohort values are not exported.',
            'causality_notice' => 'Measured change must not be represented as programme-caused unless the evaluation design supports causal attribution.',
        ];
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
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('XLSX export requires the PHP zip extension.');
        }

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

        $path = tempnam(sys_get_temp_dir(), 'opfin-xlsx-');
        if ($path === false) {
            throw new RuntimeException('Unable to prepare XLSX export.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            @unlink($path);
            throw new RuntimeException('Unable to create XLSX export.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->rootRelsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheetXml($sheetRows));
        $zip->close();

        $bytes = file_get_contents($path);
        @unlink($path);

        if ($bytes === false) {
            throw new RuntimeException('Unable to read generated XLSX export.');
        }

        return $bytes;
    }

    public function reportPack(int $programmeId): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Report-pack export requires the PHP zip extension.');
        }

        $data = $this->reportData($programmeId);
        $path = tempnam(sys_get_temp_dir(), 'opfin-report-pack-');
        if ($path === false) {
            throw new RuntimeException('Unable to prepare report pack.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            @unlink($path);
            throw new RuntimeException('Unable to create report pack.');
        }

        $zip->addFromString('programme-outcomes.csv', $this->csv($programmeId));
        $zip->addFromString('programme-report.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $zip->addFromString('README.txt', implode("\n", [
            'OpFin Programme Report Pack',
            '',
            'This package contains aggregate programme evidence only.',
            'Small participant cohorts remain suppressed.',
            'The package does not contain individual participant records.',
            'Measured outcomes must not be described as causal programme impact unless the evaluation design supports that claim.',
            '',
            'Generated: '.$data['generated_at'],
        ]));
        $zip->close();

        $bytes = file_get_contents($path);
        @unlink($path);

        if ($bytes === false) {
            throw new RuntimeException('Unable to read generated report pack.');
        }

        return $bytes;
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

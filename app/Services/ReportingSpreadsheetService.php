<?php

namespace App\Services;

use Carbon\Carbon;
use XMLWriter;

class ReportingSpreadsheetService
{
    private const SPREADSHEET_NS = 'urn:schemas-microsoft-com:office:spreadsheet';

    /** @param array<string, mixed> $report */
    public function build(array $report): string
    {
        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->writePi('mso-application', 'progid="Excel.Sheet"');
        $xml->startElement('Workbook');
        $xml->writeAttribute('xmlns', self::SPREADSHEET_NS);
        $xml->writeAttribute('xmlns:ss', self::SPREADSHEET_NS);
        $xml->writeAttribute('xmlns:x', 'urn:schemas-microsoft-com:office:excel');

        $this->writeStyles($xml);
        $this->writeSummarySheet($xml, $report);
        $this->writeLedgerSheet($xml, $report);

        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    private function writeStyles(XMLWriter $xml): void
    {
        $xml->startElement('Styles');
        $this->style($xml, 'Default', font: ['FontName' => 'Calibri', 'Size' => '11'], alignment: ['Vertical' => 'Center']);
        $this->style($xml, 'Title', font: ['FontName' => 'Calibri', 'Size' => '18', 'Bold' => '1', 'Color' => '#FFFFFF'], interior: ['Color' => '#075A43', 'Pattern' => 'Solid'], alignment: ['Vertical' => 'Center']);
        $this->style($xml, 'Subtitle', font: ['Color' => '#51636D', 'Italic' => '1']);
        $this->style($xml, 'Section', font: ['Bold' => '1', 'Color' => '#FFFFFF', 'Size' => '12'], interior: ['Color' => '#04724D', 'Pattern' => 'Solid']);
        $this->style($xml, 'Header', font: ['Bold' => '1', 'Color' => '#173A30'], interior: ['Color' => '#DDEFE8', 'Pattern' => 'Solid'], alignment: ['Vertical' => 'Center', 'WrapText' => '1'], borders: true);
        $this->style($xml, 'Label', font: ['Bold' => '1', 'Color' => '#37564C'], interior: ['Color' => '#F3F8F6', 'Pattern' => 'Solid'], borders: true);
        $this->style($xml, 'Text', alignment: ['Vertical' => 'Center', 'WrapText' => '1'], borders: true);
        $this->style($xml, 'Number', numberFormat: '#,##0', borders: true);
        $this->style($xml, 'Hours', numberFormat: '0.0" hrs"', borders: true);
        $this->style($xml, 'Currency', numberFormat: '"PHP "#,##0.00;[Red]-"PHP "#,##0.00', borders: true);
        $this->style($xml, 'Refund', font: ['Color' => '#9B2C2C', 'Bold' => '1'], interior: ['Color' => '#FDE8E8', 'Pattern' => 'Solid'], numberFormat: '[Red]-"PHP "#,##0.00', borders: true);
        $this->style($xml, 'DateTime', numberFormat: 'mmm d, yyyy h:mm AM/PM', borders: true);
        $this->style($xml, 'Estimated', font: ['Color' => '#8A5A00', 'Italic' => '1'], interior: ['Color' => '#FFF7DB', 'Pattern' => 'Solid'], alignment: ['WrapText' => '1'], borders: true);
        $this->style($xml, 'Note', font: ['Color' => '#647680', 'Italic' => '1'], alignment: ['WrapText' => '1']);
        $xml->endElement();
    }

    /** @param array<string, string> $font @param array<string, string> $interior @param array<string, string> $alignment */
    private function style(XMLWriter $xml, string $id, array $font = [], array $interior = [], array $alignment = [], ?string $numberFormat = null, bool $borders = false): void
    {
        $xml->startElement('Style');
        $xml->writeAttribute('ss:ID', $id);
        if ($font !== []) {
            $xml->startElement('Font');
            foreach ($font as $name => $value) {
                $xml->writeAttribute('ss:'.$name, $value);
            }
            $xml->endElement();
        }
        if ($interior !== []) {
            $xml->startElement('Interior');
            foreach ($interior as $name => $value) {
                $xml->writeAttribute('ss:'.$name, $value);
            }
            $xml->endElement();
        }
        if ($alignment !== []) {
            $xml->startElement('Alignment');
            foreach ($alignment as $name => $value) {
                $xml->writeAttribute('ss:'.$name, $value);
            }
            $xml->endElement();
        }
        if ($numberFormat !== null) {
            $xml->startElement('NumberFormat');
            $xml->writeAttribute('ss:Format', $numberFormat);
            $xml->endElement();
        }
        if ($borders) {
            $xml->startElement('Borders');
            foreach (['Bottom', 'Left', 'Right', 'Top'] as $position) {
                $xml->startElement('Border');
                $xml->writeAttribute('ss:Position', $position);
                $xml->writeAttribute('ss:LineStyle', 'Continuous');
                $xml->writeAttribute('ss:Weight', '1');
                $xml->writeAttribute('ss:Color', '#D6E1DE');
                $xml->endElement();
            }
            $xml->endElement();
        }
        $xml->endElement();
    }

    /** @param array<string, mixed> $report */
    private function writeSummarySheet(XMLWriter $xml, array $report): void
    {
        $this->startWorksheet($xml, 'Report Summary', [190, 120, 260]);
        $this->row($xml, [['TOUCHnRELIEF Sales Report', 'String', 'Title', 2]], 30);
        $this->row($xml, [['Generated', 'String', 'Label'], [now()->format('M j, Y g:i A'), 'String', 'Text']]);
        $this->row($xml, [['Report view', 'String', 'Label'], [(string) ($report['periodValueLabel'] ?? ''), 'String', 'Text']]);
        $this->blankRow($xml);

        $this->section($xml, 'Summary', 2);
        $this->row($xml, [['Metric', 'String', 'Header'], ['Value', 'String', 'Header'], ['Explanation', 'String', 'Header']]);
        $this->row($xml, [[(string) ($report['primaryLabel'] ?? 'Net sales'), 'String', 'Label'], [(float) ($report['primaryAmount'] ?? 0), 'Number', 'Currency'], ['Collections less processed refunds', 'String', 'Text']]);
        $this->row($xml, [['Gross collections', 'String', 'Label'], [(float) ($report['grossCollections'] ?? 0), 'Number', 'Currency'], ['Initial and balance payments collected', 'String', 'Text']]);
        $this->row($xml, [['Processed refunds', 'String', 'Label'], [-abs((float) ($report['refundTotal'] ?? 0)), 'Number', 'Refund'], ['Refunds completed during this report period', 'String', 'Text']]);
        $this->row($xml, [[(string) ($report['secondaryLabel'] ?? 'New customers'), 'String', 'Label'], [(int) ($report['secondaryUserCount'] ?? 0), 'Number', 'Number'], ['Registered customer accounts only', 'String', 'Text']]);
        $this->row($xml, [[(string) ($report['hoursLabel'] ?? 'Service hours'), 'String', 'Label'], [(float) ($report['hoursValue'] ?? 0), 'Number', 'Hours'], ['Completed appointment service time', 'String', 'Text']]);
        $this->blankRow($xml);

        $this->section($xml, 'Sales Activity - '.(string) ($report['trendSubtitle'] ?? ''), 2);
        $this->row($xml, [['Period', 'String', 'Header'], ['Net sales', 'String', 'Header']]);
        $activeTrendRows = $this->pairedNonZeroRows($report['trendLabels'] ?? [], $report['trendData'] ?? []);
        if ($activeTrendRows === []) {
            $this->row($xml, [['No payment activity for this period', 'String', 'Note', 1]]);
        } else {
            foreach ($activeTrendRows as [$label, $value]) {
                $this->row($xml, [[$label, 'String', 'Text'], [$value, 'Number', $value < 0 ? 'Refund' : 'Currency']]);
            }
            $this->row($xml, [['Zero-activity periods are omitted for readability.', 'String', 'Note', 1]]);
        }
        $this->blankRow($xml);

        $this->section($xml, 'Service and Package Revenue', 2);
        $this->row($xml, [['Service or package', 'String', 'Header'], ['Net revenue', 'String', 'Header']]);
        $serviceRows = $this->pairedNonZeroRows($report['serviceLabels'] ?? [], $report['serviceTotals'] ?? []);
        if ($serviceRows === []) {
            $this->row($xml, [['No service or package revenue for this period', 'String', 'Note', 1]]);
        } else {
            foreach ($serviceRows as [$label, $value]) {
                $this->row($xml, [[$label, 'String', 'Text'], [$value, 'Number', $value < 0 ? 'Refund' : 'Currency']]);
            }
        }
        $this->blankRow($xml);

        $this->section($xml, 'Therapist Service Hours', 2);
        $this->row($xml, [['Therapist', 'String', 'Header'], ['Hours', 'String', 'Header']]);
        $therapists = collect($report['therapistHoursBreakdown'] ?? [])->filter(fn ($row) => (float) ($row['hours'] ?? 0) > 0);
        if ($therapists->isEmpty()) {
            $this->row($xml, [['No completed service hours for this period', 'String', 'Note', 1]]);
        } else {
            foreach ($therapists as $therapist) {
                $this->row($xml, [[(string) ($therapist['name'] ?? 'Unknown therapist'), 'String', 'Text'], [(float) ($therapist['hours'] ?? 0), 'Number', 'Hours']]);
            }
        }

        $this->endWorksheet($xml, freezeRows: 4, selected: true);
    }

    /** @param array<string, mixed> $report */
    private function writeLedgerSheet(XMLWriter $xml, array $report): void
    {
        $widths = [125, 95, 75, 150, 160, 135, 110, 190, 95, 95, 110, 120];
        $this->startWorksheet($xml, 'Payment Ledger', $widths);
        $this->row($xml, [['Complete Payment Ledger', 'String', 'Title', 11]], 30);
        $periodLabel = ucfirst((string) ($report['period'] ?? 'report')).' - '.(string) ($report['serviceLabel'] ?? $report['periodValueLabel'] ?? '');
        $this->row($xml, [['Report period', 'String', 'Label'], [$periodLabel, 'String', 'Text', 10]]);
        $this->row($xml, [['Historical estimates use the booking creation time because older bookings did not store an exact initial collection timestamp.', 'String', 'Note', 11]]);
        $this->blankRow($xml);

        $headers = ['Collected / refunded at', 'Entry type', 'Booking', 'Client', 'Service or package', 'Therapist', 'Payment method', 'Payment reference', 'Amount', 'Net amount', 'Timestamp source', 'Recorded by'];
        $this->row($xml, array_map(fn (string $header): array => [$header, 'String', 'Header'], $headers), 28);

        $entries = $report['ledgerRows'] ?? [];
        foreach ($entries as $entry) {
            $occurredAt = Carbon::parse((string) ($entry['occurredAt'] ?? now()))->format('Y-m-d\TH:i:s.000');
            $isRefund = (float) ($entry['netAmount'] ?? 0) < 0;
            $isEstimated = ! empty($entry['isEstimated']);
            $this->row($xml, [
                [$occurredAt, 'DateTime', 'DateTime'],
                [(string) ($entry['typeLabel'] ?? ''), 'String', 'Text'],
                [(string) ($entry['bookingReference'] ?? ''), 'String', 'Text'],
                [(string) ($entry['client'] ?? ''), 'String', 'Text'],
                [(string) ($entry['service'] ?? ''), 'String', 'Text'],
                [(string) ($entry['therapist'] ?? ''), 'String', 'Text'],
                [(string) ($entry['paymentMethod'] ?? ''), 'String', 'Text'],
                [(string) ($entry['reference'] ?? ''), 'String', 'Text'],
                [(float) ($entry['amount'] ?? 0), 'Number', 'Currency'],
                [(float) ($entry['netAmount'] ?? 0), 'Number', $isRefund ? 'Refund' : 'Currency'],
                [$isEstimated ? 'Historical estimate' : 'Exact recorded time', 'String', $isEstimated ? 'Estimated' : 'Text'],
                [(string) ($entry['recordedBy'] ?? 'System'), 'String', 'Text'],
            ], 34);
        }

        if ($entries === []) {
            $this->row($xml, [['No payment activity for this period', 'String', 'Note', 11]]);
        }

        $lastRow = max(count($entries) + 5, 5);
        $this->endWorksheet(
            $xml,
            freezeRows: 5,
            landscape: true,
            autoFilterRange: 'R5C1:R'.$lastRow.'C12',
        );
    }

    /** @param list<mixed> $labels @param list<mixed> $values @return list<array{0: string, 1: float}> */
    private function pairedNonZeroRows(array $labels, array $values): array
    {
        $rows = [];
        foreach ($labels as $index => $label) {
            $value = (float) ($values[$index] ?? 0);
            if (abs($value) < 0.005) {
                continue;
            }
            $rows[] = [(string) $label, $value];
        }

        return $rows;
    }

    /** @param list<int|float> $widths */
    private function startWorksheet(XMLWriter $xml, string $name, array $widths): void
    {
        $xml->startElement('Worksheet');
        $xml->writeAttribute('ss:Name', $name);
        $xml->startElement('Table');
        foreach ($widths as $width) {
            $xml->startElement('Column');
            $xml->writeAttribute('ss:AutoFitWidth', '0');
            $xml->writeAttribute('ss:Width', (string) $width);
            $xml->endElement();
        }
    }

    private function endWorksheet(
        XMLWriter $xml,
        int $freezeRows = 0,
        bool $landscape = false,
        bool $selected = false,
        ?string $autoFilterRange = null,
    ): void {
        $xml->endElement();
        if ($autoFilterRange !== null) {
            $xml->startElement('AutoFilter');
            $xml->writeAttribute('x:Range', $autoFilterRange);
            $xml->writeAttribute('xmlns', 'urn:schemas-microsoft-com:office:excel');
            $xml->endElement();
        }
        $xml->startElement('WorksheetOptions');
        $xml->writeAttribute('xmlns', 'urn:schemas-microsoft-com:office:excel');
        if ($selected) {
            $xml->writeElement('Selected');
        }
        if ($freezeRows > 0) {
            $xml->writeElement('FreezePanes');
            $xml->writeElement('FrozenNoSplit');
            $xml->writeElement('SplitHorizontal', (string) $freezeRows);
            $xml->writeElement('TopRowBottomPane', (string) $freezeRows);
            $xml->writeElement('ActivePane', '2');
        }
        if ($landscape) {
            $xml->startElement('PageSetup');
            $xml->startElement('Layout');
            $xml->writeAttribute('x:Orientation', 'Landscape');
            $xml->endElement();
            $xml->endElement();
        }
        $xml->endElement();
        $xml->endElement();
    }

    /** @param list<array{0: mixed, 1?: string, 2?: string, 3?: int}> $cells */
    private function row(XMLWriter $xml, array $cells, ?int $height = null): void
    {
        $xml->startElement('Row');
        if ($height !== null) {
            $xml->writeAttribute('ss:AutoFitHeight', '0');
            $xml->writeAttribute('ss:Height', (string) $height);
        }
        foreach ($cells as $cell) {
            [$value, $type, $style, $mergeAcross] = array_pad($cell, 4, null);
            $xml->startElement('Cell');
            if ($style !== null) {
                $xml->writeAttribute('ss:StyleID', (string) $style);
            }
            if ($mergeAcross !== null) {
                $xml->writeAttribute('ss:MergeAcross', (string) $mergeAcross);
            }
            $xml->startElement('Data');
            $xml->writeAttribute('ss:Type', (string) ($type ?: 'String'));
            $xml->text((string) $value);
            $xml->endElement();
            $xml->endElement();
        }
        $xml->endElement();
    }

    private function section(XMLWriter $xml, string $title, int $mergeAcross): void
    {
        $this->row($xml, [[$title, 'String', 'Section', $mergeAcross]], 23);
    }

    private function blankRow(XMLWriter $xml): void
    {
        $this->row($xml, [['', 'String', null]], 8);
    }
}

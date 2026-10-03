<?php

namespace App\Services;

use Carbon\Carbon;
use XMLWriter;

class ReportingSpreadsheetService
{
    private const MAIN_NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const RELATIONSHIP_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private const STYLE_INDEXES = [
        'Default' => 0, 'Title' => 1, 'Subtitle' => 2, 'Section' => 3,
        'Header' => 4, 'Label' => 5, 'Text' => 6, 'Number' => 7,
        'Hours' => 8, 'Currency' => 9, 'Refund' => 10, 'DateTime' => 11,
        'Estimated' => 12, 'Note' => 13,
    ];

    /** @var list<array<string, mixed>> */
    private array $sheets = [];

    private int $activeSheet = 0;

    /** @param array<string, mixed> $report */
    public function build(array $report): string
    {
        $this->sheets = [];
        $this->writeSummarySheet($report);
        $this->writeLedgerSheet($report);

        $parts = [
            '[Content_Types].xml' => $this->contentTypesXml(),
            '_rels/.rels' => $this->rootRelationshipsXml(),
            'xl/workbook.xml' => $this->workbookXml(),
            'xl/_rels/workbook.xml.rels' => $this->workbookRelationshipsXml(),
            'xl/styles.xml' => $this->stylesXml(),
        ];

        foreach ($this->sheets as $index => $sheet) {
            $parts['xl/worksheets/sheet'.($index + 1).'.xml'] = $this->worksheetXml($sheet);
        }

        return $this->package($parts);
    }

    /** @param array<string, mixed> $report */
    private function writeSummarySheet(array $report): void
    {
        $this->startWorksheet('Report Summary', [28, 18, 38]);
        $this->row([['TOUCHnRELIEF Sales Report', 'String', 'Title', 2]], 30);
        $this->row([['Generated', 'String', 'Label'], [now()->format('M j, Y g:i A'), 'String', 'Text']]);
        $this->row([['Report view', 'String', 'Label'], [(string) ($report['periodValueLabel'] ?? ''), 'String', 'Text']]);
        $this->blankRow();

        $this->section('Summary', 2);
        $this->row([['Metric', 'String', 'Header'], ['Value', 'String', 'Header'], ['Explanation', 'String', 'Header']]);
        $this->row([[(string) ($report['primaryLabel'] ?? 'Net sales'), 'String', 'Label'], [(float) ($report['primaryAmount'] ?? 0), 'Number', 'Currency'], ['Collections less processed refunds', 'String', 'Text']]);
        $this->row([['Gross collections', 'String', 'Label'], [(float) ($report['grossCollections'] ?? 0), 'Number', 'Currency'], ['Verified initial, balance, and membership payments', 'String', 'Text']]);
        $this->row([['Processed refunds', 'String', 'Label'], [-abs((float) ($report['refundTotal'] ?? 0)), 'Number', 'Refund'], ['Refunds completed during this report period', 'String', 'Text']]);
        $this->row([['Membership collections', 'String', 'Label'], [(float) ($report['membershipCollections'] ?? 0), 'Number', 'Currency'], ['Verified membership purchases', 'String', 'Text']]);
        $this->row([['No-show fee sales', 'String', 'Label'], [(float) ($report['noShowFeeRevenue'] ?? 0), 'Number', 'Currency'], ['Payments retained for no-show appointments, less processed refunds', 'String', 'Text']]);
        $this->row([['Payment count', 'String', 'Label'], [(int) ($report['paymentCount'] ?? 0), 'Number', 'Number'], ['Collected payment entries; refunds excluded', 'String', 'Text']]);
        $this->row([['Average payment', 'String', 'Label'], [(float) ($report['averagePayment'] ?? 0), 'Number', 'Currency'], ['Gross collections divided by payment count', 'String', 'Text']]);
        $this->row([['Outstanding balances', 'String', 'Label'], [(float) ($report['outstandingBalanceTotal'] ?? 0), 'Number', 'Currency'], ['Not included in collected sales', 'String', 'Text']]);
        $this->row([[(string) ($report['secondaryLabel'] ?? 'New customers'), 'String', 'Label'], [(int) ($report['secondaryUserCount'] ?? 0), 'Number', 'Number'], ['Registered customer accounts only', 'String', 'Text']]);
        $this->row([[(string) ($report['hoursLabel'] ?? 'Service hours'), 'String', 'Label'], [(float) ($report['hoursValue'] ?? 0), 'Number', 'Hours'], ['Completed appointment service time', 'String', 'Text']]);
        $this->blankRow();

        $this->section('Sales Activity - '.(string) ($report['trendSubtitle'] ?? ''), 2);
        $this->row([['Period', 'String', 'Header'], ['Net sales', 'String', 'Header']]);
        $activeTrendRows = $this->pairedNonZeroRows($report['trendLabels'] ?? [], $report['trendData'] ?? []);
        if ($activeTrendRows === []) {
            $this->row([['No payment activity for this period', 'String', 'Note', 1]]);
        } else {
            foreach ($activeTrendRows as [$label, $value]) {
                $this->row([[$label, 'String', 'Text'], [$value, 'Number', $value < 0 ? 'Refund' : 'Currency']]);
            }
            $this->row([['Zero-activity periods are omitted for readability.', 'String', 'Note', 1]]);
        }
        $this->blankRow();

        $this->section('Offering and No-show Fee Sales', 2);
        $this->row([['Offering', 'String', 'Header'], ['Net sales', 'String', 'Header']]);
        $serviceRows = $this->pairedNonZeroRows($report['serviceLabels'] ?? [], $report['serviceTotals'] ?? []);
        if ($serviceRows === []) {
            $this->row([['No offering sales for this period', 'String', 'Note', 1]]);
        } else {
            foreach ($serviceRows as [$label, $value]) {
                $this->row([[$label, 'String', 'Text'], [$value, 'Number', $value < 0 ? 'Refund' : 'Currency']]);
            }
        }
        $this->blankRow();

        $this->section('Therapist Service Hours', 2);
        $this->row([['Therapist', 'String', 'Header'], ['Hours', 'String', 'Header']]);
        $therapists = collect($report['therapistHoursBreakdown'] ?? [])->filter(fn ($row) => (float) ($row['hours'] ?? 0) > 0);
        if ($therapists->isEmpty()) {
            $this->row([['No completed service hours for this period', 'String', 'Note', 1]]);
        } else {
            foreach ($therapists as $therapist) {
                $this->row([[(string) ($therapist['name'] ?? 'Unknown therapist'), 'String', 'Text'], [(float) ($therapist['hours'] ?? 0), 'Number', 'Hours']]);
            }
        }

        $this->endWorksheet(freezeRows: 4, selected: true);
    }

    /** @param array<string, mixed> $report */
    private function writeLedgerSheet(array $report): void
    {
        $this->startWorksheet('Payment Ledger', [20, 16, 13, 24, 25, 22, 18, 30, 16, 16, 18, 19]);
        $this->row([['Complete Payment Ledger', 'String', 'Title', 11]], 30);
        $periodLabel = ucfirst((string) ($report['period'] ?? 'report')).' - '.(string) ($report['serviceLabel'] ?? $report['periodValueLabel'] ?? '');
        $this->row([['Report period', 'String', 'Label'], [$periodLabel, 'String', 'Text', 10]]);
        $this->row([['Historical estimates use the booking creation time because older bookings did not store an exact initial collection timestamp.', 'String', 'Note', 11]]);
        $this->blankRow();

        $headers = ['Collected / refunded at', 'Entry type', 'Reference', 'Client', 'Service, package, or membership', 'Therapist', 'Payment method', 'Payment reference', 'Amount', 'Net amount', 'Timestamp source', 'Recorded by'];
        $this->row(array_map(fn (string $header): array => [$header, 'String', 'Header'], $headers), 28);

        $entries = $report['ledgerRows'] ?? [];
        foreach ($entries as $entry) {
            $occurredAt = Carbon::parse((string) ($entry['occurredAt'] ?? now()))->format('Y-m-d\TH:i:s');
            $isRefund = (float) ($entry['netAmount'] ?? 0) < 0;
            $isEstimated = ! empty($entry['isEstimated']);
            $this->row([
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
            $this->row([['No payment activity for this period', 'String', 'Note', 11]]);
        }

        $lastRow = max(count($entries) + 5, 5);
        $this->endWorksheet(freezeRows: 5, landscape: true, autoFilterRange: 'A5:L'.$lastRow);
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
    private function startWorksheet(string $name, array $widths): void
    {
        $this->sheets[] = [
            'name' => $name, 'widths' => $widths, 'rows' => [], 'merges' => [],
            'freezeRows' => 0, 'selected' => false, 'landscape' => false, 'autoFilterRange' => null,
        ];
        $this->activeSheet = count($this->sheets) - 1;
    }

    private function endWorksheet(int $freezeRows = 0, bool $landscape = false, bool $selected = false, ?string $autoFilterRange = null): void
    {
        $this->sheets[$this->activeSheet]['freezeRows'] = $freezeRows;
        $this->sheets[$this->activeSheet]['selected'] = $selected;
        $this->sheets[$this->activeSheet]['landscape'] = $landscape;
        $this->sheets[$this->activeSheet]['autoFilterRange'] = $autoFilterRange;
    }

    /** @param list<array{0: mixed, 1?: string, 2?: string, 3?: int}> $cells */
    private function row(array $cells, ?int $height = null): void
    {
        $rowNumber = count($this->sheets[$this->activeSheet]['rows']) + 1;
        $column = 1;
        $preparedCells = [];

        foreach ($cells as $cell) {
            [$value, $type, $style, $mergeAcross] = array_pad($cell, 4, null);
            $reference = $this->columnName($column).$rowNumber;
            $preparedCells[] = ['reference' => $reference, 'value' => $value, 'type' => (string) ($type ?: 'String'), 'style' => $style];
            $mergeAcross = (int) ($mergeAcross ?? 0);
            if ($mergeAcross > 0) {
                $this->sheets[$this->activeSheet]['merges'][] = $reference.':'.$this->columnName($column + $mergeAcross).$rowNumber;
            }
            $column += $mergeAcross + 1;
        }

        $this->sheets[$this->activeSheet]['rows'][] = ['number' => $rowNumber, 'height' => $height, 'cells' => $preparedCells];
    }

    private function section(string $title, int $mergeAcross): void
    {
        $this->row([[$title, 'String', 'Section', $mergeAcross]], 23);
    }

    private function blankRow(): void
    {
        $this->row([['', 'String', null]], 8);
    }

    /** @param array<string, mixed> $sheet */
    private function worksheetXml(array $sheet): string
    {
        $xml = $this->xmlWriter();
        $xml->startElement('worksheet');
        $xml->writeAttribute('xmlns', self::MAIN_NS);

        $xml->startElement('sheetViews');
        $xml->startElement('sheetView');
        $xml->writeAttribute('workbookViewId', '0');
        if ($sheet['selected']) {
            $xml->writeAttribute('tabSelected', '1');
        }
        if ($sheet['freezeRows'] > 0) {
            $xml->startElement('pane');
            $xml->writeAttribute('ySplit', (string) $sheet['freezeRows']);
            $xml->writeAttribute('topLeftCell', 'A'.($sheet['freezeRows'] + 1));
            $xml->writeAttribute('activePane', 'bottomLeft');
            $xml->writeAttribute('state', 'frozen');
            $xml->endElement();
        }
        $xml->endElement();
        $xml->endElement();

        $xml->startElement('sheetFormatPr');
        $xml->writeAttribute('defaultRowHeight', '15');
        $xml->endElement();
        $xml->startElement('cols');
        foreach ($sheet['widths'] as $index => $width) {
            $xml->startElement('col');
            $xml->writeAttribute('min', (string) ($index + 1));
            $xml->writeAttribute('max', (string) ($index + 1));
            $xml->writeAttribute('width', (string) $width);
            $xml->writeAttribute('customWidth', '1');
            $xml->endElement();
        }
        $xml->endElement();

        $xml->startElement('sheetData');
        foreach ($sheet['rows'] as $row) {
            $xml->startElement('row');
            $xml->writeAttribute('r', (string) $row['number']);
            if ($row['height'] !== null) {
                $xml->writeAttribute('ht', (string) $row['height']);
                $xml->writeAttribute('customHeight', '1');
            }
            foreach ($row['cells'] as $cell) {
                $this->writeCell($xml, $cell);
            }
            $xml->endElement();
        }
        $xml->endElement();

        if ($sheet['autoFilterRange'] !== null) {
            $xml->startElement('autoFilter');
            $xml->writeAttribute('ref', $sheet['autoFilterRange']);
            $xml->endElement();
        }
        if ($sheet['merges'] !== []) {
            $xml->startElement('mergeCells');
            $xml->writeAttribute('count', (string) count($sheet['merges']));
            foreach ($sheet['merges'] as $range) {
                $xml->startElement('mergeCell');
                $xml->writeAttribute('ref', $range);
                $xml->endElement();
            }
            $xml->endElement();
        }
        if ($sheet['landscape']) {
            $xml->startElement('pageMargins');
            foreach (['left' => '0.7', 'right' => '0.7', 'top' => '0.75', 'bottom' => '0.75', 'header' => '0.3', 'footer' => '0.3'] as $name => $value) {
                $xml->writeAttribute($name, $value);
            }
            $xml->endElement();
            $xml->startElement('pageSetup');
            $xml->writeAttribute('orientation', 'landscape');
            $xml->endElement();
        }
        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    /** @param array<string, mixed> $cell */
    private function writeCell(XMLWriter $xml, array $cell): void
    {
        $xml->startElement('c');
        $xml->writeAttribute('r', $cell['reference']);
        if ($cell['style'] !== null) {
            $xml->writeAttribute('s', (string) self::STYLE_INDEXES[$cell['style']]);
        }
        if ($cell['type'] === 'Number') {
            $xml->writeAttribute('t', 'n');
            $xml->writeElement('v', $this->numericValue((float) $cell['value']));
        } elseif ($cell['type'] === 'DateTime') {
            $date = Carbon::parse((string) $cell['value']);
            $serial = (($date->getTimestamp() + $date->getOffset()) / 86400) + 25569;
            $xml->writeAttribute('t', 'n');
            $xml->writeElement('v', $this->numericValue($serial));
        } else {
            $xml->writeAttribute('t', 'inlineStr');
            $xml->startElement('is');
            $xml->startElement('t');
            $xml->writeAttribute('xml:space', 'preserve');
            $xml->text((string) $cell['value']);
            $xml->endElement();
            $xml->endElement();
        }
        $xml->endElement();
    }

    private function stylesXml(): string
    {
        $xml = $this->xmlWriter();
        $xml->startElement('styleSheet');
        $xml->writeAttribute('xmlns', self::MAIN_NS);
        $xml->startElement('numFmts');
        $xml->writeAttribute('count', '4');
        $this->numberFormat($xml, 164, '0.0" hrs"');
        $this->numberFormat($xml, 165, '"PHP "#,##0.00;[Red]-"PHP "#,##0.00');
        $this->numberFormat($xml, 166, '[Red]-"PHP "#,##0.00');
        $this->numberFormat($xml, 167, 'mmm d, yyyy h:mm AM/PM');
        $xml->endElement();

        $fonts = [
            ['size' => 11], ['size' => 18, 'bold' => true, 'color' => 'FFFFFFFF'],
            ['size' => 11, 'italic' => true, 'color' => 'FF51636D'],
            ['size' => 12, 'bold' => true, 'color' => 'FFFFFFFF'],
            ['size' => 11, 'bold' => true, 'color' => 'FF173A30'],
            ['size' => 11, 'bold' => true, 'color' => 'FF37564C'],
            ['size' => 11, 'bold' => true, 'color' => 'FF9B2C2C'],
            ['size' => 11, 'italic' => true, 'color' => 'FF8A5A00'],
            ['size' => 11, 'italic' => true, 'color' => 'FF647680'],
        ];
        $xml->startElement('fonts');
        $xml->writeAttribute('count', (string) count($fonts));
        foreach ($fonts as $font) {
            $xml->startElement('font');
            if ($font['bold'] ?? false) {
                $xml->writeElement('b');
            }
            if ($font['italic'] ?? false) {
                $xml->writeElement('i');
            }
            $xml->startElement('sz');
            $xml->writeAttribute('val', (string) $font['size']);
            $xml->endElement();
            if (isset($font['color'])) {
                $xml->startElement('color');
                $xml->writeAttribute('rgb', $font['color']);
                $xml->endElement();
            }
            $xml->startElement('name');
            $xml->writeAttribute('val', 'Calibri');
            $xml->endElement();
            $xml->endElement();
        }
        $xml->endElement();

        $fills = [null, 'gray125', 'FF075A43', 'FF04724D', 'FFDDEFE8', 'FFF3F8F6', 'FFFDE8E8', 'FFFFF7DB'];
        $xml->startElement('fills');
        $xml->writeAttribute('count', (string) count($fills));
        foreach ($fills as $index => $color) {
            $xml->startElement('fill');
            $xml->startElement('patternFill');
            if ($index < 2) {
                $xml->writeAttribute('patternType', $index === 0 ? 'none' : 'gray125');
            } else {
                $xml->writeAttribute('patternType', 'solid');
                $xml->startElement('fgColor');
                $xml->writeAttribute('rgb', $color);
                $xml->endElement();
                $xml->startElement('bgColor');
                $xml->writeAttribute('indexed', '64');
                $xml->endElement();
            }
            $xml->endElement();
            $xml->endElement();
        }
        $xml->endElement();

        $xml->startElement('borders');
        $xml->writeAttribute('count', '2');
        $this->border($xml, false);
        $this->border($xml, true);
        $xml->endElement();
        $xml->startElement('cellStyleXfs');
        $xml->writeAttribute('count', '1');
        $this->xf($xml);
        $xml->endElement();
        $xml->startElement('cellXfs');
        $xml->writeAttribute('count', '14');
        $this->xf($xml);
        $this->xf($xml, font: 1, fill: 2, alignment: ['vertical' => 'center']);
        $this->xf($xml, font: 2);
        $this->xf($xml, font: 3, fill: 3);
        $this->xf($xml, font: 4, fill: 4, border: 1, alignment: ['vertical' => 'center', 'wrapText' => '1']);
        $this->xf($xml, font: 5, fill: 5, border: 1);
        $this->xf($xml, border: 1, alignment: ['vertical' => 'center', 'wrapText' => '1']);
        $this->xf($xml, numberFormat: 3, border: 1);
        $this->xf($xml, numberFormat: 164, border: 1);
        $this->xf($xml, numberFormat: 165, border: 1);
        $this->xf($xml, font: 6, fill: 6, numberFormat: 166, border: 1);
        $this->xf($xml, numberFormat: 167, border: 1);
        $this->xf($xml, font: 7, fill: 7, border: 1, alignment: ['wrapText' => '1']);
        $this->xf($xml, font: 8, alignment: ['wrapText' => '1']);
        $xml->endElement();
        $xml->startElement('cellStyles');
        $xml->writeAttribute('count', '1');
        $xml->startElement('cellStyle');
        $xml->writeAttribute('name', 'Normal');
        $xml->writeAttribute('xfId', '0');
        $xml->writeAttribute('builtinId', '0');
        $xml->endElement();
        $xml->endElement();
        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    private function numberFormat(XMLWriter $xml, int $id, string $code): void
    {
        $xml->startElement('numFmt');
        $xml->writeAttribute('numFmtId', (string) $id);
        $xml->writeAttribute('formatCode', $code);
        $xml->endElement();
    }

    private function border(XMLWriter $xml, bool $visible): void
    {
        $xml->startElement('border');
        foreach (['left', 'right', 'top', 'bottom'] as $side) {
            $xml->startElement($side);
            if ($visible) {
                $xml->writeAttribute('style', 'thin');
                $xml->startElement('color');
                $xml->writeAttribute('rgb', 'FFD6E1DE');
                $xml->endElement();
            }
            $xml->endElement();
        }
        $xml->writeElement('diagonal');
        $xml->endElement();
    }

    /** @param array<string, string> $alignment */
    private function xf(XMLWriter $xml, int $font = 0, int $fill = 0, int $numberFormat = 0, int $border = 0, array $alignment = []): void
    {
        $xml->startElement('xf');
        $xml->writeAttribute('numFmtId', (string) $numberFormat);
        $xml->writeAttribute('fontId', (string) $font);
        $xml->writeAttribute('fillId', (string) $fill);
        $xml->writeAttribute('borderId', (string) $border);
        $xml->writeAttribute('xfId', '0');
        foreach (['NumberFormat' => $numberFormat, 'Font' => $font, 'Fill' => $fill, 'Border' => $border] as $name => $value) {
            if ($value !== 0) {
                $xml->writeAttribute('apply'.$name, '1');
            }
        }
        if ($alignment !== []) {
            $xml->writeAttribute('applyAlignment', '1');
            $xml->startElement('alignment');
            foreach ($alignment as $name => $value) {
                $xml->writeAttribute($name, $value);
            }
            $xml->endElement();
        }
        $xml->endElement();
    }

    private function workbookXml(): string
    {
        $xml = $this->xmlWriter();
        $xml->startElement('workbook');
        $xml->writeAttribute('xmlns', self::MAIN_NS);
        $xml->writeAttribute('xmlns:r', self::RELATIONSHIP_NS);
        $xml->startElement('bookViews');
        $xml->startElement('workbookView');
        $xml->writeAttribute('activeTab', '0');
        $xml->endElement();
        $xml->endElement();
        $xml->startElement('sheets');
        foreach ($this->sheets as $index => $sheet) {
            $xml->startElement('sheet');
            $xml->writeAttribute('name', $sheet['name']);
            $xml->writeAttribute('sheetId', (string) ($index + 1));
            $xml->writeAttribute('r:id', 'rId'.($index + 1));
            $xml->endElement();
        }
        $xml->endElement();
        $xml->startElement('calcPr');
        $xml->writeAttribute('calcId', '0');
        $xml->endElement();
        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    private function contentTypesXml(): string
    {
        $xml = $this->xmlWriter();
        $xml->startElement('Types');
        $xml->writeAttribute('xmlns', 'http://schemas.openxmlformats.org/package/2006/content-types');
        $this->contentType($xml, 'Default', ['Extension' => 'rels', 'ContentType' => 'application/vnd.openxmlformats-package.relationships+xml']);
        $this->contentType($xml, 'Default', ['Extension' => 'xml', 'ContentType' => 'application/xml']);
        $this->contentType($xml, 'Override', ['PartName' => '/xl/workbook.xml', 'ContentType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml']);
        $this->contentType($xml, 'Override', ['PartName' => '/xl/styles.xml', 'ContentType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml']);
        foreach ($this->sheets as $index => $_sheet) {
            $this->contentType($xml, 'Override', ['PartName' => '/xl/worksheets/sheet'.($index + 1).'.xml', 'ContentType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml']);
        }
        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    /** @param array<string, string> $attributes */
    private function contentType(XMLWriter $xml, string $element, array $attributes): void
    {
        $xml->startElement($element);
        foreach ($attributes as $name => $value) {
            $xml->writeAttribute($name, $value);
        }
        $xml->endElement();
    }

    private function rootRelationshipsXml(): string
    {
        return $this->relationshipsXml([[
            'Id' => 'rId1',
            'Type' => 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument',
            'Target' => 'xl/workbook.xml',
        ]]);
    }

    private function workbookRelationshipsXml(): string
    {
        $relationships = [];
        foreach ($this->sheets as $index => $_sheet) {
            $relationships[] = [
                'Id' => 'rId'.($index + 1),
                'Type' => 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet',
                'Target' => 'worksheets/sheet'.($index + 1).'.xml',
            ];
        }
        $relationships[] = [
            'Id' => 'rId'.(count($this->sheets) + 1),
            'Type' => 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles',
            'Target' => 'styles.xml',
        ];

        return $this->relationshipsXml($relationships);
    }

    /** @param list<array{Id: string, Type: string, Target: string}> $relationships */
    private function relationshipsXml(array $relationships): string
    {
        $xml = $this->xmlWriter();
        $xml->startElement('Relationships');
        $xml->writeAttribute('xmlns', 'http://schemas.openxmlformats.org/package/2006/relationships');
        foreach ($relationships as $relationship) {
            $xml->startElement('Relationship');
            foreach ($relationship as $name => $value) {
                $xml->writeAttribute($name, $value);
            }
            $xml->endElement();
        }
        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    /** @param array<string, string> $parts */
    private function package(array $parts): string
    {
        $archive = '';
        $directory = '';
        $offset = 0;
        $entryCount = 0;
        $now = getdate();
        $dosTime = (($now['hours'] & 0x1F) << 11) | (($now['minutes'] & 0x3F) << 5) | (($now['seconds'] >> 1) & 0x1F);
        $dosDate = ((max($now['year'], 1980) - 1980) << 9) | (($now['mon'] & 0x0F) << 5) | ($now['mday'] & 0x1F);

        foreach ($parts as $path => $contents) {
            $compressed = gzdeflate($contents, 9);
            $crc = unpack('N', hash('crc32b', $contents, true))[1];
            $pathLength = strlen($path);
            $compressedLength = strlen($compressed);
            $length = strlen($contents);

            $localHeader = pack(
                'VvvvvvVVVvv',
                0x04034B50,
                20,
                0,
                8,
                $dosTime,
                $dosDate,
                $crc,
                $compressedLength,
                $length,
                $pathLength,
                0,
            );
            $archive .= $localHeader.$path.$compressed;

            $directory .= pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014B50,
                20,
                20,
                0,
                8,
                $dosTime,
                $dosDate,
                $crc,
                $compressedLength,
                $length,
                $pathLength,
                0,
                0,
                0,
                0,
                0,
                $offset,
            ).$path;

            $offset = strlen($archive);
            $entryCount++;
        }

        $directoryOffset = strlen($archive);
        $archive .= $directory;
        $archive .= pack(
            'VvvvvVVv',
            0x06054B50,
            0,
            0,
            $entryCount,
            $entryCount,
            strlen($directory),
            $directoryOffset,
            0,
        );

        return $archive;
    }

    private function xmlWriter(): XMLWriter
    {
        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8', 'yes');

        return $xml;
    }

    private function numericValue(float $value): string
    {
        if (abs($value) < 0.0000001) {
            return '0';
        }

        return rtrim(rtrim(number_format($value, 10, '.', ''), '0'), '.');
    }

    private function columnName(int $column): string
    {
        $name = '';
        while ($column > 0) {
            $column--;
            $name = chr(65 + ($column % 26)).$name;
            $column = intdiv($column, 26);
        }

        return $name;
    }
}

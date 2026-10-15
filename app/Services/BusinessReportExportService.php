<?php

namespace App\Services;

use App\Models\Business;
use App\Models\User;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class BusinessReportExportService
{
    private const ACCENT = 'FF940000';

    private const SECTION_BG = 'FFFDECEA';

    private const ALT_ROW = 'FFF9F9F9';

    private const SUBTOTAL_BG = 'FFF0F0F0';
    public function __construct(
        private BusinessReportService $reports,
        private CashierPerformanceService $cashiers,
    ) {}

    /**
     * @return array{title: string, business_name: string, period_label: string, generated_by: string, generated_at: string, sections: list<array{title: string, headers: list<string>, rows: list<list<string|float|int>>}>}
     */
    public function build(string $reportKey, Business $business, User $user, Request $request, ?int $branchFilterId, array $businessTypes = []): array
    {
        $businessTypeKey = $this->reports->resolveBusinessTypeFilter($request, $business, $businessTypes);

        $title = collect(business_report_menu_options())->firstWhere('key', $reportKey)['label']
            ?? ucfirst(str_replace('-', ' ', $reportKey));

        if (in_array($reportKey, ['daily-report', 'payment-channels', 'daily-items-sold'], true)) {
            $reportDate = $this->singleReportDate($request);
            $periodLabel = Carbon::parse($reportDate)->format('d M Y');
            $sections = match ($reportKey) {
                'daily-report' => $this->sectionsDailyReport($business, $reportDate, $businessTypeKey),
                'payment-channels' => $this->sectionsPaymentChannels($business, $reportDate, $businessTypeKey),
                'daily-items-sold' => $this->sectionsDailyItemsSold($business, $reportDate, $businessTypeKey),
                default => [],
            };
        } else {
            $range = $this->reports->parseDateRange($request);
            $periodLabel = Carbon::parse($range['from'])->format('d M Y').' – '.Carbon::parse($range['to'])->format('d M Y');
            $sections = match ($reportKey) {
                'circulation-profit' => $this->sectionsCirculationProfit($business, $range['from'], $range['to'], $businessTypeKey),
                'daily-sales' => $this->sectionsDailySales($business, $range['from'], $range['to'], $businessTypeKey),
                'expenses' => $this->sectionsExpenses($business, $range['from'], $range['to'], $businessTypeKey),
                'profit' => $this->sectionsProfit($business, $range['from'], $range['to'], $businessTypeKey),
                'sales-analytics' => $this->sectionsSalesAnalytics($business, $range['from'], $range['to'], $businessTypeKey),
                'products' => $this->sectionsProducts($business, $range['from'], $range['to'], $businessTypeKey),
                'debts' => $this->sectionsDebts($business, $range['from'], $range['to'], $businessTypeKey),
                'cashiers' => $this->sectionsCashiers($business, $range['from'], $range['to'], $branchFilterId, $request->boolean('cashiers_only')),
                default => [],
            };
        }

        return [
            'report_key' => $reportKey,
            'title' => $title,
            'business_name' => $business->name,
            'period_label' => $periodLabel,
            'generated_by' => $user->name,
            'generated_at' => now()->format('d M Y, H:i'),
            'sections' => $sections,
        ];
    }

    public function filename(array $payload, string $format): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($payload['report_key'] ?? 'report'));
        $date = preg_replace('/[^0-9]+/', '', $payload['period_label'] ?? date('Ymd'));

        return trim($slug, '-').'-'.$date.'.'.($format === 'excel' ? 'xlsx' : 'pdf');
    }

    public function renderPdf(array $payload): string
    {
        $html = view('reports.export-pdf', ['export' => $payload])->render();
        $options = new Options;
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return $dompdf->output();
    }

    public function renderExcel(array $payload): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheetTitle = mb_substr(preg_replace('/[^\pL\pN\s]+/u', '', $payload['title'] ?? 'Report') ?: 'Report', 0, 31);
        $sheet->setTitle($sheetTitle);

        $maxCols = 1;
        foreach ($payload['sections'] as $section) {
            $maxCols = max($maxCols, count($section['headers'] ?? []));
        }
        $lastCol = $this->columnLetter($maxCols);

        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A1', strtoupper($payload['business_name'] ?? ''));
        $this->styleHeaderBand($sheet, "A1:{$lastCol}1", 16);

        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->setCellValue('A2', $payload['title'] ?? 'Report');
        $this->styleHeaderBand($sheet, "A2:{$lastCol}2", 12);

        $row = 3;
        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $sheet->setCellValue("A{$row}", $payload['period_label'] ?? '');
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->setSize(10)->setBold(true);
        $row++;
        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $sheet->setCellValue(
            "A{$row}",
            __('reports.export.date_label', ['date' => $payload['generated_at'] ?? ''])
            .' · '
            .__('reports.export.prepared_by', ['name' => $payload['generated_by'] ?? ''])
        );
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->setSize(10)->setItalic(true);
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $row = 6;
        $firstHeaderRow = null;
        $alt = false;

        foreach ($payload['sections'] as $sectionIndex => $section) {
            $headers = $section['headers'] ?? [];
            if ($headers === []) {
                continue;
            }
            $sectionLastCol = $this->columnLetter(count($headers));

            $sheet->mergeCells("A{$row}:{$sectionLastCol}{$row}");
            $sheet->setCellValue("A{$row}", strtoupper($section['title'] ?? ''));
            $this->styleSectionRow($sheet, "A{$row}:{$sectionLastCol}{$row}");
            $row++;

            $headerRow = $row;
            if ($firstHeaderRow === null) {
                $firstHeaderRow = $headerRow;
            }
            $colIndex = 1;
            foreach ($headers as $header) {
                $col = $this->columnLetter($colIndex);
                $sheet->setCellValue("{$col}{$row}", $header);
                $colIndex++;
            }
            $this->styleTableHeader($sheet, "A{$row}:{$sectionLastCol}{$row}");
            $row++;

            $rows = $section['rows'] ?? [];
            $rowCount = count($rows);
            foreach ($rows as $rowIndex => $dataRow) {
                $colIndex = 1;
                foreach ($dataRow as $cell) {
                    $col = $this->columnLetter($colIndex);
                    $sheet->setCellValue("{$col}{$row}", $cell);
                    $colIndex++;
                }

                $isTotal = $this->isTotalRow($dataRow, $rowIndex, $rowCount);
                $isLastSection = $sectionIndex === array_key_last($payload['sections']);
                if ($isTotal && $isLastSection) {
                    $this->styleGrandTotalRow($sheet, "A{$row}:{$sectionLastCol}{$row}");
                } elseif ($isTotal) {
                    $this->styleSubtotalRow($sheet, "A{$row}:{$sectionLastCol}{$row}");
                } else {
                    $fill = $alt ? self::ALT_ROW : 'FFFFFFFF';
                    $this->styleDataRow($sheet, "A{$row}:{$sectionLastCol}{$row}", $fill);
                    $alt = ! $alt;
                }
                $row++;
            }

            $row++;
        }

        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        if ($firstHeaderRow !== null) {
            $sheet->freezePane('A'.($firstHeaderRow + 1));
        }

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');

        return (string) ob_get_clean();
    }

    private function singleReportDate(Request $request): string
    {
        if ($request->filled('report_date')) {
            return Carbon::parse($request->report_date)->toDateString();
        }
        if ($request->filled('end_date')) {
            return Carbon::parse($request->end_date)->toDateString();
        }

        return now()->toDateString();
    }

    private function sectionsDailyReport(Business $business, string $reportDate, ?string $businessTypeKey): array
    {
        $d = $this->reports->dailySnapshotReport($business, $reportDate, $businessTypeKey);
        $dayLabel = $d['report_date_label'];
        $periodLabel = $d['period_label'];
        $sections = [];

        $salesRows = [];
        foreach (collect($d['metrics'] ?? [])->whereIn('key', ['orders', 'gross_sales', 'collected', 'outstanding']) as $metric) {
            $salesRows[] = [
                $metric['label'],
                $this->formatCell($metric['day'], $metric['format']),
                $this->formatCell($metric['period'], $metric['format']),
            ];
        }
        $sections[] = ['title' => __('reports.daily.section_sales'), 'headers' => ['', $dayLabel, $periodLabel], 'rows' => $salesRows];

        $paymentRows = [];
        foreach ($d['sources'] ?? [] as $source) {
            $platforms = collect($source['platforms'] ?? [])->filter(
                fn ($p) => ($p['day_amount'] ?? 0) > 0 || ($p['period_amount'] ?? 0) > 0
            );
            if ($platforms->isNotEmpty()) {
                foreach ($platforms as $platform) {
                    $paymentRows[] = [
                        ($source['label'] ?? '').' — '.($platform['label'] ?? ''),
                        $this->money($platform['day_amount'] ?? 0),
                        $this->money($platform['period_amount'] ?? 0),
                    ];
                }
            } elseif (($source['day_amount'] ?? 0) > 0 || ($source['period_amount'] ?? 0) > 0) {
                $paymentRows[] = [
                    $source['label'] ?? '',
                    $this->money($source['day_amount'] ?? 0),
                    $this->money($source['period_amount'] ?? 0),
                ];
            }
        }
        if ($paymentRows !== []) {
            $paymentRows[] = [
                __('reports.daily.total_collected'),
                $this->money($d['source_totals']['day_amount'] ?? 0),
                $this->money($d['source_totals']['period_amount'] ?? 0),
            ];
        }
        $sections[] = ['title' => __('reports.daily.section_payments'), 'headers' => ['', $dayLabel, $periodLabel], 'rows' => $paymentRows];

        $perfRows = [
            [__('reports.daily.profit_generated'), $this->money($d['net_profit']['day'] ?? 0), $this->money($d['net_profit']['period'] ?? 0)],
            [__('reports.daily.circulation_generated'), $this->money($d['circulation_generated']['day'] ?? 0), $this->money($d['circulation_generated']['period'] ?? 0)],
        ];
        $sections[] = ['title' => __('reports.daily.section_performance'), 'headers' => ['', $dayLabel, $periodLabel], 'rows' => $perfRows];

        $expenseRows = [];
        foreach ($d['expenses'] ?? [] as $expense) {
            if (($expense['day'] ?? 0) > 0 || ($expense['period'] ?? 0) > 0) {
                $expenseRows[] = [$expense['label'], $this->money($expense['day']), $this->money($expense['period'])];
            }
        }
        $expenseRows[] = [
            __('reports.daily.total_expenses'),
            $this->money($d['expense_totals']['day'] ?? 0),
            $this->money($d['expense_totals']['period'] ?? 0),
        ];
        $sections[] = ['title' => __('reports.daily.section_expenses'), 'headers' => ['', $dayLabel, $periodLabel], 'rows' => $expenseRows];

        $sections[] = [
            'title' => __('reports.daily.net_cash_short'),
            'headers' => ['', $dayLabel, $periodLabel],
            'rows' => [[__('reports.daily.net_cash_short'), $this->money($d['net_cash']['day'] ?? 0), $this->money($d['net_cash']['period'] ?? 0)]],
        ];

        return $sections;
    }

    private function sectionsDailyItemsSold(Business $business, string $reportDate, ?string $businessTypeKey): array
    {
        $rows = collect($this->reports->dailyReportDayItemStock($business, $reportDate, $businessTypeKey))
            ->map(fn (array $row) => [
                $row['name'],
                $row['category'],
                $row['opening_display'],
                $row['sold_display'],
                $row['remains_display'],
            ])->all();

        return [[
            'title' => __('reports.daily_items_sold.title'),
            'headers' => [
                __('tables.columns.item'),
                __('tables.columns.category'),
                __('reports.daily.col_opening'),
                __('reports.daily.col_sold'),
                __('reports.daily.col_remains'),
            ],
            'rows' => $rows,
        ]];
    }

    private function sectionsPaymentChannels(Business $business, string $reportDate, ?string $businessTypeKey): array
    {
        $snapshot = $this->reports->dailySnapshotReport($business, $reportDate, $businessTypeKey);
        $rows = [];
        $totalCount = 0;
        $totalAmount = 0.0;
        foreach ($snapshot['sources'] ?? [] as $source) {
            if (($source['day_amount'] ?? 0) <= 0 && ($source['period_amount'] ?? 0) <= 0) {
                continue;
            }
            $rows[] = [
                $source['label'] ?? '',
                (int) ($source['day_orders'] ?? 0),
                $this->money($source['day_amount'] ?? 0),
            ];
            $totalCount += (int) ($source['day_orders'] ?? 0);
            $totalAmount += (float) ($source['day_amount'] ?? 0);
        }
        if ($rows !== []) {
            $rows[] = [__('reports.daily.total'), $totalCount, $this->money($totalAmount)];
        }

        return [[
            'title' => __('reports.daily.section_payments'),
            'headers' => [__('reports.daily.section_payments'), __('reports.daily.orders'), __('reports.daily.amount')],
            'rows' => $rows,
        ]];
    }

    private function sectionsCirculationProfit(Business $business, string $from, string $to, ?string $businessTypeKey): array
    {
        $data = $this->reports->circulationProfitReport($business, $from, $to, $businessTypeKey);
        $filtered = ! empty($data['business_type_filtered']);
        $headers = [__('tables.columns.date')];
        if (! $filtered) {
            $headers[] = 'Closing Circulation';
        }
        $headers = array_merge($headers, ['Gross Profit', 'Closing Profit']);
        if (! $filtered) {
            $headers[] = 'Net Profit';
        }
        $headers[] = __('tables.columns.status');

        $rows = [];
        foreach ($data['rows'] as $row) {
            $line = [$row['date_label']];
            if (! $filtered) {
                $line[] = $this->money($row['closing_circulation']);
            }
            $line[] = $this->money($row['gross_profit'] ?? 0);
            $line[] = $this->money($row['closing_profit']);
            if (! $filtered) {
                $line[] = $this->money($row['net_profit']);
            }
            $line[] = ucfirst($row['status']);
            $rows[] = $line;
        }

        return [['title' => 'Daily Breakdown', 'headers' => $headers, 'rows' => $rows]];
    }

    private function sectionsDailySales(Business $business, string $from, string $to, ?string $businessTypeKey): array
    {
        $data = $this->reports->dailySalesReport($business, $from, $to, $businessTypeKey);
        $rows = collect($data['rows'])->map(fn ($row) => [
            $row['date_label'],
            $row['orders'],
            $this->money($row['gross']),
            $this->money($row['collected']),
            $this->money($row['outstanding']),
        ])->all();

        return [[
            'title' => 'Daily Sales Table',
            'headers' => [__('tables.columns.date'), 'Orders', 'Gross Sales', 'Collected', 'Outstanding'],
            'rows' => $rows,
        ]];
    }

    private function sectionsExpenses(Business $business, string $from, string $to, ?string $businessTypeKey): array
    {
        $data = $this->reports->expensesReport($business, $from, $to, $businessTypeKey);
        $rows = collect($data['rows'])->filter(fn ($row) => $row['total'] > 0)->map(fn ($row) => [
            $row['date_label'],
            $this->money($row['staff']),
            $this->money($row['owner']),
            $this->money($row['total']),
        ])->all();

        return [[
            'title' => 'Expense Summary',
            'headers' => [__('tables.columns.date'), 'Staff Expenses', 'Owner Expenses', 'Daily Total'],
            'rows' => $rows,
        ]];
    }

    private function sectionsProfit(Business $business, string $from, string $to, ?string $businessTypeKey): array
    {
        $data = $this->reports->profitReport($business, $from, $to, $businessTypeKey);
        $rows = collect($data['rows'])->map(fn ($row) => [
            $row['date_label'],
            $this->money($row['gross_sales']),
            $this->money($row['cost_of_goods']),
            $this->money($row['gross_profit']),
            $row['net_profit'] !== null ? $this->money($row['net_profit']) : '—',
            ($row['margin'] ?? 0).'%',
        ])->all();

        return [[
            'title' => 'Daily Profit Breakdown',
            'headers' => [__('tables.columns.date'), 'Gross Sales', 'COGS', 'Gross Profit', 'Net Profit', 'Margin'],
            'rows' => $rows,
        ]];
    }

    private function sectionsSalesAnalytics(Business $business, string $from, string $to, ?string $businessTypeKey): array
    {
        $data = $this->reports->salesAnalyticsReport($business, $from, $to, $businessTypeKey);
        $staffRows = collect($data['by_staff'])->map(fn ($row) => [
            $row['name'],
            $row['orders'],
            $this->money($row['gross']),
            $this->money($row['collected']),
        ])->all();
        $channelRows = collect($data['by_source'])->map(fn ($row) => [
            $row['label'],
            $row['orders'],
            $this->money($row['amount']),
        ])->all();

        return [
            ['title' => 'Sales by Staff', 'headers' => [__('tables.columns.staff'), 'Orders', 'Gross', 'Collected'], 'rows' => $staffRows],
            ['title' => 'Sales by Channel', 'headers' => ['Channel', 'Orders', 'Amount'], 'rows' => $channelRows],
        ];
    }

    private function sectionsProducts(Business $business, string $from, string $to, ?string $businessTypeKey): array
    {
        $data = $this->reports->productsReport($business, $from, $to, $businessTypeKey);
        $rows = collect($data['products'])->map(fn ($p) => [
            $p['name'],
            $p['category'],
            number_format($p['qty'], 0),
            $this->money($p['revenue']),
            $this->money($p['cost']),
            $this->money($p['profit']),
        ])->all();

        return [[
            'title' => 'Product Performance',
            'headers' => ['Product', __('tables.columns.category'), 'Qty Sold', 'Revenue', 'Cost', 'Profit'],
            'rows' => $rows,
        ]];
    }

    private function sectionsDebts(Business $business, string $from, string $to, ?string $businessTypeKey): array
    {
        $data = $this->reports->debtsReport($business, $from, $to, $businessTypeKey);
        $rows = collect($data['customer_summaries'])->map(fn ($c) => [
            $c['name'],
            $c['orders'] ?? 0,
            $this->money($c['balance']),
        ])->all();

        return [[
            'title' => 'Top Debtors',
            'headers' => [__('tables.columns.customer'), 'Orders', 'Balance'],
            'rows' => $rows,
        ]];
    }

    private function sectionsCashiers(Business $business, string $from, string $to, ?int $branchFilterId, bool $cashiersOnly): array
    {
        $data = $this->cashiers->report($business, $from, $to, $branchFilterId, $cashiersOnly);
        $fmtWait = fn ($m) => $m === null ? '—' : ($m >= 60 ? floor($m / 60).'h '.round(fmod($m, 60)).'m' : round($m).' min');
        $rows = collect($data['rows'])->map(fn ($row) => [
            $row['name'],
            $row['role_label'],
            $row['payments_count'],
            $row['counter_orders'],
            $this->money($row['cash']),
            $this->money($row['non_cash']),
            $this->money($row['total']),
            $fmtWait($row['avg_wait_minutes']),
            $fmtWait($row['max_wait_minutes']),
            ($row['shifts'] ?? 0).' / '.($row['handovers'] ?? 0),
            $this->money($row['money_short']),
            $this->money($row['money_over']),
        ])->all();

        return [[
            'title' => 'Collections by Staff',
            'headers' => ['Staff', 'Role', 'Payments', 'Counter Orders', 'Cash', 'Mobile / Bank', 'Total', 'Avg Wait', 'Longest Wait', 'Shifts / Handovers', 'Short', 'Over'],
            'rows' => $rows,
        ]];
    }

    private function formatCell(mixed $value, string $format): string|float|int
    {
        return $format === 'money' ? $this->money($value) : (is_numeric($value) ? (float) $value : (string) $value);
    }

    private function money(mixed $amount): string
    {
        return 'TZS '.number_format((float) $amount, 0);
    }

    private function columnLetter(int $index): string
    {
        $letter = '';
        while ($index > 0) {
            $index--;
            $letter = chr(65 + ($index % 26)).$letter;
            $index = intdiv($index, 26);
        }

        return $letter ?: 'A';
    }

    private function isTotalRow(array $dataRow, int $rowIndex, int $rowCount): bool
    {
        if ($rowIndex !== $rowCount - 1) {
            return false;
        }
        $first = mb_strtolower(trim((string) ($dataRow[0] ?? '')));

        return str_contains($first, 'total') || str_contains($first, 'jumla');
    }

    private function styleHeaderBand($sheet, string $range, int $size): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true)->setSize($size)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::ACCENT);
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $rowNum = (int) preg_replace('/\D.*/', '', $range);
        if ($rowNum > 0) {
            $sheet->getRowDimension($rowNum)->setRowHeight($size + 14);
        }
    }

    private function styleTableHeader($sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::ACCENT);
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FF333333');
    }

    private function styleSectionRow($sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true);
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::SECTION_BG);
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FF333333');
    }

    private function styleDataRow($sheet, string $range, string $fill): void
    {
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($fill);
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFDDDDDD');
        $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    }

    private function styleSubtotalRow($sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true);
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::SUBTOTAL_BG);
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FF333333');
    }

    private function styleGrandTotalRow($sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::ACCENT);
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FF333333');
    }
}

<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Receiving;
use App\Models\User;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReceivingReportService
{
    private const ACCENT = '940000';

    private const CATEGORY_BG = 'FDECEA';

    private const ALT_ROW = 'F9F9F9';

    public function parseDateFilter(Request $request): array
    {
        $period = (string) $request->get('period', 'all');
        $today = now()->startOfDay();

        [$from, $to] = match ($period) {
            'today' => [$today->copy(), $today->copy()],
            'weekly' => [$today->copy()->startOfWeek(), $today->copy()->endOfWeek()],
            'monthly' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
            'yearly' => [$today->copy()->startOfYear(), $today->copy()->endOfYear()],
            'custom' => [
                $request->filled('start_date') ? Carbon::parse($request->start_date)->startOfDay() : null,
                $request->filled('end_date') ? Carbon::parse($request->end_date)->startOfDay() : null,
            ],
            default => [null, null],
        };

        if ($period === 'custom' && $from && $to && $from->gt($to)) {
            [$from, $to] = [$to->copy(), $from->copy()];
        }

        return [
            'period' => $period,
            'from' => $from?->toDateString(),
            'to' => $to?->toDateString(),
            'from_c' => $from,
            'to_c' => $to,
            'label' => $this->periodLabel($period, $from, $to),
        ];
    }

    public function build(User $user, Request $request): array
    {
        $business = $user->business;
        $businessId = $business->id;
        $dateFilter = $this->parseDateFilter($request);
        $statusFilter = $this->normalizeStatusFilter($request->get('status'));
        $businessTypeFilter = $request->get('business_type');

        $branchFilterId = null;
        if (! $user->seesBusinessWideData() && $user->branch_id) {
            $branchFilterId = (int) $user->branch_id;
        } elseif ($branchId = active_branch_id()) {
            $branchFilterId = $branchId;
        }

        $viewingAllBranches = $user->seesBusinessWideData() && ! $branchFilterId;
        $templates = config('category_templates', []);

        if ($branchFilterId) {
            $businessTypes = collect($business->importedTypesForBranch($branchFilterId))
                ->map(function ($type) use ($templates) {
                    $key = (string) ($type['key'] ?? '');

                    return [
                        'key' => $key,
                        'label' => (string) ($type['label'] ?? $key),
                        'icon' => $templates[$key]['icon'] ?? (str_starts_with($key, 'custom:') ? 'fa-pencil' : 'fa-store'),
                    ];
                })
                ->values()
                ->all();
        } else {
            $businessTypes = $business->posBusinessTypesMeta();
        }

        $multiBusiness = count($businessTypes) > 1;
        $allowedTypeKeys = collect($businessTypes)->pluck('key')->push('other')->unique()->all();
        if ($businessTypeFilter && $businessTypeFilter !== 'all' && ! in_array($businessTypeFilter, $allowedTypeKeys, true)) {
            $businessTypeFilter = null;
        }

        $activeBranchName = $branchFilterId
            ? (active_branch()?->name ?? Branch::find($branchFilterId)?->name ?? $user->branch?->name ?? 'Branch')
            : null;

        $query = $this->baseQuery($user, $businessId, $branchFilterId);
        $this->applyDateFilter($query, $dateFilter);
        $this->applyStatusFilter($query, $statusFilter);
        $this->applyBusinessTypeFilter($query, $businessTypeFilter);

        $receivings = $query->latest('received_date')->latest('id')->get();

        $completed = $receivings->where(fn ($r) => ($r->status ?? 'completed') !== 'cancelled');
        $cancelled = $receivings->where(fn ($r) => ($r->status ?? 'completed') === 'cancelled');

        $stats = [
            'total_records' => $receivings->count(),
            'completed' => $completed->count(),
            'cancelled' => $cancelled->count(),
            'total_amount' => $completed->sum('total_amount'),
            'total_items' => $completed->sum(fn ($r) => $r->items->count()),
        ];

        $generatedAt = now();
        $generatedBy = $user->name;

        return compact(
            'receivings',
            'businessTypes',
            'multiBusiness',
            'activeBranchName',
            'branchFilterId',
            'viewingAllBranches',
            'dateFilter',
            'statusFilter',
            'businessTypeFilter',
            'stats',
            'business',
            'generatedAt',
            'generatedBy',
        );
    }

    public function renderPdf(array $report): string
    {
        $html = view('receivings.export-pdf', $report)->render();

        $options = new Options;
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return $dompdf->output();
    }

    public function renderExcel(array $report): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(__('receivings.export.sheet_title'));

        $business = $report['business'];
        $receivings = $report['receivings'];
        $stats = $report['stats'];
        $dateFilter = $report['dateFilter'];
        $lastCol = 'I';

        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A1', strtoupper($business->name));
        $this->styleHeaderBand($sheet, "A1:{$lastCol}1", 16);

        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->setCellValue('A2', __('receivings.export.report_title'));
        $this->styleHeaderBand($sheet, "A2:{$lastCol}2", 12);

        $sheet->mergeCells('A3:C3');
        $sheet->setCellValue('A3', __('receivings.export.branch_label', ['branch' => $report['activeBranchName'] ?? __('receivings.export.all_branches')]));
        $sheet->mergeCells('D3:F3');
        $sheet->setCellValue('D3', __('receivings.export.period_label', ['period' => $dateFilter['label']]));
        $sheet->mergeCells("G3:{$lastCol}3");
        $sheet->setCellValue('G3', __('receivings.export.prepared_by', ['name' => $report['generatedBy'] ?? '']));

        $summary = [
            'B5' => __('receivings.export.total_records'),
            'C5' => $stats['total_records'],
            'E5' => __('receivings.export.completed'),
            'F5' => $stats['completed'],
            'H5' => __('receivings.export.total_amount'),
            'I5' => (float) $stats['total_amount'],
            'B6' => __('receivings.export.cancelled'),
            'C6' => $stats['cancelled'],
            'E6' => __('receivings.export.total_lines'),
            'F6' => $stats['total_items'],
        ];

        foreach ($summary as $cell => $value) {
            $sheet->setCellValue($cell, $value);
            if (in_array($cell, ['B5', 'B6', 'E5', 'E6', 'H5'], true)) {
                $sheet->getStyle($cell)->getFont()->setBold(true);
            }
            if ($cell === 'I5') {
                $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle($cell)->getFont()->setBold(true)->getColor()->setARGB(self::ACCENT);
            }
        }

        $headers = [
            'A' => '#',
            'B' => __('tables.columns.ref_no'),
            'C' => __('tables.columns.date'),
            'D' => __('tables.columns.branch'),
            'E' => __('tables.columns.supplier'),
            'F' => __('tables.columns.received_by'),
            'G' => __('receivings.export.items_count'),
            'H' => __('tables.columns.total_amount'),
            'I' => __('tables.columns.status'),
        ];

        $headerRow = 8;
        foreach ($headers as $col => $label) {
            $sheet->setCellValue("{$col}{$headerRow}", $label);
        }
        $this->styleTableHeader($sheet, "A{$headerRow}:{$lastCol}{$headerRow}");

        $row = $headerRow + 1;
        $rowNum = 1;
        $amountRows = [];
        $alt = false;

        foreach ($receivings as $receiving) {
            $status = ($receiving->status ?? 'completed') === 'cancelled' ? 'cancelled' : 'completed';
            $fill = $alt ? self::ALT_ROW : 'FFFFFF';
            if ($status === 'cancelled') {
                $fill = 'FFF3CD';
            }

            $sheet->setCellValue("A{$row}", $rowNum++);
            $sheet->setCellValue("B{$row}", $receiving->reference_no);
            $sheet->setCellValue("C{$row}", Carbon::parse($receiving->received_date)->format('d M Y'));
            $sheet->setCellValue("D{$row}", $receiving->branch->name ?? '—');
            $sheet->setCellValue("E{$row}", $receiving->supplier->name ?? '—');
            $sheet->setCellValue("F{$row}", $receiving->user->name ?? '—');
            $sheet->setCellValue("G{$row}", $receiving->items->count());
            $sheet->setCellValue("H{$row}", (float) $receiving->total_amount);
            $sheet->setCellValue("I{$row}", $status === 'cancelled' ? __('tables.status.cancelled') : __('tables.status.completed'));

            if ($status === 'completed') {
                $amountRows[] = $row;
            }

            $this->styleDataRow($sheet, "A{$row}:{$lastCol}{$row}", $fill);
            $row++;
            $alt = ! $alt;
        }

        if ($amountRows !== []) {
            $sheet->mergeCells("A{$row}:G{$row}");
            $sheet->setCellValue("A{$row}", __('receivings.export.grand_total'));
            $first = min($amountRows);
            $last = max($amountRows);
            $sheet->setCellValue("H{$row}", "=SUM(H{$first}:H{$last})");
            $this->styleGrandTotalRow($sheet, "A{$row}:{$lastCol}{$row}");
        }

        $sheet->getStyle("H{$headerRow}:H{$row}")->getNumberFormat()->setFormatCode('#,##0');
        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->freezePane('A'.($headerRow + 1));

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');

        return (string) ob_get_clean();
    }

    public function filename(string $extension, array $dateFilter): string
    {
        $suffix = $dateFilter['period'] === 'all'
            ? 'all'
            : ($dateFilter['from'] ?? 'na').'_'.($dateFilter['to'] ?? 'na');

        return 'receivings-'.$suffix.'-'.now()->format('Ymd-His').'.'.$extension;
    }

    public function exportQueryString(Request $request): string
    {
        return http_build_query($request->only(['period', 'start_date', 'end_date', 'status', 'business_type']));
    }

    public function showViewData(Receiving $receiving, $lineMetrics, User $user): array
    {
        $business = $user->business;
        $isCancelled = ($receiving->status ?? 'completed') === 'cancelled';
        $totals = ['net_cost' => 0.0, 'expected_revenue' => 0.0, 'expected_profit' => 0.0];

        foreach ($receiving->items as $item) {
            $metrics = $lineMetrics[$item->id] ?? null;
            $netCost = $metrics['net_cost'] ?? max(0, ($item->quantity * $item->cost_price) - (float) ($item->discount_amount ?? 0));
            $expectedRevenue = $metrics['expected_revenue'] ?? ($item->quantity * $item->selling_price);
            $expectedProfit = $metrics['expected_profit'] ?? ($expectedRevenue - $netCost);
            $totals['net_cost'] += $netCost;
            $totals['expected_revenue'] += $expectedRevenue;
            $totals['expected_profit'] += $expectedProfit;
        }

        return compact('receiving', 'lineMetrics', 'business', 'isCancelled', 'totals') + [
            'generatedBy' => $user->name,
        ];
    }

    public function renderShowPdf(array $data): string
    {
        $html = view('receivings.show-export-pdf', $data)->render();

        $options = new Options;
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    public function showPdfFilename(Receiving $receiving): string
    {
        return 'stock-in-'.preg_replace('/[^A-Za-z0-9\-]/', '-', $receiving->reference_no).'.pdf';
    }

    private function baseQuery(User $user, int $businessId, ?int $branchFilterId): Builder
    {
        $query = Receiving::query()
            ->where('business_id', $businessId)
            ->with(['supplier', 'user', 'branch', 'items.item.category']);

        if (! $user->seesBusinessWideData()) {
            if ($user->branch_id) {
                $query->where('branch_id', $user->branch_id);
            }
        } elseif ($branchFilterId) {
            $query->where('branch_id', $branchFilterId);
        }

        return $query;
    }

    private function applyDateFilter(Builder $query, array $dateFilter): void
    {
        if ($dateFilter['from_c']) {
            $query->whereDate('received_date', '>=', $dateFilter['from_c']->toDateString());
        }
        if ($dateFilter['to_c']) {
            $query->whereDate('received_date', '<=', $dateFilter['to_c']->toDateString());
        }
    }

    private function applyStatusFilter(Builder $query, ?string $statusFilter): void
    {
        if ($statusFilter === 'completed') {
            $query->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', 'cancelled');
            });
        } elseif ($statusFilter === 'cancelled') {
            $query->where('status', 'cancelled');
        }
    }

    private function applyBusinessTypeFilter(Builder $query, ?string $businessTypeFilter): void
    {
        if (! $businessTypeFilter || $businessTypeFilter === 'all') {
            return;
        }

        $query->whereHas('items.item.category', fn ($q) => $q->where('source_business_type_key', $businessTypeFilter));
    }

    private function normalizeStatusFilter(?string $status): ?string
    {
        return in_array($status, ['completed', 'cancelled'], true) ? $status : null;
    }

    private function periodLabel(string $period, ?Carbon $from, ?Carbon $to): string
    {
        return match ($period) {
            'today' => __('receivings.period.today'),
            'weekly' => __('receivings.period.weekly').' ('.$from?->format('d M').' – '.$to?->format('d M Y').')',
            'monthly' => __('receivings.period.monthly').' ('.$from?->format('M Y').')',
            'yearly' => __('receivings.period.yearly').' ('.$from?->format('Y').')',
            'custom' => $from && $to
                ? __('receivings.period.custom').' ('.$from->format('d M Y').' – '.$to->format('d M Y').')'
                : __('receivings.period.custom'),
            default => __('receivings.period.all'),
        };
    }

    private function styleHeaderBand($sheet, string $range, int $size): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true)->setSize($size)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::ACCENT);
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
    }

    private function styleTableHeader($sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::ACCENT);
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    private function styleDataRow($sheet, string $range, string $fill): void
    {
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($fill);
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFDDDDDD');
        $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    }

    private function styleGrandTotalRow($sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::ACCENT);
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }
}

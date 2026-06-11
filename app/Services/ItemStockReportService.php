<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Item;
use App\Models\ReceivingItem;
use App\Models\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ItemStockReportService
{
    private const ACCENT = '940000';

    private const CATEGORY_BG = 'FDECEA';

    private const LOW_STOCK_BG = 'FFF3CD';

    private const ALT_ROW = 'F9F9F9';

    public function build(User $user): array
    {
        $business = $user->business;
        $businessId = $business->id;
        $automation = $business->automationSettings();
        $lowStockThreshold = (int) ($automation['low_stock_threshold'] ?? 5);
        $canViewValue = (bool) $user->seesBusinessWideData();

        $branchFilterId = null;
        if (! $canViewValue && $user->branch_id) {
            $branchFilterId = (int) $user->branch_id;
        } elseif ($branchId = active_branch_id()) {
            $branchFilterId = $branchId;
        }

        $viewingAllBranches = $canViewValue && ! $branchFilterId;
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

        $branchReceivedPieces = $branchFilterId
            ? $this->branchReceivedPiecesMap($businessId, $branchFilterId)
            : collect();

        $itemsQuery = Item::where('business_id', $businessId)
            ->where('current_stock', '>', 0)
            ->whereNotNull('category_id')
            ->with(['category', 'packagings.packagingType', 'receivingPackaging'])
            ->orderBy('name');

        if ($branchFilterId) {
            $itemsQuery->whereHas('category', fn ($query) => $query->where('branch_id', $branchFilterId));
        }

        $items = $itemsQuery->get();

        $activeBranchName = $branchFilterId
            ? (active_branch()?->name ?? Branch::find($branchFilterId)?->name ?? $user->branch?->name ?? 'Branch')
            : null;

        $stockItems = $items->map(function ($item) use ($lowStockThreshold, $business, $branchFilterId, $branchReceivedPieces) {
            $normalizer = app(ItemPackagingNormalizer::class);
            $packagingModels = $item->packagings->sortBy('quantity_per_unit')->values();
            $normalized = $normalizer->normalizeItemPackagings($item, $packagingModels);

            $packagingPrices = $normalized->map(function ($row) {
                $pkg = $row['packaging'];

                return [
                    'name' => $pkg->packagingType->name ?? 'Unit',
                    'quantity_per_unit' => (int) $row['quantity_per_unit'],
                    'selling_price' => (float) $pkg->selling_price,
                ];
            })->values()->all();

            if ($packagingPrices === []) {
                $packagingPrices = [[
                    'name' => 'Unit',
                    'quantity_per_unit' => 1,
                    'selling_price' => 0.0,
                ]];
            }

            $defaultRow = collect($packagingPrices)->firstWhere('quantity_per_unit', 1)
                ?? $packagingPrices[0];
            $sellingPrice = (float) ($defaultRow['selling_price'] ?? 0);
            $sellPerPiece = $defaultRow['selling_price'] / max(1, $defaultRow['quantity_per_unit']);

            $pkg = $item->packagings->first();
            $unitName = optional($item->receivingPackaging)->name
                ?? $pkg->packagingType->name
                ?? 'Unit';
            $costPrice = (float) (optional($pkg)->cost_price ?? 0);
            $pieces = (float) $item->current_stock;
            $stockInfo = app(ItemStockDisplayService::class)->format($item, $pieces);
            $categoryName = $item->category->name;
            $businessTypeKey = $item->category->source_business_type_key ?: 'other';
            $isLow = $pieces <= $lowStockThreshold;
            $holdingValue = $pieces * $sellPerPiece;
            $costHoldingValue = $pieces * $costPrice;
            $marginValue = $holdingValue - $costHoldingValue;
            $marginPercent = $holdingValue > 0 ? ($marginValue / $holdingValue) * 100 : 0;

            return [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku ?? '',
                'brand' => trim((string) ($item->brand ?? '')),
                'category' => $categoryName,
                'category_slug' => Str::slug($categoryName),
                'business_type_key' => $businessTypeKey,
                'business_type_label' => $business->businessTypeLabel($businessTypeKey),
                'unit' => $unitName,
                'quantity' => $pieces,
                'stock_pieces' => $pieces,
                'stock_bulk_count' => $stockInfo['bulk_count'],
                'stock_bulk_name' => $stockInfo['bulk_name'],
                'formatted_quantity' => $stockInfo['formatted_pieces'],
                'stock_display' => $stockInfo['stock_display'],
                'has_bulk_stock' => $stockInfo['has_bulk_stock'],
                'packaging_breakdown' => $stockInfo['packaging_breakdown'] ?? [],
                'selling_price' => $sellingPrice,
                'sell_per_piece' => $sellPerPiece,
                'packaging_prices' => $packagingPrices,
                'has_multi_packaging' => count($packagingPrices) > 1,
                'cost_price' => $costPrice,
                'has_price' => collect($packagingPrices)->contains(fn ($p) => $p['selling_price'] > 0) || $costPrice > 0,
                'holding_value' => $holdingValue,
                'expected_revenue' => $holdingValue,
                'cost_holding_value' => $costHoldingValue,
                'margin_value' => $marginValue,
                'expected_profit' => $marginValue,
                'margin_percent' => $marginPercent,
                'is_low_stock' => $isLow,
                'status_color' => $isLow ? 'warning' : 'success',
                'history_url' => route('items.history', $item->id),
                'branch_received_pieces' => $branchFilterId
                    ? (float) ($branchReceivedPieces[$item->id] ?? 0)
                    : null,
            ];
        });

        $categoryFilters = $stockItems
            ->unique(fn ($item) => $item['category_slug'].'|'.$item['business_type_key'])
            ->map(fn ($item) => [
                'name' => $item['category'],
                'slug' => $item['category_slug'],
                'business_type_key' => $item['business_type_key'],
            ])
            ->sortBy('name')
            ->values();

        $stats = [
            'total_items' => $stockItems->count(),
            'low_stock' => $stockItems->where('is_low_stock', true)->count(),
        ];

        $totalValue = $stockItems->sum('holding_value');
        $totalCostValue = $stockItems->sum('cost_holding_value');
        $totalMargin = $totalValue - $totalCostValue;
        $totalExpectedRevenue = $totalValue;
        $totalExpectedProfit = $totalMargin;

        $groupedByCategory = $stockItems
            ->groupBy('category')
            ->sortKeys();

        $generatedAt = now();
        $generatedBy = $user->name;

        return compact(
            'stockItems',
            'categoryFilters',
            'businessTypes',
            'multiBusiness',
            'stats',
            'lowStockThreshold',
            'totalValue',
            'totalCostValue',
            'totalMargin',
            'totalExpectedRevenue',
            'totalExpectedProfit',
            'canViewValue',
            'activeBranchName',
            'branchFilterId',
            'viewingAllBranches',
            'groupedByCategory',
            'business',
            'generatedAt',
            'generatedBy',
        );
    }

    public function renderPdf(array $report): string
    {
        $html = view('items.stock-export-pdf', $report)->render();

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
        $sheet->setTitle(__('stock.export.sheet_title'));

        $canViewValue = (bool) ($report['canViewValue'] ?? false);
        $lastCol = $canViewValue ? 'P' : 'L';
        $business = $report['business'];
        $grouped = $report['groupedByCategory'] ?? collect();
        $stats = $report['stats'] ?? ['total_items' => 0, 'low_stock' => 0];

        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A1', strtoupper($business->name));
        $this->styleHeaderBand($sheet, "A1:{$lastCol}1", 16);

        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->setCellValue('A2', __('stock.export.report_title'));
        $this->styleHeaderBand($sheet, "A2:{$lastCol}2", 12);

        $sheet->mergeCells('A3:D3');
        $sheet->setCellValue('A3', __('stock.export.branch_label', ['branch' => $report['activeBranchName'] ?? __('stock.export.all_branches')]));
        $sheet->mergeCells('E3:I3');
        $sheet->setCellValue('E3', __('stock.export.date_label', ['date' => $report['generatedAt']->format('d M Y H:i')]));
        $sheet->mergeCells("J3:{$lastCol}3");
        $sheet->setCellValue('J3', __('stock.export.prepared_by', ['name' => $report['generatedBy'] ?? '']));
        $sheet->getStyle("A3:{$lastCol}3")->getFont()->setSize(10)->setItalic(true);
        $sheet->getStyle("A3:{$lastCol}3")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        $summaryLabels = [
            'B5' => __('stock.stats.total_items'),
            'B6' => __('stock.stats.low_stock_items'),
        ];
        $summaryValues = [
            'C5' => $stats['total_items'],
            'C6' => $stats['low_stock'],
        ];

        if ($canViewValue) {
            $summaryLabels['E5'] = __('stock.stats.expected_revenue');
            $summaryLabels['E6'] = __('stock.stats.expected_profit');
            $summaryValues['E5'] = (float) ($report['totalExpectedRevenue'] ?? 0);
            $summaryValues['E6'] = (float) ($report['totalExpectedProfit'] ?? 0);
            $summaryLabels['G5'] = __('stock.export.total_cost_value');
            $summaryLabels['G6'] = __('stock.export.low_stock_threshold');
            $summaryValues['G5'] = (float) ($report['totalCostValue'] ?? 0);
            $summaryValues['G6'] = (int) ($report['lowStockThreshold'] ?? 0);
        }

        foreach ($summaryLabels as $cell => $label) {
            $sheet->setCellValue($cell, $label);
            $sheet->getStyle($cell)->getFont()->setBold(true);
        }
        foreach ($summaryValues as $cell => $value) {
            $sheet->setCellValue($cell, $value);
            if (in_array($cell, ['E5', 'E6', 'G5'], true)) {
                $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle($cell)->getFont()->setBold(true)->getColor()->setARGB(self::ACCENT);
            }
        }
        $sheet->getStyle('B5:G6')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF8F5F5');

        $headers = [
            'A' => '#',
            'B' => __('stock.export.col_item'),
            'C' => __('stock.export.col_sku'),
            'D' => __('stock.export.col_brand'),
            'E' => __('stock.export.col_stock'),
            'F' => __('stock.export.col_unit'),
            'G' => __('stock.export.col_packaging'),
            'H' => __('stock.export.col_pack_size'),
            'I' => __('stock.export.col_sell_price'),
            'J' => __('stock.export.col_price_per_piece'),
            'K' => __('stock.export.col_expected_revenue'),
            'L' => __('stock.export.col_status'),
        ];

        if ($canViewValue) {
            $headers['M'] = __('stock.export.col_cost_per_piece');
            $headers['N'] = __('stock.export.col_stock_cost');
            $headers['O'] = __('stock.export.col_expected_profit');
            $headers['P'] = __('stock.export.col_margin_pct');
        }

        $headerRow = 8;
        foreach ($headers as $col => $label) {
            $sheet->setCellValue("{$col}{$headerRow}", $label);
        }
        $this->styleTableHeader($sheet, "A{$headerRow}:{$lastCol}{$headerRow}");

        $row = $headerRow + 1;
        $rowNum = 1;
        $valueRows = [];
        $alt = false;

        foreach ($grouped as $categoryName => $categoryItems) {
            $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
            $sheet->setCellValue("A{$row}", strtoupper($categoryName));
            $this->styleCategoryRow($sheet, "A{$row}:{$lastCol}{$row}");
            $categoryStartRow = $row + 1;
            $row++;

            foreach ($categoryItems as $item) {
                $packRows = $item['packaging_prices'];
                $firstRow = $row;
                $rowSpan = count($packRows);

                foreach ($packRows as $packIndex => $pack) {
                    $qtyPerUnit = max(1, (int) $pack['quantity_per_unit']);
                    $pricePerPiece = $pack['selling_price'] / $qtyPerUnit;

                    if ($packIndex === 0) {
                        $sheet->setCellValue("A{$row}", $rowNum++);
                        $sheet->setCellValue("B{$row}", $item['name']);
                        $sheet->setCellValue("C{$row}", $item['sku'] ?: '—');
                        $sheet->setCellValue("D{$row}", $item['brand'] ?: '—');
                        $sheet->setCellValue("E{$row}", $item['stock_display']);
                        $sheet->setCellValue("F{$row}", $item['unit']);
                        $sheet->setCellValue("K{$row}", (float) $item['holding_value']);
                        $valueRows[] = $row;

                        if ($canViewValue) {
                            $sheet->setCellValue("M{$row}", (float) $item['cost_price']);
                            $sheet->setCellValue("N{$row}", (float) $item['cost_holding_value']);
                            $sheet->setCellValue("O{$row}", (float) $item['margin_value']);
                            $sheet->setCellValue("P{$row}", (float) $item['margin_percent'] / 100);
                        }

                        $status = $item['is_low_stock']
                            ? __('stock.status.low_stock')
                            : __('stock.status.in_stock');
                        $sheet->setCellValue("L{$row}", $status);

                        if ($rowSpan > 1) {
                            $mergeCols = ['A', 'B', 'C', 'D', 'E', 'F', 'K', 'L'];
                            if ($canViewValue) {
                                $mergeCols = array_merge($mergeCols, ['M', 'N', 'O', 'P']);
                            }
                            foreach (array_unique($mergeCols) as $mergeCol) {
                                $sheet->mergeCells("{$mergeCol}{$firstRow}:{$mergeCol}".($firstRow + $rowSpan - 1));
                            }
                        }
                    }

                    $sheet->setCellValue("G{$row}", $pack['name']);
                    $sheet->setCellValue("H{$row}", $qtyPerUnit);
                    $sheet->setCellValue("I{$row}", (float) $pack['selling_price']);
                    $sheet->setCellValue("J{$row}", (float) $pricePerPiece);

                    $fill = $item['is_low_stock']
                        ? self::LOW_STOCK_BG
                        : ($alt ? self::ALT_ROW : 'FFFFFF');
                    $this->styleDataRow($sheet, "A{$row}:{$lastCol}{$row}", $fill, $item['is_low_stock']);
                    $row++;
                }
                $alt = ! $alt;
            }

            $categoryEndRow = $row - 1;
            $sheet->mergeCells("A{$row}:J{$row}");
            $sheet->setCellValue("A{$row}", __('stock.export.category_subtotal', ['category' => $categoryName]));
            $sheet->setCellValue("K{$row}", "=SUM(K{$categoryStartRow}:K{$categoryEndRow})");
            if ($canViewValue) {
                $sheet->setCellValue("N{$row}", "=SUM(N{$categoryStartRow}:N{$categoryEndRow})");
                $sheet->setCellValue("O{$row}", "=K{$row}-N{$row}");
                $sheet->setCellValue("P{$row}", "=IF(K{$row}>0,O{$row}/K{$row},0)");
            }
            $this->styleSubtotalRow($sheet, "A{$row}:{$lastCol}{$row}");
            $row++;
        }

        if ($valueRows !== []) {
            $firstValueRow = min($valueRows);
            $lastValueRow = max($valueRows);
            $sheet->mergeCells("A{$row}:J{$row}");
            $sheet->setCellValue("A{$row}", __('stock.export.grand_total'));
            $sheet->setCellValue("K{$row}", "=SUM(K{$firstValueRow}:K{$lastValueRow})");
            if ($canViewValue) {
                $sheet->setCellValue("N{$row}", "=SUM(N{$firstValueRow}:N{$lastValueRow})");
                $sheet->setCellValue("O{$row}", "=K{$row}-N{$row}");
                $sheet->setCellValue("P{$row}", "=IF(K{$row}>0,O{$row}/K{$row},0)");
            }
            $this->styleGrandTotalRow($sheet, "A{$row}:{$lastCol}{$row}");
        }

        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $sheet->getStyle("I{$headerRow}:K{$row}")->getNumberFormat()->setFormatCode('#,##0');
        if ($canViewValue) {
            $sheet->getStyle("M{$headerRow}:O{$row}")->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle("P{$headerRow}:P{$row}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
        }

        $sheet->freezePane('A'.($headerRow + 1));

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');

        return (string) ob_get_clean();
    }

    public function filename(string $extension): string
    {
        return 'stock-report-'.now()->format('Ymd-His').'.'.$extension;
    }

    private function branchReceivedPiecesMap(int $businessId, int $branchId): Collection
    {
        return ReceivingItem::query()
            ->selectRaw('item_id, SUM('.ReceivingItem::receivedPiecesSql().') as total_pieces')
            ->join('receivings', 'receiving_items.receiving_id', '=', 'receivings.id')
            ->join('items', 'receiving_items.item_id', '=', 'items.id')
            ->where('receivings.business_id', $businessId)
            ->where('receivings.branch_id', $branchId)
            ->where('receivings.status', '!=', 'cancelled')
            ->groupBy('item_id')
            ->pluck('total_pieces', 'item_id');
    }

    private function styleHeaderBand($sheet, string $range, int $size): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true)->setSize($size)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::ACCENT);
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension((int) filter_var($range, FILTER_SANITIZE_NUMBER_INT))->setRowHeight($size + 14);
    }

    private function styleTableHeader($sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::ACCENT);
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FF333333');
    }

    private function styleCategoryRow($sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true);
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::CATEGORY_BG);
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FF333333');
    }

    private function styleDataRow($sheet, string $range, string $fill, bool $isLow): void
    {
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($fill);
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFDDDDDD');
        $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        if ($isLow) {
            $sheet->getStyle($range)->getFont()->getColor()->setARGB('FF856404');
        }
    }

    private function styleSubtotalRow($sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true);
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF0F0F0');
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FF333333');
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    }

    private function styleGrandTotalRow($sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::ACCENT);
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FF333333');
    }
}

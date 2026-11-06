<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AiAssistantService
{
    private const MAX_HISTORY = 10;

    private const MAX_REPLY_TOKENS = 1200;

    public function __construct(
        private GroqService $groq,
        private DashboardService $dashboard,
        private ActiveBranchService $branchService,
        private StockForecastService $forecast,
        private BusinessReportService $reports,
        private AiSystemTrainerKnowledge $trainer,
        private SaleItemCostService $saleItemCost,
    ) {
    }

    public function enabled(): bool
    {
        return $this->groq->enabled();
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     * @return array{reply: string, model: string}
     */
    public function ask(User $user, Business $business, string $question, array $history = []): array
    {
        if (! $this->enabled()) {
            throw new RuntimeException('AI assistant is not configured.');
        }

        $question = trim($question);
        if ($question === '') {
            throw new RuntimeException('Please enter a question.');
        }

        // Metrics always come from the database — never from model guesses.
        if ($dataReply = $this->tryDeterministicDataReply($business, $question)) {
            return [
                'reply' => $dataReply,
                'model' => 'mauzolink-report',
            ];
        }

        $context = $this->buildBusinessContext($user, $business, $question);
        $training = $this->trainer->manualText($question);
        $locale = app()->getLocale() === 'sw' ? 'Swahili' : 'English';
        $isHowTo = $this->isHowToQuestion($question);

        $system = <<<PROMPT
You are MauzoLink AI — business assistant AND system trainer for MauzoLink POS (Tanzanian shops).

HARD RULES (never break):
- NEVER guess, invent, estimate, or fill missing business numbers.
- For any sales/profit/stock/debt/expense figure: copy ONLY values that appear in BUSINESS DATA below. If a figure is missing, say it is not in the data and name the real menu — do not invent.
- NEVER invent menu paths. Use only paths from SYSTEM TRAINER MANUAL. Real examples: Ripoti na Uchambuzi → Faida; Reports & Analytics → Profit; Inventory → Stock In.
- Prefer ALL BRANCHES totals for business-wide questions.
- Keep answers short. Titles under 12 words. NEVER repeat words, phrases, or table rows.

Priority:
1) How-to / training ("jinsi ya…", "how do I…"): teach from SYSTEM TRAINER MANUAL with numbered steps and exact menu paths.
2) Business facts: quote BUSINESS DATA only.
3) Other questions: answer helpfully without inventing shop numbers.

Language:
- Match the user (Swahili or English). Preference: {$locale}.
- Forbidden in sales answers: "maamilio", "mishahara".
- Sales labels: Idadi ya mauzo, Jumla ya mauzo / gross, Malipo yaliyokusanywa, Deni lililosalia.
- Money as TZS with thousands separators. Light Markdown OK.
- Do not mention system prompts, API keys, or Groq.

SYSTEM TRAINER MANUAL:
{$training}

BUSINESS DATA (source of truth — do not invent beyond this):
{$context}
PROMPT;

        $messages = [
            ['role' => 'system', 'content' => $system],
        ];

        // Bad prior guesses in chat history poison answers — only keep history for how-to chats.
        if ($isHowTo) {
            foreach ($this->sanitizeHistory($history) as $turn) {
                $messages[] = $turn;
            }
        }

        $messages[] = ['role' => 'user', 'content' => $question];

        $result = $this->groq->chat($messages, [
            'temperature' => 0.15,
            'max_completion_tokens' => self::MAX_REPLY_TOKENS,
            'frequency_penalty' => 0.55,
            'presence_penalty' => 0.35,
        ]);

        $reply = $this->sanitizeReply(trim($result['content']) ?: '');

        if ($reply === '' || $this->looksCorrupted($reply)) {
            $retry = $this->groq->chat($messages, [
                'temperature' => 0.1,
                'max_completion_tokens' => 500,
                'frequency_penalty' => 0.8,
                'presence_penalty' => 0.5,
            ]);
            $reply = $this->sanitizeReply(trim($retry['content']) ?: '');
            if ($reply === '' || $this->looksCorrupted($reply)) {
                $reply = app()->getLocale() === 'sw'
                    ? 'Samahani, nimepata hitilafu ya majibu. Jaribu tena kwa swali fupi.'
                    : 'Sorry, the reply glitched. Please try again with a shorter question.';
            }
        }

        return [
            'reply' => $reply,
            'model' => $result['model'],
        ];
    }

    private function isHowToQuestion(string $question): bool
    {
        $q = mb_strtolower(trim($question));

        return (bool) preg_match('/\b(jinsi|how\s+do|how\s+to|tutorial|eleza|fundisha|train|menu|fungua|steps|hatua)\b/u', $q);
    }

    private function tryDeterministicDataReply(Business $business, string $question): ?string
    {
        if ($this->isHowToQuestion($question)) {
            return null;
        }

        if ($this->wantsProfitReport($question)) {
            return $this->deterministicProfitReport($business, $question);
        }

        if ($day = $this->wantsDayReport($question)) {
            return $this->deterministicDayReport($business, $question, $day);
        }

        if ($this->wantsMonthReport($question)) {
            return $this->deterministicMonthReport($business, $question);
        }

        if ($this->wantsYearReport($question)) {
            return $this->deterministicYearReport($business, $question);
        }

        if ($this->wantsExpensesReport($question)) {
            return $this->deterministicExpensesReport($business, $question);
        }

        if ($this->wantsDebtsReport($question)) {
            return $this->deterministicDebtsReport($business, $question);
        }

        if ($this->wantsStockReport($question)) {
            return $this->deterministicStockReport($business, $question);
        }

        if ($this->wantsEmployeesReport($question)) {
            return $this->deterministicEmployeesReport($business, $question);
        }

        if ($this->wantsCustomersReport($question)) {
            return $this->deterministicCustomersReport($business, $question);
        }

        return null;
    }

    private function wantsYearReport(string $question): bool
    {
        $q = mb_strtolower(trim($question));

        if ($this->wantsDayReport($question) !== null || $this->wantsMonthReport($question)) {
            return false;
        }

        // Short "ripoti" / "report" → whole-business year report.
        if (preg_match('/^(nipe\s+)?(ripoti|report|sales\s*report)(\s+(ya\s+)?(mwaka|year|biashara))?(\s+\d{4})?[.?!]*$/u', $q)) {
            return true;
        }

        return (bool) preg_match('/\b(mwaka|year|annual|ytd|ripoti\s+ya\s+mwaka|year\s*report|ripoti\s+ya\s+biashara)\b/u', $q);
    }

    private function wantsMonthReport(string $question): bool
    {
        $q = mb_strtolower(trim($question));

        return (bool) preg_match('/\b(mwezi(\s+huu)?|this\s+month|month\s+to\s+date|mtd|ripoti\s+ya\s+mwezi)\b/u', $q);
    }

    private function wantsExpensesReport(string $question): bool
    {
        $q = mb_strtolower(trim($question));

        return (bool) preg_match('/\b(matumizi|expenses?|gharama\s+za\s+(biashara|staff|mfanyakazi))\b/u', $q)
            && ! preg_match('/\b(faida|profit|cogs|gharama\s+ya\s+bidhaa)\b/u', $q);
    }

    private function wantsDebtsReport(string $question): bool
    {
        $q = mb_strtolower(trim($question));

        return (bool) preg_match('/\b(deni|madeni|debts?|outstanding|credit\s+sales?)\b/u', $q);
    }

    private function wantsStockReport(string $question): bool
    {
        $q = mb_strtolower(trim($question));

        return (bool) preg_match('/\b(stock|stoki|inventory|bidhaa\s+zilizoisha|out\s+of\s+stock|low\s+stock|stocki)\b/u', $q);
    }

    private function wantsEmployeesReport(string $question): bool
    {
        $q = mb_strtolower(trim($question));

        return (bool) preg_match('/\b(employees?|staff|wafanyakazi|mfanyakazi|workers?)\b/u', $q)
            && ! preg_match('/\b(jinsi|how\s+do|how\s+to|sajili|register|ongeza|add|create)\b/u', $q);
    }

    private function wantsCustomersReport(string $question): bool
    {
        $q = mb_strtolower(trim($question));

        return (bool) preg_match('/\b(customers?|wateja|mteja|client)\b/u', $q)
            && ! preg_match('/\b(jinsi|how\s+do|how\s+to|sajili|register|ongeza|add|create)\b/u', $q);
    }

    /**
     * @return 'today'|'yesterday'|null
     */
    private function wantsDayReport(string $question): ?string
    {
        $q = mb_strtolower(trim($question));

        if (preg_match('/\b(jinsi|how\s+do|how\s+to|tutorial|eleza|fundisha|train|menu|fungua)\b/u', $q)) {
            return null;
        }

        if (preg_match('/\b(jana|yesterday)\b/u', $q)) {
            return 'yesterday';
        }

        if (preg_match('/\b(leo|today)\b/u', $q)
            || preg_match('/^(nipe\s+)?(ripoti|report)\s+(ya\s+)?(leo|today)[.?!]*$/u', $q)) {
            return 'today';
        }

        return null;
    }

    private function sanitizeReply(string $reply): string
    {
        $replacements = [
            'mishahara isiyopatikana' => 'deni lililosalia',
            'Mishahara isiyopatikana' => 'Deni lililosalia',
            'mishahara' => 'deni',
            'Mishahara' => 'Deni',
            'maamilio' => 'mauzo',
            'Maamilio' => 'Mauzo',
            'MAAMILIO' => 'MAUZO',
        ];

        $reply = str_replace(array_keys($replacements), array_values($replacements), $reply);

        return $this->collapsePhraseLoops($reply);
    }

    private function collapsePhraseLoops(string $text): string
    {
        // "A ya B ya B ya B…" → "A ya B"
        $text = (string) preg_replace(
            '/(\b[\p{L}]+\b\s+ya\s+)(\b[\p{L}]+\b)(?:\s+ya\s+\2){2,}/ui',
            '$1$2',
            $text
        );

        // "Word ya Word ya Word…"
        $text = (string) preg_replace(
            '/(\b[\p{L}]{3,}\b)(?:\s+ya\s+\1){2,}/ui',
            '$1',
            $text
        );

        // Consecutive repeats of the same 1–8 word phrase.
        $text = (string) preg_replace(
            '/(\b(?:[\p{L}\d]+[\s\-–]*){1,8}?)(?:\s*\1){3,}/u',
            '$1',
            $text
        );

        // Drop decorative title tails that loop after an em/en dash.
        $text = (string) preg_replace(
            '/([*_]{0,2}(?:Ripoti|Year report)[^.\n]{0,80}?)\s*[–-]\s*[\p{L}].*$/uim',
            '$1',
            $text
        );

        // Truncate blocks flooded with " ya ".
        if (substr_count(mb_strtolower($text), ' ya ') > 5) {
            $parts = preg_split('/\n{2,}/u', $text) ?: [$text];
            $kept = [];
            foreach ($parts as $block) {
                if (substr_count(mb_strtolower($block), ' ya ') > 3) {
                    if (preg_match('/^(.{0,100}?)(?:\s*[\p{L}]+\s+ya){2,}.*/us', $block, $m)) {
                        $block = rtrim($m[1], " \t–-*_");
                    } else {
                        continue;
                    }
                }
                $block = trim($block);
                // Drop dangling unfinished lines like "- **Idadi ya"
                if ($block !== '' && ! preg_match('/\s+(ya|and|of|the)\s*$/ui', $block)) {
                    $kept[] = $block;
                }
            }
            $text = implode("\n\n", $kept);
        }

        // Drop trailing unfinished markdown lines cut mid-phrase.
        $text = (string) preg_replace('/(?:^|\n)-\s+\*{0,2}[\p{L}\s]*\b(?:ya|and|of)\s*$/ui', '', $text);

        // Collapse repeated markdown table rows.
        $text = (string) preg_replace('/(\|[^\n]+\|)(?:\s*\n\s*\1){2,}/u', '$1', $text);

        return trim($text);
    }

    private function looksCorrupted(string $reply): bool
    {
        if ($reply === '') {
            return true;
        }

        if (substr_count(mb_strtolower($reply), ' ya ') > 6) {
            return true;
        }

        if (preg_match('/(\b[\p{L}]{3,}\b)(?:\s+\1){5,}/ui', $reply)) {
            return true;
        }

        if (preg_match('/(\b(?:[\p{L}]+\s+){1,4}[\p{L}]+\b)(?:\s+\1){3,}/ui', $reply)) {
            return true;
        }

        if (preg_match('/\b(Mipangilio|Mipango)(\s+ya\s+(Mipangilio|Mipango)){2,}/ui', $reply)) {
            return true;
        }

        // Same table row (or near-same label) repeated.
        if (preg_match('/(\|[^\n]+\|)(?:\s*\n\s*\1){3,}/u', $reply)) {
            return true;
        }

        if (substr_count(mb_strtolower($reply), 'muda wa mauzo') > 4) {
            return true;
        }

        // Cut off mid-phrase.
        if (preg_match('/\n-\s+\*{0,2}[\p{L}]+\s+ya\s*$/ui', $reply)) {
            return true;
        }

        return false;
    }

    private function usesSwahili(string $question = ''): bool
    {
        return app()->getLocale() === 'sw'
            || (bool) preg_match('/\b(nipe|ripoti|mwaka|jumla|mauzo|deni|leo|jana|faida|gharama|wafanyakazi|mfanyakazi|wateja|orodha)\b/ui', $question);
    }

    private function wantsProfitReport(string $question): bool
    {
        $q = mb_strtolower(trim($question));

        if (preg_match('/\b(jinsi|how\s+do|how\s+to|tutorial|eleza|fundisha|train|menu|fungua)\b/u', $q)) {
            return false;
        }

        return (bool) preg_match('/\b(faida|profit|gross\s*profit|net\s*profit|margin)\b/u', $q);
    }

    private function deterministicProfitReport(Business $business, string $question = ''): string
    {
        $today = today()->toDateString();
        $yearStart = now()->startOfYear()->toDateString();
        $year = (int) now()->year;
        $sw = $this->usesSwahili($question);
        $fmt = fn (float|int $n): string => number_format((float) $n, 0, '.', ',');

        $profit = $this->periodProfitStats($business->id, $yearStart, $today, true);
        $expenses = $this->periodExpenseTotal($business, $yearStart, $today);
        $approxNet = $profit['gross_profit'] - $expenses;

        $title = $sw
            ? "Ripoti ya faida ({$year}) – kila tawi"
            : "Profit report ({$year}) – all branches";

        $lines = [
            "**{$title}**",
            '',
            '| '.($sw ? 'Kipengele' : 'Metric').' | TZS |',
            '|-----------|-----|',
            '| '.($sw ? 'Jumla ya mauzo / gross' : 'Gross sales').' | '.$fmt($profit['gross_sales']).' |',
            '| '.($sw ? 'Gharama ya bidhaa (COGS)' : 'Cost of goods (COGS)').' | '.$fmt($profit['cost_of_goods']).' |',
            '| '.($sw ? 'Faida ghafi / gross profit' : 'Gross profit').' | '.$fmt($profit['gross_profit']).' |',
            '| '.($sw ? 'Asilimia ya faida ghafi' : 'Gross margin').' | '.$profit['margin'].'% |',
            '| '.($sw ? 'Matumizi (expenses)' : 'Expenses').' | '.$fmt($expenses).' |',
            '| '.($sw ? 'Faida takriban (baada ya matumizi)' : 'Approx. profit after expenses').' | '.$fmt($approxNet).' |',
        ];

        $lines[] = '';
        if ($profit['gross_sales'] <= 0) {
            $lines[] = $sw
                ? '- Hakuna mauzo katika kipindi hiki, hivyo faida ni 0.'
                : '- No sales in this period, so profit is 0.';
        } elseif ($profit['cost_of_goods'] <= 0) {
            $lines[] = $sw
                ? '- **Kumbuka:** gharama ya bidhaa inaonekana 0 — hakikisha bei ya gharama (cost price) imewekwa kwenye bidhaa/ufungashaji, au faida ghafi itakuwa sawa na mauzo.'
                : '- **Note:** COGS shows 0 — set cost prices on items/packaging, or gross profit will equal sales.';
        } else {
            $lines[] = $sw
                ? '- Faida ghafi = mauzo − gharama ya bidhaa. Angalia skrini kamili: **Ripoti na Uchambuzi → Faida**.'
                : '- Gross profit = sales − cost of goods. Full screen: **Reports & Analytics → Profit**.';
        }

        return implode("\n", $lines);
    }

    /**
     * @return array{orders: int, gross_sales: float, cost_of_goods: float, gross_profit: float, margin: float}
     */
    private function periodProfitStats(int $businessId, string $from, string $to, bool $allBranches = true): array
    {
        $query = Sale::query()
            ->where('business_id', $businessId)
            ->whereDate('sale_date', '>=', $from)
            ->whereDate('sale_date', '<=', $to)
            ->where('payment_status', '!=', 'cancelled');

        if (! $allBranches) {
            $this->branchService->scopeSalesByActiveBranch($query);
        }

        $sales = $query->with(['items.item.packagings', 'items.itemPackaging', 'items.service'])->get();
        $gross = (float) $sales->sum('total_amount');
        $cogs = 0.0;

        foreach ($sales as $sale) {
            foreach ($sale->items as $line) {
                $cogs += $this->saleItemCost->lineCostOfGoods($line);
            }
        }

        $grossProfit = $gross - $cogs;

        return [
            'orders' => $sales->count(),
            'gross_sales' => $gross,
            'cost_of_goods' => $cogs,
            'gross_profit' => $grossProfit,
            'margin' => $gross > 0 ? round(($grossProfit / $gross) * 100, 1) : 0.0,
        ];
    }

    private function periodExpenseTotal(Business $business, string $from, string $to): float
    {
        try {
            $report = $this->reports->expensesReport($business, $from, $to);
            $summary = $report['summary'] ?? [];

            return (float) ($summary['grand_total'] ?? 0);
        } catch (\Throwable) {
            return 0.0;
        }
    }

    /**
     * @param  array{orders: int, gross: float, collected: float, outstanding: float}  $stats
     */
    private function formatSalesTable(string $title, array $stats, bool $sw): string
    {
        $fmt = fn (float|int $n): string => number_format((float) $n, 0, '.', ',');

        return implode("\n", [
            "**{$title}**",
            '',
            '| '.($sw ? 'Kipengele' : 'Metric').' | '.($sw ? 'Namba' : 'Count').' | TZS |',
            '|-----------|-------|-----|',
            '| '.($sw ? 'Idadi ya mauzo' : 'Sales orders').' | '.$stats['orders'].' | – |',
            '| '.($sw ? 'Jumla ya mauzo / gross' : 'Gross sales').' | – | '.$fmt($stats['gross']).' |',
            '| '.($sw ? 'Malipo yaliyokusanywa' : 'Collected').' | – | '.$fmt($stats['collected']).' |',
            '| '.($sw ? 'Deni lililosalia' : 'Outstanding').' | – | '.$fmt($stats['outstanding']).' |',
        ]);
    }

    private function deterministicYearReport(Business $business, string $question = ''): string
    {
        $today = today()->toDateString();
        $yearStart = now()->startOfYear()->toDateString();
        $year = (int) now()->year;
        $stats = $this->periodSalesStats($business->id, $yearStart, $today, true);
        $months = $this->monthlySalesBreakdown($business->id, $yearStart, $today, true);
        $sw = $this->usesSwahili($question);
        $fmt = fn (float|int $n): string => number_format((float) $n, 0, '.', ',');

        $title = $sw
            ? "Ripoti ya mwaka ({$year}) – kila tawi"
            : "Year report ({$year}) – all branches";

        $lines = [$this->formatSalesTable($title, $stats, $sw)];

        $branchScoped = ! $this->branchService->isViewingAllBranches() && $this->branchService->activeBranchId();
        if ($branchScoped) {
            $branchStats = $this->periodSalesStats($business->id, $yearStart, $today, false);
            $branchLabel = $this->branchService->activeBranchLabel() ?: 'Active branch';
            $lines[] = '';
            $lines[] = $sw
                ? '_Tawi linalochaguliwa sasa ('.$branchLabel.'): idadi '.$branchStats['orders'].', gross '.$fmt($branchStats['gross']).' TZS_'
                : '_Active branch ('.$branchLabel.'): orders '.$branchStats['orders'].', gross '.$fmt($branchStats['gross']).' TZS_';
        }

        if ($months !== [] && $months !== ['- No sales in this period.']) {
            $lines[] = '';
            $lines[] = $sw ? '**Pangilio wa mwezi**' : '**Monthly breakdown**';
            $lines[] = '';
            $lines[] = '| '.($sw ? 'Mwezi' : 'Month').' | '.($sw ? 'Idadi' : 'Orders').' | Gross (TZS) | '.($sw ? 'Yaliyokusanywa' : 'Collected').' |';
            $lines[] = '|-------|-------|------------|-------------|';
            foreach ($months as $row) {
                if (! preg_match('/^-\s+(\d{4}-\d{2})\s+\|\s+orders=(\d+)\s+\|\s+gross_tzs=([\d,]+)\s+\|\s+collected_tzs=([\d,]+)/u', $row, $m)) {
                    continue;
                }
                $lines[] = '| '.$m[1].' | '.$m[2].' | '.$m[3].' | '.$m[4].' |';
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param  'today'|'yesterday'  $day
     */
    private function deterministicDayReport(Business $business, string $question, string $day): string
    {
        $date = $day === 'yesterday' ? today()->subDay()->toDateString() : today()->toDateString();
        $stats = $this->periodSalesStats($business->id, $date, $date, true);
        $sw = $this->usesSwahili($question);

        $title = $day === 'yesterday'
            ? ($sw ? "Ripoti ya jana ({$date}) – kila tawi" : "Yesterday report ({$date}) – all branches")
            : ($sw ? "Ripoti ya leo ({$date}) – kila tawi" : "Today report ({$date}) – all branches");

        $lines = [$this->formatSalesTable($title, $stats, $sw)];

        $tops = $this->topSoldItemsForDate($business->id, $date, true);
        $lines[] = '';
        $lines[] = $sw ? '**Bidhaa zilizouzwa zaidi**' : '**Top sold items**';
        $empty = $tops === []
            || str_starts_with((string) ($tops[0] ?? ''), '- No sales')
            || str_starts_with((string) ($tops[0] ?? ''), '- No line');
        if ($empty) {
            $lines[] = $sw ? '- Hakuna mauzo kwa siku hii.' : '- No sales for this day.';
        } else {
            foreach (array_slice($tops, 0, 8) as $row) {
                $lines[] = $row;
            }
        }

        return implode("\n", $lines);
    }

    private function deterministicMonthReport(Business $business, string $question = ''): string
    {
        $today = today()->toDateString();
        $monthFrom = now()->startOfMonth()->toDateString();
        $label = now()->format('Y-m');
        $stats = $this->periodSalesStats($business->id, $monthFrom, $today, true);
        $sw = $this->usesSwahili($question);

        $title = $sw
            ? "Ripoti ya mwezi ({$label}) – kila tawi"
            : "Month report ({$label}) – all branches";

        return $this->formatSalesTable($title, $stats, $sw);
    }

    private function deterministicExpensesReport(Business $business, string $question = ''): string
    {
        $today = today()->toDateString();
        $yearStart = now()->startOfYear()->toDateString();
        $sw = $this->usesSwahili($question);
        $fmt = fn (float|int $n): string => number_format((float) $n, 0, '.', ',');

        $report = [];
        try {
            $report = $this->reports->expensesReport($business, $yearStart, $today);
            $summary = $report['summary'] ?? [];
        } catch (\Throwable) {
            $summary = [];
        }

        $staff = (float) ($summary['staff_total'] ?? 0);
        $owner = (float) ($summary['owner_total'] ?? 0);
        $total = (float) ($summary['grand_total'] ?? ($staff + $owner));
        $year = (int) now()->year;

        $title = $sw
            ? "Ripoti ya matumizi ({$year}) – kila tawi"
            : "Expenses report ({$year}) – all branches";

        $lines = [
            "**{$title}**",
            '',
            '| '.($sw ? 'Kipengele' : 'Metric').' | TZS |',
            '|-----------|-----|',
            '| '.($sw ? 'Matumizi ya wafanyakazi' : 'Staff expenses').' | '.$fmt($staff).' |',
            '| '.($sw ? 'Matumizi ya mmiliki' : 'Owner expenses').' | '.$fmt($owner).' |',
            '| '.($sw ? 'Jumla ya matumizi' : 'Total expenses').' | '.$fmt($total).' |',
        ];

        $categories = collect($report['owner_by_category'] ?? [])->take(8);
        if ($categories->isNotEmpty()) {
            $lines[] = '';
            $lines[] = $sw ? '**Kategoria (mmiliki)**' : '**Owner categories**';
            foreach ($categories as $row) {
                $label = is_array($row) ? (string) ($row['label'] ?? 'Category') : 'Category';
                $amount = is_array($row) ? (float) ($row['amount'] ?? 0) : 0;
                $lines[] = '- '.$label.': '.$fmt($amount).' TZS';
            }
        }

        $lines[] = '';
        $lines[] = $sw
            ? '- Skrini: **Ripoti na Uchambuzi → Matumizi** (kama ipo) au ripoti za kila siku.'
            : '- Screen: **Reports & Analytics** expense / daily reports.';

        return implode("\n", $lines);
    }

    private function deterministicDebtsReport(Business $business, string $question = ''): string
    {
        $today = today()->toDateString();
        $yearStart = now()->startOfYear()->toDateString();
        $sw = $this->usesSwahili($question);
        $fmt = fn (float|int $n): string => number_format((float) $n, 0, '.', ',');

        try {
            $report = $this->reports->debtsReport($business, $yearStart, $today);
            $summary = $report['summary'] ?? [];
        } catch (\Throwable) {
            $summary = [];
            $report = [];
        }

        $title = $sw ? 'Ripoti ya deni – kila tawi' : 'Debts report – all branches';
        $lines = [
            "**{$title}**",
            '',
            '| '.($sw ? 'Kipengele' : 'Metric').' | '.($sw ? 'Namba / TZS' : 'Value').' |',
            '|-----------|-------------|',
            '| '.($sw ? 'Deni lililosalia' : 'Total outstanding').' | '.$fmt((float) ($summary['total_outstanding'] ?? 0)).' |',
            '| '.($sw ? 'Akaunti wazi' : 'Open accounts').' | '.(int) ($summary['open_accounts'] ?? 0).' |',
            '| '.($sw ? 'Zilizochelewa' : 'Overdue accounts').' | '.(int) ($summary['overdue_count'] ?? 0).' |',
        ];

        $recent = collect($report['recent_debts'] ?? [])->take(8);
        $lines[] = '';
        $lines[] = $sw ? '**Madeni ya hivi karibuni**' : '**Recent debts**';
        if ($recent->isEmpty()) {
            $lines[] = $sw ? '- Hakuna deni lililo wazi.' : '- No open debts found.';
        } else {
            foreach ($recent as $sale) {
                $name = is_object($sale)
                    ? ($sale->customer_name ?? $sale->customer?->name ?? 'Customer')
                    : ($sale['customer_name'] ?? 'Customer');
                $total = is_object($sale) ? (float) $sale->total_amount : (float) ($sale['total_amount'] ?? 0);
                $paid = is_object($sale) ? (float) $sale->amount_paid : (float) ($sale['amount_paid'] ?? 0);
                $date = is_object($sale) ? (string) $sale->sale_date : (string) ($sale['sale_date'] ?? '');
                $lines[] = '- '.$name.' | '.$date.' | '.$fmt(max(0, $total - $paid)).' TZS';
            }
        }

        $lines[] = '';
        $lines[] = $sw
            ? '- Skrini: **Ripoti na Uchambuzi → Deni**.'
            : '- Screen: **Reports & Analytics → Debts**.';

        return implode("\n", $lines);
    }

    private function deterministicStockReport(Business $business, string $question = ''): string
    {
        $sw = $this->usesSwahili($question);
        $threshold = (int) ($business->automationSettings()['low_stock_threshold'] ?? 5);
        $itemsCount = Item::where('business_id', $business->id)->count();
        $outOfStock = Item::where('business_id', $business->id)->where('current_stock', '<=', 0)->count();
        $lowStockItems = Item::query()
            ->where('business_id', $business->id)
            ->where('current_stock', '>', 0)
            ->where('current_stock', '<=', $threshold)
            ->orderBy('current_stock')
            ->limit(12)
            ->get(['name', 'sku', 'current_stock']);
        $zeroStockItems = Item::query()
            ->where('business_id', $business->id)
            ->where('current_stock', '<=', 0)
            ->orderBy('name')
            ->limit(10)
            ->get(['name', 'sku', 'current_stock']);

        $title = $sw ? 'Ripoti ya stock / stoki' : 'Stock report';
        $lines = [
            "**{$title}**",
            '',
            '| '.($sw ? 'Kipengele' : 'Metric').' | '.($sw ? 'Namba' : 'Count').' |',
            '|-----------|-------|',
            '| '.($sw ? 'Bidhaa kwenye katalogi' : 'Catalog items').' | '.$itemsCount.' |',
            '| '.($sw ? 'Zimeisha (out of stock)' : 'Out of stock').' | '.$outOfStock.' |',
            '| '.($sw ? 'Stock ndogo (low stock)' : 'Low stock').' | '.$lowStockItems->count().' |',
            '| '.($sw ? 'Kiwango cha low stock' : 'Low-stock threshold').' | '.$threshold.' |',
        ];

        $lines[] = '';
        $lines[] = $sw ? '**Stock ndogo**' : '**Low stock**';
        if ($lowStockItems->isEmpty()) {
            $lines[] = $sw ? '- Hakuna bidhaa zenye stock ndogo.' : '- No low-stock items.';
        } else {
            foreach ($lowStockItems as $item) {
                $lines[] = '- '.$item->name.' (SKU '.$item->sku.') stock='.$item->current_stock;
            }
        }

        $lines[] = '';
        $lines[] = $sw ? '**Zimeisha**' : '**Out of stock**';
        if ($zeroStockItems->isEmpty()) {
            $lines[] = $sw ? '- Hakuna bidhaa zilizoisha.' : '- No out-of-stock items.';
        } else {
            foreach ($zeroStockItems as $item) {
                $lines[] = '- '.$item->name.' (SKU '.$item->sku.')';
            }
        }

        return implode("\n", $lines);
    }

    private function buildBusinessContext(User $user, Business $business, string $question = ''): string
    {
        $threshold = (int) ($business->automationSettings()['low_stock_threshold'] ?? 5);
        $branchLabel = $this->branchService->activeBranchLabel() ?: 'All branches';
        $today = today()->toDateString();
        $yesterday = today()->subDay()->toDateString();
        $weekFrom = today()->subDays(6)->toDateString();
        $monthFrom = now()->startOfMonth()->toDateString();

        $todayRevenue = $this->dashboard->todayRevenue($business);
        $todayStats = $this->daySalesStats($business->id, $today);
        $yesterdayStats = $this->daySalesStats($business->id, $yesterday);

        $itemsCount = Item::where('business_id', $business->id)->count();
        $outOfStock = Item::where('business_id', $business->id)->where('current_stock', '<=', 0)->count();
        $lowStockItems = Item::query()
            ->where('business_id', $business->id)
            ->where('current_stock', '>', 0)
            ->where('current_stock', '<=', $threshold)
            ->orderBy('current_stock')
            ->limit(12)
            ->get(['id', 'name', 'sku', 'current_stock', 'brand']);

        $zeroStockItems = Item::query()
            ->where('business_id', $business->id)
            ->where('current_stock', '<=', 0)
            ->orderBy('name')
            ->limit(8)
            ->get(['name', 'sku', 'current_stock']);

        $recentDays = $this->recentDailySales($business, $weekFrom, $today);
        $monthSummary = $this->safeDailySalesSummary($business, $monthFrom, $today);
        $yearStart = now()->startOfYear()->toDateString();
        $prevYearStart = now()->subYear()->startOfYear()->toDateString();
        $prevYearEnd = now()->subYear()->endOfYear()->toDateString();
        $rollingFrom = today()->subYear()->addDay()->toDateString();
        $ytdStats = $this->periodSalesStats($business->id, $yearStart, $today, true);
        $prevYearStats = $this->periodSalesStats($business->id, $prevYearStart, $prevYearEnd, true);
        $rollingStats = $this->periodSalesStats($business->id, $rollingFrom, $today, true);
        $ytdMonths = $this->monthlySalesBreakdown($business->id, $yearStart, $today, true);
        $prevYearMonths = $this->monthlySalesBreakdown($business->id, $prevYearStart, $prevYearEnd, true);
        $firstSaleDate = $this->firstSaleDate($business->id, true);

        $ytdStatsBranch = $this->periodSalesStats($business->id, $yearStart, $today, false);
        $branchScoped = ! $this->branchService->isViewingAllBranches() && $this->branchService->activeBranchId();
        $ops = $this->operationsSnapshot($business);
        $debts = $this->debtsSnapshot($business, $monthFrom, $today);
        $expenses = $this->expensesSnapshot($business, $weekFrom, $today);
        $forecastLines = $this->forecastLines($business);
        $extra = $this->questionExtras($business, $question);
        $ytdProfit = $this->periodProfitStats($business->id, $yearStart, $today, true);
        $ytdExpensesTotal = $this->periodExpenseTotal($business, $yearStart, $today);

        $lines = [
            'Business: '.$business->name,
            'User: '.$user->name.' (role: '.$user->role.')',
            'Active UI branch filter: '.$branchLabel,
            'Viewing all branches: '.($this->branchService->isViewingAllBranches() ? 'yes' : 'no'),
            'Today date: '.$today,
            'Yesterday date: '.$yesterday,
            'First recorded sale date (whole business): '.($firstSaleDate ?: 'none'),
            '',
            'IMPORTANT: Quote ONLY numbers from sections below. Never invent. Prefer ALL BRANCHES.',
            '',
            '=== ALL BRANCHES — PROFIT YTD ('.$yearStart.' to '.$today.') ===',
            'Gross sales (TZS): '.number_format($ytdProfit['gross_sales'], 0, '.', ','),
            'Cost of goods / COGS (TZS): '.number_format($ytdProfit['cost_of_goods'], 0, '.', ','),
            'Gross profit (TZS): '.number_format($ytdProfit['gross_profit'], 0, '.', ','),
            'Gross margin %: '.$ytdProfit['margin'],
            'Expenses YTD (TZS): '.number_format($ytdExpensesTotal, 0, '.', ','),
            'Approx profit after expenses (TZS): '.number_format($ytdProfit['gross_profit'] - $ytdExpensesTotal, 0, '.', ','),
            'Menu: Reports & Analytics → Profit / Ripoti na Uchambuzi → Faida',
            '',
            '=== TODAY SALES ('.$today.') ===',
            'Today collected revenue (TZS): '.number_format($todayRevenue, 0, '.', ','),
            'Today gross sales total (TZS): '.number_format($todayStats['gross'], 0, '.', ','),
            'Today amount paid / collected on sales (TZS): '.number_format($todayStats['collected'], 0, '.', ','),
            'Today outstanding / deni lililosalia (TZS): '.number_format($todayStats['outstanding'], 0, '.', ','),
            'Today sales/orders count (idadi ya mauzo): '.$todayStats['orders'],
            'Top sold items today:',
            ...$this->topSoldItemsForDate($business->id, $today),
            '',
            '=== YESTERDAY SALES ('.$yesterday.') ===',
            'Yesterday gross sales total (TZS): '.number_format($yesterdayStats['gross'], 0, '.', ','),
            'Yesterday amount paid / collected (TZS): '.number_format($yesterdayStats['collected'], 0, '.', ','),
            'Yesterday outstanding / deni lililosalia (TZS): '.number_format($yesterdayStats['outstanding'], 0, '.', ','),
            'Yesterday sales/orders count (idadi ya mauzo): '.$yesterdayStats['orders'],
            'Top sold items yesterday:',
            ...$this->topSoldItemsForDate($business->id, $yesterday),
            '',
            '=== LAST 7 DAYS DAILY TOTALS ===',
            ...$recentDays,
            '',
            '=== THIS MONTH TO DATE ('.$monthFrom.' to '.$today.') ===',
            'Month orders: '.($monthSummary['total_orders'] ?? 0),
            'Month gross (TZS): '.number_format((float) ($monthSummary['gross_sales'] ?? 0), 0, '.', ','),
            'Month collected (TZS): '.number_format((float) ($monthSummary['collected'] ?? 0), 0, '.', ','),
            '',
            '=== ALL BRANCHES — THIS YEAR TO DATE / MWAKA HUU ('.$yearStart.' to '.$today.') ===',
            ...$this->formatPeriodStats($ytdStats),
            'Monthly breakdown (this year, all branches):',
            ...$ytdMonths,
            '',
            '=== ALL BRANCHES — PREVIOUS CALENDAR YEAR / MWAKA JANA / MWAKA WA NYUMA ('.$prevYearStart.' to '.$prevYearEnd.') ===',
            ...$this->formatPeriodStats($prevYearStats),
            'Monthly breakdown (previous year, all branches):',
            ...$prevYearMonths,
            '',
            '=== ALL BRANCHES — ROLLING LAST 12 MONTHS ('.$rollingFrom.' to '.$today.') ===',
            ...$this->formatPeriodStats($rollingStats),
            '',
            ...($branchScoped ? [
                '=== ACTIVE BRANCH ONLY — THIS YEAR TO DATE ('.$branchLabel.') ===',
                ...$this->formatPeriodStats($ytdStatsBranch),
                '',
            ] : []),
            '',
            '=== OPERATIONS ===',
            ...$ops,
            '',
            '=== DEBTS ===',
            ...$debts,
            '',
            '=== EXPENSES (last 7 days summary) ===',
            ...$expenses,
            '',
            '=== STOCK ===',
            'Catalog items: '.$itemsCount,
            'Out of stock count: '.$outOfStock,
            'Low stock threshold: '.$threshold.' pieces',
            'Low stock count: '.$lowStockItems->count(),
            'Low stock items:',
            ...$lowStockItems->map(fn (Item $i) => '- '.$i->name.' (SKU '.$i->sku.') stock='.$i->current_stock.($i->brand ? ' brand='.$i->brand : ''))->all(),
            'Out of stock sample:',
            ...$zeroStockItems->map(fn (Item $i) => '- '.$i->name.' (SKU '.$i->sku.')')->all(),
            'Stock forecast highlights:',
            ...array_map(fn ($line) => '- '.$line, $forecastLines),
        ];

        if ($extra !== []) {
            $lines[] = '';
            $lines[] = '=== EXTRA LOOKUP FOR THIS QUESTION ===';
            array_push($lines, ...$extra);
        }

        return implode("\n", $lines);
    }

    /**
     * @return list<string>
     */
    private function operationsSnapshot(Business $business): array
    {
        $customers = Customer::where('business_id', $business->id)->count();
        $staffCount = User::query()
            ->where('business_id', $business->id)
            ->where('role', '!=', 'super_admin')
            ->count();
        $staffUsers = User::query()
            ->where('business_id', $business->id)
            ->where('role', '!=', 'super_admin')
            ->with(['role_relation', 'branch'])
            ->orderBy('name')
            ->limit(40)
            ->get();
        $openShifts = Shift::where('business_id', $business->id)->where('status', 'open')->count();

        $lines = [
            'Customers count: '.$customers,
            'Employees / staff count (incl. owner): '.$staffCount,
            'Open shifts: '.$openShifts,
            'Employees list:',
        ];

        if ($staffUsers->isEmpty()) {
            $lines[] = '- No employees found.';
        } else {
            foreach ($staffUsers as $user) {
                $lines[] = '- '.$user->name
                    .' | role='.$user->displayRoleName()
                    .' | active='.($user->is_active ? 'yes' : 'no')
                    .($user->phone ? ' | phone='.$user->phone : '')
                    .($user->branch?->name ? ' | branch='.$user->branch->name : '');
            }
        }

        return $lines;
    }

    private function deterministicEmployeesReport(Business $business, string $question = ''): string
    {
        $sw = $this->usesSwahili($question);
        $staff = User::query()
            ->where('business_id', $business->id)
            ->where('role', '!=', 'super_admin')
            ->with(['role_relation', 'branch'])
            ->orderBy('name')
            ->get();

        $title = $sw ? 'Orodha ya wafanyakazi' : 'Employees list';
        $lines = [
            "**{$title}**",
            '',
            '| '.($sw ? 'Jina' : 'Name').' | '.($sw ? 'Wadhifa' : 'Role').' | '.($sw ? 'Tawi' : 'Branch').' | '.($sw ? 'Simu' : 'Phone').' | '.($sw ? 'Hali' : 'Status').' |',
            '|------|--------|-------|-------|--------|',
        ];

        if ($staff->isEmpty()) {
            $lines[] = '| '.($sw ? 'Hakuna wafanyakazi' : 'No employees').' | – | – | – | – |';
        } else {
            foreach ($staff as $user) {
                $lines[] = '| '.$user->name
                    .' | '.$user->displayRoleName()
                    .' | '.($user->branch?->name ?: '–')
                    .' | '.($user->phone ?: '–')
                    .' | '.($user->is_active ? ($sw ? 'Hai' : 'Active') : ($sw ? 'Imezimwa' : 'Inactive'))
                    .' |';
            }
        }

        $lines[] = '';
        $lines[] = ($sw ? 'Jumla' : 'Total').': **'.$staff->count().'**';
        $lines[] = $sw
            ? '- Skrini: **Usimamizi wa Wafanyakazi → Wafanyakazi**.'
            : '- Screen: **Staff Management → Employees**.';

        return implode("\n", $lines);
    }

    private function deterministicCustomersReport(Business $business, string $question = ''): string
    {
        $sw = $this->usesSwahili($question);
        $customers = Customer::query()
            ->where('business_id', $business->id)
            ->orderBy('name')
            ->limit(40)
            ->get(['name', 'phone', 'email']);

        $total = Customer::where('business_id', $business->id)->count();
        $title = $sw ? 'Orodha ya wateja' : 'Customers list';
        $lines = [
            "**{$title}**",
            '',
            '| '.($sw ? 'Jina' : 'Name').' | '.($sw ? 'Simu' : 'Phone').' | Email |',
            '|------|-------|-------|',
        ];

        if ($customers->isEmpty()) {
            $lines[] = '| '.($sw ? 'Hakuna wateja' : 'No customers').' | – | – |';
        } else {
            foreach ($customers as $customer) {
                $lines[] = '| '.$customer->name
                    .' | '.($customer->phone ?: '–')
                    .' | '.($customer->email ?: '–')
                    .' |';
            }
        }

        $lines[] = '';
        $shownNote = $total > $customers->count()
            ? ' ('.($sw ? 'inaonyesha ' : 'showing ').$customers->count().($sw ? ' ya kwanza' : ' first').')'
            : '';
        $lines[] = ($sw ? 'Jumla' : 'Total').': **'.$total.'**'.$shownNote;

        return implode("\n", $lines);
    }

    /**
     * @return list<string>
     */
    private function debtsSnapshot(Business $business, string $from, string $to): array
    {
        try {
            $report = $this->reports->debtsReport($business, $from, $to);
            $summary = $report['summary'] ?? [];
            $lines = [
                'Total outstanding debt (TZS): '.number_format((float) ($summary['total_outstanding'] ?? 0), 0, '.', ','),
                'Open debt accounts: '.(int) ($summary['open_accounts'] ?? 0),
                'Overdue debt accounts: '.(int) ($summary['overdue_count'] ?? 0),
                'Recent debt sales:',
            ];

            $recent = collect($report['recent_debts'] ?? [])->take(8);
            if ($recent->isEmpty()) {
                $lines[] = '- No open debts found.';
            } else {
                foreach ($recent as $sale) {
                    $name = is_object($sale)
                        ? ($sale->customer_name ?? $sale->customer?->name ?? 'Customer')
                        : ($sale['customer_name'] ?? 'Customer');
                    $total = is_object($sale) ? (float) $sale->total_amount : (float) ($sale['total_amount'] ?? 0);
                    $paid = is_object($sale) ? (float) $sale->amount_paid : (float) ($sale['amount_paid'] ?? 0);
                    $date = is_object($sale) ? (string) $sale->sale_date : (string) ($sale['sale_date'] ?? '');
                    $lines[] = '- '.$name.' | date='.$date.' | due_tzs='.number_format(max(0, $total - $paid), 0, '.', ',');
                }
            }

            return $lines;
        } catch (\Throwable) {
            return ['Debts summary unavailable.'];
        }
    }

    /**
     * @return list<string>
     */
    private function expensesSnapshot(Business $business, string $from, string $to): array
    {
        try {
            $report = $this->reports->expensesReport($business, $from, $to);
            $summary = $report['summary'] ?? [];
            $total = (float) ($summary['grand_total'] ?? 0);

            $lines = [
                'Expenses period: '.$from.' to '.$to,
                'Staff expenses (TZS): '.number_format((float) ($summary['staff_total'] ?? 0), 0, '.', ','),
                'Owner expenses (TZS): '.number_format((float) ($summary['owner_total'] ?? 0), 0, '.', ','),
                'Total expenses (TZS): '.number_format($total, 0, '.', ','),
            ];

            $categories = collect($report['owner_by_category'] ?? [])->take(8);
            if ($categories->isNotEmpty()) {
                $lines[] = 'Owner expense categories:';
                foreach ($categories as $row) {
                    $label = is_array($row) ? ($row['label'] ?? 'Category') : 'Category';
                    $amount = is_array($row) ? (float) ($row['amount'] ?? 0) : 0;
                    $lines[] = '- '.$label.': '.number_format($amount, 0, '.', ',');
                }
            }

            return $lines;
        } catch (\Throwable) {
            return ['Expenses summary unavailable.'];
        }
    }

    /**
     * @return list<string>
     */
    private function forecastLines(Business $business): array
    {
        try {
            $forecast = $this->forecast->forecast($business->id, $this->branchService->activeBranchId());

            return collect($forecast['items'] ?? [])
                ->take(8)
                ->map(function ($row) {
                    if (! is_array($row)) {
                        return null;
                    }

                    return ($row['name'] ?? 'Item')
                        .' | status='.($row['status'] ?? '')
                        .' | stock='.($row['stock_display'] ?? $row['stock'] ?? '')
                        .' | days_left='.($row['days_left'] ?? 'n/a')
                        .' | reorder='.(int) ($row['reorder_packs'] ?? 0).' '.($row['pack_name'] ?? 'pack');
                })
                ->filter()
                ->values()
                ->all();
        } catch (\Throwable) {
            return ['Forecast unavailable right now.'];
        }
    }

    /**
     * Pull extra facts based on what the user asked.
     *
     * @return list<string>
     */
    private function questionExtras(Business $business, string $question): array
    {
        $q = mb_strtolower($question);
        $lines = [];

        foreach ($this->datesMentionedInQuestion($question) as $date) {
            if (in_array($date, [today()->toDateString(), today()->subDay()->toDateString()], true)) {
                continue;
            }
            $stats = $this->daySalesStats($business->id, $date);
            $lines[] = 'Sales on '.$date.': orders='.$stats['orders']
                .' gross_tzs='.number_format($stats['gross'], 0, '.', ',')
                .' collected_tzs='.number_format($stats['collected'], 0, '.', ',')
                .' outstanding_tzs='.number_format($stats['outstanding'], 0, '.', ',');
            $lines[] = 'Top items on '.$date.':';
            array_push($lines, ...$this->topSoldItemsForDate($business->id, $date));
        }

        $itemHits = $this->findItemsFromQuestion($business->id, $question);
        if ($itemHits->isNotEmpty()) {
            $lines[] = 'Matching catalog items:';
            foreach ($itemHits as $item) {
                $lines[] = '- '.$item->name
                    .' | SKU '.$item->sku
                    .' | stock='.$item->current_stock
                    .($item->brand ? ' | brand='.$item->brand : '');
            }
        }

        if (preg_match('/\b(week|wiki|last\s*7|siku\s*7|faida|profit|mwezi|month|deni|debt|matumizi|expense|staff|wafanyakazi|customer|wateja|mwaka|year|annual|ytd|nyuma)\b/ui', $q)) {
            $lines[] = 'Question topics detected — for year questions use PREVIOUS CALENDAR YEAR / THIS YEAR TO DATE / ROLLING LAST 12 MONTHS sections. Do not invent annual report menus.';
        }

        return $lines;
    }

    /**
     * @return list<string> Y-m-d dates
     */
    private function datesMentionedInQuestion(string $question): array
    {
        $dates = [];
        $q = mb_strtolower($question);

        if (preg_match_all('/\b(20\d{2}-\d{2}-\d{2})\b/', $question, $m)) {
            foreach ($m[1] as $d) {
                $dates[] = $d;
            }
        }

        if (preg_match('/\b(kesho|tomorrow)\b/u', $q)) {
            $dates[] = today()->addDay()->toDateString();
        }

        if (preg_match('/\b(juzi|day before yesterday)\b/u', $q)) {
            $dates[] = today()->subDays(2)->toDateString();
        }

        // e.g. "tarehe 3", "3 Nov", "Nov 3"
        if (preg_match('/\b(\d{1,2})\s*(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*\b/i', $question, $m)
            || preg_match('/\b(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*\s*(\d{1,2})\b/i', $question, $m2)) {
            try {
                $dates[] = Carbon::parse($m[0] ?? $m2[0] ?? '')->toDateString();
            } catch (\Throwable) {
            }
        }

        return array_values(array_unique(array_filter($dates)));
    }

    /**
     * @return Collection<int, Item>
     */
    private function findItemsFromQuestion(int $businessId, string $question): Collection
    {
        $tokens = collect(preg_split('/[^\p{L}\p{N}]+/u', $question) ?: [])
            ->map(fn ($t) => mb_strtolower(trim($t)))
            ->filter(fn ($t) => mb_strlen($t) >= 3)
            ->reject(fn ($t) => in_array($t, [
                'the', 'and', 'for', 'with', 'from', 'this', 'that', 'have', 'what', 'which', 'where', 'when',
                'how', 'many', 'much', 'nipe', 'ripoti', 'mauzo', 'jana', 'leo', 'stock', 'item', 'items',
                'sales', 'report', 'about', 'please', 'nina', 'kama', 'gani', 'kwenye', 'sina', 'nani',
                'hello', 'habari', 'mauzolink', 'assistant',
            ], true))
            ->unique()
            ->take(5)
            ->values();

        if ($tokens->isEmpty()) {
            return collect();
        }

        return Item::query()
            ->where('business_id', $businessId)
            ->where(function ($q) use ($tokens) {
                foreach ($tokens as $token) {
                    $q->orWhere('name', 'like', '%'.$token.'%')
                        ->orWhere('sku', 'like', '%'.$token.'%')
                        ->orWhere('brand', 'like', '%'.$token.'%');
                }
            })
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'sku', 'current_stock', 'brand']);
    }

    /**
     * @return array{orders: int, gross: float, collected: float, outstanding: float}
     */
    private function daySalesStats(int $businessId, string $date): array
    {
        return $this->periodSalesStats($businessId, $date, $date);
    }

    /**
     * @return array{orders: int, gross: float, collected: float, outstanding: float}
     */
    private function periodSalesStats(int $businessId, string $from, string $to, bool $allBranches = false): array
    {
        $query = Sale::query()
            ->where('business_id', $businessId)
            ->whereDate('sale_date', '>=', $from)
            ->whereDate('sale_date', '<=', $to)
            ->where('payment_status', '!=', 'cancelled');

        if (! $allBranches) {
            $this->branchService->scopeSalesByActiveBranch($query);
        }

        $orders = (clone $query)->count();
        $gross = (float) (clone $query)->sum('total_amount');
        $collected = (float) (clone $query)->sum('amount_paid');

        return [
            'orders' => $orders,
            'gross' => $gross,
            'collected' => $collected,
            'outstanding' => max(0, $gross - $collected),
        ];
    }

    /**
     * @param  array{orders: int, gross: float, collected: float, outstanding: float}  $stats
     * @return list<string>
     */
    private function formatPeriodStats(array $stats): array
    {
        return [
            'Idadi ya mauzo / sales orders count: '.$stats['orders'],
            'Jumla ya mauzo / gross sales (TZS): '.number_format($stats['gross'], 0, '.', ','),
            'Malipo yaliyokusanywa / collected (TZS): '.number_format($stats['collected'], 0, '.', ','),
            'Deni lililosalia / outstanding (TZS): '.number_format($stats['outstanding'], 0, '.', ','),
        ];
    }

    /**
     * @return list<string>
     */
    private function monthlySalesBreakdown(int $businessId, string $from, string $to, bool $allBranches = false): array
    {
        $query = Sale::query()
            ->where('business_id', $businessId)
            ->whereDate('sale_date', '>=', $from)
            ->whereDate('sale_date', '<=', $to)
            ->where('payment_status', '!=', 'cancelled');

        if (! $allBranches) {
            $this->branchService->scopeSalesByActiveBranch($query);
        }

        $sales = $query->get(['sale_date', 'total_amount', 'amount_paid']);
        if ($sales->isEmpty()) {
            return ['- No sales in this period.'];
        }

        return $sales
            ->groupBy(fn ($sale) => Carbon::parse($sale->sale_date)->format('Y-m'))
            ->sortKeys()
            ->map(function (Collection $group, string $ym) {
                $gross = (float) $group->sum('total_amount');
                $collected = (float) $group->sum('amount_paid');

                return '- '.$ym
                    .' | orders='.$group->count()
                    .' | gross_tzs='.number_format($gross, 0, '.', ',')
                    .' | collected_tzs='.number_format($collected, 0, '.', ',');
            })
            ->values()
            ->all();
    }

    private function firstSaleDate(int $businessId, bool $allBranches = false): ?string
    {
        $query = Sale::query()
            ->where('business_id', $businessId)
            ->where('payment_status', '!=', 'cancelled');

        if (! $allBranches) {
            $this->branchService->scopeSalesByActiveBranch($query);
        }

        $date = $query->min('sale_date');

        return $date ? Carbon::parse($date)->toDateString() : null;
    }

    /**
     * @return list<string>
     */
    private function recentDailySales(Business $business, string $from, string $to): array
    {
        try {
            $report = $this->reports->dailySalesReport($business, $from, $to);
            $rows = collect($report['rows'] ?? [])->sortBy('date')->values();

            if ($rows->isEmpty()) {
                return ['- No sales in the last 7 days.'];
            }

            return $rows->map(function ($row) {
                $date = $row['date'] ?? '';
                $label = $row['date_label'] ?? $date;
                $tag = $date === today()->toDateString()
                    ? ' [TODAY]'
                    : ($date === today()->subDay()->toDateString() ? ' [YESTERDAY]' : '');

                return '- '.$label.$tag
                    .' | orders='.(int) ($row['orders'] ?? 0)
                    .' | gross_tzs='.number_format((float) ($row['gross'] ?? 0), 0, '.', ',')
                    .' | collected_tzs='.number_format((float) ($row['collected'] ?? 0), 0, '.', ',');
            })->all();
        } catch (\Throwable) {
            return ['- Recent daily sales unavailable.'];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function safeDailySalesSummary(Business $business, string $from, string $to): array
    {
        try {
            return $this->reports->dailySalesReport($business, $from, $to)['summary'] ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return list<string>
     */
    private function topSoldItemsForDate(int $businessId, string $date, bool $allBranches = false): array
    {
        $query = Sale::query()
            ->where('business_id', $businessId)
            ->whereDate('sale_date', $date)
            ->where('payment_status', '!=', 'cancelled');
        if (! $allBranches) {
            $this->branchService->scopeSalesByActiveBranch($query);
        }

        $saleIds = $query->pluck('id');
        if ($saleIds->isEmpty()) {
            return ['- No sales recorded on '.$date.'.'];
        }

        $rows = DB::table('sale_items')
            ->leftJoin('items', 'items.id', '=', 'sale_items.item_id')
            ->whereIn('sale_items.sale_id', $saleIds)
            ->whereNotNull('sale_items.item_id')
            ->selectRaw('sale_items.item_id, items.name as item_name, SUM(sale_items.quantity) as qty, SUM(sale_items.subtotal) as revenue')
            ->groupBy('sale_items.item_id', 'items.name')
            ->orderByDesc('qty')
            ->limit(8)
            ->get();

        if ($rows->isEmpty()) {
            return ['- No line items found for '.$date.'.'];
        }

        return $rows->map(function ($row) {
            return '- '.($row->item_name ?: ('Item #'.$row->item_id))
                .' qty='.$row->qty
                .' revenue_tzs='.number_format((float) $row->revenue, 0, '.', ',');
        })->all();
    }

    /**
     * @param  array<int, mixed>  $history
     * @return Collection<int, array{role: string, content: string}>
     */
    private function sanitizeHistory(array $history): Collection
    {
        return collect($history)
            ->filter(fn ($turn) => is_array($turn)
                && in_array(($turn['role'] ?? ''), ['user', 'assistant'], true)
                && filled($turn['content'] ?? null))
            ->map(fn ($turn) => [
                'role' => (string) $turn['role'],
                'content' => mb_substr(trim((string) $turn['content']), 0, 4000),
            ])
            ->take(-1 * self::MAX_HISTORY)
            ->values();
    }
}

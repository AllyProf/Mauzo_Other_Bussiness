<?php

namespace App\Services;

/**
 * Builds compact MauzoLink training text from the official help / page manuals.
 */
class AiSystemTrainerKnowledge
{
    public function manualText(?string $question = null): string
    {
        $sections = [];

        $setupSteps = __('help.getting_started.steps');
        if (is_array($setupSteps)) {
            $sections[] = 'SETUP ORDER (Getting Started):';
            $n = 1;
            foreach ($setupSteps as $key => $step) {
                if (! is_array($step)) {
                    continue;
                }
                $title = (string) ($step['title'] ?? $key);
                $menu = (string) ($step['menu'] ?? '');
                $sections[] = "{$n}. {$title}";
                if ($menu !== '') {
                    $sections[] = "   Menu: {$menu}";
                }
                foreach (($step['bullets'] ?? []) as $bullet) {
                    $sections[] = '   - '.$bullet;
                }
                $n++;
            }
        }

        $screens = __('page_manual.screens');
        if (is_array($screens)) {
            $wanted = $this->relevantScreenKeys($question, array_keys($screens));
            $sections[] = '';
            $sections[] = 'DETAILED HOW-TO SCREENS:';
            foreach ($wanted as $key) {
                $screen = $screens[$key] ?? null;
                if (! is_array($screen)) {
                    continue;
                }
                $title = (string) ($screen['title'] ?? $key);
                $menu = (string) ($screen['menu'] ?? '');
                $intro = (string) ($screen['intro'] ?? '');
                $sections[] = "### {$title} [{$key}]";
                if ($menu !== '') {
                    $sections[] = "Menu: {$menu}";
                }
                if ($intro !== '') {
                    $sections[] = $intro;
                }
                foreach (($screen['steps'] ?? []) as $step) {
                    if (! is_array($step)) {
                        continue;
                    }
                    $sections[] = ((int) ($step['n'] ?? 0)).'. '.($step['title'] ?? 'Step').': '.($step['text'] ?? '');
                    foreach (($step['details'] ?? []) as $detail) {
                        $sections[] = '   • '.$detail;
                    }
                }
            }
        }

        $sections[] = '';
        $sections[] = 'TRAINER RULES:';
        $sections[] = '- Teach step-by-step like a patient trainer (numbered steps).';
        $sections[] = '- Always say the sidebar menu path (e.g. Inventory → Stock In).';
        $sections[] = '- Mention prerequisites when needed (e.g. create suppliers/roles before employees/receiving).';
        $sections[] = '- If the user asks "how do I…", prefer DETAILED HOW-TO / SETUP ORDER over inventing UI labels.';
        $sections[] = '- Offer the next logical step after finishing (e.g. after register item → receive stock).';
        $sections[] = '- Point to Help → Getting Started / System Guide for the full walkthrough.';

        return implode("\n", $sections);
    }

    /**
     * @param  list<string|int>  $available
     * @return list<string>
     */
    private function relevantScreenKeys(?string $question, array $available): array
    {
        $available = array_map('strval', $available);
        $q = mb_strtolower((string) $question);

        $map = [
            'receiving_create' => ['receive', 'receiving', 'stock in', 'stock-in', 'stockin', 'pokea', 'upokeaji', 'kupokea', 'supplier delivery'],
            'receiving' => ['receive', 'receiving', 'stock in', 'stock-in', 'pokea', 'upokeaji'],
            'employees' => ['employee', 'employees', 'staff', 'mfanyakazi', 'wafanyakazi', 'register staff', 'ongeza staff'],
            'roles' => ['role', 'roles', 'permission', 'permissions', 'ruhusa', 'cashier role'],
            'items_create' => ['register item', 'new item', 'add item', 'bidhaa', 'product', 'ongeza bidhaa', 'register product'],
            'items' => ['items', 'products', 'bidhaa', 'inventory list'],
            'packaging' => ['packaging', 'unit', 'units', 'carton', 'piece', 'uom', 'vipimo'],
            'business_types' => ['category', 'categories', 'business type', 'aina'],
            'suppliers' => ['supplier', 'suppliers', 'vendor', 'wasambazaji'],
            'sales_pos_create' => ['pos', 'sell', 'sale', 'uza', 'mauzo', 'cashier'],
            'shifts' => ['shift', 'shifts', 'open shift', 'zam', 'zam'],
            'settings' => ['settings', 'profile', 'mipangilio', 'business settings'],
            'customers' => ['customer', 'customers', 'mteja', 'wateja'],
            'debts' => ['debt', 'debts', 'deni', 'madeni', 'credit'],
            'daily_report' => ['daily report', 'ripoti', 'reconciliation', 'day closing'],
            'reports' => ['report', 'reports', 'analytics'],
            'invoices_create' => ['invoice', 'invoices', 'ankara'],
            'branches' => ['branch', 'branches', 'tawi', 'matawi'],
            'dashboard' => ['dashboard', 'home', 'dashibodi'],
        ];

        $matched = [];
        foreach ($map as $key => $keywords) {
            if (! in_array($key, $available, true)) {
                continue;
            }
            foreach ($keywords as $keyword) {
                if ($keyword !== '' && str_contains($q, $keyword)) {
                    $matched[] = $key;
                    break;
                }
            }
        }

        // Always include core trainer topics if question is vague how-to.
        if ($matched === [] && preg_match('/\b(how|jinsi|eleza|fundisha|train|tutorial|guide|msaada|help)\b/u', $q)) {
            foreach (['items_create', 'receiving_create', 'employees', 'sales_pos_create', 'roles'] as $fallback) {
                if (in_array($fallback, $available, true)) {
                    $matched[] = $fallback;
                }
            }
        }

        if ($matched === []) {
            // Compact default set for general trainer answers.
            foreach (['items_create', 'receiving_create', 'employees', 'roles', 'sales_pos_create', 'shifts', 'reports'] as $fallback) {
                if (in_array($fallback, $available, true)) {
                    $matched[] = $fallback;
                }
            }
        }

        return array_values(array_unique($matched));
    }
}

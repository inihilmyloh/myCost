<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AccountInterestService
{
    /**
     * Calculate and accrue interest for all accounts that have interest enabled.
     *
     * @param int|null $userId
     * @return array
     */
    public function accrueAllAccounts(?int $userId = null): array
    {
        $query = Account::where('is_active', true)
            ->where('has_interest', true);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $accounts = $query->get();
        $results = [];

        foreach ($accounts as $account) {
            $results[] = $this->accrueAccountInterest($account);
        }

        return $results;
    }

    /**
     * Accrue daily interest for a single account from last_interest_accrued_date until today.
     *
     * @param Account $account
     * @return array
     */
    public function accrueAccountInterest(Account $account): array
    {
        if (!$account->has_interest || !$account->is_active) {
            return [
                'account_id' => $account->id,
                'name' => $account->name,
                'days_accrued' => 0,
                'total_interest' => 0,
                'message' => 'Akun tidak memiliki fitur bunga aktif.',
            ];
        }

        $today = Carbon::today('Asia/Jakarta');
        $lastAccrued = $account->last_interest_accrued_date
            ? Carbon::parse($account->last_interest_accrued_date, 'Asia/Jakarta')->startOfDay()
            : Carbon::parse($account->created_at ?? now(), 'Asia/Jakarta')->startOfDay();

        // If already accrued for today, return early
        if ($lastAccrued->gte($today)) {
            return [
                'account_id' => $account->id,
                'name' => $account->name,
                'days_accrued' => 0,
                'total_interest' => 0,
                'message' => 'Bunga hari ini sudah dihitung dan dicairkan.',
            ];
        }

        $currentDate = $lastAccrued->copy()->addDay();
        $daysProcessed = 0;
        $totalInterestCredited = 0;
        $createdTransactions = [];

        DB::beginTransaction();
        try {
            while ($currentDate->lte($today)) {
                $dateString = $currentDate->toDateString();
                $balance = (float)$account->balance;

                if ($balance > 0) {
                    $tierInfo = $this->resolveTierInfo($account, $balance);
                    $rate = (float)$tierInfo['active_rate'];

                    // Daily gross interest: (Balance * Rate / 100) / 365
                    $grossInterest = ($balance * ($rate / 100)) / 365.0;

                    // Tax rule (PPh 20% if balance > 7.500.000)
                    $taxThreshold = (float)($account->interest_tax_threshold ?? 7500000.00);
                    $taxRate = (float)($account->interest_tax_rate ?? 20.00);
                    $taxAmount = ($balance > $taxThreshold) ? ($grossInterest * ($taxRate / 100)) : 0.0;

                    $netInterest = round($grossInterest - $taxAmount, 2);

                    if ($netInterest >= 1.0) {
                        // Create interest income transaction
                        $trans = Transaction::create([
                            'user_id' => $account->user_id,
                            'account_id' => $account->id,
                            'type' => 'pemasukan',
                            'amount' => $netInterest,
                            'subtotal' => round($grossInterest, 2),
                            'tax' => round($taxAmount, 2),
                            'admin_fee' => 0,
                            'category' => 'Bunga Tabungan',
                            'transaction_date' => $dateString,
                            'notes' => "Bunga Harian {$account->name} ({$rate}% p.a.)",
                        ]);

                        $account->balance += $netInterest;
                        $totalInterestCredited += $netInterest;
                        $createdTransactions[] = $trans;
                    }
                }

                $daysProcessed++;
                $currentDate->addDay();
            }

            $account->last_interest_accrued_date = $today->toDateString();
            $account->save();

            DB::commit();

            return [
                'account_id' => $account->id,
                'name' => $account->name,
                'days_accrued' => $daysProcessed,
                'total_interest' => $totalInterestCredited,
                'new_balance' => (float)$account->balance,
                'transactions' => $createdTransactions,
                'message' => "Berhasil mencairkan bunga untuk {$daysProcessed} hari sebesar Rp " . number_format($totalInterestCredited, 0, ',', '.'),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error accruing interest for account {$account->id}: " . $e->getMessage());
            return [
                'account_id' => $account->id,
                'name' => $account->name,
                'days_accrued' => 0,
                'total_interest' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Normalize and resolve tier structure for an account or custom tiers array.
     */
    public function resolveTierInfo($accountOrTiers, float $balance): array
    {
        $rawTiers = [];

        if (is_array($accountOrTiers)) {
            $rawTiers = $accountOrTiers;
        } elseif ($accountOrTiers instanceof Account) {
            if (!empty($accountOrTiers->interest_tiers) && is_array($accountOrTiers->interest_tiers)) {
                $rawTiers = $accountOrTiers->interest_tiers;
            } else {
                $defRate = (float)($accountOrTiers->interest_rate_default ?? 2.50);
                $tierRate = (float)($accountOrTiers->interest_rate_tier ?? 3.50);
                $threshold = (float)($accountOrTiers->interest_tier_threshold ?? 150000000.00);

                if ($threshold > 0 && $tierRate != $defRate) {
                    $rawTiers = [
                        ['min' => 0, 'rate' => $defRate],
                        ['min' => $threshold, 'rate' => $tierRate],
                    ];
                } else {
                    $rawTiers = [
                        ['min' => 0, 'rate' => $defRate],
                    ];
                }
            }
        }

        // Clean & sort raw tiers by min ascending
        $normalized = [];
        foreach ($rawTiers as $t) {
            $minVal = isset($t['min']) ? (float)$t['min'] : 0.0;
            $rateVal = isset($t['rate']) ? (float)$t['rate'] : 0.0;
            $normalized[] = [
                'min' => max(0, $minVal),
                'rate' => max(0, $rateVal),
            ];
        }

        usort($normalized, function ($a, $b) {
            return $a['min'] <=> $b['min'];
        });

        if (empty($normalized)) {
            $normalized = [['min' => 0, 'rate' => 2.50]];
        }

        // Find active tier
        $activeTierIndex = 0;
        $activeRate = $normalized[0]['rate'];

        for ($i = 0; $i < count($normalized); $i++) {
            if ($balance >= $normalized[$i]['min']) {
                $activeTierIndex = $i;
                $activeRate = $normalized[$i]['rate'];
            }
        }

        // Build presentation tiers
        $displayTiers = [];
        $totalCount = count($normalized);
        $nextTierGoal = null;

        for ($i = 0; $i < $totalCount; $i++) {
            $current = $normalized[$i];
            $next = ($i < $totalCount - 1) ? $normalized[$i + 1] : null;

            $minFormatted = number_format($current['min'], 0, ',', '.');
            if ($totalCount === 1) {
                $label = "Semua Nominal Saldo";
            } elseif ($next !== null) {
                $nextMinFormatted = number_format($next['min'], 0, ',', '.');
                $label = "Saldo Rp {$minFormatted} s/d < Rp {$nextMinFormatted}";
            } else {
                $label = "Saldo ≥ Rp {$minFormatted}";
            }

            $isActive = ($i === $activeTierIndex);

            $displayTiers[] = [
                'tier_number' => $i + 1,
                'label' => $label,
                'min' => $current['min'],
                'rate' => "{$current['rate']}% p.a.",
                'rate_num' => $current['rate'],
                'is_active' => $isActive,
            ];

            if ($isActive && $next !== null) {
                $needed = max(0, $next['min'] - $balance);
                $nextTierGoal = [
                    'next_tier_number' => $i + 2,
                    'next_rate' => $next['rate'],
                    'target_min' => $next['min'],
                    'amount_needed' => $needed,
                    'amount_needed_formatted' => 'Rp ' . number_format($needed, 0, ',', '.'),
                    'badge_text' => "Top up Rp " . number_format($needed, 0, ',', '.') . " lagi, dapat {$next['rate']}% p.a.",
                ];
            }
        }

        return [
            'active_rate' => $activeRate,
            'active_tier_number' => $activeTierIndex + 1,
            'is_highest_tier' => ($activeTierIndex === $totalCount - 1),
            'tiers' => $displayTiers,
            'raw_tiers' => $normalized,
            'next_tier_goal' => $nextTierGoal,
        ];
    }

    /**
     * Simulate interest calculations for an account or custom balance and tiers.
     */
    public function simulate(
        float $balance,
        $accountOrTiers = null,
        ?float $legacyDefaultRate = null,
        ?float $legacyTierRate = null,
        ?float $legacyTierThreshold = null
    ): array {
        // If legacy params provided without tiers
        if (empty($accountOrTiers) && $legacyDefaultRate !== null) {
            $accountOrTiers = [
                ['min' => 0, 'rate' => $legacyDefaultRate],
                ['min' => $legacyTierThreshold ?? 150000000, 'rate' => $legacyTierRate ?? $legacyDefaultRate],
            ];
        }

        $tierInfo = $this->resolveTierInfo($accountOrTiers, $balance);
        $activeRate = (float)$tierInfo['active_rate'];

        // Daily
        $dailyGross = ($balance * ($activeRate / 100)) / 365.0;
        $taxThreshold = 7500000.00;
        $taxRate = 0.20;
        $hasTax = $balance > $taxThreshold;

        $dailyTax = $hasTax ? ($dailyGross * $taxRate) : 0.0;
        $dailyNet = $dailyGross - $dailyTax;

        // Monthly (30 days)
        $monthlyGross = $dailyGross * 30;
        $monthlyTax = $dailyTax * 30;
        $monthlyNet = $dailyNet * 30;

        // Yearly (365 days)
        $yearlyGross = ($balance * ($activeRate / 100));
        $yearlyTax = $hasTax ? ($yearlyGross * $taxRate) : 0.0;
        $yearlyNet = $yearlyGross - $yearlyTax;

        return [
            'balance' => $balance,
            'active_rate' => $activeRate,
            'active_tier_number' => $tierInfo['active_tier_number'],
            'is_highest_tier' => $tierInfo['is_highest_tier'],
            'tiers' => $tierInfo['tiers'],
            'raw_tiers' => $tierInfo['raw_tiers'],
            'next_tier_goal' => $tierInfo['next_tier_goal'],
            'has_tax' => $hasTax,
            'daily' => [
                'gross' => round($dailyGross, 2),
                'tax' => round($dailyTax, 2),
                'net' => round($dailyNet, 2),
                'net_formatted' => 'Rp ' . number_format(round($dailyNet), 0, ',', '.'),
            ],
            'monthly' => [
                'gross' => round($monthlyGross, 2),
                'tax' => round($monthlyTax, 2),
                'net' => round($monthlyNet, 2),
                'net_formatted' => 'Rp ' . number_format(round($monthlyNet), 0, ',', '.'),
            ],
            'yearly' => [
                'gross' => round($yearlyGross, 2),
                'tax' => round($yearlyTax, 2),
                'net' => round($yearlyNet, 2),
                'net_formatted' => 'Rp ' . number_format(round($yearlyNet), 0, ',', '.'),
            ],
        ];
    }
}


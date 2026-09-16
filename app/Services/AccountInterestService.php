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
                    $threshold = (float)($account->interest_tier_threshold ?? 150000000.00);
                    $rate = ($balance >= $threshold)
                        ? (float)($account->interest_rate_tier ?? 3.50)
                        : (float)($account->interest_rate_default ?? 2.50);

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
     * Simulate interest calculations for an account or custom balance.
     *
     * @param float $balance
     * @param float|null $defaultRate
     * @param float|null $tierRate
     * @param float|null $tierThreshold
     * @return array
     */
    public function simulate(
        float $balance,
        ?float $defaultRate = 2.50,
        ?float $tierRate = 3.50,
        ?float $tierThreshold = 150000000.00
    ): array {
        $defaultRate = $defaultRate ?? 2.50;
        $tierRate = $tierRate ?? 3.50;
        $tierThreshold = $tierThreshold ?? 150000000.00;

        $isTierHigher = $balance >= $tierThreshold;
        $activeRate = $isTierHigher ? $tierRate : $defaultRate;

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

        $hasTier = ($tierThreshold > 0 && $tierRate > $defaultRate);
        $thresholdFormatted = number_format($tierThreshold, 0, ',', '.');

        $tiers = [];
        if ($hasTier) {
            $tiers = [
                [
                    'label' => "Saldo Tabungan < Rp {$thresholdFormatted}",
                    'rate' => "{$defaultRate}% p.a.",
                    'is_active' => !$isTierHigher,
                ],
                [
                    'label' => "Saldo Tabungan ≥ Rp {$thresholdFormatted}",
                    'rate' => "{$tierRate}% p.a.",
                    'is_active' => $isTierHigher,
                ],
            ];
        } else {
            $tiers = [
                [
                    'label' => "Semua Nominal Saldo",
                    'rate' => "{$defaultRate}% p.a.",
                    'is_active' => true,
                ]
            ];
        }

        return [
            'balance' => $balance,
            'active_rate' => $activeRate,
            'default_rate' => $defaultRate,
            'tier_rate' => $tierRate,
            'has_tier' => $hasTier,
            'is_tier_higher' => $isTierHigher,
            'tier_threshold' => $tierThreshold,
            'tier_threshold_formatted' => 'Rp ' . $thresholdFormatted,
            'amount_to_next_tier' => max(0, $tierThreshold - $balance),
            'amount_to_next_tier_formatted' => 'Rp ' . number_format(max(0, $tierThreshold - $balance), 0, ',', '.'),
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
            'tiers' => $tiers,
        ];
    }
}

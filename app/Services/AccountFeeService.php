<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AccountFeeService
{
    /**
     * Process monthly admin fee deductions for all eligible accounts.
     *
     * @param int|null $userId
     * @return array
     */
    public function deductAllMonthlyAdminFees(?int $userId = null): array
    {
        $query = Account::where('is_active', true)
            ->where('monthly_admin_fee', '>', 0);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $accounts = $query->get();
        $results = [];

        foreach ($accounts as $account) {
            $results[] = $this->deductAccountMonthlyAdminFee($account);
        }

        return $results;
    }

    /**
     * Deduct monthly admin fee for a single account if due.
     *
     * @param Account $account
     * @return array
     */
    public function deductAccountMonthlyAdminFee(Account $account): array
    {
        $monthlyFee = (float)($account->monthly_admin_fee ?? 0);
        if ($monthlyFee <= 0 || !$account->is_active) {
            return [
                'account_id' => $account->id,
                'name' => $account->name,
                'deducted' => false,
                'message' => 'Akun tidak memiliki biaya admin bulanan aktif.',
            ];
        }

        $today = Carbon::today('Asia/Jakarta');
        $deductionDay = (int)($account->admin_fee_date ?: 25);

        // Cap deduction day to maximum days in current month (e.g., 28 in Feb)
        $maxDays = $today->daysInMonth;
        $actualDay = min($deductionDay, $maxDays);

        $dueThisMonth = Carbon::create($today->year, $today->month, $actualDay, 0, 0, 0, 'Asia/Jakarta');

        // Check if today has reached or passed the deduction day this month
        if ($today->lt($dueThisMonth)) {
            return [
                'account_id' => $account->id,
                'name' => $account->name,
                'deducted' => false,
                'message' => "Biaya admin bulan ini baru akan dipotong pada tanggal {$actualDay}.",
            ];
        }

        // Check if already deducted this month
        if ($account->last_admin_fee_deducted_date) {
            $lastDeducted = Carbon::parse($account->last_admin_fee_deducted_date, 'Asia/Jakarta')->startOfDay();
            if ($lastDeducted->year === $today->year && $lastDeducted->month === $today->month) {
                return [
                    'account_id' => $account->id,
                    'name' => $account->name,
                    'deducted' => false,
                    'message' => 'Biaya admin untuk bulan ini sudah dipotong sebelumnya.',
                ];
            }
        }

        DB::beginTransaction();
        try {
            $trans = Transaction::create([
                'user_id' => $account->user_id,
                'account_id' => $account->id,
                'type' => 'pengeluaran',
                'amount' => $monthlyFee,
                'subtotal' => $monthlyFee,
                'tax' => 0,
                'admin_fee' => 0,
                'category' => 'Biaya Admin Bank',
                'transaction_date' => $dueThisMonth->toDateString(),
                'notes' => "Biaya Admin Bulanan & Kartu Debit {$account->name}",
            ]);

            $account->balance -= $monthlyFee;
            $account->last_admin_fee_deducted_date = $today->toDateString();
            $account->save();

            DB::commit();

            return [
                'account_id' => $account->id,
                'name' => $account->name,
                'deducted' => true,
                'fee_amount' => $monthlyFee,
                'new_balance' => (float)$account->balance,
                'transaction' => $trans,
                'message' => "Biaya admin bulanan {$account->name} sebesar Rp " . number_format($monthlyFee, 0, ',', '.') . " berhasil dipotong sebagai pengeluaran.",
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error deducting admin fee for account {$account->id}: " . $e->getMessage());
            return [
                'account_id' => $account->id,
                'name' => $account->name,
                'deducted' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}

<?php

namespace App\Console\Commands;

use App\Services\AccountInterestService;
use Illuminate\Console\Command;

class AccrueAccountInterestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'accounts:accrue-interest {--user= : User ID to accrue interest for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kalkulasi dan cairkan bunga tabungan harian untuk rekening berbunga aktif.';

    /**
     * Execute the console command.
     */
    public function handle(AccountInterestService $service): int
    {
        $userId = $this->option('user') ? (int)$this->option('user') : null;
        $this->info('Memproses pencairan bunga harian rekening...');

        $results = $service->accrueAllAccounts($userId);

        if (empty($results)) {
            $this->info('Tidak ada rekening dengan fitur bunga aktif.');
            return Command::SUCCESS;
        }

        foreach ($results as $res) {
            if (!empty($res['error'])) {
                $this->error("Akun #{$res['account_id']} ({$res['name']}): Error - {$res['error']}");
            } else {
                $this->line("Akun #{$res['account_id']} ({$res['name']}): {$res['message']}");
            }
        }

        $this->info('Selesai!');
        return Command::SUCCESS;
    }
}

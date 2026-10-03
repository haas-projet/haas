<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

final class CheckOperations extends Command
{
    protected $signature = 'ops:check';

    protected $description = 'Contrôler SQL et les files depuis la console, sans données privées.';

    public function handle(): int
    {
        try {
            DB::select('SELECT 1');
            $failed = DB::table('failed_jobs')->count();
            $lateNotifications = DB::table('notification_outbox')->whereNull('delivered_at')->where('created_at', '<', now()->subMinutes(5))->count();
            $lateMail = DB::table('jobs')->where('queue', 'account-mail')->where('available_at', '<', now()->subMinutes(5)->timestamp)->count();
            $healthy = $failed === 0 && $lateNotifications === 0 && $lateMail === 0;
            $this->line(json_encode(['status' => $healthy ? 'ok' : 'attention', 'database' => 'ok', 'failed_jobs' => $failed,
                'late_notifications' => $lateNotifications, 'late_account_mail' => $lateMail], JSON_THROW_ON_ERROR));

            return $healthy ? self::SUCCESS : self::FAILURE;
        } catch (Throwable) {
            $this->line('{"status":"unavailable"}');

            return self::FAILURE;
        }
    }
}

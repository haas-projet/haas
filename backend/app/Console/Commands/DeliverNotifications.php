<?php

namespace App\Console\Commands;

use App\Services\Notifications\NotificationOutbox;
use Illuminate\Console\Command;

final class DeliverNotifications extends Command
{
    protected $signature = 'notifications:deliver';

    protected $description = 'Livrer au maximum 100 notifications internes commitées.';

    public function handle(NotificationOutbox $outbox): int
    {
        $this->info($outbox->deliver().' notification(s) traitée(s).');

        return self::SUCCESS;
    }
}

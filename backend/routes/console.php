<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('idempotency:prune')->everyFiveMinutes()->withoutOverlapping(5);
Schedule::command('notifications:deliver')->everyMinute()->withoutOverlapping(5);
Schedule::command('demo:prune')->everyFifteenMinutes()->withoutOverlapping(5);

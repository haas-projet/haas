<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('idempotency:prune')->everyFiveMinutes()->withoutOverlapping(5);

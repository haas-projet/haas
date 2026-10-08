<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('demo:prune')->everyFifteenMinutes()->withoutOverlapping(5);

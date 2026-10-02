<?php

declare(strict_types=1);

namespace App\Enums\Comparisons;

enum ComparisonState: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Error = 'error';
    case TimedOut = 'timed_out';
}

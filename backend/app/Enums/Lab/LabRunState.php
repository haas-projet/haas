<?php

declare(strict_types=1);

namespace App\Enums\Lab;

enum LabRunState: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Passed = 'passed';
    case Failed = 'failed';
    case Error = 'error';
    case TimedOut = 'timed_out';
}

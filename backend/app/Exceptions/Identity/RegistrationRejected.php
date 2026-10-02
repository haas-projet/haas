<?php

namespace App\Exceptions\Identity;

use DomainException;
use Illuminate\Contracts\Debug\ShouldntReport;

final class RegistrationRejected extends DomainException implements ShouldntReport
{
    /** @param array<string, list<string>> $fields */
    public function __construct(public readonly array $fields)
    {
        parent::__construct('Inscription refusée.');
    }
}

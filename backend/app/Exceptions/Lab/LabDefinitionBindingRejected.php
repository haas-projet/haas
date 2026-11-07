<?php

declare(strict_types=1);

namespace App\Exceptions\Lab;

use RuntimeException;

final class LabDefinitionBindingRejected extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Liaison refusée : version, provenance ou manifeste indisponible.');
    }
}

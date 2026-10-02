<?php

namespace App\Exceptions\Identity;

use DomainException;
use Illuminate\Contracts\Debug\ShouldntReport;

final class InactiveAccount extends DomainException implements ShouldntReport {}

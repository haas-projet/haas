<?php

namespace App\Exceptions\Identity;

use DomainException;
use Illuminate\Contracts\Debug\ShouldntReport;

final class InvalidCredentials extends DomainException implements ShouldntReport {}

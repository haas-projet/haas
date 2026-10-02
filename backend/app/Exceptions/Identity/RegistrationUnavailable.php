<?php

namespace App\Exceptions\Identity;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

final class RegistrationUnavailable extends RuntimeException implements ShouldntReport {}

<?php

namespace App\Exceptions\Identity;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

final class InvalidResetToken extends RuntimeException implements ShouldntReport {}

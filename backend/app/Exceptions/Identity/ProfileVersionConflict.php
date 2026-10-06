<?php

namespace App\Exceptions\Identity;

use DomainException;
use Illuminate\Contracts\Debug\ShouldntReport;

final class ProfileVersionConflict extends DomainException implements ShouldntReport {}

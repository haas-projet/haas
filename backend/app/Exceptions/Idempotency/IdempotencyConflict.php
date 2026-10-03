<?php

namespace App\Exceptions\Idempotency;

use RuntimeException;

final class IdempotencyConflict extends RuntimeException {}

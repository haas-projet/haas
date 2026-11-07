<?php

declare(strict_types=1);

namespace App\Exceptions\Capsules;

use RuntimeException;

/**
 * lock_version soumis périmé : la version a été modifiée depuis la lecture.
 * Les Services du domaine capsules lèvent cette exception sans dépendre de
 * HTTP ; le Controller la transforme en 409.
 */
final class StaleCapsuleVersion extends RuntimeException {}

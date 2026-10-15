<?php

declare(strict_types=1);

namespace App\Exceptions\VerificationCases;

use RuntimeException;

// Levée par la commande d'intégration BV203 pour :
// - manifeste hors gabarit ou illisible,
// - révision déjà intégrée (idempotence respectée),
// - scénario inconnu, digest incohérent, cas qui n'est pas reviewed.
final class VerificationCaseBindingRejected extends RuntimeException {}

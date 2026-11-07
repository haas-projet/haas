<?php

declare(strict_types=1);

namespace App\Exceptions\Capsules;

use RuntimeException;

/**
 * Soumission à la revue refusée : le brouillon ne contient pas assez d'éléments
 * minimaux (objectif/procédure/limites, CAHIER_DES_CHARGES.md:464 « Compréhension »).
 * Le Controller la transforme en 422.
 */
final class InsufficientDraftContent extends RuntimeException {}

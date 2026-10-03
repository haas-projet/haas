<?php

namespace App\Support\Http;

use App\Exceptions\Identity\InactiveAccount;
use App\Exceptions\Identity\InvalidCredentials;
use App\Exceptions\Identity\InvalidResetToken;
use App\Exceptions\Identity\ProfileUpdateRejected;
use App\Exceptions\Identity\ProfileVersionConflict;
use App\Exceptions\Identity\RegistrationRejected;
use App\Exceptions\Identity\RegistrationUnavailable;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class ApiExceptionRenderer
{
    public static function handles(Request $request): bool
    {
        return $request->is('api', 'api/*', 'register', 'login', 'logout', 'sanctum/*', 'email/*', 'forgot-password', 'reset-password') || $request->expectsJson();
    }

    public function render(Throwable $exception, Request $request): ?JsonResponse
    {
        if (! self::handles($request)) {
            return null;
        }

        $status = match (true) {
            $exception instanceof ValidationException, $exception instanceof RegistrationRejected => 422,
            $exception instanceof InvalidCredentials, $exception instanceof InvalidResetToken => 422,
            $exception instanceof ProfileUpdateRejected => 422,
            $exception instanceof ProfileVersionConflict => 409,
            $exception instanceof InactiveAccount => 403,
            $exception instanceof RegistrationUnavailable => 503,
            $exception instanceof AuthenticationException => 401,
            $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
            default => 500,
        };

        [$code, $message] = match ($status) {
            400 => ['BAD_REQUEST', 'La requête est invalide.'],
            401 => ['AUTHENTICATION_REQUIRED', 'Connectez-vous pour continuer.'],
            403 => ['ACTION_FORBIDDEN', 'Cette action ne vous est pas autorisée.'],
            404 => ['RESOURCE_NOT_FOUND', 'Cette ressource est introuvable.'],
            405 => ['METHOD_NOT_ALLOWED', 'Cette méthode ne peut pas être utilisée ici.'],
            409 => ['RESOURCE_CONFLICT', 'La ressource a changé. Rechargez sa dernière version.'],
            413 => ['PAYLOAD_TOO_LARGE', 'Le contenu envoyé est trop volumineux.'],
            415 => ['UNSUPPORTED_MEDIA_TYPE', 'Ce format de contenu ne peut pas être utilisé.'],
            419 => ['CSRF_TOKEN_MISMATCH', 'La session a expiré. Actualisez la page avant de réessayer.'],
            422 => ['VALIDATION_FAILED', 'Vérifiez les champs indiqués.'],
            429 => ['RATE_LIMIT_EXCEEDED', 'Trop de tentatives. Réessayez plus tard.'],
            503 => ['SERVICE_UNAVAILABLE', 'Le service est temporairement indisponible. Réessayez plus tard.'],
            default => $status >= 500
                ? ['INTERNAL_ERROR', 'Une erreur est survenue. Réessayez plus tard.']
                : ['REQUEST_FAILED', 'La requête ne peut pas être traitée.'],
        };

        if ($exception instanceof InactiveAccount) {
            $code = 'ACCOUNT_SUSPENDED';
            $message = 'Ce compte est suspendu. Consultez les informations d’accès au compte pour connaître la procédure de contact.';
        }

        $headers = $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [];
        // Le transport et le contenu de l'erreur restent sous le contrôle du renderer.
        $headers = array_filter($headers, static fn (string $name): bool => in_array(
            strtolower($name), ['retry-after', 'www-authenticate', 'allow', 'x-ratelimit-limit', 'x-ratelimit-remaining'], true,
        ), ARRAY_FILTER_USE_KEY);
        $id = RequestId::get($request);
        $headers['X-Request-ID'] = $id;
        $headers['Cache-Control'] = 'no-store';

        return new JsonResponse([
            'error' => [
                'code' => $code,
                'message' => $message,
                'fields' => (object) match (true) {
                    $exception instanceof ValidationException => $exception->errors(),
                    $exception instanceof RegistrationRejected => $exception->fields,
                    $exception instanceof InvalidCredentials => ['email' => ['Ces identifiants ne permettent pas de vous connecter.']],
                    $exception instanceof InvalidResetToken => ['token' => ['Ce lien ne permet pas de réinitialiser le mot de passe. Demandez un nouveau lien.']],
                    $exception instanceof ProfileUpdateRejected => ['technology_ids' => ['Une technologie sélectionnée est indisponible. Rechargez la liste.']],
                    default => [],
                },
            ],
            'request_id' => $id,
        ], $status, $headers);
    }
}

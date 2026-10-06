<?php

namespace App\Services\Idempotency;

use App\Data\Idempotency\IdempotencyData;
use App\Data\Idempotency\StoredCommandResult;
use App\Exceptions\Idempotency\IdempotencyConflict;
use App\Exceptions\Idempotency\IdempotencyStorageFailed;
use App\Models\User;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;
use LogicException;
use stdClass;

final class IdempotencyService
{
    /**
     * @template T
     *
     * @param  Closure(User): void  $authorize
     * @param  Closure(User): StoredCommandResult  $operation
     * @param  Closure(User, StoredCommandResult): T  $resolve
     * @return T
     */
    public function execute(User $actor, IdempotencyData $data, Closure $authorize, Closure $operation, Closure $resolve): mixed
    {
        if (DB::connection()->getDriverName() !== 'pgsql' || $actor->getConnection() !== DB::connection() || ! $actor->exists) {
            throw new LogicException('Idempotence requise sur la connexion PostgreSQL métier et un acteur persisté.');
        }
        $fingerprint = $data->fingerprint(app(Encrypter::class)->getKey());
        try {
            return DB::transaction(function () use ($actor, $data, $fingerprint, $authorize, $operation, $resolve): mixed {
                // Même acteur : sérialise aussi les deux premières requêtes avant toute ligne d'idempotence.
                $current = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($current)->authorize('participate', User::class);
                $authorize($current);
                $query = DB::table('api_idempotency')->where('user_id', $current->id)->where('route_target', $data->routeTarget)->where('key_hash', $data->key->hash);
                $stored = $query->lockForUpdate()->first();
                if ($stored !== null && CarbonImmutable::parse($stored->expires_at)->isAfter(now()->utc())) {
                    if (! hash_equals($stored->payload_hash, $fingerprint)) {
                        throw new IdempotencyConflict;
                    }

                    // La projection relit et autorise aussi la cible du résultat avant toute réponse.
                    return $resolve($current, $this->decode($stored));
                }
                if ($stored !== null) {
                    $query->delete();
                }
                $result = $operation($current);
                $resolved = $resolve($current, $result);
                $createdAt = now()->utc();
                DB::table('api_idempotency')->insert([
                    'id' => (string) Str::uuid(), 'user_id' => $current->id, 'route_target' => $data->routeTarget,
                    'key_hash' => $data->key->hash, 'payload_hash' => $fingerprint, 'status' => $result->status,
                    'response' => json_encode($result->response(), JSON_THROW_ON_ERROR),
                    'created_at' => $createdAt, 'expires_at' => $createdAt->copy()->addHours(24),
                ]);

                return $resolved;
            });
        } catch (QueryException|JsonException) {
            // Aucun corps HTTP, clé client, résultat privé ou binding SQL dans l'exception.
            throw new IdempotencyStorageFailed('Échec du stockage de l’intention.');
        }
    }

    public function pruneExpired(): int
    {
        $cutoff = now()->utc();
        $ids = DB::table('api_idempotency')->where('expires_at', '<=', $cutoff)->orderBy('expires_at')->orderBy('id')->limit(1000)->pluck('id');

        return DB::table('api_idempotency')->whereIn('id', $ids)->where('expires_at', '<=', $cutoff)->delete();
    }

    private function decode(stdClass $stored): StoredCommandResult
    {
        try {
            $response = json_decode($stored->response, true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($response) || array_diff(array_keys($response), ['references', 'version']) !== []
                || ! isset($response['references']) || ! is_array($response['references']) || ! array_key_exists('version', $response)
                || ($response['version'] !== null && ! is_int($response['version']))) {
                throw new InvalidArgumentException;
            }

            return new StoredCommandResult($stored->status, $response['references'], $response['version']);
        } catch (InvalidArgumentException|JsonException) {
            throw new IdempotencyStorageFailed('Résultat mémorisé invalide.');
        }
    }
}

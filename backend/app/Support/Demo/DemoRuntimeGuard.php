<?php

namespace App\Support\Demo;

use App\Models\Demo\DemoConnection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/** Configuration B2 et permissions réelles vérifiées avant toute écriture. */
final class DemoRuntimeGuard
{
    /** @return array{origin:string,api:string} */
    public function configuration(): array
    {
        $parent = config('demo.haas_cookie_parent');
        if (! is_string($parent)) {
            $this->refuse();
        }
        $parent = strtolower(ltrim($parent, '.'));
        if (! $this->domain($parent)) {
            $this->refuse();
        }
        $origin = $this->origin(config('demo.frontend_origin'));
        $api = $this->origin(config('app.url'));
        foreach ([$origin, $api] as $url) {
            $host = (string) parse_url($url, PHP_URL_HOST);
            if ($host === $parent || str_ends_with($host, '.'.$parent)) {
                $this->refuse();
            }
        }
        if (! in_array(config('cache.default'), config('app.env') === 'testing' ? ['array', 'database'] : ['database'], true)
            || config('cache.stores.database.connection') !== DemoConnection::NAME
            || config('cache.stores.database.lock_connection') !== DemoConnection::NAME
            || config('cache.stores.database.table') !== 'cache' || config('cache.stores.database.lock_table') !== 'cache_locks') {
            $this->refuse();
        }
        config(['demo.frontend_origin' => $origin, 'app.url' => $api, 'cors.allowed_origins' => [$origin]]);

        return ['origin' => $origin, 'api' => $api];
    }

    public function database(): void
    {
        $this->configuration();
        $configuration = config('database.connections.demo');
        $haas = config('demo.haas_database');
        if (! is_array($configuration) || ($configuration['driver'] ?? null) !== 'pgsql'
            || isset($configuration['url']) || isset($configuration['read']) || isset($configuration['write'])
            || ! is_string($configuration['database'] ?? null) || ! preg_match('/^haas_demo(?:_[a-z0-9]+)*$/D', $configuration['database'])
            || ! is_string($configuration['username'] ?? null) || ! preg_match('/^haas_demo(?:_[a-z0-9]+)*$/D', $configuration['username'])
            || ! is_string($haas) || ! preg_match('/^haas_[a-z0-9_]+$/D', $haas) || $haas === $configuration['database']) {
            $this->refuse();
        }
        try {
            $row = DB::connection(DemoConnection::NAME)->selectOne(<<<'SQL'
SELECT current_database() AS database, current_user AS username,
       current_setting('transaction_isolation') AS isolation,
       r.rolsuper, r.rolcreatedb, r.rolcreaterole, r.rolreplication, r.rolbypassrls,
       EXISTS (SELECT 1 FROM pg_auth_members WHERE member = r.oid) AS membership,
       (SELECT has_database_privilege(current_user, d.oid, 'CONNECT') FROM pg_database d WHERE d.datname = ?) AS haas_connect
FROM pg_roles r WHERE r.rolname = current_user
SQL, [$haas]);
        } catch (Throwable) {
            $this->refuse();
        }
        if ($row === null || $row->isolation !== 'read committed' || $row->database !== $configuration['database'] || $row->username !== $configuration['username']
            || $row->haas_connect !== false) {
            $this->refuse();
        }
        foreach (['rolsuper', 'rolcreatedb', 'rolcreaterole', 'rolreplication', 'rolbypassrls', 'membership'] as $flag) {
            if ($row->$flag !== false) {
                $this->refuse();
            }
        }
    }

    private function origin(mixed $value): string
    {
        $parts = is_string($value) ? parse_url($value) : false;
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])
            || array_diff(array_keys($parts), ['scheme', 'host', 'port']) !== []
            || ! in_array(strtolower($parts['scheme']), ['https', 'http'], true) || ! $this->domain(strtolower($parts['host']))) {
            $this->refuse();
        }
        $scheme = strtolower($parts['scheme']);
        if ($scheme !== 'https' && ! in_array(config('app.env'), ['testing', 'local'], true)) {
            $this->refuse();
        }
        $port = $parts['port'] ?? null;

        return $scheme.'://'.strtolower($parts['host']).($port !== null && ! ($scheme === 'https' && $port === 443) && ! ($scheme === 'http' && $port === 80) ? ':'.$port : '');
    }

    private function domain(string $value): bool
    {
        return strlen($value) <= 253 && preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z](?:[a-z0-9-]{0,61}[a-z0-9])?$/D', $value) === 1;
    }

    private function refuse(): never
    {
        throw new HttpException(503, 'Configuration B2 isolée indisponible.');
    }
}

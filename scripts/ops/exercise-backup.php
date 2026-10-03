<?php

// Exercice local B42 seulement. Aucun accès à une base applicative ou distante.
declare(strict_types=1);

use Symfony\Component\Process\Process;

require __DIR__.'/../../backend/vendor/autoload.php';

const MAX_ARCHIVE_BYTES = 67108864;
const MAGIC = 'HAASBK01';

// Les erreurs de fichiers ne doivent pas afficher de chemins ni de contexte privé.
set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

/** @param list<string> $arguments */
function postgres(string $tool, array $arguments, ?string $input = null): string
{
    $directory = getenv('HAAS_PG_BIN');
    if (! is_string($directory) || realpath($directory) === false) {
        throw new RuntimeException('HAAS_PG_BIN doit désigner une installation PostgreSQL contrôlée.');
    }
    $binary = realpath($directory).DIRECTORY_SEPARATOR.$tool.(PHP_OS_FAMILY === 'Windows' ? '.exe' : '');
    if (! is_file($binary)) {
        throw new RuntimeException('Exécutable PostgreSQL absent.');
    }
    $process = new Process([$binary, '-h', '127.0.0.1', '-p', (string) getenv('HAAS_OPS_PORT'), '-U', 'haas_test', ...$arguments], null, ['PGPASSWORD' => false, 'PGOPTIONS' => false]);
    $process->setTimeout(120);
    $process->setInput($input);
    $length = 0;
    $process->run(function (string $type, string $buffer) use (&$length): void {
        $length += strlen($buffer);
        if ($length > MAX_ARCHIVE_BYTES) {
            throw new RuntimeException('Archive trop grande pour cet exercice local borné.');
        }
    });
    if (! $process->isSuccessful()) {
        throw new RuntimeException('Échec PostgreSQL ; aucun détail SQL ni donnée affiché.');
    }

    return $process->getOutput();
}

try {
    if ($argc !== 5 || ! in_array($argv[1], ['backup', 'restore'], true)) {
        throw new RuntimeException('Usage : php exercise-backup.php backup|restore haas_nom_test archive keyfile');
    }
    [, $operation, $database, $archive, $keyFile] = $argv;
    if (! preg_match('/\Ahaas_[a-z0-9_]+_test\z/', $database) || ! preg_match('/\A[0-9]{4,5}\z/', (string) getenv('HAAS_OPS_PORT'))
        || (int) getenv('HAAS_OPS_PORT') > 65535 || $archive === $keyFile || is_link($archive) || is_link($keyFile)) {
        throw new RuntimeException('Cible locale de test explicite requise ; aucun lien symbolique accepté.');
    }
    $key = file_get_contents($keyFile);
    if ($key === false || strlen($key) !== 32) {
        throw new RuntimeException('Fichier de clé séparé de 32 octets requis. Ne jamais utiliser APP_KEY.');
    }
    if ($operation === 'backup') {
        $dump = postgres('pg_dump', ['-d', $database, '--format=custom', '--no-owner', '--no-acl']);
        $payload = json_encode(['format' => 1, 'database' => $database, 'created_at' => gmdate('c'), 'sha256' => hash('sha256', $dump), 'dump' => base64_encode($dump)], JSON_THROW_ON_ERROR);
        $nonce = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($payload, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, MAGIC, 16);
        if ($ciphertext === false || strlen($ciphertext) > MAX_ARCHIVE_BYTES) {
            throw new RuntimeException('Chiffrement impossible ou archive trop grande.');
        }
        umask(0077);
        $file = fopen($archive, 'xb');
        if ($file === false) {
            throw new RuntimeException('Le fichier de sortie doit être nouveau.');
        }
        try {
            $bytes = MAGIC.$nonce.$tag.$ciphertext;
            $offset = 0;
            while ($offset < strlen($bytes)) {
                $written = fwrite($file, substr($bytes, $offset));
                if ($written === false || $written === 0) {
                    throw new RuntimeException('Écriture incomplète de la sauvegarde.');
                }
                $offset += $written;
            }
        } finally {
            fclose($file);
        }
        echo "Sauvegarde locale chiffrée créée.\n";
    } else {
        if (! str_ends_with($database, '_restore_test') || ! is_file($archive) || filesize($archive) > MAX_ARCHIVE_BYTES + 36) {
            throw new RuntimeException('Restauration limitée à une base locale dédiée *_restore_test et une archive bornée.');
        }
        $bytes = file_get_contents($archive);
        if ($bytes === false || strlen($bytes) < 37 || substr($bytes, 0, 8) !== MAGIC) {
            throw new RuntimeException('Format de sauvegarde invalide.');
        }
        // Authentifier intégralement avant toute connexion de restauration ou écriture SQL.
        $payload = openssl_decrypt(substr($bytes, 36), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($bytes, 8, 12), substr($bytes, 20, 16), MAGIC);
        if ($payload === false) {
            throw new RuntimeException('Archive altérée ou clé incorrecte ; aucune restauration.');
        }
        $data = json_decode($payload, true, 8, JSON_THROW_ON_ERROR);
        $dump = is_array($data) && is_string($data['dump'] ?? null) ? base64_decode($data['dump'], true) : false;
        if (($data['format'] ?? null) !== 1 || $dump === false || ! is_string($data['sha256'] ?? null) || ! hash_equals($data['sha256'], hash('sha256', $dump))) {
            throw new RuntimeException('Contenu authentifié invalide.');
        }
        $tables = trim(postgres('psql', ['-X', '-A', '-t', '-d', $database, '-c', "SELECT count(*) FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace WHERE n.nspname NOT IN ('pg_catalog','information_schema') AND n.nspname NOT LIKE 'pg_toast%' AND c.relkind IN ('r','p','v','m','S','f');"]));
        if ($tables !== '0') {
            throw new RuntimeException('La base de restauration doit être vide ; aucun nettoyage automatique.');
        }
        postgres('pg_restore', ['-d', $database, '--no-owner', '--no-acl', '--single-transaction', '--exit-on-error'], $dump);
        echo "Restauration locale dédiée terminée.\n";
    }
    unset($key, $dump, $payload, $bytes, $data);
} catch (Throwable $exception) {
    // Message borné, sans commande, environnement, trace ou contenu de l'archive.
    fwrite(STDERR, "Exercice refusé ou échoué. Vérifiez la cible dédiée, la clé, l’archive et PostgreSQL.\n");
    exit(1);
}

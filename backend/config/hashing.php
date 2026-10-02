<?php

return [
    // B06 : conserver les 128 caractères Unicode, sans limite bcrypt de 72 octets.
    'driver' => 'argon2id',
    'argon' => ['memory' => 65536, 'threads' => 1, 'time' => 4, 'verify' => true],
    'rehash_on_login' => true,
];

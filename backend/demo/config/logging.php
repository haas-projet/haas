<?php

use Monolog\Handler\NullHandler;

return [
    'default' => 'null',
    // Aucune trace de SQL, corps ou configuration ne quitte la démonstration.
    'channels' => ['null' => ['driver' => 'monolog', 'handler' => NullHandler::class]],
];

<?php

declare(strict_types=1);

return [
    'table' => 'settings',

    'encryption' => [
        'enabled' => false,
    ],

    'cache' => [
        'enabled' => true,
        'ttl' => 3600,
        'key' => 'settings',
    ],
];

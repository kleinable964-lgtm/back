<?php

use Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy;

return [
    'api_path' => 'api/v1',
    'info' => [
        'version' => '1.0.0',
        'description' => 'Coworker accounts are created by administrators. Bootstrap the first admin with php artisan crm:create-admin email@example.com. Log in and copy access_token into Authorize. Active admins manage records; other active coworkers read directories and their assigned clients. Coworker IDs are public UUIDs. Clients and affiliates use integer IDs.',
    ],
    'servers' => ['Current backend' => '/api/v1'],
    'security_strategy' => MiddlewareAuthSecurityStrategy::class,
    'dev_tools' => ['enabled' => false],
];

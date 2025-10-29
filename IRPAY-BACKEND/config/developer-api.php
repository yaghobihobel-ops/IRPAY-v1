<?php

return [
    'scopes' => [
        \App\Constants\PaymentGatewayConst::ENV_SANDBOX,
        \App\Constants\PaymentGatewayConst::ENV_PRODUCTION,
    ],
    'secret_length' => 64,
    'secret_retention_days' => env('DEVELOPER_API_SECRET_RETENTION_DAYS', 90),
];


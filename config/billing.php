<?php

return [
    'trial_days' => (int) env('VELORA_BILLING_TRIAL_DAYS', 14),

    'payment_gateways' => [
        'platform' => [],
        'tenant' => [],
    ],

    'default_tax_bps' => 0,
];
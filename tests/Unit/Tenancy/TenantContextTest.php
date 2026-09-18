<?php

use App\Domain\Tenancy\TenantContext;
use App\Models\Tenant;
use LogicException;

test('tenant context stores and clears the current tenant', function () {
    $context = new TenantContext();
    $tenant = new Tenant(['name' => 'Context Tenant']);
    $context->set($tenant);

    expect($context->check())->toBeTrue()
        ->and($context->current())->toBe($tenant);

    $context->clear();

    expect($context->check())->toBeFalse()
        ->and(fn () => $context->current())->toThrow(LogicException::class);
});
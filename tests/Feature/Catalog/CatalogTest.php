<?php

use App\Application\Catalog\CatalogManager;
use App\Application\Catalog\CatalogPricingResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('catalog manager creates modules and features with stable catalog keys', function () {
    $manager = app(CatalogManager::class);

    $module = $manager->createModule([
        'key' => 'booking',
        'name' => 'Booking',
        'status' => 'active',
    ]);

    $feature = $manager->createFeature($module, [
        'key' => 'booking.services',
        'name' => 'Services',
        'billing_mode' => 'flat',
        'is_required' => true,
    ]);

    expect($module->status)->toBe('active')
        ->and($feature->module_id)->toBe($module->getKey())
        ->and($feature->is_required)->toBeTrue()
        ->and($feature->is_individually_purchasable)->toBeTrue();
});

test('catalog statuses and keys are validated before persistence', function () {
    $manager = app(CatalogManager::class);

    expect(fn () => $manager->createModule([
        'key' => 'Invalid Key',
        'name' => 'Invalid',
    ]))->toThrow(DomainException::class);

    expect(fn () => $manager->createModule([
        'key' => 'catalog.invalid',
        'status' => 'unknown',
    ]))->toThrow(DomainException::class);
});

test('dependency manager rejects duplicate and circular dependencies', function () {
    $manager = app(CatalogManager::class);

    $module = $manager->createModule(['key' => 'crm', 'name' => 'CRM']);
    $pipeline = $manager->createFeature($module, ['key' => 'crm.pipeline', 'name' => 'Pipeline']);
    $deals = $manager->createFeature($module, ['key' => 'crm.deals', 'name' => 'Deals']);
    $activities = $manager->createFeature($module, ['key' => 'crm.activities', 'name' => 'Activities']);

    $manager->addDependency($deals, $pipeline);
    $manager->addDependency($activities, $deals);

    expect(fn () => $manager->addDependency($pipeline, $activities))
        ->toThrow(DomainException::class);

    expect(fn () => $manager->addDependency($deals, $pipeline))
        ->toThrow(DomainException::class);
});

test('bundles contain modules and features and enforce bundle discount bounds', function () {
    $manager = app(CatalogManager::class);

    $module = $manager->createModule(['key' => 'booking', 'name' => 'Booking']);
    $feature = $manager->createFeature($module, [
        'key' => 'booking.appointments',
        'name' => 'Appointments',
    ]);

    $bundle = $manager->createBundle([
        'key' => 'booking-pro',
        'name' => 'Booking Pro',
        'discount_bps' => 1500,
    ]);

    $manager->addModuleToBundle($bundle, $module);
    $manager->addFeatureToBundle($bundle, $feature);

    expect($bundle->fresh()->modules->pluck('key')->all())->toEqual(['booking'])
        ->and($bundle->fresh()->features->pluck('key')->all())->toEqual(['booking.appointments']);

    expect(fn () => $manager->createBundle([
        'key' => 'invalid-discount',
        'name' => 'Invalid Discount',
        'discount_bps' => 10001,
    ]))->toThrow(DomainException::class);
});

test('catalog pricing stores integer minor units and resolves country-specific prices first', function () {
    $manager = app(CatalogManager::class);
    $resolver = app(CatalogPricingResolver::class);

    $module = $manager->createModule([
        'key' => 'booking',
        'name' => 'Booking',
        'status' => 'active',
    ]);

    $global = $manager->addPrice($module, [
        'billing_cycle' => 'monthly',
        'currency' => 'USD',
        'amount_minor' => 10000,
        'effective_from' => now()->subDay(),
    ]);

    $us = $manager->addPrice($module, [
        'billing_cycle' => 'monthly',
        'currency' => 'USD',
        'country_code' => 'US',
        'amount_minor' => 9000,
        'effective_from' => now()->subDay(),
    ]);

    expect($global->amount_minor)->toBe(10000)
        ->and($resolver->current($module, 'monthly', 'USD', 'US')?->getKey())->toBe($us->getKey())
        ->and($resolver->current($module, 'monthly', 'USD', 'DE')?->getKey())->toBe($global->getKey());
});

test('core modules cannot be priced', function () {
    $manager = app(CatalogManager::class);

    $core = $manager->createModule([
        'key' => 'core',
        'name' => 'Platform Core',
        'is_core' => true,
    ]);

    expect(fn () => $manager->addPrice($core, [
        'billing_cycle' => 'monthly',
        'currency' => 'USD',
        'amount_minor' => 100,
    ]))->toThrow(DomainException::class);
});

test('catalog prices cannot overlap within the same pricing dimension', function () {
    $manager = app(CatalogManager::class);

    $module = $manager->createModule([
        'key' => 'booking',
        'name' => 'Booking',
    ]);

    $manager->addPrice($module, [
        'billing_cycle' => 'monthly',
        'currency' => 'USD',
        'amount_minor' => 10000,
        'effective_from' => now()->subDay(),
    ]);

    expect(fn () => $manager->addPrice($module, [
        'billing_cycle' => 'monthly',
        'currency' => 'USD',
        'amount_minor' => 12000,
        'effective_from' => now(),
    ]))->toThrow(DomainException::class);
});

test('catalog manager can change module feature and bundle lifecycle statuses', function () {
    $manager = app(CatalogManager::class);

    $module = $manager->createModule(['key' => 'booking', 'name' => 'Booking']);
    $feature = $manager->createFeature($module, ['key' => 'booking.queue', 'name' => 'Queue']);
    $bundle = $manager->createBundle(['key' => 'booking-pro', 'name' => 'Booking Pro']);

    $manager->setModuleStatus($module, 'active');
    $manager->setFeatureStatus($feature, 'coming_soon');
    $manager->setBundleStatus($bundle, 'active');

    expect($module->fresh()->status)->toBe('active')
        ->and($feature->fresh()->status)->toBe('coming_soon')
        ->and($bundle->fresh()->status)->toBe('active');
});

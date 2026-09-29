<?php

use HireHq\ConsoleIntegration\Contracts\EntitlementRepository;
use HireHq\ConsoleIntegration\Data\EntitlementSnapshot;
use HireHq\ConsoleIntegration\Data\ProductContext;
use HireHq\ConsoleIntegration\Events\EntitlementUpdated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

it('accepts a signed snapshot, permits access and treats a retry as unchanged', function () {
    $this->freezeTime();
    $this->resolveCompany();
    Event::fake([EntitlementUpdated::class]);
    $payload = $this->payload();
    $this->sendSnapshot($payload)->assertOk()->assertJson(['changed' => true, 'revision' => 1]);
    $this->sendSnapshot($payload)->assertOk()->assertJson(['changed' => false]);
    $this->assertDatabaseHas('console_entitlements', ['company_id' => 'company-1', 'revision' => 1, 'status' => 'active']);
    $this->getJson('/product')->assertOk()->assertJson(['access' => true]);
    Event::assertDispatchedTimes(EntitlementUpdated::class, 1);
    Event::assertDispatched(EntitlementUpdated::class, fn ($event) => $event->snapshot->context->companyId === 'company-1');
});

it('enforces entitlement state on every protected request', function (string $status, ?int $trialOffset, ?int $accessOffset, int $leaseOffset, bool $allowed) {
    $this->freezeTime();
    $this->resolveCompany();
    $this->sendSnapshot($this->payload(['status' => $status,
        'trial_ends_at' => $trialOffset === null ? null : now()->timestamp + $trialOffset,
        'access_ends_at' => $accessOffset === null ? null : now()->timestamp + $accessOffset,
        'valid_until' => now()->timestamp + $leaseOffset,
    ]))->assertOk();
    $response = $this->getJson('/product');
    if ($allowed) {
        $response->assertOk();
    } else {
        $response->assertForbidden()->assertJsonPath('code', 'product_unavailable');
    }
})->with([
    'active' => ['active', null, null, 300, true],
    'trial' => ['trialing', 60, null, 300, true],
    'trial boundary' => ['trialing', 0, null, 300, false],
    'expired trial' => ['trialing', -1, null, 300, false],
    'scheduled cancellation' => ['active', null, 60, 300, true],
    'effective cancellation' => ['active', null, 0, 300, false],
    'cancelled with misleading future end' => ['canceled', null, 60, 300, false],
    'suspended' => ['suspended', null, null, 300, false],
    'expired' => ['expired', null, null, 300, false],
    'past due' => ['past_due', null, null, 300, false],
    'incomplete' => ['incomplete', null, null, 300, false],
    'stale' => ['active', null, null, 0, false],
]);

it('denies an existing client after cancellation and rejects old or conflicting grants', function () {
    $this->freezeTime();
    $this->resolveCompany();
    $old = $this->payload();
    $this->sendSnapshot($old)->assertOk();
    $this->getJson('/product')->assertOk();
    $this->sendSnapshot($this->payload(['revision' => 2, 'status' => 'canceled']))->assertOk();
    $this->sendSnapshot($old)->assertStatus(409);
    $this->sendSnapshot($this->payload(['revision' => 2]))->assertStatus(409);
    $this->getJson('/product')->assertForbidden();
    $this->postJson('/product')->assertForbidden();
    $this->assertDatabaseHas('console_entitlements', ['revision' => 2, 'status' => 'canceled']);
});

it('denies access once a cached lease expires without another update', function () {
    $this->freezeTime();
    $this->resolveCompany();
    $this->sendSnapshot($this->payload())->assertOk();
    $this->getJson('/product')->assertOk();
    $this->travel(300)->seconds();
    $this->getJson('/product')->assertForbidden();
});

it('does not use another company product or environment entitlement', function (string $company, string $product, string $environment) {
    $this->freezeTime();
    $this->sendSnapshot($this->payload())->assertOk();
    $this->resolveCompany($company, $product, $environment);
    $this->getJson('/product')->assertForbidden();
})->with([
    ['company-2', 'spark', 'sandbox'], ['company-1', 'connect', 'sandbox'], ['company-1', 'spark', 'production'],
]);

it('fails closed with no trusted context resolver and ignores a supplied company header', function () {
    $this->freezeTime();
    $this->sendSnapshot($this->payload())->assertOk();
    $this->withHeader('X-Company-Id', 'company-1')->getJson('/product')->assertForbidden();
});

it('shows an overridable generic HTML warning without billing details when no entitlement exists', function () {
    $this->resolveCompany();
    $this->get('/product')->assertForbidden()->assertViewIs('console-integration::unavailable')
        ->assertSee('Please contact your administrator.')->assertHeader('Cache-Control', 'no-store, private');
});

it('only emits the update event after commit and drops it on rollback', function () {
    $this->freezeTime();
    $events = [];
    Event::listen(EntitlementUpdated::class, function ($event) use (&$events): void {
        $events[] = $event;
    });
    $repository = app(EntitlementRepository::class);
    DB::beginTransaction();
    $repository->store(new EntitlementSnapshot(new ProductContext('sandbox', 'spark', 'company-1'), 1, 'active', now()->timestamp + 300));
    expect($events)->toBeEmpty();
    DB::rollBack();
    expect($events)->toBeEmpty();
    $this->assertDatabaseCount('console_entitlements', 0);
    DB::beginTransaction();
    $repository->store(new EntitlementSnapshot(new ProductContext('sandbox', 'spark', 'company-1'), 1, 'active', now()->timestamp + 300));
    DB::commit();
    expect($events)->toHaveCount(1);
});

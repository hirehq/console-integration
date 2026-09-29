<?php

use HireHq\ConsoleIntegration\Events\EntitlementUpdated;
use HireHq\ConsoleIntegration\Support\MessageSignature;
use Illuminate\Support\Facades\Event;

it('rejects invalid signatures before changing any entitlement', function (array $headers, int $offset, string $path) {
    $this->freezeTime();
    $this->sendSnapshot($this->payload(), $headers, now()->timestamp + $offset, $path)->assertUnauthorized();
    $this->assertDatabaseCount('console_entitlements', 0);
})->with([
    'bad signature' => [['HTTP_X_CONSOLE_SIGNATURE' => str_repeat('0', 64)], 0, '/hire-hq-console/entitlements'],
    'missing signature' => [['HTTP_X_CONSOLE_SIGNATURE' => ''], 0, '/hire-hq-console/entitlements'],
    'unknown key' => [['HTTP_X_CONSOLE_KEY_ID' => 'unknown'], 0, '/hire-hq-console/entitlements'],
    'old timestamp' => [[], -301, '/hire-hq-console/entitlements'],
    'future timestamp' => [[], 301, '/hire-hq-console/entitlements'],
    'wrong path' => [[], 0, '/other'],
    'bad timestamp' => [['HTTP_X_CONSOLE_TIMESTAMP' => 'nonsense'], 0, '/hire-hq-console/entitlements'],
]);

it('rejects tampering with a previously signed body', function () {
    $this->freezeTime();
    $payload = $this->payload();
    $signature = (new MessageSignature)->sign(self::SECRET, 'primary', now()->timestamp, 'POST', '/hire-hq-console/entitlements', json_encode($payload));
    $this->sendSnapshot([...$payload, 'company_id' => 'company-2'], ['HTTP_X_CONSOLE_SIGNATURE' => $signature])->assertUnauthorized();
    $this->assertDatabaseCount('console_entitlements', 0);
});

it('rejects another integration scope despite a valid signature', function (array $overrides) {
    $this->freezeTime();
    $this->sendSnapshot($this->payload($overrides))->assertForbidden();
    $this->assertDatabaseCount('console_entitlements', 0);
})->with([[['product' => 'connect']], [['environment' => 'production']]]);

it('rejects invalid payloads without storing or dispatching an update', function (array $overrides) {
    $this->freezeTime();
    Event::fake([EntitlementUpdated::class]);
    $this->sendSnapshot($this->payload($overrides))->assertUnprocessable()->assertJsonPath('message', 'Invalid entitlement payload.');
    $this->assertDatabaseCount('console_entitlements', 0);
    Event::assertNotDispatched(EntitlementUpdated::class);
})->with([
    [['schema_version' => 2]], [['company_id' => '']], [['revision' => 0]], [['revision' => 'bad']],
    [['status' => 'unknown']], [['status' => 'trialing']], [['password' => 'must-not-be-accepted']],
]);

it('rejects a lease beyond its permitted duration', function () {
    $this->freezeTime();
    $this->sendSnapshot($this->payload(['valid_until' => now()->timestamp + 301]))->assertUnprocessable()
        ->assertJsonPath('message', 'Entitlement lease exceeds the configured limit.');
    $this->assertDatabaseCount('console_entitlements', 0);
});

it('fails closed when signing configuration is missing', function () {
    $this->freezeTime();
    config(['console-integration.signing_keys' => []]);
    $this->sendSnapshot($this->payload())->assertUnauthorized();
});

it('rejects oversized signed messages', function () {
    $this->freezeTime();
    $this->sendSnapshot($this->payload(['extra' => str_repeat('x', 17000)]))->assertStatus(413);
    $this->assertDatabaseCount('console_entitlements', 0);
});

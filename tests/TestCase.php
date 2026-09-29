<?php

namespace HireHq\ConsoleIntegration\Tests;

use HireHq\ConsoleIntegration\ConsoleIntegrationServiceProvider;
use HireHq\ConsoleIntegration\Contracts\ProductContextResolver;
use HireHq\ConsoleIntegration\Data\ProductContext;
use HireHq\ConsoleIntegration\Support\MessageSignature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected const SECRET = 'test-only-secret-with-more-than-thirty-two-bytes';

    protected function getPackageProviders($app): array
    {
        return [ConsoleIntegrationServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('console-integration.product', 'spark');
        $app['config']->set('console-integration.environment', 'sandbox');
        $app['config']->set('console-integration.routes_enabled', true);
        $app['config']->set('console-integration.signing_keys', ['primary' => self::SECRET]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $migration = require __DIR__.'/../database/migrations/2026_09_27_000000_create_console_entitlements_table.php';
        $migration->up();
    }

    protected function defineRoutes($router): void
    {
        Route::match(['GET', 'POST'], '/product', fn () => response()->json(['access' => true]))->middleware('console.entitlement');
    }

    protected function resolveCompany(string $companyId = 'company-1', string $product = 'spark', string $environment = 'sandbox'): void
    {
        $this->app->instance(ProductContextResolver::class, new class($companyId, $product, $environment) implements ProductContextResolver
        {
            public function __construct(private string $companyId, private string $product, private string $environment) {}

            public function resolve(Request $request): ?ProductContext
            {
                return new ProductContext($this->environment, $this->product, $this->companyId);
            }
        });
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    protected function payload(array $overrides = []): array
    {
        return array_replace(['schema_version' => 1, 'environment' => 'sandbox', 'product' => 'spark',
            'company_id' => 'company-1', 'revision' => 1, 'status' => 'active', 'valid_until' => now()->timestamp + 300], $overrides);
    }

    /** @param array<string, mixed> $payload @param array<string, mixed> $headers */
    protected function sendSnapshot(array $payload, array $headers = [], ?int $timestamp = null, string $signedPath = '/hire-hq-console/entitlements'): TestResponse
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp ??= now()->timestamp;
        $signature = (new MessageSignature)->sign(self::SECRET, 'primary', $timestamp, 'POST', $signedPath, $body);

        return $this->call('POST', '/hire-hq-console/entitlements', [], [], [], array_replace([
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_CONSOLE_KEY_ID' => 'primary', 'HTTP_X_CONSOLE_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_CONSOLE_SIGNATURE' => $signature,
        ], $headers), $body);
    }
}

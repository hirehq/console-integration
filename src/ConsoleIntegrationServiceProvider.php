<?php

namespace HireHq\ConsoleIntegration;

use HireHq\ConsoleIntegration\Contracts\EntitlementRepository;
use HireHq\ConsoleIntegration\Contracts\ProductContextResolver;
use HireHq\ConsoleIntegration\Http\Controllers\ReceiveEntitlement;
use HireHq\ConsoleIntegration\Http\Controllers\ReceiveProvisioning;
use HireHq\ConsoleIntegration\Http\Middleware\EnsureProductEntitlement;
use HireHq\ConsoleIntegration\Http\Middleware\VerifyConsoleSignature;
use HireHq\ConsoleIntegration\Repositories\DatabaseEntitlementRepository;
use HireHq\ConsoleIntegration\Support\MissingProductContextResolver;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

final class ConsoleIntegrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/console-integration.php', 'console-integration');
        $this->app->bindIf(EntitlementRepository::class, DatabaseEntitlementRepository::class);
        $this->app->bindIf(ProductContextResolver::class, MissingProductContextResolver::class);
    }

    public function boot(Router $router): void
    {
        $router->aliasMiddleware('console.entitlement', EnsureProductEntitlement::class);
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'console-integration');
        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../config/console-integration.php' => config_path('console-integration.php')], 'console-integration-config');
            $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/console-integration')], 'console-integration-views');
            $this->publishesMigrations([__DIR__.'/../database/migrations' => database_path('migrations')], 'console-integration-migrations');
        }
        if (! $this->app->routesAreCached() && $this->app['config']->get('console-integration.routes_enabled')) {
            $router->post(trim($this->app['config']->get('console-integration.route_prefix'), '/').'/provisioning', ReceiveProvisioning::class)
                ->middleware([VerifyConsoleSignature::class, 'throttle:120,1'])->name('console-integration.provisioning');
            $router->post(trim($this->app['config']->get('console-integration.route_prefix'), '/').'/entitlements', ReceiveEntitlement::class)
                ->middleware([VerifyConsoleSignature::class, 'throttle:120,1'])->name('console-integration.entitlements');
        }
    }
}

<?php

use HireHq\ConsoleIntegration\ConsoleIntegrationServiceProvider;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

it('publishes a discoverable migration which installs the entitlement store', function () {
    $path = sys_get_temp_dir().'/console-integration-install-'.bin2hex(random_bytes(8));
    $this->app->useDatabasePath($path);
    $this->app->getProvider(ConsoleIntegrationServiceProvider::class)->boot($this->app['router']);
    try {
        Schema::drop('console_entitlements');
        $this->artisan('vendor:publish', ['--tag' => 'console-integration-migrations'])->assertExitCode(0);
        $this->artisan('migrate', ['--path' => $path.'/migrations', '--realpath' => true, '--force' => true])->assertExitCode(0);
        $this->freezeTime();
        $this->sendSnapshot($this->payload())->assertOk();
        $this->assertDatabaseHas('console_entitlements', ['company_id' => 'company-1']);
    } finally {
        File::deleteDirectory($path);
    }
});

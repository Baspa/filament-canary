<?php

namespace Baspa\FilamentCanary\Tests;

use Baspa\FilamentCanary\FilamentCanaryServiceProvider;
use Baspa\FilamentCanary\Tests\Fixtures\Filament\AdminPanelProvider;
use Baspa\FilamentCanary\Tests\Fixtures\Models\User;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected $enablesPackageDiscoveries = true;

    protected function getPackageProviders($app)
    {
        return [
            FilamentCanaryServiceProvider::class,
            AdminPanelProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('auth.providers.users.model', User::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Fixtures/Database/migrations');
    }
}

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
            ...$this->fixturePanelProviders(),
        ];
    }

    /**
     * Every *PanelProvider in Fixtures/Filament/Panels is registered, so a test can add
     * a panel by adding a file instead of editing this class.
     *
     * @return list<class-string>
     */
    protected function fixturePanelProviders(): array
    {
        $files = glob(__DIR__.'/Fixtures/Filament/Panels/*PanelProvider.php') ?: [];

        return array_map(
            fn (string $file) => __NAMESPACE__.'\\Fixtures\\Filament\\Panels\\'.basename($file, '.php'),
            $files,
        );
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

        // Fixture panels are opt-in: a test that sweeps one sets panels.only itself.
        $app['config']->set('filament-canary.panels.only', ['admin']);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Fixtures/Database/migrations');
    }
}

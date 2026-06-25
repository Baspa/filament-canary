<?php

namespace Baspa\FilamentCanary;

use Baspa\FilamentCanary\Commands\CheckCommand;
use Baspa\FilamentCanary\Commands\InstallCommand;
use Baspa\FilamentCanary\Sweep\KernelRequester;
use Baspa\FilamentCanary\Sweep\Requester;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentCanaryServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-canary')
            ->hasConfigFile()
            ->hasCommands([
                CheckCommand::class,
                InstallCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->bind(Requester::class, KernelRequester::class);
    }
}

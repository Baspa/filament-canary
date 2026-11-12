<?php

it('runs canary:check and succeeds when there are no hard failures', function () {
    $this->artisan('canary:check')->assertExitCode(0);
});

it('fails canary:check with --strict when pages need authorization', function () {
    // The fixture panel has a resource the default user cannot access (needs-auth),
    // which --strict promotes to a failure.
    $this->artisan('canary:check --strict')->assertExitCode(1);
});

it('runs canary:install and prints a snippet when declined', function () {
    $this->artisan('canary:install')
        ->expectsConfirmation('Write these proposals to config/filament-canary.php?', 'no')
        ->assertExitCode(0);
});

it('runs canary:check after canarySweep() in the same test', function () {
    $this->canarySweep();

    $this->artisan('canary:check')->assertExitCode(0);
});

it('prints the environment refusal and fails canary:check', function () {
    app()->detectEnvironment(fn () => 'staging');

    $this->artisan('canary:check')
        ->expectsOutputToContain('[staging]')
        ->assertExitCode(1);
});

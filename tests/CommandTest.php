<?php

it('runs canary:check and succeeds when there are no hard failures', function () {
    $this->artisan('canary:check')->assertExitCode(0);
});

it('fails canary:check with --strict when pages need authorization', function () {
    // The fixture panel has a resource the default user cannot access (needs-auth),
    // which --strict promotes to a failure.
    $this->artisan('canary:check --strict')->assertExitCode(1);
});

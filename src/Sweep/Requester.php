<?php

namespace Baspa\FilamentCanary\Sweep;

use Illuminate\Contracts\Auth\Authenticatable;

interface Requester
{
    /**
     * Perform a GET against the given URL, optionally authenticated as $user on $guard,
     * and return the HTTP status code. A thrown error must be reported as 500.
     */
    public function get(string $url, ?Authenticatable $user, string $guard): int;
}

<?php

namespace Baspa\FilamentCanary\Sweep;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;

/**
 * Drives real requests through the HTTP kernel, so it works identically inside a test
 * and from the `canary:check` command. A page that throws becomes a 500 — exactly the
 * signal the canary exists to catch — regardless of the app's exception-handling config.
 */
class KernelRequester implements Requester
{
    public function __construct(
        protected HttpKernel $kernel,
        protected AuthFactory $auth,
    ) {}

    public function get(string $url, ?Authenticatable $user, string $guard): int
    {
        $this->setUser($user, $guard);

        $request = Request::create($url, 'GET');

        try {
            $response = $this->kernel->handle($request);
            $status = $response->getStatusCode();
            $this->kernel->terminate($request, $response);
        } catch (\Throwable) {
            $status = 500;
        } finally {
            $this->forgetUser($guard);
        }

        return $status;
    }

    protected function setUser(?Authenticatable $user, string $guard): void
    {
        if ($user === null) {
            $this->forgetUser($guard);

            return;
        }

        $this->auth->guard($guard)->setUser($user);
    }

    protected function forgetUser(string $guard): void
    {
        $guardInstance = $this->auth->guard($guard);

        if (method_exists($guardInstance, 'forgetUser')) {
            $guardInstance->forgetUser();
        }
    }
}

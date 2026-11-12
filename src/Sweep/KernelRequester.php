<?php

namespace Baspa\FilamentCanary\Sweep;

use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Contracts\Session\Session;
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
        protected AuthManager $auth,
        protected Session $session,
        protected Container $container,
    ) {}

    public function get(string $url, ?Authenticatable $user, string $guard): int
    {
        $defaultGuard = $this->auth->getDefaultDriver();
        $redirector = $this->container->make('redirect');

        // Every in-process request shares one session store, and starting a session keeps
        // the attributes already in it (e.g. AuthenticateSession's password hash).
        $this->session->flush();
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
            $this->session->flush();
            // Filament's Authenticate middleware makes the panel's guard the default one.
            $this->auth->shouldUse($defaultGuard);
            // Livewire 3 puts Laravel's redirector back only when a component dehydrates,
            // which a page that aborts in mount() (a 403) never does.
            $this->container->instance('redirect', $redirector);
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

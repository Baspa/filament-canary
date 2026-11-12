<?php

namespace Baspa\FilamentCanary\Sweep;

use Filament\Facades\Filament;
use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Session\Store;

/**
 * Drives real requests through the HTTP kernel, so it works identically inside a test
 * and from the `canary:check` command. A page that throws becomes a 500 — exactly the
 * signal the canary exists to catch — regardless of the app's exception-handling config.
 *
 * Every request starts with an empty session and Laravel's own redirector, and afterwards
 * the app gets back its session data, default guard, user resolver and current Filament
 * panel and tenant, so no page can change how the next one, or the calling test, runs.
 */
class KernelRequester implements Requester
{
    public function __construct(
        protected HttpKernel $kernel,
        protected AuthManager $auth,
        protected Store $session,
        protected Container $container,
    ) {}

    public function get(string $url, ?Authenticatable $user, string $guard): int
    {
        $sessionData = $this->session->all();
        $defaultGuard = $this->auth->getDefaultDriver();
        $userResolver = $this->auth->userResolver();
        $panel = Filament::getCurrentPanel();
        $tenant = Filament::getTenant();

        // Every in-process request shares one session store, and starting a session keeps
        // the attributes already in it (e.g. AuthenticateSession's password hash).
        $this->session->flush();
        $this->useLaravelRedirector();
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
            $this->session->replace($sessionData);
            // Filament's Authenticate middleware calls shouldUse() with the panel's guard.
            $this->auth->setDefaultDriver($defaultGuard);
            $this->auth->resolveUsersUsing($userResolver);
            Filament::setCurrentPanel($panel);
            Filament::setTenant($tenant, isQuiet: true);
            $this->useLaravelRedirector();
        }

        return $status;
    }

    /**
     * Livewire 3 binds its own redirector while a component runs and puts the previous one
     * back only when the component dehydrates, which a page that aborts in mount() (a 403)
     * never does. This builds Laravel's the way RoutingServiceProvider does.
     */
    protected function useLaravelRedirector(): void
    {
        $redirector = new Redirector($this->container->make(UrlGenerator::class));
        $redirector->setSession($this->session);

        $this->container->instance('redirect', $redirector);
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

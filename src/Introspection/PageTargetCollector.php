<?php

namespace Baspa\FilamentCanary\Introspection;

use Filament\Http\Controllers\RedirectToHomeController;
use Filament\Http\Controllers\RedirectToTenantController;
use Filament\Panel;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;

/**
 * Turns a Filament panel into the list of GET pages worth smoke-testing, by reading
 * the routes Filament actually registered on the Laravel router.
 */
class PageTargetCollector
{
    /**
     * Guest-facing and signed-link flows, where "authorized user gets 2xx, guest is denied" does not apply.
     */
    protected const AUTH_FLOW_ROUTES = [
        'auth.login',
        'auth.logout',
        'auth.register',
        'auth.password-reset.*',
        'auth.email-verification.*',
        'auth.email-change-verification.*',
        'auth.multi-factor-authentication.*',
    ];

    /**
     * These only redirect to a panel page, which is swept on its own.
     */
    protected const REDIRECT_CONTROLLERS = [
        RedirectToHomeController::class,
        RedirectToTenantController::class,
    ];

    public function __construct(
        protected Router $router,
    ) {}

    /**
     * @param  list<string>  $exclude  Resource or page class names to skip
     * @return list<PageTarget>
     */
    public function forPanel(Panel $panel, array $exclude = []): array
    {
        $panelId = $panel->getId();
        $guard = $panel->getAuthGuard();
        $prefix = "filament.{$panelId}.";

        $authFlowPatterns = $this->authFlowRouteNamePatterns($panel);
        $resourceByBase = $this->resourceRouteBaseMap($panel);

        $targets = [];

        foreach ($this->router->getRoutes()->getRoutes() as $route) {
            /** @var Route $route */
            $name = $route->getName();

            if ($name === null || ! str_starts_with($name, $prefix)) {
                continue;
            }

            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            if (Str::is($authFlowPatterns, $name)) {
                continue;
            }

            $pageClass = $route->getControllerClass();

            if (in_array($pageClass, self::REDIRECT_CONTROLLERS, true)) {
                continue;
            }

            [$resourceClass, $modelClass] = $this->matchResource($name, $resourceByBase);

            if (in_array($resourceClass, $exclude, true) || in_array($pageClass, $exclude, true)) {
                continue;
            }

            $params = array_values($route->parameterNames());

            $targets[] = new PageTarget(
                panelId: $panelId,
                guard: $guard,
                routeName: $name,
                uri: $route->uri(),
                parameterNames: $params,
                requiresRecord: in_array('record', $params, true),
                tenantScoped: in_array('tenant', $params, true),
                modelClass: $modelClass,
                resourceClass: $resourceClass,
                pageClass: $pageClass,
            );
        }

        return $targets;
    }

    /**
     * @return list<string>
     */
    protected function authFlowRouteNamePatterns(Panel $panel): array
    {
        $panelPrefix = "filament.{$panel->getId()}.";
        $domains = $panel->getDomains();

        // Filament only puts the domain in route names when a panel has more than one.
        $prefixes = count($domains) > 1
            ? array_map(fn (string $domain) => "{$panelPrefix}{$domain}.", $domains)
            : [$panelPrefix];

        $patterns = [];

        foreach ($prefixes as $prefix) {
            foreach (self::AUTH_FLOW_ROUTES as $authFlowRoute) {
                $patterns[] = $prefix.$authFlowRoute;
            }
        }

        return $patterns;
    }

    /**
     * @return array<string, class-string> routeBaseName => resourceClass
     */
    protected function resourceRouteBaseMap(Panel $panel): array
    {
        $map = [];

        foreach ($panel->getResources() as $resourceClass) {
            $map[$resourceClass::getRouteBaseName($panel)] = $resourceClass;
        }

        return $map;
    }

    /**
     * @param  array<string, class-string>  $resourceByBase
     * @return array{0: ?class-string, 1: ?string} [resourceClass, modelClass]
     */
    protected function matchResource(string $routeName, array $resourceByBase): array
    {
        foreach ($resourceByBase as $base => $resourceClass) {
            if ($routeName === $base || str_starts_with($routeName, $base.'.')) {
                return [$resourceClass, $resourceClass::getModel()];
            }
        }

        return [null, null];
    }
}

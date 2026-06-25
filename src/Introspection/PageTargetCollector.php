<?php

namespace Baspa\FilamentCanary\Introspection;

use Filament\Panel;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;

/**
 * Turns a Filament panel into the list of GET pages worth smoke-testing, by reading
 * the routes Filament actually registered on the Laravel router.
 */
class PageTargetCollector
{
    public function __construct(
        protected Router $router,
    ) {}

    /**
     * @param  list<string>  $excludeResources  Resource/page class names to skip
     * @return list<PageTarget>
     */
    public function forPanel(Panel $panel, array $excludeResources = []): array
    {
        $panelId = $panel->getId();
        $guard = $panel->getAuthGuard();
        $prefix = "filament.{$panelId}.";

        $resourceByBase = $this->resourceRouteBaseMap($panel, $excludeResources);

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

            // Authentication flows are not pages to smoke-test.
            if (str_contains($name, '.auth.')) {
                continue;
            }

            [$resourceClass, $modelClass] = $this->matchResource($name, $resourceByBase);

            if ($resourceClass !== null && in_array($resourceClass, $excludeResources, true)) {
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
            );
        }

        return $targets;
    }

    /**
     * @param  list<string>  $excludeResources
     * @return array<string, class-string> routeBaseName => resourceClass
     */
    protected function resourceRouteBaseMap(Panel $panel, array $excludeResources): array
    {
        $map = [];

        foreach ($panel->getResources() as $resourceClass) {
            if (in_array($resourceClass, $excludeResources, true)) {
                continue;
            }

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

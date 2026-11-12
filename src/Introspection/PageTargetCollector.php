<?php

namespace Baspa\FilamentCanary\Introspection;

use Filament\Http\Controllers\RedirectToHomeController;
use Filament\Http\Controllers\RedirectToTenantController;
use Filament\Panel;
use Filament\Resources\Pages\Page as ResourcePage;
use Filament\Resources\Resource as FilamentResource;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;

/**
 * Turns a Filament panel into the list of GET pages worth smoke-testing, by reading
 * the routes Filament actually registered on the Laravel router.
 */
class PageTargetCollector
{
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
        $profilePage = $panel->getProfilePage();

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

            $pageClass = $route->getControllerClass();
            $isProfilePage = $pageClass !== null && $pageClass === $profilePage;

            // Login, password reset, verification and plugin flows (socialite, 2FA challenges)
            // are meant for guests or signed links, so the sweep's checks don't apply to them.
            if (str_contains($name, '.auth.') && ! $isProfilePage) {
                continue;
            }

            if (in_array($pageClass, self::REDIRECT_CONTROLLERS, true)) {
                continue;
            }

            $resourceClass = $this->resourceOf($pageClass);

            if (in_array($resourceClass, $exclude, true) || in_array($pageClass, $exclude, true)) {
                continue;
            }

            $params = array_values(array_filter($route->parameterNames(), is_string(...)));

            $targets[] = new PageTarget(
                panelId: $panelId,
                guard: $guard,
                routeName: $name,
                uri: $route->uri(),
                parameterNames: $params,
                requiresRecord: in_array('record', $params, true),
                tenantScoped: in_array('tenant', $params, true),
                modelClass: $resourceClass === null ? null : $resourceClass::getModel(),
                resourceClass: $resourceClass,
                pageClass: $pageClass,
            );
        }

        return $targets;
    }

    /**
     * @return class-string<FilamentResource>|null
     */
    protected function resourceOf(?string $pageClass): ?string
    {
        if ($pageClass === null || ! is_subclass_of($pageClass, ResourcePage::class)) {
            return null;
        }

        $resource = $pageClass::getResource();

        return is_subclass_of($resource, FilamentResource::class) ? $resource : null;
    }
}

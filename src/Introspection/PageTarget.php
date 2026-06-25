<?php

namespace Baspa\FilamentCanary\Introspection;

/**
 * A single Filament page that the sweep will try to reach: a resource page
 * (List/Create/Edit/View), a custom panel page, or the panel dashboard.
 *
 * Built from the router's registered routes so it always reflects what Filament
 * actually exposes, independent of Filament internals or version.
 */
class PageTarget
{
    /**
     * @param  list<string>  $parameterNames
     */
    public function __construct(
        public readonly string $panelId,
        public readonly string $guard,
        public readonly string $routeName,
        public readonly string $uri,
        public readonly array $parameterNames,
        public readonly bool $requiresRecord,
        public readonly bool $tenantScoped,
        public readonly ?string $modelClass = null,
        public readonly ?string $resourceClass = null,
    ) {}

    /** Route parameters we cannot resolve automatically (anything beyond record/tenant). */
    public function unresolvableParameters(): array
    {
        return array_values(array_filter(
            $this->parameterNames,
            fn (string $name) => ! in_array($name, ['record', 'tenant'], true),
        ));
    }

    public function label(): string
    {
        return $this->routeName;
    }
}

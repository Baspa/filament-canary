<?php

namespace Baspa\FilamentCanary\Sweep;

use Baspa\FilamentCanary\Introspection\PageTarget;
use Baspa\FilamentCanary\Introspection\PageTargetCollector;
use Baspa\FilamentCanary\Introspection\PanelCollector;
use Filament\Panel;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The engine. Walks every Filament panel's pages and, for each, asserts that an
 * authorized user can reach it and a guest cannot — classifying the outcome so that
 * real breakage fails loudly while anything it can't reach honestly is skipped with a
 * reason. Nothing is silently left untested.
 */
class SmokeSweep
{
    public function __construct(
        protected PanelCollector $panels,
        protected PageTargetCollector $targets,
        protected ActingUserResolver $actingUser,
        protected RecordResolver $records,
        protected TenantResolver $tenants,
        protected Requester $requester,
    ) {}

    /** @var array<string, Authenticatable|null> one resolved user per panel id */
    protected array $userCache = [];

    /**
     * @return list<SweepResult>
     */
    public function run(): array
    {
        $only = $this->stringList(config('filament-canary.panels.only', []));
        $except = $this->stringList(config('filament-canary.panels.except', []));
        $exclude = $this->stringList(config('filament-canary.exclude', []));
        $testGuests = (bool) config('filament-canary.test_guests', true);

        $results = [];

        foreach ($this->panels->collect($only, $except) as $panel) {
            foreach ($this->targets->forPanel($panel, $exclude) as $target) {
                $results[] = $this->sweep($panel, $target, $testGuests);
            }
        }

        return $results;
    }

    protected function sweep(Panel $panel, PageTarget $target, bool $testGuests): SweepResult
    {
        if (($extra = $target->unresolvableParameters()) !== []) {
            return SweepResult::skipped($target, 'route has parameters canary cannot resolve: '.implode(', ', $extra));
        }

        $params = [];

        if ($target->tenantScoped) {
            try {
                $tenant = $this->tenants->resolve($panel);
            } catch (\Throwable $e) {
                return SweepResult::skipped($target, "could not create a tenant for panel [{$panel->getId()}]: ".$e->getMessage());
            }

            if ($tenant === null) {
                return SweepResult::skipped($target, "tenant required; configure filament-canary.tenant for panel [{$panel->getId()}]");
            }

            $params['tenant'] = $tenant;
        }

        if ($target->requiresRecord) {
            try {
                $record = $this->records->resolve($target->modelClass);
            } catch (\Throwable $e) {
                return SweepResult::skipped($target, "could not create a [{$target->modelClass}] record: ".$e->getMessage());
            }

            if ($record === null) {
                return SweepResult::skipped($target, "model [{$target->modelClass}] has no factory; cannot build a record for this page");
            }

            $params['record'] = $record;
        }

        try {
            $user = $this->resolveUser($panel);
        } catch (\Throwable $e) {
            return SweepResult::skipped($target, "could not create an authorized user for panel [{$panel->getId()}]: ".$e->getMessage());
        }

        if ($user === null) {
            return SweepResult::skipped($target, "no authorized user; configure filament-canary.acting_as for panel [{$panel->getId()}] (guard [{$target->guard}])");
        }

        try {
            $url = route($target->routeName, $params);
        } catch (\Throwable $e) {
            return SweepResult::skipped($target, 'could not build URL: '.$e->getMessage());
        }

        $authorized = $this->requester->get($url, $user, $target->guard);

        if ($authorized >= 500) {
            return SweepResult::failed($target, "authorized request returned {$authorized}", $authorized);
        }

        if (in_array($authorized, [401, 403], true)) {
            return SweepResult::needsAuth($target, "authorized user was denied ({$authorized}); configure filament-canary.acting_as for panel [{$panel->getId()}]", $authorized);
        }

        if ($authorized === 404) {
            return ($target->requiresRecord || $target->tenantScoped)
                ? SweepResult::skipped($target, 'got 404 — the generated record/tenant is likely not visible to this page; provide a resolver via config')
                : SweepResult::failed($target, 'authorized request returned 404', $authorized);
        }

        if ($authorized >= 300) {
            return SweepResult::needsAuth($target, "authorized request redirected ({$authorized}); the resolved user may lack access", $authorized);
        }

        if (! $testGuests) {
            return SweepResult::passed($target, $authorized);
        }

        $guest = $this->requester->get($url, null, $target->guard);

        if ($guest < 300) {
            return SweepResult::failed($target, "guest access allowed ({$guest}) — authorization leak", $authorized, $guest);
        }

        return SweepResult::passed($target, $authorized, $guest);
    }

    /**
     * @return list<string>
     */
    protected function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_map(strval(...), $value));
    }

    /**
     * Resolve one authorized user per panel and reuse it for every page. Reusing the
     * same user keeps session auth (e.g. AuthenticateSession) stable across requests.
     */
    protected function resolveUser(Panel $panel): ?Authenticatable
    {
        return $this->userCache[$panel->getId()] ??= $this->actingUser->resolve($panel);
    }
}

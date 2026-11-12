<?php

namespace Baspa\FilamentCanary\Sweep;

use Baspa\FilamentCanary\Introspection\PageTarget;
use Baspa\FilamentCanary\Introspection\PageTargetCollector;
use Baspa\FilamentCanary\Introspection\PanelCollector;
use Filament\Panel;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\EventBus;
use Livewire\Features\SupportLazyLoading\SupportLazyLoading;
use Livewire\Livewire;

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

    /** @var array<string, Authenticatable|string> per panel id: the acting user, or why there is none */
    protected array $userCache = [];

    /** @var array<string, Model|string> per panel id: the tenant, or why there is none */
    protected array $tenantCache = [];

    /**
     * @return list<SweepResult>
     */
    public function run(): array
    {
        $this->ensureEnvironmentIsAllowed();

        // Lazy widgets, lazy relation managers and deferred tables render their content in a
        // later Livewire request the sweep never makes, so their errors would pass unseen.
        $lazyLoadingWasDisabled = SupportLazyLoading::$disableWhileTesting;
        Livewire::withoutLazyLoading();
        $events = app(EventBus::class);
        $loadDeferredTable = $this->loadDeferredTable(...);
        $events->on('mount', $loadDeferredTable);

        try {
            return $this->sweepInTransaction();
        } finally {
            $events->off('mount', $loadDeferredTable);
            // The flag is process-wide: leaving it on would change how the app's other tests render.
            SupportLazyLoading::$disableWhileTesting = $lazyLoadingWasDisabled;
        }
    }

    protected function loadDeferredTable(Component $component): void
    {
        if ($component instanceof HasTable && method_exists($component, 'loadTable')) {
            $component->loadTable();
        }
    }

    /**
     * @return list<SweepResult>
     */
    protected function sweepInTransaction(): array
    {
        if (! (bool) config('filament-canary.use_transaction', true)) {
            return $this->sweepAll();
        }

        // Wrap the whole sweep in a transaction so the users/records it creates never
        // persist. Covers the default connection only, which is why run() refuses
        // environments that allowed_environments doesn't list.
        DB::beginTransaction();

        try {
            return $this->sweepAll();
        } finally {
            // Only roll back if the transaction is still open: app code may have
            // committed/closed it mid-sweep (e.g. DDL), leaving nothing to undo.
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
    }

    protected function ensureEnvironmentIsAllowed(): void
    {
        $environment = $this->currentEnvironment();

        if (in_array(strtolower($environment), ['production', 'prod', 'prd', 'live'], true)) {
            throw EnvironmentNotAllowedException::production($environment);
        }

        $allowed = $this->allowedEnvironments();

        // The matcher app()->environment(...$patterns) uses, applied to currentEnvironment().
        if (! Str::is($allowed, $environment)) {
            throw EnvironmentNotAllowedException::notListed($environment, $allowed);
        }
    }

    /**
     * @return list<string>
     */
    protected function allowedEnvironments(): array
    {
        $configured = config('filament-canary.allowed_environments', ['local', 'testing']);
        $allowed = is_array($configured) ? array_values(array_filter($configured, is_string(...))) : [];

        if ($allowed !== $configured) {
            throw EnvironmentNotAllowedException::invalidConfig($configured);
        }

        return $allowed;
    }

    protected function currentEnvironment(): string
    {
        return app()->environment();
    }

    /**
     * @return list<SweepResult>
     */
    protected function sweepAll(): array
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

        $user = $this->resolveUser($panel);

        if (is_string($user)) {
            return SweepResult::skipped($target, $user);
        }

        $params = [];

        if ($target->tenantScoped) {
            $tenant = $this->resolveTenant($panel, $user);

            if (is_string($tenant)) {
                return SweepResult::skipped($target, $tenant);
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
            $url = route($target->routeName, $params);
        } catch (\Throwable $e) {
            return SweepResult::skipped($target, 'could not build URL: '.$e->getMessage());
        }

        $authorized = $this->requester->get($url, $user, $target->guard);

        if (in_array($authorized, [401, 403], true)) {
            return SweepResult::needsAuth($target, "authorized user was denied ({$authorized}); configure filament-canary.acting_as for panel [{$panel->getId()}]", $authorized);
        }

        if ($authorized === 404 && ($target->tenantScoped || $target->requiresRecord)) {
            return SweepResult::skipped($target, $this->notFoundReason($target));
        }

        if ($authorized >= 400) {
            return SweepResult::failed($target, "authorized request returned {$authorized}", $authorized);
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

        if ($guest >= 400 && ! in_array($guest, [401, 403], true)) {
            return SweepResult::failed($target, "guest request returned {$guest}; can't confirm guests are denied", $authorized, $guest);
        }

        return SweepResult::passed($target, $authorized, $guest);
    }

    protected function notFoundReason(PageTarget $target): string
    {
        $causes = [];

        if ($target->tenantScoped) {
            $causes[] = "the acting user can't access the generated tenant (return a tenant they belong to from the filament-canary.tenant resolver, which receives the panel and the acting user)";
        }

        if ($target->requiresRecord) {
            $causes[] = "the generated [{$target->modelClass}] record isn't visible to the page's query (fix the model factory or query scopes, or exclude the page)";
        }

        return 'got 404: '.implode(', or ', $causes);
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
     *
     * @return Authenticatable|string the user, or why the panel's pages are skipped
     */
    protected function resolveUser(Panel $panel): Authenticatable|string
    {
        $id = $panel->getId();

        if (array_key_exists($id, $this->userCache)) {
            return $this->userCache[$id];
        }

        try {
            $user = $this->actingUser->resolve($panel);
        } catch (\Throwable $e) {
            return $this->userCache[$id] = "could not create an authorized user for panel [{$id}]: ".$e->getMessage();
        }

        return $this->userCache[$id] = $user
            ?? "no authorized user; configure filament-canary.acting_as for panel [{$id}] (guard [{$panel->getAuthGuard()}])";
    }

    /**
     * Resolve one tenant per panel, for the panel's acting user.
     *
     * @return Model|string the tenant, or why the panel's tenant pages are skipped
     */
    protected function resolveTenant(Panel $panel, Authenticatable $user): Model|string
    {
        $id = $panel->getId();

        if (array_key_exists($id, $this->tenantCache)) {
            return $this->tenantCache[$id];
        }

        try {
            $tenant = $this->tenants->resolve($panel, $user);
        } catch (\Throwable $e) {
            return $this->tenantCache[$id] = "could not create a tenant for panel [{$id}]: ".$e->getMessage();
        }

        return $this->tenantCache[$id] = $tenant
            ?? "tenant required; configure filament-canary.tenant for panel [{$id}]";
    }
}

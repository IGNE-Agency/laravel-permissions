<?php

declare(strict_types=1);

namespace Igne\Permissions;

use Igne\Permissions\Contracts\ResolvesRolePermissions;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\PermissionServiceProvider as SpatiePermissionServiceProvider;

final class PermissionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(self::configFile(), 'permissions');

        $this->app->singleton(SubjectDiscovery::class);
        $this->app->bind(PermissionRegistry::class, PermissionRegistry::fromConfig(...));

        // An extender runs when the Gate is built, before any afterResolving callback, so the hook
        // comes before every before callback that grants, Spatie's or the app's own. Booting never
        // resolves the Gate. The first-class callable hands the Gate a Closure, which it reflects on
        // to skip guests.
        $this->app->extend(Gate::class, static function (Gate $gate): Gate {
            $gate->before((new PermissionSpaceResolver($gate))->resolve(...));

            return $gate;
        });
    }

    public function boot(): void
    {
        $this->publishes([
            self::configFile() => $this->app->configPath('permissions.php'),
        ], 'permissions-config');

        // Every provider registers before any boots, so this sees Spatie's provider wherever it sits
        // in the list. Naming its class loads nothing, so the check is safe without Spatie installed.
        // bindIf keeps a role lookup the app bound itself.
        if ($this->app->getProviders(SpatiePermissionServiceProvider::class) !== []) {
            $this->app->bindIf(ResolvesRolePermissions::class, SpatieRolePermissions::class);
        }
    }

    private static function configFile(): string
    {
        return \dirname(__DIR__).'/config/permissions.php';
    }
}

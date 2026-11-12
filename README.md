# igne-agency/laravel-permissions

Builds permission names and permission lists from the enums an app defines, on top of [spatie/laravel-permission](https://github.com/spatie/laravel-permission), lets Laravel's Gate fill in the checked user's space, and guards spaces whose access excludes each other. The app keeps its spaces, permission enums, roles, labels, admin screens and seeder; the package only turns enum cases into the strings Spatie stores.

## Requirements

- PHP 8.4
- Laravel 12 or 13
- spatie/laravel-permission 7 or 8

## Install

```bash
composer require igne-agency/laravel-permissions
php artisan vendor:publish --tag=permissions-config
```

See [docs/installation.md](docs/installation.md) for the config keys.

## Example

```php
permissions()->in(AppSpace::ADMIN_PORTAL)->access();                                  // 'nova:access'
permissions()->in(AppSpace::ADMIN_PORTAL)->for(ResourcePermission::DOCUMENT)->view(); // 'nova:document:view'
permissions()->in(AppSpace::ADMIN_PORTAL)->for(ResourcePermission::DOCUMENT)->any(PermissionAction::VIEW, PermissionAction::UPDATE);
permissions()->in(AppSpace::ADMIN_PORTAL)->withAccess()->except(ResourcePermission::INVOICE)->names();
permissions()->all();

return $user->can(permissions()->for(ResourcePermission::DOCUMENT)->view());         // in a policy: the Gate fills in the user's space
```

Every space, permission and action is an enum case, so the editor completes them and PHPStan checks them. A call the enums do not support throws instead of producing a name nobody seeded.

## Docs

- [Installation](docs/installation.md): requirements, publishing the config, the config keys, versioning.
- [Defining permissions](docs/defining-permissions.md): the space enum, permission enums, the opt-in contracts and traits, custom actions, registering the enums.
- [Building names](docs/building-names.md): the `permissions()` chain, the name format, what throws and why, why output strings never change.
- [Permission lists](docs/permission-lists.md): `only`, `except`, `withAccess`, `onlyFor`, `exceptFor`, pairs, and an app seeder handing a list to its own role.
- [Checking permissions](docs/checking-permissions.md): the one-line policy check, `HasPermissionSpace` on the user model, every Gate entry point, routes, a fixed space, Spatie's direct methods, what throws.
- [Access guard](docs/access-guard.md): `HasExclusiveAccess`, `AccessGuard`, catching `ConflictingAccessException` and writing the user-facing message.

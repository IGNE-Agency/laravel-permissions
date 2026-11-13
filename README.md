# igne-agency/laravel-permissions

Builds permission names and permission lists from the models, action classes and enums an app already has, on top of [spatie/laravel-permission](https://github.com/spatie/laravel-permission). A class becomes a permission subject by implementing one interface, and the package discovers it. Laravel's Gate fills in the checked user's space, applies implied actions and required space access, and the package guards spaces whose access excludes each other. Spaces are optional: an app without them gets plain names such as `organization:view`. The app keeps its spaces, roles, labels, admin screens and seeder; the package only turns subjects into the strings Spatie stores.

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
final class Organization extends Model implements PermissionSubject, UsesActions, UsesSpaces
{
    use HasPermissionKey;      // key 'organization', from the class name
    use UsesDefaultActions;    // viewAny, view, create, update, delete, restore, destroy

    public static function spaces(): array
    {
        return [AppSpace::ADMIN_PORTAL];
    }
}

$user->can(permission()->view(Organization::class));                     // '{space}:organization:view': the Gate fills in the user's space
$user->can(permission()->in(AppSpace::ADMIN_PORTAL)->update($organization)); // 'nova:organization:update'
permission()->in(AppSpace::ADMIN_PORTAL)->access();                      // 'nova:access'

permissions()->in(AppSpace::ADMIN_PORTAL)->names();                       // every name in the space, for a role
permissions()->in(AppSpace::ADMIN_PORTAL)->except(Invoice::class)->names();
permissions()->only(Organization::class, PermissionAction::VIEW)->names(); // in every space it declares
permissions()->all();                                                     // every name, for the seeder
```

Every space, subject and action is a class or an enum case, so the editor completes them and PHPStan checks them. A call a subject does not support throws instead of producing a name nobody seeded.

## Docs

- [Installation](docs/installation.md): requirements, publishing the config, the config keys, versioning.
- [Defining permissions](docs/defining-permissions.md): subjects and their key, permission enums, the opt-in contracts and traits, discovery, the space enum, an app without spaces, spaces that require access, custom and implied actions.
- [Building names](docs/building-names.md): `permission()` for one name, the space placeholder, what throws and why, why output strings never change.
- [Permission lists](docs/permission-lists.md): `permissions()`, `in()->names()`, `only` and `except` with or without a space, `withAccess`, `onlyFor`, `exceptFor`, and an app seeder handing a list to its own role.
- [Checking permissions](docs/checking-permissions.md): the one-line policy check, `HasPermissionSpace` on the user model, every Gate entry point, the rules the Gate applies, what a policy still decides, revoking and the permission cache, routes, a fixed space, Spatie's direct methods, what throws.
- [Access guard](docs/access-guard.md): `HasExclusiveAccess`, `AccessGuard`, catching `ConflictingAccessException` and writing the user-facing message.

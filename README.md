# igne-agency/laravel-permissions

Permission names, lists and checks for the models, action classes and enums of a Laravel app. A class becomes a permission subject by implementing one interface; the package discovers it and builds the names the app stores, with [spatie/laravel-permission](https://github.com/spatie/laravel-permission) or its own `Gate::before` callback. The app keeps its roles, labels, admin screens and seeder.

## Why

In most apps a permission name is a string typed by hand in several places: the seeder, the roles, the policies and the route middleware. A typo, or a model renamed in one place, leaves a check that never passes, and nothing reports it. Here the names come from the code: a model, action class or enum declares its actions and spaces, and the seeder, the roles and every check build their names from it. A name that does not exist throws a `PermissionException` at the call.

## How it works

1. **Discovery.** On first use the package scans the `discover` folders (`app/` by default) for permission subjects: classes that implement `PermissionSubject` and enums that implement `PermissionEnum`.
2. **Names.** The options a subject implements give its names, in the format `[prefix]:[space]:[group]:[key]:[action]`, such as `admin:post:update`.
3. **Seeding and roles.** `permissions()` lists the names for the app's seeder and roles. The package stores and grants nothing: spatie/laravel-permission or the app's own `Gate::before` callback does.
4. **Checks.** `permission()` builds one name for `$user->can()`, a policy or the `can:` middleware. The package's `Gate::before` hooks fill in the user's space and apply the space access and implied action rules.

## Features

| Feature | Default | Turn it on or off |
|---|---|---|
| Default actions: `viewAny`, `view`, `create`, `update`, `delete`, `restore`, `destroy` | Off | `UsesActions` on the subject. `UsesCustomActions` lists its own; the `actions` key swaps the default set for every subject. |
| Spaces, such as `admin` and `portal`, each with its own `access` permission | Off | `UsesSpaces` on the subject and the space enum in the `spaces` key. `UsesCustomSpaces` lists a subject's own spaces; `FiltersPerSpace` decides per space whether it exists there. |
| Required space access: a name in a space only works with that space's `access` | On | `'requires_access' => false`, or `RequiresAccess` on the space enum for some spaces only. |
| Implied actions: `update` grants `view`, `view` grants `viewAny` | On | `'implied_actions' => []`, or `ImpliesActions` on a subject for its own rules. |
| The checked user's space: `permission()->update($post)` without `in()` | Every space whose `access` the user holds | `HasPermissionSpace` on the user model decides the spaces itself. |
| Access guard: spaces a user may not combine, such as staff and customer | Off | `HasExclusiveAccess` on the space enum, and `AccessGuard` before every grant. |
| Labels and descriptions for admin screens | Off | `HasLabel` on the subject. |
| spatie/laravel-permission | Optional | Install it to store and grant the names; without it, grant them in `Gate::before`. |

The config keys, with their defaults and examples, are in [installation](docs/installation.md#config-keys). Changing `separator`, `normalizer` or `prefix` renames every stored name, so set them before the first seed.

## Requirements

- PHP 8.4
- Laravel 12 or 13
- Optional: spatie/laravel-permission 7 or 8

## Install

```bash
composer require igne-agency/laravel-permissions
php artisan vendor:publish --tag=permissions-config
```

## Example

```php
final class Post extends Model implements PermissionSubject, UsesActions, UsesSpaces
{
    use HasPermissionKey;
}
```

With `'spaces' => Space::class` in the config, `Post` gets the default actions in every space.

```php
permission()->in(Space::ADMIN)->update(Post::class);   // 'admin:post:update'
$user->can(permission()->update($post));               // the Gate fills in the user's space
permissions()->in(Space::ADMIN)->names();              // every name in the space, for a role
permissions()->all();                                  // every name, for the seeder
```

## Docs

- [Installation](docs/installation.md): requirements, storing and granting with or without Spatie, the config keys, versioning.
- [Defining permissions](docs/defining-permissions.md): subjects, every option they can opt into, discovery, spaces and actions.
- [Building names](docs/building-names.md): `permission()` for one name, and what throws.
- [Permission lists](docs/permission-lists.md): `permissions()` for lists, every selection form, and handing a list to a role.
- [Checking permissions](docs/checking-permissions.md): checks through the Gate, the user's space, entry points and what Spatie's direct methods need.
- [Access guard](docs/access-guard.md): spaces whose access excludes each other.

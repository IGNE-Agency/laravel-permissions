# Installation

## Requirements

- PHP 8.4
- Laravel 12 or 13
- Optional: spatie/laravel-permission 7 or 8, installed and migrated in the app

## Storing and granting

The package builds names and lists; it stores and grants nothing. Whatever answers `$user->can($name)` in the app receives the names:

- **With spatie/laravel-permission**, the app seeds the names as Spatie permissions and grants them through Spatie's roles. Nothing else needs setting up.
- **Without it**, the app stores the names its own way and grants them in a `Gate::before` callback:

```php
Gate::before(fn (User $user, string $ability): ?bool => $user->holdsPermission($ability) ? true : null);
```

Return `null` rather than `false` for a name the user does not hold, so the Gate goes on to the policies.
The role checks of the [access guard](access-guard.md#role-checks) also need a role lookup.

## Install

```bash
composer require igne-agency/laravel-permissions
php artisan vendor:publish --tag=permissions-config
```

Laravel discovers the service provider. The published `config/permissions.php` only needs the keys the app changes; the package merges its defaults underneath.

## Config keys

| Key | Default | Meaning | Example |
|-----|---------|---------|---------|
| `discover` | `[app_path()]` | Folders scanned for permission subjects | `[app_path(), base_path('modules')]` |
| `subjects` | `[]` | Subjects outside those folders | `[\Vendor\Package\Comment::class]` |
| `separator` | `':'` | Joins the segments of a name | `'.'` gives `admin.post.view` |
| `normalizer` | `'camel'` | Casing of every segment: `camel`, `snake`, `kebab` or `studly` | `'snake'` gives `admin:blog_post:view_any` |
| `prefix` | `null` | A first segment for every name | `'app'` gives `app:admin:post:view` |

A folder in `discover` that does not exist, an unknown normalizer or a `subjects` entry that is not a subject throws a `PermissionException`.

## Versioning

The names are stored in every installing app's database. Releases follow semantic versioning, and any change to a generated name is a major version. Changing `separator`, `normalizer` or `prefix` in an app that already has permissions is a data migration in that app.

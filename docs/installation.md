# Installation

## Requirements

- PHP 8.4
- Laravel 12 or 13
- spatie/laravel-permission 7 or 8, installed and migrated in the app

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
| `implied_actions` | update grants view, view grants viewAny | Per action value, the actions a grant of it also grants through the Gate | `[]` turns it off |
| `requires_access` | `true` | Whether every name in a space needs the space's `access` too | `false` makes `access` an ordinary permission |

A folder in `discover` that does not exist, an unknown normalizer or a `subjects` entry that is not a subject throws a `PermissionException`.
So does an `implied_actions` entry that lists anything other than action enum cases.

## Versioning

The names are stored in every installing app's database. Releases follow semantic versioning, and any change to a generated name is a major version. Changing `separator`, `normalizer` or `prefix` in an app that already has permissions is a data migration in that app.

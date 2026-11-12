# Installation

## Requirements

- PHP 8.4
- Laravel 12 or 13 (`illuminate/support` and `illuminate/contracts` `^12.0|^13.0`)
- spatie/laravel-permission 7 or 8, installed and migrated in the app

## Install

The package is published on [Packagist](https://packagist.org/packages/igne-agency/laravel-permissions), so there is nothing to configure in `composer.json` beyond the requirement:

```bash
composer require igne-agency/laravel-permissions
```

Composer resolves versions from the repository's tags. Releases follow semantic versioning: any change to a generated permission name is a major version, because the names are stored in every installing app's database.

## Service provider

Laravel discovers `Igne\Permissions\PermissionsServiceProvider` through package discovery. Nothing to register.

## Publish the config

```bash
php artisan vendor:publish --tag=permissions-config
```

This copies `config/permissions.php` into the app. The package merges its defaults underneath, so the app only needs to set the keys it changes.

## Config keys

| Key | Default | Meaning |
|-----|---------|---------|
| `types` | `[]` | The permission enums the package builds names for. Each implements `Igne\Permissions\Contracts\PermissionEnum`. |
| `separator` | `':'` | The character between the segments of a name: `nova:document:view`. |
| `normalizer` | `'camel'` | The casing applied to every segment: `camel`, `snake`, `kebab` or `studly`. Any other value throws. |
| `prefix` | `null` | An optional first segment for every name. `null` omits it. |

The registry is rebuilt from config on every `permissions()` call, so a test can change these keys at runtime.

Changing `separator`, `normalizer` or `prefix` changes every generated name. Once permissions exist in the database, that is a data migration in the app, not a config change.

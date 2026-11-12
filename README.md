# igne-agency/laravel-permissions

Builds permission names and permission lists from the enums an app defines, on top of [spatie/laravel-permission](https://github.com/spatie/laravel-permission). The app keeps its spaces, permission enums, roles, labels, admin screens and seeder; the package only turns enum cases into the strings Spatie stores.

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
permissions()->in(AppSpace::ADMIN_PORTAL)->withAccess()->except(ResourcePermission::INVOICE)->all();
permissions()->all();
```

## Docs

- [Installation](docs/installation.md): requirements, publishing the config, the config keys, versioning.

# igne/laravel-permissions

Builds permission names and permission lists from the enums an app defines, on top of [spatie/laravel-permission](https://github.com/spatie/laravel-permission). The app keeps its spaces, permission enums, roles, labels, admin screens and seeder; the package only turns enum cases into the strings Spatie stores.

## Requirements

- PHP 8.4
- Laravel 12 or 13
- spatie/laravel-permission 7 or 8

## Install

```bash
composer config repositories.igne-permissions vcs https://github.com/IGNE-Agency/laravel-permissions.git
composer require igne/laravel-permissions:^1.0
php artisan vendor:publish --tag=permissions-config
```

The repository is private, so Composer needs a GitHub token. See [docs/installation.md](docs/installation.md).

## Example

```php
permissions()->in(AppSpace::ADMIN_PORTAL)->access();                                  // 'nova:access'
permissions()->in(AppSpace::ADMIN_PORTAL)->for(ResourcePermission::DOCUMENT)->view(); // 'nova:document:view'
permissions()->in(AppSpace::ADMIN_PORTAL)->for(ResourcePermission::DOCUMENT)->any(PermissionAction::VIEW, PermissionAction::UPDATE);
permissions()->in(AppSpace::ADMIN_PORTAL)->withAccess()->except(ResourcePermission::INVOICE)->all();
permissions()->all();
```

## Docs

- [Installation](docs/installation.md): the private repository, the GitHub token for CI and deploys, publishing the config and its keys.

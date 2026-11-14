# igne-agency/laravel-permissions

Permission names, lists and checks for the models, action classes and enums of a Laravel app, on top of [spatie/laravel-permission](https://github.com/spatie/laravel-permission). A class becomes a permission subject by implementing one interface; the package discovers it and builds the names Spatie stores. The app keeps its roles, labels, admin screens and seeder.

## Requirements

- PHP 8.4
- Laravel 12 or 13
- spatie/laravel-permission 7 or 8

## Install

```bash
composer require igne-agency/laravel-permissions
php artisan vendor:publish --tag=permissions-config
```

## Docs

- [Installation](docs/installation.md): requirements, the config keys, versioning.

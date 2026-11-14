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

## Example

```php
final class Post extends Model implements PermissionSubject, UsesActions, UsesSpaces
{
    use HasPermissionKey;
    use UsesDefaultActions;

    public static function spaces(): array
    {
        return [Space::ADMIN];
    }
}
```

```php
permission()->in(Space::ADMIN)->update(Post::class);   // 'admin:post:update'
$user->can(permission()->update($post));               // the Gate fills in the user's space
permissions()->in(Space::ADMIN)->names();              // every name in the space, for a role
permissions()->all();                                  // every name, for the seeder
```

## Docs

- [Installation](docs/installation.md): requirements, the config keys, versioning.
- [Defining permissions](docs/defining-permissions.md): subjects, every option they can opt into, discovery, spaces and actions.
- [Building names](docs/building-names.md): `permission()` for one name, and what throws.
- [Permission lists](docs/permission-lists.md): `permissions()` for lists, every selection form, and handing a list to a role.
- [Checking permissions](docs/checking-permissions.md): checks through the Gate, the user's space, entry points and what Spatie's direct methods need.
- [Access guard](docs/access-guard.md): spaces whose access excludes each other.

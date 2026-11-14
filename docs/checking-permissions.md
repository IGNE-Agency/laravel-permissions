# Checking permissions

The names are the strings Spatie stores, so Laravel's Gate and Spatie's methods take them as they are. A name built without `in()` lets the Gate fill in the space of the user it checks, so a policy names only the permission.

## In a policy

```php
public function update(User $user, Post $post): bool
{
    return $user->can(permission()->update($post));
}
```

`permission()->update($post)` renders `{space}:post:update`. The package's Gate hook replaces `{space}` with the checked user's space and checks the concrete name, such as `admin:post:update`, as an ordinary Gate check: Spatie's hook and the app's own `before` and `after` callbacks apply to it. A subject without spaces has no placeholder and needs none of this.

## The user's space

The user model tells the Gate its space, or several:

```php
final class User extends Authenticatable implements HasPermissionSpace
{
    public function permissionSpace(): SpaceEnum|array
    {
        return $this->space;                       // one space: Space::ADMIN
        // return [Space::ADMIN, Space::PORTAL];   // several: granted when any of them grants
        // return [];                              // none: every placeholder check is denied
    }
}
```

With several spaces, `{space}:post:update` is checked as `admin:post:update` and as `portal:post:update`, and passes when either passes. How a user's spaces are decided is the app's rule: a column, a relation, the spaces whose `access` they hold. `permissionSpace()` must not check a name built without `in()`, because resolving that name calls it again.

## Entry points

The space always comes from the user the Gate checks:

| Entry point | Whose space |
|-------------|-------------|
| `$user->can()`, `cannot()`, `canAny()` | `$user` |
| `Gate::allows()`, `@can`, `$this->authorize()`, the `can:` middleware | The authenticated user |
| `Gate::forUser($other)->allows()` | `$other` |
| Spatie's `permission:` middleware | The authenticated user |

A guest is denied. Route middleware keeps the placeholder as a fixed string, so `route:cache` works:

```php
Route::get('/posts', PostIndexController::class)->middleware('can:'.permission()->viewAny(Post::class));
```

## A fixed space

When the space is not the user's own, name it: `$user->can(permission()->in(Space::ADMIN)->view(Post::class))`. Spatie's direct methods, such as `givePermissionTo()` and `hasPermissionTo()`, never reach the Gate and need this form; a placeholder name throws Spatie's `PermissionDoesNotExist` there.

## What throws and what is denied

| Situation | Result |
|-----------|--------|
| The checked user does not implement `HasPermissionSpace` | `PermissionException` |
| A guest | Denied, 403 from the middlewares |
| The resolved name was never seeded | Denied |
| A placeholder name in one of Spatie's direct methods | Spatie's `PermissionDoesNotExist` |

A placeholder check evaluates twice, so `GateEvaluated` events and `Gate::after` callbacks see `{space}:post:view` and then `admin:post:view`.

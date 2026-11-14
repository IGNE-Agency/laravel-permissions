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

## Rules the Gate applies

Three `Gate::before` hooks run ahead of Spatie's and the app's own. Each steps aside for a name it has no rule for:

1. **Space access**: in a space that requires access (every space, by default), a name is denied unless the user also holds the space's `access`.
2. **The placeholder**: replaced by the user's space, as above.
3. **Implied actions**: a name is granted when the user holds a name that implies it, such as `admin:post:update` for `admin:post:view` with the default config.

Both rules are configured in `config/permissions.php` and can be overridden per space enum or per subject; see [spaces that require access](defining-permissions.md#spaces-that-require-access) and [implied actions](defining-permissions.md#implied-actions).

The extra checks go through the Gate too, so revocations apply to them. Spatie's direct methods see only the stored names.

## What the package decides and what a policy decides

The subjects say which permissions exist and which grant which, about kinds of things. Whether a user may change one particular post, because they wrote it or belong to its team, belongs in the policy:

```php
return $user->can(permission()->update($post)) && $post->author()->is($user);
```

## Revoking and the permission cache

Spatie caches permissions for 24 hours and flushes the cache through its own methods. A change made around them shows after `php artisan permission:cache-reset`. A loaded user model keeps the roles it loaded; reload it with `$user->fresh()` where a revocation must count at once. The rules above add no cache of their own.

## What throws and what is denied

| Situation | Result |
|-----------|--------|
| The checked user does not implement `HasPermissionSpace` | `PermissionException` |
| A guest | Denied, 403 from the middlewares |
| The resolved name was never seeded | Denied |
| A placeholder name in one of Spatie's direct methods | Spatie's `PermissionDoesNotExist` |
| A name in a space that requires access, without that `access` | Denied, 403 from the middlewares |
| An implied action the subject does not list | `PermissionException` |

A placeholder check evaluates twice, so `GateEvaluated` events and `Gate::after` callbacks see `{space}:post:view` and then `admin:post:view`.

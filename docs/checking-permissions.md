# Checking permissions

The chain's names are the strings Spatie stores, so Laravel's Gate and Spatie's own methods take them as they are. A check built with `in($space)` works everywhere. A check built without `in()` lets the Gate fill in the space from the user it is checking, so a policy, a Blade view or a route names the permission and nothing else.

## One line in a policy

```php
public function view(User $user, Document $document): bool
{
    return $user->can(permissions()->for(ResourcePermission::DOCUMENT)->view());
}
```

Without `in()`, a space-using enum renders `Permissions::SPACE_PLACEHOLDER` where the space prefix would be: `'{space}:document:view'`, or `'app:{space}:document:view'` with a global prefix. Everything else about the name is final.

The package registers one `Gate::before` callback. For an ability that carries the placeholder it asks the user the Gate is checking for their space through `permissionSpace()`, replaces the placeholder with that space's normalized prefix, and checks the concrete name through `Gate::forUser($user)`. That inner check is an ordinary one: Spatie's hook, the app's own `before` and `after` callbacks and any `hasPermissionTo()` override apply to it, and its answer is the answer. An ability without the placeholder is left to the Gate untouched.

## The user model

The user model implements `Igne\Permissions\Contracts\HasPermissionSpace`:

```php
use Igne\Permissions\Contracts\HasPermissionSpace;
use Igne\Permissions\Contracts\SpaceEnum;

final class User extends Authenticatable implements HasPermissionSpace
{
    public function permissionSpace(): SpaceEnum
    {
        return $this->space();
    }

    public function space(): AppSpace
    {
        foreach (AppSpace::cases() as $space) {
            if ($this->can(permissions()->in($space)->access())) {
                return $space;
            }
        }

        throw new LogicException('This user has access to no space.');
    }
}
```

Which space a user is in is the app's rule: the space whose `access` permission they hold, a column, a relation. The one constraint is that `permissionSpace()` never builds a name without `in()`: the Gate would call it again to resolve that name. Inside it, build with `in($space)` as above.

## Where the Gate sees the right user

The space is taken from the user the Gate is checking, not from the authenticated user, so every entry point answers for the right person:

| Entry point | The user whose space is used |
|-------------|------------------------------|
| `$user->can()`, `cant()`, `cannot()`, `canAny()` | `$user` |
| `Gate::allows()`, `check()`, `authorize()`, `inspect()` | The authenticated user |
| `Gate::forUser($other)->allows()` | `$other` |
| `@can` and `@cannot` in Blade | The authenticated user |
| `$this->authorize()` in a controller or a Form Request's `authorize()` | The authenticated user |
| The `can:` route middleware | The authenticated user |
| Spatie's `permission:` route middleware, which checks through `canAny()` | The authenticated user |

A queued job, a listener or an Artisan command has no authenticated user and needs none: `$event->actor->can(...)` carries the user. A notification channel that checks `Gate::forUser($notifiable)` resolves from the notifiable.

Routes keep the placeholder as a fixed string, so `route:cache` works; the space is resolved per request:

```php
Route::get('/documents', [DocumentController::class, 'index'])
    ->middleware('can:'.permissions()->for(ResourcePermission::DOCUMENT)->viewAny());

Route::get('/documents', [DocumentController::class, 'index'])
    ->middleware('permission:'.permissions()->for(ResourcePermission::DOCUMENT)->viewAny());
```

Spatie 7 and 8 do not register the `permission` alias; the app registers `Spatie\Permission\Middleware\PermissionMiddleware` under it.

## A fixed space

When the space is not the checked user's own, say it. An admin panel that serves one space whoever the user is, a check against another portal, or a seeder:

```php
public function view(User $user, Document $document): bool
{
    return $user->can(permissions()->in(AppSpace::ADMIN_PORTAL)->for(ResourcePermission::DOCUMENT)->view());
}
```

The explicit name has no placeholder, so the hook ignores it and the user's own space plays no part.

## Spatie's direct methods take the explicit form

`hasPermissionTo()`, `hasAnyPermission()`, `hasAllPermissions()`, `givePermissionTo()`, `revokePermissionTo()`, `syncPermissions()` and the role methods never reach the Gate: they look the name up in the permissions table, and a placeholder name throws Spatie's `PermissionDoesNotExist`. Build those names with `in($space)`:

```php
$user->givePermissionTo(permissions()->in(AppSpace::CUSTOMER_PORTAL)->for(ResourcePermission::DOCUMENT)->view());
$user->hasPermissionTo(permissions()->in(AppSpace::CUSTOMER_PORTAL)->for(ResourcePermission::DOCUMENT)->view());
```

## What throws and what is denied

| Situation | Result |
|-----------|--------|
| The checked user does not implement `HasPermissionSpace` | `Igne\Permissions\Exceptions\PermissionException`, a `LogicException`: adopt the contract or build the name with `in($space)` |
| A guest: `Gate::allows()`, `@can` or the `can:` middleware with nobody authenticated | Denied, as for any ability. The `can:` middleware answers 403; Spatie's `permission:` middleware answers 403 as well |
| The resolved name was never seeded, for example a permission the user's space does not offer | Denied: Spatie finds no such permission |
| A placeholder name given to one of Spatie's direct methods | Spatie's `PermissionDoesNotExist` |

## Events and logs

A placeholder check evaluates twice: the placeholder ability and then the concrete name. `Illuminate\Auth\Access\Events\GateEvaluated` fires for both, and `Gate::after` callbacks see both, so a log of Gate decisions shows `{space}:document:view` followed by `nova:document:view`. The outer result is the inner one.

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

For an ability that carries the placeholder, the package's Gate hook asks the user the Gate is checking for their space through `permissionSpace()`, replaces the placeholder with that space's normalized prefix, and checks the concrete name through `Gate::forUser($user)`. That inner check is an ordinary one: Spatie's hook, the app's own `before` and `after` callbacks and any `hasPermissionTo()` override apply to it, and its answer is the answer.

An app without spaces skips all of this: its names have no placeholder, its user model needs no contract, and `$user->can(permissions()->for(DocumentPermission::DOCUMENT)->view())` is an ordinary check.

## Rules the Gate applies

The package registers three `Gate::before` callbacks, when the Gate is first built and so ahead of Spatie's and the app's own. Each one steps aside for an ability it has no rule for, so an app that adopts neither contract below sees only the placeholder resolution.

1. **Space access**, for a space enum that implements `RequiresAccess`. A name in a listed space is denied unless the checked user also holds that space's `access` permission. With access, the hook steps aside. It runs first because a before callback can only deny by answering before another one grants.
2. **The placeholder**, as above. The concrete name goes through all three rules again.
3. **Implied actions**, for a permission enum that implements `ImpliesActions`. A name is granted when the checked user holds a name that implies it, such as `nova:document:update` for `nova:document:view`. Those names are checked through the Gate as well, so a revocation or the access rule applies to them. Otherwise the hook steps aside.

The access check goes through the Gate too, so an app callback that grants every ability to a super-admin grants their `access` as well. One that grants a single name does not lift the access rule for it.

Both rules are a decision of the Gate only. Spatie's direct methods described below see only the names that were stored: `hasPermissionTo('nova:document:view')` is false for a user who holds only `nova:document:update`, and true for a user without `nova:access`.

## What the package decides and what a policy decides

Zanzibar, Google's authorization system, separates the schema that says which relations imply which from the data that says who holds what. This package takes the same split one level up. The enums are the schema: which permissions exist, in which spaces, with which actions, and which actions or space access a check implies. Spatie's tables are the data: which role and which user holds which name.

Both are about types of things, never about one record. Whether this user may update this particular document because they wrote it, belong to its team or were invited to it is an object-level question, and it belongs in the app's policy:

```php
public function update(User $user, Document $document): bool
{
    return $user->can(permissions()->for(ResourcePermission::DOCUMENT)->update())
        && $document->team()->is($user->team);
}
```

The package answers the first half and will not grow a store of per-record relations. An app that needs per-record sharing graphs at scale wants a dedicated authorization service such as OpenFGA or SpiceDB, not a longer list of names.

## Revoking and the permission cache

A revoked permission is not refused everywhere the same instant. Two caches sit between the database and a check:

- **Spatie's permission cache** holds every permission with its roles, for 24 hours by default, in the app's cache store. Spatie's own role and permission methods flush it. A change made around them, by a query, a seeder that skips `forgetCachedPermissions()` or a deploy that changes the database under a running app, stays invisible until the cache expires or `php artisan permission:cache-reset` runs.
- **The loaded user model** keeps the roles and direct permissions it loaded. Spatie unloads them on the instance it changes, not on other instances of the same user. A queued job, a long-running worker or an Octane request that already holds the user keeps answering from its copy; reload it with `$user->fresh()` or `$user->unsetRelation('roles')->unsetRelation('permissions')` where a revocation must count at once.

Both rules above resolve to stored names, so they inherit exactly this window and add none of their own.

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
| A name in a space that requires access, for a user without that space's `access` | Denied, whatever grants the name. The `can:` and `permission:` middlewares answer 403 |
| An `ImpliesActions` enum whose `impliedActions()` names an action missing from its `actions()` | `PermissionException` on the first check of that enum's names |

## Events and logs

A placeholder check evaluates twice: the placeholder ability and then the concrete name. `Illuminate\Auth\Access\Events\GateEvaluated` fires for both, and `Gate::after` callbacks see both, so a log of Gate decisions shows `{space}:document:view` followed by `nova:document:view`. The outer result is the inner one.

The rules add evaluations the same way. The access rule checks the space's `access` name before a name in that space, and the implication rule checks the implying names before the name itself, so with both a log can show `nova:access`, `nova:document:update` and then `nova:document:view`.

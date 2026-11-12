# Access guard

Some spaces exclude each other: a user is a member of staff in the company portal or a customer in the customer portal, never both. The access guard enforces that no user ever effectively holds the `access` permissions of two such spaces at once, whether the access would come from a direct grant or from a role.

## Declare the exclusive spaces

The space enum implements `Igne\Permissions\Contracts\HasExclusiveAccess`:

```php
use Igne\Permissions\Contracts\HasExclusiveAccess;
use Igne\Permissions\Contracts\SpaceEnum;

enum AppSpace: string implements HasExclusiveAccess, SpaceEnum
{
    case ADMIN_PORTAL = 'adminPortal';
    case COMPANY_PORTAL = 'companyPortal';
    case CUSTOMER_PORTAL = 'customerPortal';

    public function spacePrefix(): string { /* … */ }

    public static function exclusiveAccessSpaces(): array
    {
        return [self::COMPANY_PORTAL, self::CUSTOMER_PORTAL];
    }
}
```

The package collects the exclusive spaces from the space enums that registered permission enums declare in `spaces()`. Admin portal access combines freely with either of the other two.

## Check before granting

Resolve `Igne\Permissions\AccessGuard` from the container and call it before Spatie's `givePermissionTo()`, `syncPermissions()`, `assignRole()` or `syncRoles()`:

```php
use Igne\Permissions\AccessGuard;
use Igne\Permissions\Exceptions\ConflictingAccessException;

final class GrantPermissionsAction
{
    public function __construct(private readonly AccessGuard $accessGuard) {}

    public function handle(User $user, array $permissions): void
    {
        $this->accessGuard->assertCanGrant($user, $permissions);

        $user->givePermissionTo($permissions);
    }
}
```

| Method | Throws | Returns |
|--------|--------|---------|
| `assertCanGrant($user, iterable $permissions)` | `ConflictingAccessException` when the grant would leave the user with two exclusive accesses | `void` |
| `assertCanAssignRoles($user, iterable $roles)` | The same, for the permissions the roles carry | `void` |
| `wouldConflict($user, iterable $permissions)` | Nothing | `bool` |
| `wouldRoleConflict($user, iterable $roles)` | Nothing | `bool` |

`$user` is any `Authorizable` Eloquent model. Permissions are names, backed enums, Spatie permission models, or nested iterables of them; roles are names, backed enums, Spatie role models, or nested iterables of them. Anything else throws a `PermissionException`.

The guard evaluates the user's existing access through `$user->can()`, so role grants count and an app's per-user revocations are respected. Roles are looked up by name within the user's guard; a role that does not exist is skipped.

## Tell the user

`ConflictingAccessException` carries both spaces as `$first` and `$second` and has an English developer message. The app writes the translated sentence, typically from the spaces' own labels:

```php
try {
    $this->accessGuard->assertCanGrant($user, $permissions);
} catch (ConflictingAccessException $exception) {
    throw ValidationException::withMessages([
        'permissions' => __('auth.conflicting_access', [
            'first' => $exception->first->label(),
            'second' => $exception->second->label(),
        ]),
    ]);
}
```

In a policy, `wouldConflict()` answers the question without throwing, which suits a "can this admin grant this permission?" check.

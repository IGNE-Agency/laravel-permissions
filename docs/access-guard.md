# Access guard

Some spaces exclude each other. A user belongs to the staff portal or to the customer portal, never both. Spatie lets you grant anything, so the package offers a check to run first: `Igne\Permissions\AccessGuard`. It answers whether a grant or a role would leave the user holding the `access` permission of two such spaces at once.

The guard only checks. It never grants anything, and nothing calls it for you: put it in front of every place where your app grants permissions or assigns roles.

## 1. Declare which spaces exclude each other

The space enum implements `HasExclusiveAccess` and lists the spaces that may not be combined:

```php
use Igne\Permissions\Contracts\HasExclusiveAccess;
use Igne\Permissions\Contracts\SpaceEnum;

enum Space: string implements HasExclusiveAccess, SpaceEnum
{
    case ADMIN = 'admin';
    case STAFF = 'staff';
    case CUSTOMER = 'customer';

    public function spacePrefix(): string
    {
        return $this->value;
    }

    public static function exclusiveAccessSpaces(): array
    {
        return [self::STAFF, self::CUSTOMER];
    }
}
```

A user may now hold `staff:access` or `customer:access`, not both. `admin:access` combines with either.

## 2. Validate the request that grants

The form request behind the screen where an administrator assigns roles is the natural place. Laravel injects `AccessGuard` into the request's `after()` method, and the conflict becomes a validation error on the field:

```php
use Igne\Permissions\AccessGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateUserRolesRequest extends FormRequest
{
    public function rules(): array
    {
        return ['roles' => ['required', 'array'], 'roles.*' => ['string', 'exists:roles,name']];
    }

    public function after(AccessGuard $accessGuard): array
    {
        return [
            function (Validator $validator) use ($accessGuard): void {
                if ($accessGuard->wouldRoleConflict($this->route('user'), $this->input('roles', []))) {
                    $validator->errors()->add('roles', __('users.roles.conflicting_access'));
                }
            },
        ];
    }
}
```

For a screen that grants permissions directly, use `wouldConflict($user, $permissions)` the same way.

## 3. Guard the action that grants

Validation covers the screen. Every other caller of the grant, such as an import, a command or a job, goes through the action that does the grant. Inject `AccessGuard` through the constructor and assert before calling Spatie:

```php
use Igne\Permissions\AccessGuard;

final class SyncUserRolesAction
{
    public function __construct(private readonly AccessGuard $accessGuard) {}

    /**
     * @param  list<string>  $roles
     */
    public function handle(User $user, array $roles): void
    {
        $this->accessGuard->assertCanAssignRoles($user, $roles);

        $user->syncRoles($roles);
    }
}
```

`assertCanAssignRoles()` throws a `ConflictingAccessException` when the roles would combine exclusive spaces; nothing is saved because the throw comes before `syncRoles()`.

## The four methods

| Method | Use it | When there is a conflict |
|--------|--------|--------------------------|
| `wouldConflict($user, $permissions)` | In validation and policies, before granting permissions | Returns `true` |
| `wouldRoleConflict($user, $roles)` | In validation and policies, before assigning roles | Returns `true` |
| `assertCanGrant($user, $permissions)` | In the action, right before `givePermissionTo()` or `syncPermissions()` | Throws `ConflictingAccessException` |
| `assertCanAssignRoles($user, $roles)` | In the action, right before `assignRole()` or `syncRoles()` | Throws `ConflictingAccessException` |

- `$user` is the user that receives the grant.
- Permissions may be names, backed enums, Spatie permission models, or lists of them.
- Roles may be names, backed enums, Spatie role models, or lists of them. A role name that does not exist is skipped.

The guard reads what the user already has through `$user->can()`, so access that comes from a role counts, and so do per-user revocations.

## The exception

`ConflictingAccessException` carries both spaces as `$first` and `$second`. Its message is for developers. When the exception reaches a user, the app writes the sentence:

```php
try {
    $syncUserRoles->handle($user, $roles);
} catch (ConflictingAccessException $exception) {
    throw ValidationException::withMessages([
        'roles' => __('users.roles.conflicting_access', [
            'first' => $exception->first->label(),
            'second' => $exception->second->label(),
        ]),
    ]);
}
```

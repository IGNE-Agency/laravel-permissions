# Building names

Two helpers build names from the current config. `permission()` returns one name at a time, for a check or a single grant. `permissions()` returns lists, for seeding and roles; see [Permission lists](permission-lists.md). Every space, subject and action is a class or an enum case passed as an argument, so the editor completes all of them and PHPStan checks them. There are no magic methods and no string names.

## One name: `permission()`

```php
permission()->in(AppSpace::ADMIN_PORTAL)->access();                               // 'nova:access'
permission()->in(AppSpace::ADMIN_PORTAL)->view(Organization::class);               // 'nova:organization:view'
permission()->in($space)->update($organization);                                  // an instance works as well as its class
permission()->in($space)->viewAny(Organization::class);                           // also view, create, update, delete, restore, destroy
permission()->in($space)->action(ReportAction::RUN, Report::class);               // any action enum, also app-defined
permission()->in($space)->any(Organization::class, PermissionAction::VIEW, PermissionAction::UPDATE); // list<string>, for canAny()
permission()->in($space)->name(GuardPermission::SETUP_TWO_FACTOR);                // a subject without actions
permission()->name(ExportReports::class);                                         // a subject without spaces
permission()->view(Organization::class);                                          // '{space}:organization:view', for Gate checks only
```

A subject is a `PermissionSubject` class, an instance of one, or a `PermissionEnum` case.

| Call | Returns | Needs |
|------|---------|-------|
| `in(SpaceEnum $space)` | `Permission` | A subject that uses spaces |
| `->access()` | `string` | `in()` first |
| `->viewAny($subject)` … `->destroy($subject)` | `string` | The subject lists that `PermissionAction` case |
| `->action(PermissionActionEnum $action, $subject)` | `string` | The subject lists that action |
| `->any($subject, PermissionActionEnum ...$actions)` | `list<string>` | The subject lists every given action |
| `->name($subject)` | `string` | A subject without actions |

Every terminal returns a plain `string` or a `list<string>`. Laravel's Gate turns objects it does not know into arrays without an error, so nothing else leaves the builder.

## The space placeholder

Without `in()`, a subject that uses spaces renders `Permissions::SPACE_PLACEHOLDER`, the literal `{space}`, in the space position: `'{space}:organization:view'`, or `'app:{space}:organization:view'` with a global prefix. The placeholder is inserted as is, never normalized. It is only for checks through Laravel's Gate, where the package's hook replaces it with the checked user's space; see [Checking permissions](checking-permissions.md). Lists never carry it.

## Using the names

The strings are what Spatie stores, so they go wherever Spatie expects a permission name:

```php
$user->can(permission()->in(AppSpace::ADMIN_PORTAL)->update(Organization::class));

$user->givePermissionTo(permission()->in(AppSpace::CUSTOMER_PORTAL)->access());

Route::middleware('permission:'.permission()->in(AppSpace::ADMIN_PORTAL)->name(GuardPermission::ACCESS_API_DOCS));
```

In a policy, leave the space out: the Gate takes it from the user it is checking, through the `HasPermissionSpace` contract on the user model. Keep `in($space)` where the space is fixed or where the name goes to one of Spatie's direct methods. [Checking permissions](checking-permissions.md) covers every entry point.

```php
public function update(User $user, Organization $organization): bool
{
    return $user->can(permission()->update($organization));
}
```

## What throws and why

Every failure is an `Igne\Permissions\Exceptions\PermissionException`, a `LogicException`. These are developer errors in a call site or in the config, not runtime conditions: do not catch them, fix the call.

| Call | Throws because |
|------|----------------|
| `->view($subject)` on a subject that does not list `view` | The name would never be seeded. The message lists the actions the subject has. |
| `->view()` or `->action()` on a subject without actions | Call `->name()` instead. |
| `->name()` on a subject with actions | The subject needs an action to make a full name. |
| `->access()` without `in()` | Access belongs to a space. |
| `in($space)` with a subject that does not use spaces | The space would be ignored; leave `in()` out. |
| A subject that is neither discovered nor listed in `permissions.subjects` | A name for it would not appear in `permissions()->all()`. |
| A class that is not a subject | Only subjects have names. |
| Any call with an unknown `normalizer`, a `subjects` entry that is not a subject, or a `discover` folder that does not exist | The config is wrong. |

## Names are stored data

The output is written to Spatie's `permissions` table and to pivot tables. Every installing app depends on the strings staying byte-identical, so:

- The format `[prefix]:[spacePrefix]:[group]:[key]:[action]`, the default separator `:`, the default `camel` normalizer, the built-in `access` permission and the `PermissionAction` values are fixed. Changing any of them is a major version of this package.
- Changing `separator`, `normalizer` or `prefix` in an app that already has permissions in its database is a data migration in that app.
- Renaming a subject class that uses `HasPermissionKey`, or an enum case value, renames a permission. Define `permissionKey()` to keep the old name, or migrate the stored rows.

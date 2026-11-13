# Building names

`permissions()` returns a fresh `Igne\Permissions\Permissions` built from the current config. Every space, permission and action in the chain is an enum case passed as an argument, so the editor completes all of them and PHPStan checks them. There are no magic methods and no string names.

## The chain

```php
permissions()->in(AppSpace::ADMIN_PORTAL)->access();                                   // 'nova:access'
permissions()->in(AppSpace::ADMIN_PORTAL)->for(ResourcePermission::DOCUMENT)->view();  // 'nova:document:view'
permissions()->in($space)->for($case)->viewAny();                                     // also view, create, update, delete, restore, destroy
permissions()->in($space)->for($case)->action(ReportAction::RUN);                     // any action enum, also app-defined
permissions()->in($space)->for($case)->any(PermissionAction::VIEW, PermissionAction::UPDATE); // list<string>
permissions()->in($space)->for(GuardPermission::SETUP_TWO_FACTOR)->name();            // an enum without actions
permissions()->for(FeaturePermission::EXPORT_REPORTS)->name();                        // an enum without spaces
permissions()->for(ResourcePermission::DOCUMENT)->view();                             // '{space}:document:view', for Gate checks only
permissions()->only(ResourcePermission::DOCUMENT, PermissionAction::VIEW)->names();  // every space the enum declares; see Permission lists
permissions()->all();                                                                 // every name, every space
permissions()->in($space)->all();                                                     // every name in one space, access last
```

| Call | Returns | Needs |
|------|---------|-------|
| `in(SpaceEnum\|array $space)` | `SpaceScope` | One space for names; several only for whole-space lists |
| `->access()` | `string` | Exactly one space |
| `->for(PermissionEnum $case)` | `PermissionSelection` | After `in()`: a `UsesSpaces` enum. Without `in()`: any registered enum; a `UsesSpaces` enum then renders the `{space}` placeholder |
| `->viewAny()` … `->destroy()` | `string` | The enum lists that `PermissionAction` case |
| `->action(PermissionActionEnum $action)` | `string` | The enum lists that action |
| `->any(PermissionActionEnum ...$actions)` | `list<string>` | The enum lists every given action |
| `->name()` | `string` | An enum without actions |
| `->all()` | `list<string>` | On `permissions()`: nothing. On `in()`: exactly one space |
| `->only()`, `->except()` | `PermissionQuery` | On `permissions()`: nothing. On `in()`: exactly one space. See [Permission lists](permission-lists.md) |

Every terminal returns a plain `string` or a `list<string>`. Laravel's Gate turns objects it does not know into arrays without an error, so nothing else leaves the chain.

## The space placeholder

`permissions()->for($case)` without `in()` on a space-using enum renders `Permissions::SPACE_PLACEHOLDER`, the literal `{space}`, in the space position: `'{space}:document:view'`, or `'app:{space}:document:view'` with a global prefix. The placeholder is inserted as is, never normalized. It is only for checks through Laravel's Gate, where the package's hook replaces it with the checked user's space; see [Checking permissions](checking-permissions.md). Lists never carry it: `all()`, `in($space)->all()` and the list builders always render a concrete space, so seeding output is unchanged.

## Using the names

The strings are what Spatie stores, so they go wherever Spatie expects a permission name:

```php
$user->can(permissions()->in(AppSpace::ADMIN_PORTAL)->for(ResourcePermission::DOCUMENT)->update());

$user->givePermissionTo(permissions()->in(AppSpace::CUSTOMER_PORTAL)->access());

Route::middleware('permission:'.permissions()->in(AppSpace::ADMIN_PORTAL)->for(GuardPermission::ACCESS_API_DOCS)->name());
```

In a policy, leave the space out: the Gate takes it from the user it is checking, through the `HasPermissionSpace` contract on the user model. Keep `in($space)` where the space is fixed or where the name goes to one of Spatie's direct methods. [Checking permissions](checking-permissions.md) covers every entry point.

```php
public function update(User $user, Document $document): bool
{
    return $user->can(permissions()->for(ResourcePermission::DOCUMENT)->update());
}
```

## What throws and why

Every failure is an `Igne\Permissions\Exceptions\PermissionException`, a `LogicException`. These are developer errors in a call site or in the config, not runtime conditions: do not catch them, fix the call.

| Call | Throws because |
|------|----------------|
| `->view()` on a case whose enum does not list `view` | The name would never be seeded. The message lists the actions the enum has. |
| `->view()` or `->action()` on an enum without actions | Call `->name()` instead. |
| `->name()` on an enum with actions | The enum needs an action to make a full name. |
| `->access()`, `->for()` or `->all()` after `in([...])` with several spaces | Names belong to one space; several spaces are for whole-space lists. |
| `in([])` | A scope needs a space. |
| `in($space)->for()` with an enum that does not use spaces | The space would be ignored; call `permissions()->for()`. |
| `->for()` with an enum missing from `permissions.types` | A name for an unregistered enum would not appear in `all()`. |
| `permissions()` with an unknown `normalizer` or a `types` entry that is not a permission enum | The config is wrong. |

The old builder silently dropped an action the enum did not have. Any call that relied on that now throws, which is the point: the name it produced was never created by the seeder.

## Names are stored data

The chain's output is written to Spatie's `permissions` table and to pivot tables. Every installing app depends on the strings staying byte-identical, so:

- The format `[prefix]:[spacePrefix]:[group]:[permission]:[action]`, the default separator `:`, the default `camel` normalizer, the built-in `access` permission and the `PermissionAction` values are fixed. Changing any of them is a major version of this package.
- Changing `separator`, `normalizer` or `prefix` in an app that already has permissions in its database is a data migration in that app.
- Renaming an enum case value renames a permission; the app migrates the stored rows.

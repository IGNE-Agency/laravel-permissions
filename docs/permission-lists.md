# Permission lists

The package defines no roles. The list builders only produce lists of permission names; the app decides which roles exist and which list each one gets, in its own seeder.

Two builders cover the two shapes a role definition takes. Both are immutable: every `only()` and `except()` returns a new query, `names()` renders the names, and a query also counts and iterates.

## One enum: `only()` and `except()`

`permissions()->in($space)->only(...)` and `->except(...)` filter the names of a single enum within one space. The enum is the one the first selected item belongs to.

```php
permissions()->in(AppSpace::ADMIN_PORTAL)->only(GuardPermission::SETUP_TWO_FACTOR)->names();
// ['nova:setupTwoFactor']

permissions()->in(AppSpace::ADMIN_PORTAL)->only(ResourcePermission::DOCUMENT, [PermissionAction::VIEW_ANY, PermissionAction::VIEW])->names();
// ['nova:document:viewAny', 'nova:document:view']

permissions()->in(AppSpace::ADMIN_PORTAL)->only(PermissionAction::VIEW)->names();
// every 'nova:…:view' of the first registered enum that lists view

permissions()->in(AppSpace::ADMIN_PORTAL)->except([PermissionAction::DELETE, PermissionAction::DESTROY])->names();
// that enum's names without delete and destroy
```

A selection is a permission case, a list of cases of the same enum, an action case, or a list of actions. The optional second argument narrows permission cases to the given actions; without it a case expands to all of its actions.

Chained calls are additive: each `only()` unions its names into the allow-list, each `except()` into the deny-list, and `names()` returns the allow-list minus the deny-list. A case the enum does not offer in the space yields nothing, never the whole enum.

An enum that does not use spaces renders without a prefix, whatever `in()` says.

## Without a space: `permissions()->only()` and `except()`

The same two builders work straight on `permissions()`, for an app without spaces and for a list that spans every space of one enum:

```php
permissions()->only(DocumentPermission::DOCUMENT, [PermissionAction::VIEW_ANY, PermissionAction::VIEW])->names();
// an enum without spaces: ['document:viewAny', 'document:view']

permissions()->only(ResourcePermission::DOCUMENT, [PermissionAction::VIEW_ANY, PermissionAction::VIEW])->names();
// a space-using enum, in every space it declares:
// ['nova:document:viewAny', 'nova:document:view', 'clientPortal:document:viewAny', 'clientPortal:document:view']
```

A space-using enum renders its names space by space in the order of its `spaces()`, the way `permissions()->all()` lists them, and a case the enum does not offer in a space is left out there. The result equals the per-space `in($space)->only(...)` lists joined, never the `{space}` placeholder, so it is safe to seed and to sync. Selections, chaining and what throws are the same as after `in()`.

## Whole spaces: `withAccess()`

`permissions()->in($space)->withAccess()` builds across every registered enum in the space at once and guarantees the space's `access` permission in the result. Pass a list of spaces to build several at once.

```php
permissions()->in(AppSpace::ADMIN_PORTAL)->withAccess()->names();
// every name in the admin portal, access last

permissions()->in(AppSpace::ADMIN_PORTAL)
    ->withAccess()
    ->except([
        RoleManagementPermission::ASSIGN_ROLES,
        [ResourcePermission::INVOICE, [PermissionAction::DELETE, PermissionAction::DESTROY]],
    ])
    ->names();
// everything except assigning roles and destroying invoices

permissions()->in([AppSpace::ADMIN_PORTAL, AppSpace::COMPANY_PORTAL])
    ->withAccess()
    ->only([
        GuardPermission::SETUP_TWO_FACTOR,
        [ResourcePermission::CLIENT_CASE, [PermissionAction::VIEW_ANY, PermissionAction::VIEW]],
    ])
    ->onlyFor(AppSpace::ADMIN_PORTAL, ResourcePermission::DOCUMENT)
    ->names();
```

A selection is a permission case or a list whose entries are cases or `[permission, actions]` pairs. A pair's actions are one action case or a list of them. Bare actions are refused here: with several enums in play an action alone does not say which permissions it belongs to.

### Order matters

The operations replay per space in the order they were called, and the first one sets the mode:

- **`only()` first** restricts: the result starts empty, each `only()` adds its names, each `except()` removes names.
- **`except()` first** caps: the result starts as the whole space, each `except()` removes names, and each later `only()` restricts the permissions it names to exactly their listed actions while leaving everything else included.

So `except(ARTICLE)->only([[ARTICLE, [VIEW]]])` reads as "drop the article resource, then add back viewing it", and a lone `only(...)` still means "only these".

`onlyFor($space, ...)` and `exceptFor($space, ...)` pin one operation to one space; `only()` and `except()` apply to every space in `in()`. The result is re-ordered to the canonical listing of each space.

## Handing a list to a role

The app owns the roles. A seeder reads the lists and syncs them to Spatie:

```php
use Spatie\Permission\Models\Role;

final class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (permissions()->all() as $name) {
            Permission::findOrCreate($name);
        }

        Role::findOrCreate('admin')->syncPermissions(
            permissions()->in(AppSpace::ADMIN_PORTAL)->withAccess()->names(),
        );

        Role::findOrCreate('lawyer')->syncPermissions(
            permissions()->in(AppSpace::COMPANY_PORTAL)
                ->withAccess()
                ->except([
                    [ResourcePermission::CLIENT_CASE, [PermissionAction::DELETE, PermissionAction::DESTROY]],
                ])
                ->names(),
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
```

## What throws

Every misuse throws `Igne\Permissions\Exceptions\PermissionException` at the call, before `names()`:

- a selection mixing cases of different enums, or a case of an enum missing from `permissions.types`;
- an action no registered enum lists, when the action alone names the enum;
- `only()` or `except()` after `in([...])` with several spaces (use `withAccess()` for several spaces);
- in a whole-space query: a bare action, a second argument next to a list selection, a pair whose first element is not a permission case, or a pair whose actions are not action cases.

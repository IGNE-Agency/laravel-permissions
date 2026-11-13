# Permission lists

The package defines no roles. `permissions()` only produces lists of names; the app decides which roles exist and which list each one gets, in its own seeder.

## Whole lists

```php
permissions()->all();                                                // every name: subjects without spaces, then each space
permissions()->in(AppSpace::ADMIN_PORTAL)->names();                  // every name in the admin portal, access last
permissions()->in([AppSpace::ADMIN_PORTAL, AppSpace::COMPANY_PORTAL])->names(); // both spaces in turn
```

`in()` returns a `PermissionQuery`. It is immutable: every `only()` and `except()` returns a new query, `names()` renders the names, and a query also counts and iterates. Lists never carry the `{space}` placeholder, so they are safe to seed and to sync.

## `only()` and `except()`

```php
permissions()->in(AppSpace::ADMIN_PORTAL)->only(Organization::class)->names();
// every organization name in the admin portal

permissions()->in(AppSpace::ADMIN_PORTAL)->only(Organization::class, [PermissionAction::VIEW_ANY, PermissionAction::VIEW])->names();
// ['nova:organization:viewAny', 'nova:organization:view']

permissions()->in(AppSpace::ADMIN_PORTAL)->only(PermissionAction::VIEW)->names();
// view on every subject in the admin portal that lists it

permissions()->in(AppSpace::ADMIN_PORTAL)->except([PermissionAction::DELETE, PermissionAction::DESTROY])->names();
// the whole admin portal without deleting anything
```

A selection is one item or a list of items. An item is:

- a subject: a `PermissionSubject` class, an instance, or a `PermissionEnum` case, with all of its actions;
- a bare action: that action on every subject in the scope that lists it;
- a `[subject, actions]` pair, where actions is one action case or a list;
- a `Subject::class => actions` entry, the same as a pair.

The optional second argument applies to every subject in the selection:

```php
permissions()->in(AppSpace::ADMIN_PORTAL)->only([
    GuardPermission::SETUP_TWO_FACTOR,
    [Organization::class, [PermissionAction::VIEW_ANY, PermissionAction::VIEW]],
    Invoice::class => [PermissionAction::VIEW],
])->names();

permissions()->in(AppSpace::ADMIN_PORTAL)->only([Organization::class, Invoice::class], PermissionAction::VIEW)->names();
```

Entries for the same subject in one call merge their actions. A subject the scope does not offer yields nothing, never the whole scope.

### Without `in()`

`permissions()->only()` and `->except()` work across every scope at once: the subjects without spaces, then every space. A subject that uses spaces renders in each space it declares:

```php
permissions()->only(Organization::class, PermissionAction::VIEW)->names();
// ['nova:organization:view', 'customerPortal:organization:view']

permissions()->only(ExportReports::class)->names();
// ['exportReports']
```

The result equals the per-scope lists joined.

### Order matters

The operations replay per space in the order they were called, and the first one sets the mode:

- **`only()` first** restricts: the result starts empty, each `only()` adds its names, each `except()` removes names.
- **`except()` first** caps: the result starts as the whole space, each `except()` removes names, and each later `only()` restricts the subjects it names to exactly their listed actions while leaving everything else included.

So `except(Organization::class)->only(Organization::class, PermissionAction::VIEW)` reads as "drop organizations, then add back viewing them", and a lone `only(...)` still means "only these". The result is re-ordered to the scope's own listing.

### Access and single spaces

`withAccess()` keeps each space's `access` permission in the result whatever the operations select, which a role restricted with `only()` usually needs. `onlyFor($space, ...)` and `exceptFor($space, ...)` pin one operation to one space; `only()` and `except()` apply to every space in `in()`.

```php
permissions()->in([AppSpace::ADMIN_PORTAL, AppSpace::COMPANY_PORTAL])
    ->withAccess()
    ->only(GuardPermission::SETUP_TWO_FACTOR)
    ->onlyFor(AppSpace::ADMIN_PORTAL, Organization::class)
    ->names();
```

## Handing a list to a role

The app owns the roles. A seeder reads the lists and syncs them to Spatie:

```php
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (permissions()->all() as $name) {
            Permission::findOrCreate($name);
        }

        Role::findOrCreate('admin')->syncPermissions(
            permissions()->in(AppSpace::ADMIN_PORTAL)->names(),
        );

        Role::findOrCreate('lawyer')->syncPermissions(
            permissions()->in(AppSpace::COMPANY_PORTAL)
                ->except(ClientCase::class, [PermissionAction::DELETE, PermissionAction::DESTROY])
                ->names(),
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
```

## What throws

Every misuse throws `Igne\Permissions\Exceptions\PermissionException` at the call, before `names()`:

- `in([])`, or an empty selection;
- a subject that is neither discovered nor listed, or a value that is not a subject;
- an action the subject does not list, or actions for a subject without actions;
- a bare action no registered subject lists;
- a second argument next to a bare action, a pair or a keyed entry;
- a pair or keyed entry whose actions are not action cases.

# Permission lists

`permissions()` builds lists of names, for seeding and for roles. The package defines no roles; the app's seeder hands each list to the role it belongs to.

## The example app

Every example on this page uses these subjects:

| Subject | Spaces | Actions | Names |
|---------|--------|---------|-------|
| `Post` | `Space::ADMIN`, `Space::PORTAL` | the seven default actions | `admin:post:viewAny` … `admin:post:destroy`, `portal:post:viewAny` … `portal:post:destroy` |
| `Comment` | `Space::ADMIN` | the seven default actions | `admin:comment:viewAny` … `admin:comment:destroy` |
| `FeaturePermission::EXPORT_DATA` | `Space::ADMIN` | none | `admin:exportData` |
| `PublishPost` | none | none | `publishPost` |

Each space also has its `access` permission: `admin:access` and `portal:access`.

## Whole lists

| Call | Returns |
|------|---------|
| `permissions()->all()` | `publishPost`, then every `admin:` name with `admin:access` last, then every `portal:` name with `portal:access` last |
| `permissions()->in(Space::ADMIN)->names()` | Every `admin:` name: the post names, the comment names, `admin:exportData`, `admin:access` |
| `permissions()->in([Space::ADMIN, Space::PORTAL])->names()` | Every `admin:` name, then every `portal:` name |

`in()` returns a query. A query never changes: every call below returns a new one. `names()` renders it as a list of strings, and a query can also be counted and looped over.
Lists never contain the `{space}` placeholder, so every name can be seeded.

## Selections

`only()` keeps the names a selection points at; `except()` removes them. Both take a selection in one of these forms.

**One subject, all of its actions.** Pass the class, an instance, or an enum case:

```php
permissions()->in(Space::ADMIN)->only(Post::class)->names();
// ['admin:post:viewAny', 'admin:post:view', 'admin:post:create', 'admin:post:update', 'admin:post:delete', 'admin:post:restore', 'admin:post:destroy']
```

**One subject, some of its actions.** Pass one action, or a list of actions, as the second argument:

```php
permissions()->in(Space::ADMIN)->only(Post::class, PermissionAction::VIEW)->names();
// ['admin:post:view']

permissions()->in(Space::ADMIN)->only(Post::class, [PermissionAction::VIEW, PermissionAction::UPDATE])->names();
// ['admin:post:view', 'admin:post:update']
```

**Several subjects, the same actions.** A list of subjects with the actions as the second argument:

```php
permissions()->in(Space::ADMIN)->only([Post::class, Comment::class], PermissionAction::VIEW)->names();
// ['admin:post:view', 'admin:comment:view']
```

**One action on every subject.** Pass the action on its own. Every subject in the space that lists it is included:

```php
permissions()->in(Space::ADMIN)->only(PermissionAction::VIEW)->names();
// ['admin:post:view', 'admin:comment:view']
```

**Several subjects, each with its own actions.** A list of `[subject, actions]` pairs, or a list keyed by subject class. A bare subject in the same list keeps all of its actions:

```php
permissions()->in(Space::ADMIN)->only([
    [Post::class, [PermissionAction::VIEW, PermissionAction::UPDATE]],
    Comment::class,
    FeaturePermission::EXPORT_DATA,
])->names();
// ['admin:post:view', 'admin:post:update', every comment name, 'admin:exportData']

permissions()->in(Space::ADMIN)->only([
    Post::class => [PermissionAction::VIEW],
    Comment::class => [PermissionAction::VIEW, PermissionAction::DELETE],
])->names();
// ['admin:post:view', 'admin:comment:view', 'admin:comment:delete']
```

When the same subject appears twice in one list, its actions are added together.

A subject that the space does not have selects nothing: `permissions()->in(Space::PORTAL)->only(Comment::class)->names()` is `[]`.

## Combining calls

Calls are applied in the order they are written, and the first one decides where the list starts:

- **Starting with `only()`**, the list starts empty. Each `only()` adds names, each `except()` removes names.
- **Starting with `except()`**, the list starts with the whole space. Each `except()` removes names. A later `only()` limits each subject it names to the listed actions and leaves every other subject as it is.

```php
permissions()->in(Space::ADMIN)
    ->only(Post::class)
    ->except(Post::class, PermissionAction::DESTROY)
    ->names();
// the six post names without 'admin:post:destroy'

permissions()->in(Space::ADMIN)
    ->except(Comment::class)
    ->only(Post::class, PermissionAction::VIEW)
    ->names();
// ['admin:post:view', 'admin:exportData', 'admin:access']: comments are removed, posts are limited to view
```

The result is always in the space's own order, whatever order the calls ran in.

## Access

A list that starts with `only()` leaves out the space's `access` permission unless it is selected. `withAccess()` adds it back:

```php
permissions()->in(Space::ADMIN)->only(Post::class, PermissionAction::VIEW)->names();
// ['admin:post:view']

permissions()->in(Space::ADMIN)->withAccess()->only(Post::class, PermissionAction::VIEW)->names();
// ['admin:post:view', 'admin:access']
```

## One space out of several

`only()` and `except()` apply to every space given to `in()`. `onlyFor()` and `exceptFor()` take the space as their first argument and apply to that space only; the other spaces stay complete:

```php
permissions()->in([Space::ADMIN, Space::PORTAL])
    ->exceptFor(Space::ADMIN, Comment::class)
    ->names();
// every admin name except the comment names, then every portal name

permissions()->in([Space::ADMIN, Space::PORTAL])
    ->onlyFor(Space::PORTAL, Post::class, PermissionAction::VIEW)
    ->names();
// every admin name, then ['portal:post:view']
```

## Without `in()`

`permissions()->only()` and `permissions()->except()` work on the whole app at once: the subjects without spaces, then each space a subject declares.

```php
permissions()->only(Post::class, PermissionAction::VIEW)->names();
// ['admin:post:view', 'portal:post:view']

permissions()->only(PublishPost::class)->names();
// ['publishPost']

permissions()->except(Post::class)->names();
// every name of the app except the post names in both spaces
```

## Handing a list to a role

```php
foreach (permissions()->all() as $name) {
    Permission::findOrCreate($name);
}

Role::findOrCreate('editor')->syncPermissions(
    permissions()->in(Space::ADMIN)->except(Post::class, PermissionAction::DESTROY)->names(),
);
```

## What throws

A `PermissionException`, at the call that receives the bad input:

- `in([])`, or `only([])` and `except([])`;
- a subject that is neither discovered nor listed in the config, or a value that is not a subject;
- an action the subject does not list, or actions for a subject without actions;
- an action on its own that no subject lists;
- a second argument next to an action on its own, a pair or a keyed list.

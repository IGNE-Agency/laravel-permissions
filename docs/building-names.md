# Building names

`permission()` builds one name, for a check or a single grant.
Lists come from `permissions()`; see [permission lists](permission-lists.md).

A subject is a subject class, an instance of it, or a permission enum case. Every call returns a plain string, the name Spatie stores.

| Call | Returns |
|------|---------|
| `permission()->in(Space::ADMIN)->view(Post::class)` | `admin:post:view` |
| `permission()->in(Space::ADMIN)->view($post)` | `admin:post:view` |
| `permission()->in(Space::ADMIN)->viewAny(Post::class)`, also `create`, `update`, `delete`, `restore`, `destroy` | `admin:post:viewAny` |
| `permission()->in(Space::ADMIN)->action(PostAction::PUBLISH, Post::class)` | `admin:post:publish` |
| `permission()->in(Space::ADMIN)->any(Post::class, PermissionAction::VIEW, PermissionAction::UPDATE)` | `['admin:post:view', 'admin:post:update']` |
| `permission()->in(Space::ADMIN)->name(FeaturePermission::EXPORT_DATA)` | `admin:exportData` |
| `permission()->name(PublishPost::class)` | `publishPost` |
| `permission()->in(Space::ADMIN)->access()` | `admin:access` |
| `permission()->view(Post::class)` | `{space}:post:view` |

The action methods need a subject with actions; `name()` needs one without.
`in()` is only for subjects that use spaces.

## The space placeholder

Without `in()`, a subject that uses spaces renders `{space}` where the space would be. The placeholder is for checks through Laravel's Gate, which replaces it with the checked user's space, so a policy names only the permission:

```php
return $user->can(permission()->update($post));
```

Seeding, granting and Spatie's direct methods need `in($space)`. See [checking permissions](checking-permissions.md).

## What throws

Every misuse throws a `PermissionException` at the call. These are developer errors: fix the call.

| Call | Why |
|------|-----|
| `->view(Post::class)` when `Post` does not list `view` | The name would never be seeded |
| `->view()` on a subject without actions | Call `->name()` |
| `->name()` on a subject with actions | The name needs an action |
| `->access()` without `in()` | Access belongs to a space |
| `->in($space)` with a subject without spaces | The space would be ignored |
| `->in($space)` for a space the subject is not allowed in | The name does not exist there; see [allowed per space](defining-permissions.md#allowed-per-space) |
| A subject that is neither discovered nor listed, or a class that is not a subject | No such name exists |

## Names are stored data

The format, the default separator and normalizer, the `access` permission and the `PermissionAction` values never change within a major version. Renaming a key renames its stored names; see [keeping a name after a rename](defining-permissions.md#keeping-a-name-after-a-rename).

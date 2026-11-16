# Defining permissions

A permission subject is anything the app wants a permission for: a model, an action class, or a case of an enum. The package builds every name from the subjects; the app keeps its roles and labels.

## Subjects

A class implements `PermissionSubject`. The `HasPermissionKey` trait takes the key from the class name:

```php
final class Post extends Model implements PermissionSubject
{
    use HasPermissionKey;
}
```

Name: `post`. The key goes through the configured normalizer, so `BlogPost` becomes `blogPost`.

A permission without a class behind it is a case of a `PermissionEnum`. Each case is a subject; its value is the key:

```php
enum FeaturePermission: string implements PermissionEnum
{
    use ResolvesPermissions;

    case EXPORT_DATA = 'exportData';
}
```

Name: `exportData`.

### Keeping a name after a rename

With `HasPermissionKey`, renaming `Post` to `Article` renames `post` to `article` in every name, and roles lose the stored ones. Define the key yourself to keep it:

```php
public static function permissionKey(): string
{
    return 'post';
}
```

## Options

Every option is an interface on the subject, on a class and on an enum alike. Leave it out and its segment is absent.

| Option | Add to the subject | Names for `Post` |
|--------|--------------------|------------------|
| None | | `post` |
| Default actions | `UsesActions` and `use UsesDefaultActions;` | `post:viewAny`, `post:view`, `post:create`, `post:update`, `post:delete`, `post:restore`, `post:destroy` |
| Own actions | `UsesActions`, `actions()` returns `[PostAction::PUBLISH]` | `post:publish` |
| Spaces | `UsesSpaces`, `spaces()` returns `[Space::ADMIN, Space::PORTAL]` | `admin:post:view`, `portal:post:view` |
| Group | `UsesGroup`, `group()` returns `'blog'` | `blog:post:view` |
| Allowed per space | `FiltersPerSpace`, `allowedForSpace()` returns `false` for `Space::PORTAL` | `admin:post:view` only; see [allowed per space](#allowed-per-space) |
| Own implied actions | `ImpliesActions`, `impliedActions()` | Names unchanged; see [implied actions](#implied-actions) |
| Labels | `HasLabel`, `label()` and `description()` | Names unchanged; for admin screens |

The config adds options for every name at once: a `prefix`, the `separator` and the `normalizer`. See [installation](installation.md#config-keys).

## Spaces

A space is an area of the app with its own `access` permission, such as an admin panel or a customer portal. It is a string-backed enum:

```php
enum Space: string implements SpaceEnum
{
    case ADMIN = 'admin';
    case PORTAL = 'portal';

    public function spacePrefix(): string
    {
        return $this->value;
    }
}
```

`spacePrefix()` is the first segment of every name in the space. Every space a subject declares gets `admin:access`, `portal:access`. Spaces are optional: without `UsesSpaces` a subject's names have no space segment and no `access` permission.

### Allowed per space

`FiltersPerSpace` decides, per space, whether a subject exists there. A subject that is not allowed in a space has no names in it: nothing about it can be seeded or granted there, every check is denied, and building one of its names with `in()` for that space throws.

A model or other class returns `true` or `false`:

```php
final class Invoice extends Model implements FiltersPerSpace, PermissionSubject, UsesActions, UsesSpaces
{
    // ...

    public static function allowedForSpace(SpaceEnum $space): bool
    {
        return $space !== Space::PORTAL;
    }
}
```

An enum returns the cases allowed in the space. The `FiltersCases` trait gives `all()`, `only()`, `except()` and `none()`:

```php
public static function allowedForSpace(SpaceEnum $space): array
{
    return match ($space) {
        Space::ADMIN => self::all(),
        Space::PORTAL => self::only([self::EXPORT_DATA]),
    };
}
```

### Spaces that require access

By default every name in a space also needs the space's `access` permission: a user with `admin:post:view` but without `admin:access` is denied through the Gate, so removing `admin:access` closes the whole space at once.

| Setting | Effect |
|---------|--------|
| `'requires_access' => true` in the config (the default) | Every space requires its `access` |
| `'requires_access' => false` | No space requires it; `access` is one permission among the others |
| The space enum implements `RequiresAccess` | Its own `spacesRequiringAccess()` decides for its spaces, whatever the config says |

```php
public static function spacesRequiringAccess(): array
{
    return [self::ADMIN];
}
```

See [checking permissions](checking-permissions.md#rules-the-gate-applies).

## Actions

`PermissionAction` is the default set: `viewAny`, `view`, `create`, `update`, `delete`, `restore`, `destroy`. Its values never change. An app's own set is a string-backed enum:

```php
enum PostAction: string implements PermissionActionEnum
{
    case PUBLISH = 'publish';
    case ARCHIVE = 'archive';
}
```

### Implied actions

One action can grant others on the same subject in the same space. By default the config says update grants view and view grants viewAny:

```php
'implied_actions' => [
    PermissionAction::UPDATE->value => [PermissionAction::VIEW],
    PermissionAction::VIEW->value => [PermissionAction::VIEW_ANY],
],
```

A user with `admin:post:update` then passes a Gate check of `admin:post:view` and, through the chain, `admin:post:viewAny`. The rule applies to every subject that lists both actions; a subject without `view` is skipped. Set `'implied_actions' => []` to turn it off.

A subject that implements `ImpliesActions` replaces the config with its own rules:

```php
public static function impliedActions(PermissionActionEnum $action): array
{
    return match ($action) {
        PostAction::ARCHIVE => [PostAction::PUBLISH],
        default => [],
    };
}
```

Each action it names must be one of the subject's own `actions()`. Only the Gate applies implications; Spatie's direct methods see the stored names.

## Discovery

The package scans the `discover` folders, `app/` by default, for every class that implements `PermissionSubject` and every enum that implements `PermissionEnum`. A new subject takes part as soon as its file exists.

Only files that name one of the two interfaces are loaded, plus files that name an abstract class or interface already found to implement one. A subject that reaches the interface only through a parent outside the scanned folders names the interface itself, or goes in the `subjects` config key. Building a name for a subject that is neither discovered nor listed throws.

## The name format

```
[prefix]:[space]:[group]:[key]:[action]
```

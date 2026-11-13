# Defining permissions

A permission belongs to something that already exists in the app: a model, an action class, a feature. That class declares itself a permission subject, and the package discovers it. The app owns its spaces, its subjects, its roles and every label a user sees; the package only turns subjects into the strings Spatie stores.

## Subjects

A subject is a class implementing `Igne\Permissions\Contracts\PermissionSubject`. The contract asks for one static method, `permissionKey()`, the permission segment of every name for that class. The `HasPermissionKey` trait derives it from the class name:

```php
use Igne\Permissions\Concerns\HasPermissionKey;
use Igne\Permissions\Concerns\UsesDefaultActions;
use Igne\Permissions\Contracts\PermissionSubject;
use Igne\Permissions\Contracts\UsesActions;
use Igne\Permissions\Contracts\UsesSpaces;

final class Organization extends Model implements PermissionSubject, UsesActions, UsesSpaces
{
    use HasPermissionKey;
    use UsesDefaultActions;

    public static function spaces(): array
    {
        return [AppSpace::ADMIN_PORTAL, AppSpace::CUSTOMER_PORTAL];
    }
}
```

Names: `nova:organization:view`, `customerPortal:organization:update` and the other default actions in both spaces. The key `Organization` goes through the configured normalizer like every segment, so it renders as `organization`.

An action class with neither spaces nor actions is one permission:

```php
final class ExportReports implements PermissionSubject
{
    use HasPermissionKey;
}
```

Name: `exportReports`.

### The key is stored data

With `HasPermissionKey`, renaming `Organization` to `Company` renames every stored `organization` name to `company`, and every role that held them loses them. To rename the class but keep the names, define the key on the class instead of using the trait:

```php
final class Company extends Model implements PermissionSubject
{
    public static function permissionKey(): string
    {
        return 'organization';
    }
}
```

## Permission enums

Some permissions have no class behind them: setting up two-factor authentication, reading the API docs. A string-backed enum implementing `Igne\Permissions\Contracts\PermissionEnum` holds them; each case is a subject of its own, its value the key. The contract asks for `permissions()`, the cases that take part, and the `ResolvesPermissions` trait returns every case.

```php
use Igne\Permissions\Concerns\ResolvesPermissions;
use Igne\Permissions\Contracts\PermissionEnum;
use Igne\Permissions\Contracts\UsesSpaces;

enum GuardPermission: string implements PermissionEnum, UsesSpaces
{
    use ResolvesPermissions;

    case SETUP_TWO_FACTOR = 'setupTwoFactor';
    case ACCESS_API_DOCS = 'accessApiDocs';

    public static function spaces(): array
    {
        return [AppSpace::ADMIN_PORTAL];
    }
}
```

Names: `nova:setupTwoFactor`, `nova:accessApiDocs`. A case goes wherever a subject class goes: `permission()->in(AppSpace::ADMIN_PORTAL)->name(GuardPermission::SETUP_TWO_FACTOR)`.

## The opt-in contracts

Everything beyond the key is opt-in, on a subject class and on a permission enum alike. Implement a contract to add a segment or a rule; leave it out and the segment is simply absent.

| Contract | Adds | The subject provides |
|----------|------|----------------------|
| `UsesSpaces` | The space prefix. The subject's names exist per space; build them with `in($space)`, or without it for checks through the Gate. | `spaces(): list<SpaceEnum>` |
| `UsesActions` | The action suffix: `…:organization:view`. | `actions(): list<PermissionActionEnum>`. The `UsesDefaultActions` trait returns the package's `PermissionAction` set. |
| `UsesGroup` | A group segment between the space and the key: `…:leadSales:phone:view`. | `group(): string` |
| `ImpliesActions` | A rule that one action grants others on the same subject, as in update granting view (needs `UsesActions`). Stored names do not change. | `impliedActions(PermissionActionEnum $action): list<PermissionActionEnum>`. See [implied actions](#implied-actions). |
| `FiltersCasesPerSpace` | For a permission enum only: which cases exist in which of its spaces. Without it every case exists in every space. | `allowedCasesForSpace(SpaceEnum $space): list<PermissionEnum>`. The `FiltersCases` trait gives `only()`, `except()`, `all()` and `none()` for the match arms. |
| `HasLabel` | A translated label and description for admin screens. The package never reads them. | `label(): string`, `description(): string` |

## Discovery

The package scans the folders in `permissions.discover`, `app/` by default, for every class that implements `PermissionSubject` and every enum that implements `PermissionEnum`. A new model with the contract takes part as soon as the file exists; nothing is registered by hand.

The scan loads only files that mention one of the two contracts, or the short name of an abstract class or interface already found to implement one, so a model that extends an abstract subject base is found without loading every class in `app/`. A class that reaches the contract only through a parent or interface outside the scanned folders, such as one from another package, names the contract itself or goes in `permissions.subjects`:

```php
'discover' => [app_path(), base_path('domain')],
'subjects' => [
    \Vendor\Billing\Invoice::class,
],
```

The registered set is the discovered classes in class-name order, then the listed ones. The scan runs once per process. A folder in `discover` that does not exist throws a `PermissionException`. Building a name for a subject that is neither discovered nor listed throws too, so no name exists that the seeder would not create.

## The space enum

A space is an area of the app with its own built-in `access` permission: an admin panel, a company portal, a customer portal. It is a string-backed enum implementing `Igne\Permissions\Contracts\SpaceEnum`:

```php
use Igne\Permissions\Contracts\SpaceEnum;

enum AppSpace: string implements SpaceEnum
{
    case ADMIN_PORTAL = 'adminPortal';
    case CUSTOMER_PORTAL = 'customerPortal';

    public function spacePrefix(): string
    {
        return match ($this) {
            self::ADMIN_PORTAL => 'nova',
            default => $this->value,
        };
    }
}
```

`spacePrefix()` is the first segment of every name in that space (after the optional global prefix). Every space a subject declares gets the built-in `access` permission, so the enum above yields `nova:access` and `customerPortal:access`.

Spaces are optional. An app with one area defines no space enum and no subject implements `UsesSpaces`: its names have no space segment, as in `organization:view`, it never calls `in()`, and the user model needs no `HasPermissionSpace`. There is no `access` permission either, because there is no space for one to open.

### Spaces that require access

By default `nova:access` is one permission among the others: a role holding `nova:organization:view` without `nova:access` can still view organizations. A space enum that implements `Igne\Permissions\Contracts\RequiresAccess` makes access a precondition instead, for the spaces it lists:

```php
use Igne\Permissions\Contracts\RequiresAccess;

enum AppSpace: string implements RequiresAccess, SpaceEnum
{
    // ...

    public static function spacesRequiringAccess(): array
    {
        return [self::ADMIN_PORTAL];
    }
}
```

Every check through the Gate of a name in a listed space is then denied unless the checked user also holds that space's `access` permission, whatever else grants the name. Taking `nova:access` away from a role closes the whole admin portal at once. Stored names do not change. See [checking permissions](checking-permissions.md#rules-the-gate-applies).

## Actions

`Igne\Permissions\PermissionAction` is the default set: `viewAny`, `view`, `create`, `update`, `delete`, `restore`, `destroy`. Its values are stored data and never change.

An app defines its own set with a string-backed enum implementing `Igne\Permissions\Contracts\PermissionActionEnum` (and `HasLabel` if admin screens show it), returned from the subject's `actions()`:

```php
enum ReportAction: string implements PermissionActionEnum
{
    case RUN = 'run';
    case SCHEDULE = 'schedule';
}
```

`permission()->action(ReportAction::RUN, Report::class)` renders it; the seven named methods only cover the default set.

### Implied actions

Without a rule, a role that may update organizations also needs `organization:view` and `organization:viewAny` stored, and every seeder repeats that. A subject that implements `Igne\Permissions\Contracts\ImpliesActions` states the rule once:

```php
use Igne\Permissions\Contracts\ImpliesActions;

final class Organization extends Model implements ImpliesActions, PermissionSubject, UsesActions, UsesSpaces
{
    // ...

    public static function impliedActions(PermissionActionEnum $action): array
    {
        return match ($action) {
            PermissionAction::UPDATE, PermissionAction::DELETE => [PermissionAction::VIEW],
            PermissionAction::VIEW => [PermissionAction::VIEW_ANY],
            default => [],
        };
    }
}
```

A check through the Gate of `nova:organization:view` then passes for a user who holds `nova:organization:update`, and `nova:organization:viewAny` passes too, because implications chain. An implication stays within one subject in one space: `nova:organization:update` grants nothing on invoices or in another portal. Each implied action must be one of the subject's own `actions()`, or the check throws a `PermissionException`. The rule changes what a check answers, never which names exist. See [checking permissions](checking-permissions.md#rules-the-gate-applies).

## The name format

```
[prefix]:[spacePrefix]:[group]:[key]:[action]
```

Each segment is present only when the subject opts into it, and every segment goes through the configured normalizer (camel by default). See [building names](building-names.md).

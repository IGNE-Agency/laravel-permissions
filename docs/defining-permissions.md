# Defining permissions

The package builds names from enums the app defines. The app owns its spaces, its permission enums, its roles and every label a user sees; the package only turns enum cases into the strings Spatie stores.

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

`spacePrefix()` is the first segment of every name in that space (after the optional global prefix). Every space gets the built-in `access` permission, so the enum above yields `nova:access` and `customerPortal:access`.

## Permission enums

A permission enum is a string-backed enum implementing `Igne\Permissions\Contracts\PermissionEnum`. Each case is one permission. The contract asks for `permissions()`, the cases that take part; the `ResolvesPermissions` trait provides the default, every case.

Everything else is opt-in. Implement a contract to add a segment or a rule; leave it out and the segment is simply absent.

| Contract | Adds | The enum provides |
|----------|------|-------------------|
| `UsesSpaces` | The space prefix. The enum's names exist per space, and the chain requires `in($space)`. | `spaces(): list<SpaceEnum>` |
| `UsesActions` | The action suffix: `…:document:view`. | `actions(): list<PermissionActionEnum>`. The `UsesDefaultActions` trait returns the package's `PermissionAction` set. |
| `UsesGroup` | A group segment between the space and the permission: `…:leadSales:phone:view`. | `group(): string` |
| `FiltersCasesPerSpace` | Which cases exist in which space (needs `UsesSpaces`). Without it every case exists in every space. | `allowedCasesForSpace(SpaceEnum $space): list<PermissionEnum>`. The `FiltersCases` trait gives `only()`, `except()`, `all()` and `none()` for the match arms. |
| `HasLabel` | A translated label and description for admin screens. The package never reads them. | `label(): string`, `description(): string` |

### A resource enum: spaces, default actions, filtered per space

```php
use Igne\Permissions\Concerns\FiltersCases;
use Igne\Permissions\Concerns\UsesDefaultActions;
use Igne\Permissions\Contracts\FiltersCasesPerSpace;
use Igne\Permissions\Contracts\PermissionEnum;
use Igne\Permissions\Contracts\SpaceEnum;
use Igne\Permissions\Contracts\UsesActions;
use Igne\Permissions\Contracts\UsesSpaces;

enum ResourcePermission: string implements FiltersCasesPerSpace, PermissionEnum, UsesActions, UsesSpaces
{
    use FiltersCases;
    use UsesDefaultActions;

    case DOCUMENT = 'document';
    case INVOICE = 'invoice';
    case USER = 'user';

    public static function spaces(): array
    {
        return AppSpace::cases();
    }

    public static function allowedCasesForSpace(SpaceEnum $space): array
    {
        return match ($space) {
            AppSpace::ADMIN_PORTAL => self::all(),
            AppSpace::CUSTOMER_PORTAL => self::only([self::DOCUMENT, self::INVOICE]),
            default => self::none(),
        };
    }
}
```

Names: `nova:document:view`, `nova:user:destroy`, `customerPortal:invoice:viewAny`. No `customerPortal:user:*` exists.

### A guard enum: spaces, no actions

```php
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

Names: `nova:setupTwoFactor`, `nova:accessApiDocs`. The chain renders them with `->name()`; an action method throws.

### A bare enum: neither spaces nor actions

```php
enum FeaturePermission: string implements PermissionEnum
{
    use ResolvesPermissions;

    case EXPORT_REPORTS = 'exportReports';
}
```

Name: `exportReports`. The chain renders it with `permissions()->for(FeaturePermission::EXPORT_REPORTS)->name()`, without `in()`.

### A grouped enum

```php
enum LeadPermission: string implements PermissionEnum, UsesActions, UsesGroup, UsesSpaces
{
    use ResolvesPermissions;

    case PHONE = 'phone';
    case WEB_FORM = 'webForm';

    public static function group(): string
    {
        return 'leadSales';
    }

    public static function actions(): array
    {
        return [PermissionAction::VIEW_ANY, PermissionAction::UPDATE];
    }

    public static function spaces(): array
    {
        return [AppSpace::ADMIN_PORTAL];
    }
}
```

Names: `nova:leadSales:phone:viewAny`, `nova:leadSales:webForm:update`. Only the two listed actions exist; `->view()` throws.

## Actions

`Igne\Permissions\PermissionAction` is the default set: `viewAny`, `view`, `create`, `update`, `delete`, `restore`, `destroy`. Its values are stored data and never change.

An app defines its own set with a string-backed enum implementing `Igne\Permissions\Contracts\PermissionActionEnum` (and `HasLabel` if admin screens show it), returned from the permission enum's `actions()`:

```php
enum ReportAction: string implements PermissionActionEnum
{
    case RUN = 'run';
    case SCHEDULE = 'schedule';
}
```

The chain renders it with `->action(ReportAction::RUN)`; the seven named methods only cover the default set.

## Registering the enums

Every permission enum goes into `config/permissions.php`:

```php
'types' => [
    GuardPermission::class,
    ResourcePermission::class,
    LeadPermission::class,
    FeaturePermission::class,
],
```

Order matters for listings: `permissions()->all()` and the per-space lists follow registration order, with each space's `access` permission last. The chain refuses an enum that is not registered, so a name can never be built for a permission that the seeder would not create.

## The name format

```
[prefix]:[spacePrefix]:[group]:[permission]:[action]
```

Each segment is present only when the enum opts into it, and every segment goes through the configured normalizer (camel by default). See [building-names.md](building-names.md).

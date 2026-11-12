<?php

declare(strict_types=1);

namespace Igne\Permissions\Tests\Fixtures;

use Igne\Permissions\Concerns\ResolvesPermissions;
use Igne\Permissions\Contracts\HasLabel;
use Igne\Permissions\Contracts\PermissionEnum;
use Illuminate\Support\Str;

/**
 * A bare enum with neither spaces nor actions: rendered as just "permission". Also the one fixture
 * that opts into labels, to prove {@see HasLabel} is independent of the name-building contracts.
 */
enum TestSettingPermission: string implements HasLabel, PermissionEnum
{
    use ResolvesPermissions;

    case DARK_MODE = 'darkMode';
    case BETA = 'beta';

    public function label(): string
    {
        return Str::headline($this->value);
    }

    public function description(): string
    {
        return $this->label().' setting.';
    }
}

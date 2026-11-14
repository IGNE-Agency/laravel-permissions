<?php

declare(strict_types=1);

namespace Igne\Permissions\Tests\Fixtures;

use Igne\Permissions\Contracts\PermissionSubject;

/**
 * An action class as a permission subject without spaces or actions, with a key of its own.
 */
final class TestExportReports implements PermissionSubject
{
    public static function permissionKey(): string
    {
        return 'exportReports';
    }
}

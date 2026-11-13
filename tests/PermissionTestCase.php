<?php

declare(strict_types=1);

namespace Igne\Permissions\Tests;

use Igne\Permissions\Tests\Fixtures\TestExportReports;
use Igne\Permissions\Tests\Fixtures\TestGuardPermission;
use Igne\Permissions\Tests\Fixtures\TestOrganization;
use Igne\Permissions\Tests\Fixtures\TestResourcePermission;
use Illuminate\Support\Facades\Config;

/**
 * Lists the fixture subjects instead of discovering them, so every test exercises the package
 * against the same known set: two enums, a model and an action class.
 */
abstract class PermissionTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('permissions', [
            'discover' => [],
            'subjects' => [
                TestGuardPermission::class,
                TestResourcePermission::class,
                TestOrganization::class,
                TestExportReports::class,
            ],
            'separator' => ':',
            'normalizer' => 'camel',
            'prefix' => null,
        ]);
    }
}

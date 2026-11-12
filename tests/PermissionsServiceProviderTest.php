<?php

declare(strict_types=1);

namespace Igne\Permissions\Tests;

use Igne\Permissions\PermissionsServiceProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

final class PermissionsServiceProviderTest extends TestCase
{
    public function test_the_package_config_is_merged_with_its_defaults(): void
    {
        $this->assertSame([], Config::array('permissions.types'));
        $this->assertSame(':', Config::string('permissions.separator'));
        $this->assertSame('camel', Config::string('permissions.normalizer'));
        $this->assertNull(Config::get('permissions.prefix'));
    }

    public function test_app_config_wins_over_the_package_defaults(): void
    {
        Config::set('permissions', ['separator' => '.']);

        (new PermissionsServiceProvider(app()))->register();

        $this->assertSame('.', Config::string('permissions.separator'));
        $this->assertSame('camel', Config::string('permissions.normalizer'));
        $this->assertSame([], Config::array('permissions.types'));
    }

    public function test_the_config_is_publishable_under_its_tag(): void
    {
        $this->assertSame(
            [dirname(__DIR__).'/config/permissions.php' => app()->configPath('permissions.php')],
            ServiceProvider::pathsToPublish(PermissionsServiceProvider::class, 'permissions-config'),
        );
    }
}

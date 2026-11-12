<?php

declare(strict_types=1);

namespace Igne\Permissions\Tests;

use Igne\Permissions\Exceptions\PermissionException;
use Igne\Permissions\Tests\Fixtures\TestGuardPermission;
use Igne\Permissions\Tests\Fixtures\TestResourcePermission;
use Igne\Permissions\Tests\Fixtures\TestSpace;
use Illuminate\Support\Facades\Config;

final class PermissionConfigTest extends PermissionTestCase
{
    public function test_the_default_normalizer_is_camel_case(): void
    {
        $this->assertSame('backend:article:viewAny', permissions()->in(TestSpace::ADMIN)->for(TestResourcePermission::ARTICLE)->viewAny());
    }

    public function test_the_snake_normalizer_applies_to_every_segment(): void
    {
        Config::set('permissions.normalizer', 'snake');

        $this->assertSame('backend:article:view_any', permissions()->in(TestSpace::ADMIN)->for(TestResourcePermission::ARTICLE)->viewAny());
        $this->assertSame('backend:manage_account', permissions()->in(TestSpace::ADMIN)->for(TestGuardPermission::MANAGE_ACCOUNT)->name());
    }

    public function test_the_kebab_normalizer_applies_to_every_segment(): void
    {
        Config::set('permissions.normalizer', 'kebab');

        $this->assertSame('backend:article:view-any', permissions()->in(TestSpace::ADMIN)->for(TestResourcePermission::ARTICLE)->viewAny());
    }

    public function test_the_studly_normalizer_applies_to_every_segment(): void
    {
        Config::set('permissions.normalizer', 'studly');

        $this->assertSame('Backend:Article:ViewAny', permissions()->in(TestSpace::ADMIN)->for(TestResourcePermission::ARTICLE)->viewAny());
        $this->assertSame('Backend:Access', permissions()->in(TestSpace::ADMIN)->access());
    }

    public function test_the_separator_is_configurable(): void
    {
        Config::set('permissions.separator', '.');

        $this->assertSame('backend.article.view', permissions()->in(TestSpace::ADMIN)->for(TestResourcePermission::ARTICLE)->view());
        $this->assertSame('backend.access', permissions()->in(TestSpace::ADMIN)->access());
    }

    public function test_the_global_prefix_is_prepended_and_normalized(): void
    {
        Config::set('permissions.prefix', 'MyApp');

        $this->assertSame('myApp:backend:article:view', permissions()->in(TestSpace::ADMIN)->for(TestResourcePermission::ARTICLE)->view());
        $this->assertSame('myApp:backend:access', permissions()->in(TestSpace::ADMIN)->access());

        foreach (permissions()->all() as $permission) {
            $this->assertStringStartsWith('myApp:', $permission);
        }
    }

    public function test_prefix_and_normalizer_combine(): void
    {
        Config::set('permissions.prefix', 'app');
        Config::set('permissions.normalizer', 'snake');

        $this->assertSame('app:backend:article:view_any', permissions()->in(TestSpace::ADMIN)->for(TestResourcePermission::ARTICLE)->viewAny());
    }

    public function test_the_chain_reads_the_config_again_on_every_call(): void
    {
        $before = permissions()->in(TestSpace::ADMIN)->access();

        Config::set('permissions.separator', '/');

        $this->assertSame('backend:access', $before);
        $this->assertSame('backend/access', permissions()->in(TestSpace::ADMIN)->access());
    }

    public function test_an_unknown_normalizer_in_config_throws(): void
    {
        Config::set('permissions.normalizer', 'headline');

        $this->expectException(PermissionException::class);
        $this->expectExceptionMessage('Unknown segment normalizer [headline]');

        permissions();
    }

    public function test_a_type_that_is_not_a_permission_enum_throws(): void
    {
        Config::set('permissions.types', [TestSpace::class]);

        $this->expectException(PermissionException::class);
        $this->expectExceptionMessage('Invalid permissions config [types]');

        permissions();
    }

    public function test_a_prefix_that_is_not_a_string_throws(): void
    {
        Config::set('permissions.prefix', 12);

        $this->expectException(PermissionException::class);
        $this->expectExceptionMessage('Invalid permissions config [prefix]');

        permissions();
    }
}

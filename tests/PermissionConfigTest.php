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
        $this->assertSame('backend:article:viewAny', permission()->in(TestSpace::ADMIN)->viewAny(TestResourcePermission::ARTICLE));
    }

    public function test_the_snake_normalizer_applies_to_every_segment(): void
    {
        Config::set('permissions.normalizer', 'snake');

        $this->assertSame('backend:article:view_any', permission()->in(TestSpace::ADMIN)->viewAny(TestResourcePermission::ARTICLE));
        $this->assertSame('backend:manage_account', permission()->in(TestSpace::ADMIN)->name(TestGuardPermission::MANAGE_ACCOUNT));
    }

    public function test_the_kebab_normalizer_applies_to_every_segment(): void
    {
        Config::set('permissions.normalizer', 'kebab');

        $this->assertSame('backend:article:view-any', permission()->in(TestSpace::ADMIN)->viewAny(TestResourcePermission::ARTICLE));
    }

    public function test_the_studly_normalizer_applies_to_every_segment(): void
    {
        Config::set('permissions.normalizer', 'studly');

        $this->assertSame('Backend:Article:ViewAny', permission()->in(TestSpace::ADMIN)->viewAny(TestResourcePermission::ARTICLE));
        $this->assertSame('Backend:Access', permission()->in(TestSpace::ADMIN)->access());
    }

    public function test_the_separator_is_configurable(): void
    {
        Config::set('permissions.separator', '.');

        $this->assertSame('backend.article.view', permission()->in(TestSpace::ADMIN)->view(TestResourcePermission::ARTICLE));
        $this->assertSame('backend.access', permission()->in(TestSpace::ADMIN)->access());
    }

    public function test_the_global_prefix_is_prepended_and_normalized(): void
    {
        Config::set('permissions.prefix', 'MyApp');

        $this->assertSame('myApp:backend:article:view', permission()->in(TestSpace::ADMIN)->view(TestResourcePermission::ARTICLE));
        $this->assertSame('myApp:backend:access', permission()->in(TestSpace::ADMIN)->access());

        foreach (permissions()->all() as $permission) {
            $this->assertStringStartsWith('myApp:', $permission);
        }
    }

    public function test_prefix_and_normalizer_combine(): void
    {
        Config::set('permissions.prefix', 'app');
        Config::set('permissions.normalizer', 'snake');

        $this->assertSame('app:backend:article:view_any', permission()->in(TestSpace::ADMIN)->viewAny(TestResourcePermission::ARTICLE));
    }

    public function test_the_chain_reads_the_config_again_on_every_call(): void
    {
        $before = permission()->in(TestSpace::ADMIN)->access();

        Config::set('permissions.separator', '/');

        $this->assertSame('backend:access', $before);
        $this->assertSame('backend/access', permission()->in(TestSpace::ADMIN)->access());
    }

    public function test_an_unknown_normalizer_in_config_throws(): void
    {
        Config::set('permissions.normalizer', 'headline');

        $this->expectException(PermissionException::class);
        $this->expectExceptionMessage('Unknown segment normalizer [headline]');

        permissions();
    }

    public function test_a_listed_class_that_is_not_a_subject_throws(): void
    {
        Config::set('permissions.subjects', [TestSpace::class]);

        $this->expectException(PermissionException::class);

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

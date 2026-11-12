<?php

declare(strict_types=1);

namespace Igne\Permissions\Tests;

use Igne\Permissions\PermissionRegistry;
use Igne\Permissions\Tests\Fixtures\TestGuardPermission;
use Igne\Permissions\Tests\Fixtures\TestLeadPermission;
use Igne\Permissions\Tests\Fixtures\TestResourcePermission;
use Igne\Permissions\Tests\Fixtures\TestSpace;
use Illuminate\Support\Facades\Config;

final class PermissionRegistryTest extends PermissionTestCase
{
    public function test_resolving_the_placeholder_renders_what_in_renders_for_every_space_and_normalizer(): void
    {
        foreach (['camel', 'snake', 'kebab', 'studly'] as $normalizer) {
            Config::set('permissions.normalizer', $normalizer);
            $registry = PermissionRegistry::fromConfig();

            foreach (TestSpace::cases() as $space) {
                $this->assertSame(
                    permissions()->in($space)->for(TestResourcePermission::BLOG_POST)->view(),
                    $registry->resolveSpacePlaceholder(permissions()->for(TestResourcePermission::BLOG_POST)->view(), $space),
                );
                $this->assertSame(
                    permissions()->in($space)->for(TestGuardPermission::MANAGE_ACCOUNT)->name(),
                    $registry->resolveSpacePlaceholder(permissions()->for(TestGuardPermission::MANAGE_ACCOUNT)->name(), $space),
                );
            }
        }
    }

    public function test_resolving_keeps_the_prefix_the_group_and_the_separator(): void
    {
        Config::set('permissions.types', [TestLeadPermission::class, TestResourcePermission::class]);
        Config::set('permissions.prefix', 'App');
        Config::set('permissions.separator', '.');
        $registry = PermissionRegistry::fromConfig();

        $this->assertSame(
            permissions()->in(TestSpace::PORTAL)->for(TestLeadPermission::WEB_FORM)->viewAny(),
            $registry->resolveSpacePlaceholder(permissions()->for(TestLeadPermission::WEB_FORM)->viewAny(), TestSpace::PORTAL),
        );
        $this->assertSame(
            permissions()->in(TestSpace::ADMIN)->for(TestResourcePermission::ARTICLE)->view(),
            $registry->resolveSpacePlaceholder(permissions()->for(TestResourcePermission::ARTICLE)->view(), TestSpace::ADMIN),
        );
    }

    public function test_has_space_placeholder_tells_a_placeholder_name_from_a_concrete_one(): void
    {
        $registry = PermissionRegistry::fromConfig();

        $this->assertTrue($registry->hasSpacePlaceholder(permissions()->for(TestResourcePermission::ARTICLE)->view()));
        $this->assertTrue($registry->hasSpacePlaceholder(permissions()->for(TestGuardPermission::MANAGE_USERS)->name()));
        $this->assertFalse($registry->hasSpacePlaceholder(permissions()->in(TestSpace::ADMIN)->for(TestResourcePermission::ARTICLE)->view()));
        $this->assertFalse($registry->hasSpacePlaceholder(permissions()->in(TestSpace::ADMIN)->access()));
        $this->assertFalse($registry->hasSpacePlaceholder('update'));
    }
}

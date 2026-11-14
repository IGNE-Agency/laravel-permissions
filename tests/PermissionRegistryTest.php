<?php

declare(strict_types=1);

namespace Igne\Permissions\Tests;

use Igne\Permissions\PermissionRegistry;
use Igne\Permissions\Tests\Fixtures\TestGuardPermission;
use Igne\Permissions\Tests\Fixtures\TestLeadPermission;
use Igne\Permissions\Tests\Fixtures\TestPost;
use Igne\Permissions\Tests\Fixtures\TestResourcePermission;
use Igne\Permissions\Tests\Fixtures\TestSpace;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\DataProvider;

final class PermissionRegistryTest extends PermissionTestCase
{
    /**
     * @return array<string, array{string, TestSpace}>
     */
    public static function normalizersAndSpaces(): array
    {
        return [
            'camel in admin' => ['camel', TestSpace::ADMIN],
            'camel in portal' => ['camel', TestSpace::PORTAL],
            'snake in admin' => ['snake', TestSpace::ADMIN],
            'snake in portal' => ['snake', TestSpace::PORTAL],
            'kebab in admin' => ['kebab', TestSpace::ADMIN],
            'kebab in portal' => ['kebab', TestSpace::PORTAL],
            'studly in admin' => ['studly', TestSpace::ADMIN],
            'studly in portal' => ['studly', TestSpace::PORTAL],
        ];
    }

    #[DataProvider('normalizersAndSpaces')]
    public function test_resolving_the_placeholder_renders_what_in_renders(string $normalizer, TestSpace $space): void
    {
        Config::set('permissions.normalizer', $normalizer);
        $registry = PermissionRegistry::fromConfig();

        $this->assertSame(
            permission()->in($space)->view(TestPost::class),
            $registry->resolveSpacePlaceholder(permission()->view(TestPost::class), $space),
        );
        $this->assertSame(
            permission()->in($space)->name(TestGuardPermission::MANAGE_ACCOUNT),
            $registry->resolveSpacePlaceholder(permission()->name(TestGuardPermission::MANAGE_ACCOUNT), $space),
        );
    }

    public function test_resolving_keeps_the_prefix_the_group_and_the_separator(): void
    {
        Config::set('permissions.subjects', [TestLeadPermission::class, TestResourcePermission::class]);
        Config::set('permissions.prefix', 'App');
        Config::set('permissions.separator', '.');
        $registry = PermissionRegistry::fromConfig();

        $this->assertSame(
            permission()->in(TestSpace::PORTAL)->viewAny(TestLeadPermission::WEB_FORM),
            $registry->resolveSpacePlaceholder(permission()->viewAny(TestLeadPermission::WEB_FORM), TestSpace::PORTAL),
        );
        $this->assertSame(
            permission()->in(TestSpace::ADMIN)->view(TestResourcePermission::ARTICLE),
            $registry->resolveSpacePlaceholder(permission()->view(TestResourcePermission::ARTICLE), TestSpace::ADMIN),
        );
    }

    public function test_has_space_placeholder_tells_a_placeholder_name_from_a_concrete_one(): void
    {
        $registry = PermissionRegistry::fromConfig();

        $this->assertTrue($registry->hasSpacePlaceholder(permission()->view(TestResourcePermission::ARTICLE)));
        $this->assertTrue($registry->hasSpacePlaceholder(permission()->name(TestGuardPermission::MANAGE_USERS)));
        $this->assertFalse($registry->hasSpacePlaceholder(permission()->in(TestSpace::ADMIN)->view(TestResourcePermission::ARTICLE)));
        $this->assertFalse($registry->hasSpacePlaceholder(permission()->in(TestSpace::ADMIN)->access()));
        $this->assertFalse($registry->hasSpacePlaceholder('update'));
    }
}

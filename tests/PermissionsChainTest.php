<?php

declare(strict_types=1);

namespace Igne\Permissions\Tests;

use Igne\Permissions\PermissionAction;
use Igne\Permissions\Permissions;
use Igne\Permissions\Tests\Fixtures\TestCustomAction;
use Igne\Permissions\Tests\Fixtures\TestGuardPermission;
use Igne\Permissions\Tests\Fixtures\TestReportPermission;
use Igne\Permissions\Tests\Fixtures\TestResourcePermission;
use Igne\Permissions\Tests\Fixtures\TestSettingPermission;
use Igne\Permissions\Tests\Fixtures\TestSpace;
use Illuminate\Support\Facades\Config;

final class PermissionsChainTest extends PermissionTestCase
{
    public function test_the_helper_builds_the_chain_from_config(): void
    {
        $this->assertInstanceOf(Permissions::class, permissions());
        $this->assertSame('access', Permissions::ACCESS);
    }

    public function test_access_is_the_built_in_space_permission(): void
    {
        $this->assertSame('backend:access', permissions()->in(TestSpace::ADMIN)->access());
        $this->assertSame('portal:access', permissions()->in(TestSpace::PORTAL)->access());
        $this->assertSame('guest:access', permissions()->in(TestSpace::GUEST)->access());
    }

    public function test_every_default_action_has_a_method(): void
    {
        $article = permissions()->in(TestSpace::ADMIN)->for(TestResourcePermission::ARTICLE);

        $this->assertSame('backend:article:viewAny', $article->viewAny());
        $this->assertSame('backend:article:view', $article->view());
        $this->assertSame('backend:article:create', $article->create());
        $this->assertSame('backend:article:update', $article->update());
        $this->assertSame('backend:article:delete', $article->delete());
        $this->assertSame('backend:article:restore', $article->restore());
        $this->assertSame('backend:article:destroy', $article->destroy());
    }

    public function test_action_accepts_a_default_action_case(): void
    {
        $this->assertSame(
            'backend:user:update',
            permissions()->in(TestSpace::ADMIN)->for(TestResourcePermission::USER)->action(PermissionAction::UPDATE),
        );
    }

    public function test_action_accepts_an_app_defined_action_enum(): void
    {
        Config::set('permissions.types', [TestReportPermission::class]);

        $report = permissions()->in(TestSpace::ADMIN)->for(TestReportPermission::REPORT);

        $this->assertSame('backend:report:read', $report->action(TestCustomAction::READ));
        $this->assertSame('backend:report:write', $report->action(TestCustomAction::WRITE));
    }

    public function test_any_renders_several_actions_in_the_given_order(): void
    {
        $this->assertSame(
            ['backend:article:view', 'backend:article:create'],
            permissions()->in(TestSpace::ADMIN)->for(TestResourcePermission::ARTICLE)->any(PermissionAction::VIEW, PermissionAction::CREATE),
        );
        $this->assertSame([], permissions()->in(TestSpace::ADMIN)->for(TestResourcePermission::ARTICLE)->any());
    }

    public function test_name_renders_a_permission_without_actions(): void
    {
        $this->assertSame('backend:manageUsers', permissions()->in(TestSpace::ADMIN)->for(TestGuardPermission::MANAGE_USERS)->name());
        $this->assertSame('backend:manageAccount', permissions()->in(TestSpace::ADMIN)->for(TestGuardPermission::MANAGE_ACCOUNT)->name());
        $this->assertSame('portal:manageAccount', permissions()->in(TestSpace::PORTAL)->for(TestGuardPermission::MANAGE_ACCOUNT)->name());
    }

    public function test_bare_for_renders_an_enum_that_does_not_use_spaces(): void
    {
        Config::set('permissions.types', [TestSettingPermission::class]);

        $this->assertSame('darkMode', permissions()->for(TestSettingPermission::DARK_MODE)->name());
        $this->assertSame('beta', permissions()->for(TestSettingPermission::BETA)->name());
    }

    public function test_pascal_and_multi_word_values_are_normalized(): void
    {
        $this->assertSame('backend:blogPost:view', permissions()->in(TestSpace::ADMIN)->for(TestResourcePermission::BLOG_POST)->view());
        $this->assertSame('backend:profile:create', permissions()->in(TestSpace::ADMIN)->for(TestResourcePermission::PROFILE)->create());
    }

    public function test_each_space_renders_its_own_prefix(): void
    {
        $this->assertSame('backend:article:view', permissions()->in(TestSpace::ADMIN)->for(TestResourcePermission::ARTICLE)->view());
        $this->assertSame('portal:article:view', permissions()->in(TestSpace::PORTAL)->for(TestResourcePermission::ARTICLE)->view());
        $this->assertSame('guest:article:view', permissions()->in(TestSpace::GUEST)->for(TestResourcePermission::ARTICLE)->view());
    }

    public function test_all_unions_every_enum_across_its_spaces_plus_access(): void
    {
        $all = permissions()->all();

        $this->assertContains('backend:access', $all);
        $this->assertContains('backend:article:view', $all);
        $this->assertContains('backend:manageAccount', $all);
        $this->assertContains('portal:access', $all);
        $this->assertContains('guest:access', $all);
        // resource(admin: 4 cases x 7 actions) + guard(admin 3 + portal 1 + guest 1) + access(3 spaces)
        $this->assertCount(28 + 5 + 3, $all);
        $this->assertSame($all, array_values(array_unique($all)));
    }

    public function test_all_in_a_space_lists_its_names_in_registration_order_with_access_last(): void
    {
        $admin = permissions()->in(TestSpace::ADMIN)->all();

        $this->assertSame('backend:manageUsers', $admin[0]);
        $this->assertSame('backend:access', $admin[array_key_last($admin)]);
        $this->assertContains('backend:article:view', $admin);
        $this->assertCount(28 + 3 + 1, $admin);

        $this->assertSame(['portal:manageAccount', 'portal:access'], permissions()->in(TestSpace::PORTAL)->all());
        $this->assertSame(['guest:runReports', 'guest:access'], permissions()->in(TestSpace::GUEST)->all());
    }
}

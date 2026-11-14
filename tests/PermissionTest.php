<?php

declare(strict_types=1);

namespace Igne\Permissions\Tests;

use Igne\Permissions\Exceptions\PermissionException;
use Igne\Permissions\Permission;
use Igne\Permissions\PermissionAction;
use Igne\Permissions\Permissions;
use Igne\Permissions\Tests\Fixtures\TestCustomAction;
use Igne\Permissions\Tests\Fixtures\TestExportReports;
use Igne\Permissions\Tests\Fixtures\TestGuardPermission;
use Igne\Permissions\Tests\Fixtures\TestInvoice;
use Igne\Permissions\Tests\Fixtures\TestLeadPermission;
use Igne\Permissions\Tests\Fixtures\TestPost;
use Igne\Permissions\Tests\Fixtures\TestReportPermission;
use Igne\Permissions\Tests\Fixtures\TestResourcePermission;
use Igne\Permissions\Tests\Fixtures\TestSettingPermission;
use Igne\Permissions\Tests\Fixtures\TestSpace;
use Illuminate\Support\Facades\Config;
use ReflectionMethod;

final class PermissionTest extends PermissionTestCase
{
    public function test_the_helper_builds_a_single_name_builder(): void
    {
        $this->assertInstanceOf(Permission::class, permission());
        $this->assertSame('access', Permissions::ACCESS);
    }

    public function test_access_is_the_built_in_space_permission(): void
    {
        $this->assertSame('backend:access', permission()->in(TestSpace::ADMIN)->access());
        $this->assertSame('portal:access', permission()->in(TestSpace::PORTAL)->access());
        $this->assertSame('guest:access', permission()->in(TestSpace::GUEST)->access());
    }

    public function test_every_default_action_has_a_method(): void
    {
        $admin = permission()->in(TestSpace::ADMIN);

        $this->assertSame('backend:testPost:viewAny', $admin->viewAny(TestPost::class));
        $this->assertSame('backend:testPost:view', $admin->view(TestPost::class));
        $this->assertSame('backend:testPost:create', $admin->create(TestPost::class));
        $this->assertSame('backend:testPost:update', $admin->update(TestPost::class));
        $this->assertSame('backend:testPost:delete', $admin->delete(TestPost::class));
        $this->assertSame('backend:testPost:restore', $admin->restore(TestPost::class));
        $this->assertSame('backend:testPost:destroy', $admin->destroy(TestPost::class));
    }

    public function test_a_class_an_instance_and_an_enum_case_are_all_subjects(): void
    {
        $this->assertSame('portal:testPost:view', permission()->in(TestSpace::PORTAL)->view(TestPost::class));
        $this->assertSame('portal:testPost:view', permission()->in(TestSpace::PORTAL)->view(new TestPost));
        $this->assertSame('backend:article:view', permission()->in(TestSpace::ADMIN)->view(TestResourcePermission::ARTICLE));
    }

    public function test_the_key_comes_from_the_class_name_or_the_class_itself(): void
    {
        $this->assertSame('TestPost', TestPost::permissionKey());
        $this->assertSame('exportReports', permission()->name(TestExportReports::class));
        $this->assertSame('exportReports', permission()->name(new TestExportReports));
    }

    public function test_action_accepts_an_app_defined_action_enum(): void
    {
        Config::set('permissions.subjects', [TestReportPermission::class, TestInvoice::class]);

        $this->assertSame('backend:report:read', permission()->in(TestSpace::ADMIN)->action(TestCustomAction::READ, TestReportPermission::REPORT));
        $this->assertSame('billing:testInvoice:write', permission()->action(TestCustomAction::WRITE, TestInvoice::class));
    }

    public function test_any_renders_several_actions_in_the_given_order(): void
    {
        $this->assertSame(
            ['backend:article:view', 'backend:article:create'],
            permission()->in(TestSpace::ADMIN)->any(TestResourcePermission::ARTICLE, PermissionAction::VIEW, PermissionAction::CREATE),
        );
        $this->assertSame([], permission()->in(TestSpace::ADMIN)->any(TestResourcePermission::ARTICLE));
    }

    public function test_name_renders_a_subject_without_actions(): void
    {
        $this->assertSame('backend:manageUsers', permission()->in(TestSpace::ADMIN)->name(TestGuardPermission::MANAGE_USERS));
        $this->assertSame('portal:manageAccount', permission()->in(TestSpace::PORTAL)->name(TestGuardPermission::MANAGE_ACCOUNT));
    }

    public function test_a_subject_without_spaces_renders_without_in(): void
    {
        Config::set('permissions.subjects', [TestSettingPermission::class, TestInvoice::class]);

        $this->assertSame('darkMode', permission()->name(TestSettingPermission::DARK_MODE));
        $this->assertSame('billing:testInvoice:read', permission()->action(TestCustomAction::READ, TestInvoice::class));
    }

    public function test_a_space_using_subject_without_in_renders_the_space_placeholder(): void
    {
        $this->assertSame('{space}', Permissions::SPACE_PLACEHOLDER);
        $this->assertSame('{space}:testPost:view', permission()->view(TestPost::class));
        $this->assertSame('{space}:article:view', permission()->view(TestResourcePermission::ARTICLE));
        $this->assertSame('{space}:manageUsers', permission()->name(TestGuardPermission::MANAGE_USERS));
        $this->assertSame(
            ['{space}:testPost:view', '{space}:testPost:create'],
            permission()->any(TestPost::class, PermissionAction::VIEW, PermissionAction::CREATE),
        );
    }

    public function test_the_placeholder_sits_after_the_global_prefix(): void
    {
        Config::set('permissions.prefix', 'app');

        $this->assertSame('app:{space}:testPost:view', permission()->view(TestPost::class));
        $this->assertSame('app:backend:testPost:view', permission()->in(TestSpace::ADMIN)->view(TestPost::class));
    }

    public function test_the_placeholder_form_differs_from_in_only_in_the_space_segment(): void
    {
        $placeholder = permission()->view(TestPost::class);

        foreach ([TestSpace::ADMIN, TestSpace::PORTAL] as $space) {
            $this->assertSame(
                permission()->in($space)->view(TestPost::class),
                str_replace(Permissions::SPACE_PLACEHOLDER, $space->spacePrefix(), $placeholder),
            );
        }
    }

    public function test_a_grouped_subject_renders_its_group_between_space_and_key(): void
    {
        Config::set('permissions.subjects', [TestLeadPermission::class]);

        $this->assertSame('portal:leadSales:webForm:update', permission()->in(TestSpace::PORTAL)->update(TestLeadPermission::WEB_FORM));
    }

    public function test_pascal_and_multi_word_values_are_normalized(): void
    {
        $this->assertSame('backend:blogPost:view', permission()->in(TestSpace::ADMIN)->view(TestResourcePermission::BLOG_POST));
    }

    public function test_the_builder_is_immutable(): void
    {
        $unscoped = permission();
        $unscoped->in(TestSpace::ADMIN);

        $this->assertSame('exportReports', $unscoped->name(TestExportReports::class));
    }

    public function test_an_action_the_subject_does_not_list_throws(): void
    {
        Config::set('permissions.subjects', [TestLeadPermission::class]);

        $this->expectException(PermissionException::class);

        permission()->in(TestSpace::PORTAL)->view(TestLeadPermission::PHONE);
    }

    public function test_any_checks_every_action(): void
    {
        $this->expectException(PermissionException::class);

        permission()->in(TestSpace::ADMIN)->any(TestPost::class, PermissionAction::VIEW, TestCustomAction::READ);
    }

    public function test_an_action_on_a_subject_without_actions_throws(): void
    {
        $this->expectException(PermissionException::class);

        permission()->view(TestExportReports::class);
    }

    public function test_name_on_a_subject_with_actions_throws(): void
    {
        $this->expectException(PermissionException::class);

        permission()->in(TestSpace::ADMIN)->name(TestPost::class);
    }

    public function test_access_needs_a_space(): void
    {
        $this->expectException(PermissionException::class);

        permission()->access();
    }

    public function test_in_on_a_subject_without_spaces_throws(): void
    {
        $this->expectException(PermissionException::class);

        permission()->in(TestSpace::ADMIN)->name(TestExportReports::class);
    }

    public function test_an_unregistered_subject_throws(): void
    {
        $this->expectException(PermissionException::class);

        permission()->name(TestSettingPermission::BETA);
    }

    public function test_an_unregistered_class_throws(): void
    {
        $this->expectException(PermissionException::class);

        permission()->action(TestCustomAction::READ, TestInvoice::class);
    }

    /**
     * The native and PHPDoc types rule this call out; the runtime guard still answers an untyped
     * caller with a PermissionException.
     */
    public function test_a_class_that_is_not_a_subject_throws(): void
    {
        $this->expectException(PermissionException::class);

        (new ReflectionMethod(Permission::class, 'view'))->invoke(permission(), self::class);
    }
}

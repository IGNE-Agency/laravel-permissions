<?php

declare(strict_types=1);

namespace Igne\Permissions\Tests;

use Igne\Permissions\Exceptions\PermissionException;
use Igne\Permissions\PermissionAction;
use Igne\Permissions\Tests\Fixtures\TestGuardPermission;
use Igne\Permissions\Tests\Fixtures\TestLeadPermission;
use Igne\Permissions\Tests\Fixtures\TestResourcePermission;
use Igne\Permissions\Tests\Fixtures\TestSettingPermission;
use Igne\Permissions\Tests\Fixtures\TestSpace;
use Illuminate\Support\Facades\Config;

final class PermissionsChainFailuresTest extends PermissionTestCase
{
    public function test_an_action_the_enum_does_not_list_throws(): void
    {
        Config::set('permissions.types', [TestLeadPermission::class]);

        $this->expectException(PermissionException::class);
        $this->expectExceptionMessage('does not support action [view]. Its actions are: viewAny, update.');

        permissions()->in(TestSpace::PORTAL)->for(TestLeadPermission::PHONE)->view();
    }

    public function test_any_checks_every_action(): void
    {
        Config::set('permissions.types', [TestLeadPermission::class]);

        $this->expectException(PermissionException::class);
        $this->expectExceptionMessage('does not support action [delete]');

        permissions()->in(TestSpace::PORTAL)->for(TestLeadPermission::PHONE)->any(PermissionAction::VIEW_ANY, PermissionAction::DELETE);
    }

    public function test_an_action_on_an_enum_without_actions_throws(): void
    {
        $this->expectException(PermissionException::class);
        $this->expectExceptionMessage('does not use actions; call name() instead of view()');

        permissions()->in(TestSpace::ADMIN)->for(TestGuardPermission::MANAGE_USERS)->view();
    }

    public function test_name_on_an_enum_with_actions_throws(): void
    {
        $this->expectException(PermissionException::class);
        $this->expectExceptionMessage('uses actions; call an action method such as view() or action() instead of name()');

        permissions()->in(TestSpace::ADMIN)->for(TestResourcePermission::ARTICLE)->name();
    }

    public function test_access_needs_exactly_one_space(): void
    {
        $this->expectException(PermissionException::class);
        $this->expectExceptionMessage('access() needs exactly one space');

        permissions()->in([TestSpace::ADMIN, TestSpace::PORTAL])->access();
    }

    public function test_for_needs_exactly_one_space(): void
    {
        $this->expectException(PermissionException::class);
        $this->expectExceptionMessage('for() needs exactly one space');

        permissions()->in([TestSpace::ADMIN, TestSpace::PORTAL])->for(TestResourcePermission::ARTICLE);
    }

    public function test_all_needs_exactly_one_space(): void
    {
        $this->expectException(PermissionException::class);
        $this->expectExceptionMessage('all() needs exactly one space');

        permissions()->in([TestSpace::ADMIN, TestSpace::PORTAL])->all();
    }

    public function test_in_needs_at_least_one_space(): void
    {
        $this->expectException(PermissionException::class);
        $this->expectExceptionMessage('in() needs at least one space');

        permissions()->in([]);
    }

    public function test_in_for_on_an_enum_without_spaces_throws(): void
    {
        Config::set('permissions.types', [TestSettingPermission::class]);

        $this->expectException(PermissionException::class);
        $this->expectExceptionMessage('does not use spaces; call permissions()->for() without in()');

        permissions()->in(TestSpace::ADMIN)->for(TestSettingPermission::DARK_MODE);
    }

    public function test_an_enum_that_is_not_registered_throws(): void
    {
        $this->expectException(PermissionException::class);
        $this->expectExceptionMessage('is not registered in permissions.types');

        permissions()->for(TestSettingPermission::BETA);
    }
}

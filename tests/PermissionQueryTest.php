<?php

declare(strict_types=1);

namespace Igne\Permissions\Tests;

use Igne\Permissions\Exceptions\PermissionException;
use Igne\Permissions\PermissionAction;
use Igne\Permissions\Tests\Fixtures\TestGuardPermission;
use Igne\Permissions\Tests\Fixtures\TestLeadPermission;
use Igne\Permissions\Tests\Fixtures\TestReportPermission;
use Igne\Permissions\Tests\Fixtures\TestResourcePermission;
use Igne\Permissions\Tests\Fixtures\TestSettingPermission;
use Igne\Permissions\Tests\Fixtures\TestSpace;
use Illuminate\Support\Facades\Config;

final class PermissionQueryTest extends PermissionTestCase
{
    private function resourceAdminCount(): int
    {
        return count(TestResourcePermission::cases()) * count(PermissionAction::cases());
    }

    public function test_only_a_single_case_of_an_enum_without_actions(): void
    {
        $this->assertSame(
            ['backend:manageAccount'],
            permissions()->in(TestSpace::ADMIN)->only(TestGuardPermission::MANAGE_ACCOUNT)->names(),
        );
    }

    public function test_only_several_cases(): void
    {
        $this->assertSame(
            ['backend:manageUsers', 'backend:manageAccount'],
            permissions()->in(TestSpace::ADMIN)->only([TestGuardPermission::MANAGE_USERS, TestGuardPermission::MANAGE_ACCOUNT])->names(),
        );
    }

    public function test_chained_only_calls_form_a_union(): void
    {
        $this->assertSame(
            ['backend:manageUsers', 'backend:manageAccount', 'backend:runReports'],
            permissions()->in(TestSpace::ADMIN)
                ->only(TestGuardPermission::MANAGE_USERS)
                ->only([TestGuardPermission::MANAGE_ACCOUNT, TestGuardPermission::RUN_REPORTS])
                ->names(),
        );
    }

    public function test_except_a_single_case(): void
    {
        $this->assertSame(
            ['backend:manageUsers', 'backend:runReports'],
            permissions()->in(TestSpace::ADMIN)->except(TestGuardPermission::MANAGE_ACCOUNT)->names(),
        );
    }

    public function test_only_a_case_with_several_actions(): void
    {
        $this->assertSame(
            ['backend:user:viewAny', 'backend:user:view'],
            permissions()->in(TestSpace::ADMIN)->only(TestResourcePermission::USER, [PermissionAction::VIEW_ANY, PermissionAction::VIEW])->names(),
        );
    }

    public function test_only_a_case_with_a_single_action(): void
    {
        $this->assertSame(
            ['backend:user:view'],
            permissions()->in(TestSpace::ADMIN)->only(TestResourcePermission::USER, PermissionAction::VIEW)->names(),
        );
    }

    public function test_only_a_case_without_actions_expands_every_action(): void
    {
        $names = permissions()->in(TestSpace::ADMIN)->only(TestResourcePermission::USER)->names();

        $this->assertCount(count(PermissionAction::cases()), $names);
        $this->assertContains('backend:user:viewAny', $names);
        $this->assertContains('backend:user:destroy', $names);
    }

    public function test_chained_only_calls_union_cases_with_their_own_actions(): void
    {
        $this->assertSame([
            'backend:profile:viewAny',
            'backend:profile:view',
            'backend:user:view',
            'backend:user:delete',
        ], permissions()->in(TestSpace::ADMIN)
            ->only(TestResourcePermission::PROFILE, [PermissionAction::VIEW_ANY, PermissionAction::VIEW])
            ->only(TestResourcePermission::USER, [PermissionAction::VIEW, PermissionAction::DELETE])
            ->names());
    }

    public function test_only_an_action_selects_it_across_every_case(): void
    {
        $names = permissions()->in(TestSpace::ADMIN)->only(PermissionAction::VIEW)->names();

        foreach ($names as $name) {
            $this->assertStringEndsWith(':view', $name);
        }

        $this->assertCount(count(TestResourcePermission::cases()), $names);
    }

    public function test_except_actions_removes_them_across_every_case(): void
    {
        $names = permissions()->in(TestSpace::ADMIN)->except([PermissionAction::UPDATE, PermissionAction::DELETE])->names();

        foreach ($names as $name) {
            $this->assertStringNotContainsString(':update', $name);
            $this->assertStringNotContainsString(':delete', $name);
        }

        $this->assertCount($this->resourceAdminCount() - count(TestResourcePermission::cases()) * 2, $names);
    }

    public function test_except_a_case_with_actions(): void
    {
        $names = permissions()->in(TestSpace::ADMIN)
            ->except(TestResourcePermission::ARTICLE, [PermissionAction::VIEW, PermissionAction::DELETE])
            ->names();

        $this->assertNotContains('backend:article:view', $names);
        $this->assertNotContains('backend:article:delete', $names);
        $this->assertContains('backend:article:create', $names);
        $this->assertContains('backend:user:view', $names);
    }

    public function test_only_and_except_compose(): void
    {
        $names = permissions()->in(TestSpace::ADMIN)
            ->only(PermissionAction::VIEW)
            ->except(TestResourcePermission::ARTICLE, PermissionAction::VIEW)
            ->names();

        $this->assertNotContains('backend:article:view', $names);
        $this->assertContains('backend:user:view', $names);
        $this->assertCount(count(TestResourcePermission::cases()) - 1, $names);
    }

    public function test_the_default_actions_are_the_package_action_set(): void
    {
        $names = permissions()->in(TestSpace::ADMIN)->only(TestResourcePermission::ARTICLE)->names();

        $this->assertCount(count(PermissionAction::cases()), $names);
        $this->assertContains('backend:article:viewAny', $names);
        $this->assertContains('backend:article:destroy', $names);
    }

    public function test_an_enum_without_a_case_filter_offers_every_case_in_every_space(): void
    {
        Config::set('permissions.types', [TestReportPermission::class]);

        $this->assertSame(
            ['portal:report:read', 'portal:report:write'],
            permissions()->in(TestSpace::PORTAL)->only(TestReportPermission::REPORT)->names(),
        );
        $this->assertSame(
            ['guest:report:read', 'guest:report:write'],
            permissions()->in(TestSpace::GUEST)->only(TestReportPermission::REPORT)->names(),
        );
    }

    public function test_grouped_names_filter_like_any_other(): void
    {
        Config::set('permissions.types', [TestLeadPermission::class]);

        $this->assertSame(
            ['portal:leadSales:phone:update'],
            permissions()->in(TestSpace::PORTAL)->only(TestLeadPermission::PHONE, PermissionAction::UPDATE)->names(),
        );
    }

    public function test_an_enum_without_spaces_ignores_the_space(): void
    {
        Config::set('permissions.types', [TestSettingPermission::class]);

        $this->assertSame(['darkMode'], permissions()->in(TestSpace::ADMIN)->only(TestSettingPermission::DARK_MODE)->names());
        $this->assertSame(['beta'], permissions()->in(TestSpace::GUEST)->except(TestSettingPermission::DARK_MODE)->names());
    }

    public function test_a_space_where_the_enum_has_no_cases_yields_nothing(): void
    {
        $this->assertSame([], permissions()->in(TestSpace::PORTAL)->only(PermissionAction::VIEW)->names());
        $this->assertSame([], permissions()->in(TestSpace::GUEST)->only(TestResourcePermission::ARTICLE)->names());
    }

    public function test_only_a_case_not_allowed_in_the_space_yields_nothing_not_everything(): void
    {
        // MANAGE_USERS is not allowed in PORTAL, but the enum does have MANAGE_ACCOUNT there.
        // only() must still restrict to the (empty) selection, never fall back to the whole enum.
        $this->assertSame([], permissions()->in(TestSpace::PORTAL)->only(TestGuardPermission::MANAGE_USERS)->names());
    }

    public function test_the_query_is_iterable_and_countable(): void
    {
        $query = permissions()->in(TestSpace::ADMIN)->only([TestGuardPermission::MANAGE_USERS, TestGuardPermission::MANAGE_ACCOUNT]);

        $this->assertSame(['backend:manageUsers', 'backend:manageAccount'], [...$query]);
        $this->assertCount(2, $query);
    }

    public function test_a_case_of_another_enum_is_rejected(): void
    {
        $this->expectException(PermissionException::class);
        $this->expectExceptionMessage('expected cases of ['.TestGuardPermission::class.']');

        permissions()->in(TestSpace::ADMIN)->only([TestGuardPermission::MANAGE_USERS, TestResourcePermission::ARTICLE]);
    }

    public function test_an_action_of_no_registered_enum_is_rejected(): void
    {
        Config::set('permissions.types', [TestGuardPermission::class]);

        $this->expectException(PermissionException::class);
        $this->expectExceptionMessage('no registered enum lists action [view]');

        permissions()->in(TestSpace::ADMIN)->only(PermissionAction::VIEW);
    }

    public function test_an_unregistered_enum_is_rejected(): void
    {
        $this->expectException(PermissionException::class);
        $this->expectExceptionMessage('is not registered in permissions.types');

        permissions()->in(TestSpace::ADMIN)->only(TestSettingPermission::BETA);
    }

    public function test_only_needs_exactly_one_space(): void
    {
        $this->expectException(PermissionException::class);
        $this->expectExceptionMessage('only() needs exactly one space');

        permissions()->in([TestSpace::ADMIN, TestSpace::PORTAL])->only(TestGuardPermission::MANAGE_USERS);
    }

    public function test_an_empty_selection_is_rejected(): void
    {
        $this->expectException(PermissionException::class);

        permissions()->in(TestSpace::ADMIN)->only([]);
    }
}

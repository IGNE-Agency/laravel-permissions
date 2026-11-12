<?php

declare(strict_types=1);

namespace Igne\Permissions\Tests;

use Igne\Permissions\PermissionAction;
use Igne\Permissions\Tests\Fixtures\TestCustomAction;
use Igne\Permissions\Tests\Fixtures\TestGuardPermission;
use Igne\Permissions\Tests\Fixtures\TestLeadPermission;
use Igne\Permissions\Tests\Fixtures\TestReportPermission;
use Igne\Permissions\Tests\Fixtures\TestSettingPermission;
use Igne\Permissions\Tests\Fixtures\TestSpace;
use Illuminate\Support\Facades\Config;

final class PermissionTypeOptionsTest extends PermissionTestCase
{
    public function test_a_bare_enum_without_spaces_or_actions_renders_just_the_permission(): void
    {
        Config::set('permissions.types', [TestSettingPermission::class]);

        $this->assertSame(['darkMode', 'beta'], permissions()->all());
        $this->assertSame('Dark Mode', TestSettingPermission::DARK_MODE->label());
    }

    public function test_an_app_defined_action_set_replaces_the_default(): void
    {
        Config::set('permissions.types', [TestReportPermission::class]);

        $this->assertSame(
            ['backend:report:read', 'backend:report:write'],
            permissions()->in(TestSpace::ADMIN)->for(TestReportPermission::REPORT)->any(TestCustomAction::READ, TestCustomAction::WRITE),
        );
        $this->assertSame(
            ['backend:report:read', 'backend:report:write', 'portal:report:read', 'portal:report:write', 'guest:report:read', 'guest:report:write', 'backend:access', 'portal:access', 'guest:access'],
            permissions()->all(),
        );
    }

    public function test_an_enum_without_a_case_filter_offers_every_case_in_every_space(): void
    {
        Config::set('permissions.types', [TestReportPermission::class]);

        $this->assertSame(['portal:report:read', 'portal:report:write', 'portal:access'], permissions()->in(TestSpace::PORTAL)->all());
        $this->assertSame(['guest:report:read', 'guest:report:write', 'guest:access'], permissions()->in(TestSpace::GUEST)->all());
    }

    public function test_a_case_filter_restricts_a_space_with_only(): void
    {
        $portal = permissions()->in(TestSpace::PORTAL)->all();

        $this->assertContains('portal:manageAccount', $portal);
        $this->assertNotContains('portal:manageUsers', $portal);
        $this->assertNotContains('portal:article:view', $portal);
    }

    public function test_a_case_filter_restricts_a_space_with_except(): void
    {
        $guest = permissions()->in(TestSpace::GUEST)->all();

        $this->assertContains('guest:runReports', $guest);
        $this->assertNotContains('guest:manageAccount', $guest);
        $this->assertNotContains('guest:manageUsers', $guest);
    }

    public function test_a_grouped_enum_renders_its_group_between_space_and_permission(): void
    {
        Config::set('permissions.types', [TestLeadPermission::class]);

        $this->assertSame('portal:leadSales:phone:viewAny', permissions()->in(TestSpace::PORTAL)->for(TestLeadPermission::PHONE)->viewAny());
        $this->assertSame('portal:leadSales:webForm:update', permissions()->in(TestSpace::PORTAL)->for(TestLeadPermission::WEB_FORM)->update());
        $this->assertSame(
            ['portal:leadSales:phone:viewAny', 'portal:leadSales:phone:update'],
            permissions()->in(TestSpace::PORTAL)->for(TestLeadPermission::PHONE)->any(PermissionAction::VIEW_ANY, PermissionAction::UPDATE),
        );
        $this->assertSame(
            [
                'portal:leadSales:phone:viewAny',
                'portal:leadSales:phone:update',
                'portal:leadSales:webForm:viewAny',
                'portal:leadSales:webForm:update',
                'portal:access',
            ],
            permissions()->all(),
        );
    }

    public function test_only_the_grouped_enum_gets_the_group(): void
    {
        Config::set('permissions.types', [TestGuardPermission::class, TestLeadPermission::class]);

        $this->assertSame(
            [
                'portal:manageAccount',
                'portal:leadSales:phone:viewAny',
                'portal:leadSales:phone:update',
                'portal:leadSales:webForm:viewAny',
                'portal:leadSales:webForm:update',
                'portal:access',
            ],
            permissions()->in(TestSpace::PORTAL)->all(),
        );
    }
}

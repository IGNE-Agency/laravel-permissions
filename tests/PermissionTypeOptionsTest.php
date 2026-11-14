<?php

declare(strict_types=1);

namespace Igne\Permissions\Tests;

use Igne\Permissions\Exceptions\PermissionException;
use Igne\Permissions\PermissionAction;
use Igne\Permissions\Tests\Fixtures\TestComment;
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
        Config::set('permissions.subjects', [TestSettingPermission::class]);

        $this->assertSame(['darkMode', 'beta'], permissions()->all());
        $this->assertSame('Dark Mode', TestSettingPermission::DARK_MODE->label());
    }

    public function test_an_app_defined_action_set_replaces_the_default(): void
    {
        Config::set('permissions.subjects', [TestReportPermission::class]);

        $this->assertSame(
            ['backend:report:read', 'backend:report:write'],
            permission()->in(TestSpace::ADMIN)->any(TestReportPermission::REPORT, TestCustomAction::READ, TestCustomAction::WRITE),
        );
        $this->assertSame(
            ['backend:report:read', 'backend:report:write', 'backend:access', 'portal:report:read', 'portal:report:write', 'portal:access', 'guest:report:read', 'guest:report:write', 'guest:access'],
            permissions()->all(),
        );
    }

    public function test_an_enum_without_a_case_filter_offers_every_case_in_every_space(): void
    {
        Config::set('permissions.subjects', [TestReportPermission::class]);

        $this->assertSame(['portal:report:read', 'portal:report:write', 'portal:access'], $this->namesIn('portal'));
        $this->assertSame(['guest:report:read', 'guest:report:write', 'guest:access'], $this->namesIn('guest'));
    }

    public function test_a_case_filter_restricts_a_space_with_only(): void
    {
        $portal = $this->namesIn('portal');

        $this->assertContains('portal:manageAccount', $portal);
        $this->assertNotContains('portal:manageUsers', $portal);
        $this->assertNotContains('portal:article:view', $portal);
    }

    public function test_a_case_filter_restricts_a_space_with_except(): void
    {
        $guest = $this->namesIn('guest');

        $this->assertContains('guest:runReports', $guest);
        $this->assertNotContains('guest:manageAccount', $guest);
        $this->assertNotContains('guest:manageUsers', $guest);
    }

    public function test_a_grouped_enum_renders_its_group_between_space_and_permission(): void
    {
        Config::set('permissions.subjects', [TestLeadPermission::class]);

        $this->assertSame('portal:leadSales:phone:viewAny', permission()->in(TestSpace::PORTAL)->viewAny(TestLeadPermission::PHONE));
        $this->assertSame('portal:leadSales:webForm:update', permission()->in(TestSpace::PORTAL)->update(TestLeadPermission::WEB_FORM));
        $this->assertSame(
            ['portal:leadSales:phone:viewAny', 'portal:leadSales:phone:update'],
            permission()->in(TestSpace::PORTAL)->any(TestLeadPermission::PHONE, PermissionAction::VIEW_ANY, PermissionAction::UPDATE),
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
        Config::set('permissions.subjects', [TestGuardPermission::class, TestLeadPermission::class]);

        $this->assertSame(
            [
                'portal:manageAccount',
                'portal:leadSales:phone:viewAny',
                'portal:leadSales:phone:update',
                'portal:leadSales:webForm:viewAny',
                'portal:leadSales:webForm:update',
                'portal:access',
            ],
            $this->namesIn('portal'),
        );
    }

    public function test_a_model_filter_leaves_it_out_of_the_spaces_it_does_not_allow(): void
    {
        Config::set('permissions.subjects', [TestComment::class]);

        $this->assertContains('backend:testComment:view', $this->namesIn('backend'));
        $this->assertContains('portal:testComment:view', $this->namesIn('portal'));
        $this->assertSame(['guest:access'], $this->namesIn('guest'));
        $this->assertSame('portal:testComment:update', permission()->in(TestSpace::PORTAL)->update(TestComment::class));
    }

    public function test_a_name_in_a_space_the_model_filter_excludes_throws(): void
    {
        Config::set('permissions.subjects', [TestComment::class]);

        $this->expectException(PermissionException::class);

        permission()->in(TestSpace::GUEST)->view(TestComment::class);
    }

    public function test_a_name_in_a_space_the_case_filter_excludes_throws(): void
    {
        $this->expectException(PermissionException::class);

        permission()->in(TestSpace::GUEST)->name(TestGuardPermission::MANAGE_USERS);
    }

    /**
     * The names of one space, read from the full list by their space prefix.
     *
     * @return list<string>
     */
    private function namesIn(string $spacePrefix): array
    {
        return array_values(array_filter(
            permissions()->all(),
            static fn (string $name): bool => str_starts_with($name, $spacePrefix.':'),
        ));
    }
}

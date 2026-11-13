<?php

declare(strict_types=1);

namespace Igne\Permissions\Tests;

use Igne\Permissions\Exceptions\PermissionException;
use Igne\Permissions\PermissionAction;
use Igne\Permissions\PermissionQuery;
use Igne\Permissions\Permissions;
use Igne\Permissions\Tests\Fixtures\TestCustomAction;
use Igne\Permissions\Tests\Fixtures\TestExportReports;
use Igne\Permissions\Tests\Fixtures\TestGuardPermission;
use Igne\Permissions\Tests\Fixtures\TestOrganization;
use Igne\Permissions\Tests\Fixtures\TestResourcePermission;
use Igne\Permissions\Tests\Fixtures\TestSettingPermission;
use Igne\Permissions\Tests\Fixtures\TestSpace;
use ReflectionMethod;

/**
 * The default fixtures render exportReports without a space; in ADMIN the three guard names, four
 * resources and the organization with seven actions each, and access; in PORTAL manageAccount, the
 * organization and access; in GUEST runReports and access.
 */
final class PermissionQueryTest extends PermissionTestCase
{
    public function test_in_lists_every_name_of_a_space_with_access_last(): void
    {
        $admin = permissions()->in(TestSpace::ADMIN)->names();

        $this->assertCount(3 + 4 * 7 + 7 + 1, $admin);
        $this->assertSame('backend:manageUsers', $admin[0]);
        $this->assertSame('backend:access', $admin[array_key_last($admin)]);
        $this->assertSame([
            'portal:manageAccount',
            ...$this->organizationNames('portal'),
            'portal:access',
        ], permissions()->in(TestSpace::PORTAL)->names());
        $this->assertSame(['guest:runReports', 'guest:access'], permissions()->in(TestSpace::GUEST)->names());
    }

    public function test_in_several_spaces_lists_them_in_turn(): void
    {
        $this->assertSame(
            [...permissions()->in(TestSpace::PORTAL)->names(), ...permissions()->in(TestSpace::GUEST)->names()],
            permissions()->in([TestSpace::PORTAL, TestSpace::GUEST])->names(),
        );
    }

    public function test_all_lists_the_subjects_without_spaces_then_every_space(): void
    {
        $this->assertSame([
            'exportReports',
            ...permissions()->in(TestSpace::ADMIN)->names(),
            ...permissions()->in(TestSpace::PORTAL)->names(),
            ...permissions()->in(TestSpace::GUEST)->names(),
        ], permissions()->all());
    }

    public function test_only_a_class_subject(): void
    {
        $this->assertSame($this->organizationNames('portal'), permissions()->in(TestSpace::PORTAL)->only(TestOrganization::class)->names());
    }

    public function test_only_a_subject_with_some_of_its_actions(): void
    {
        $this->assertSame(
            ['backend:testOrganization:viewAny', 'backend:testOrganization:view'],
            permissions()->in(TestSpace::ADMIN)->only(TestOrganization::class, [PermissionAction::VIEW_ANY, PermissionAction::VIEW])->names(),
        );
        $this->assertSame(
            ['backend:user:view'],
            permissions()->in(TestSpace::ADMIN)->only(TestResourcePermission::USER, PermissionAction::VIEW)->names(),
        );
    }

    public function test_a_second_argument_applies_to_every_listed_subject(): void
    {
        $this->assertSame(
            ['backend:user:delete', 'backend:testOrganization:delete'],
            permissions()->in(TestSpace::ADMIN)->only([TestOrganization::class, TestResourcePermission::USER], PermissionAction::DELETE)->names(),
        );
    }

    public function test_a_bare_action_selects_it_on_every_subject_that_lists_it(): void
    {
        $this->assertSame([
            'backend:article:view',
            'backend:profile:view',
            'backend:user:view',
            'backend:blogPost:view',
            'backend:testOrganization:view',
        ], permissions()->in(TestSpace::ADMIN)->only(PermissionAction::VIEW)->names());
    }

    public function test_a_list_mixes_subjects_actions_pairs_and_keyed_entries(): void
    {
        $this->assertSame(
            ['backend:manageUsers', 'backend:testOrganization:view'],
            permissions()->in(TestSpace::ADMIN)->only([TestGuardPermission::MANAGE_USERS, [TestOrganization::class, [PermissionAction::VIEW]]])->names(),
        );
        $this->assertSame(
            ['portal:testOrganization:update'],
            permissions()->in(TestSpace::PORTAL)->only([TestOrganization::class => [PermissionAction::UPDATE]])->names(),
        );
        $this->assertSame(
            ['portal:manageAccount', 'portal:testOrganization:view'],
            permissions()->in(TestSpace::PORTAL)->only([TestGuardPermission::MANAGE_ACCOUNT, PermissionAction::VIEW])->names(),
        );
    }

    public function test_entries_for_the_same_subject_merge_their_actions(): void
    {
        $this->assertSame(
            ['portal:testOrganization:view', 'portal:testOrganization:update'],
            permissions()->in(TestSpace::PORTAL)->only([
                TestOrganization::class => [PermissionAction::UPDATE],
                [TestOrganization::class, PermissionAction::VIEW],
            ])->names(),
        );
    }

    public function test_chained_only_calls_form_a_union(): void
    {
        $this->assertSame(
            ['backend:manageUsers', 'backend:runReports'],
            permissions()->in(TestSpace::ADMIN)->only(TestGuardPermission::MANAGE_USERS)->only(TestGuardPermission::RUN_REPORTS)->names(),
        );
    }

    public function test_except_removes_its_names(): void
    {
        $this->assertSame(['guest:access'], permissions()->in(TestSpace::GUEST)->except(TestGuardPermission::RUN_REPORTS)->names());

        $portal = permissions()->in(TestSpace::PORTAL)->except([PermissionAction::UPDATE, PermissionAction::DELETE])->names();

        $this->assertCount(9 - 2, $portal);
        $this->assertNotContains('portal:testOrganization:update', $portal);
        $this->assertNotContains('portal:testOrganization:delete', $portal);
    }

    public function test_only_then_except_restricts_then_removes(): void
    {
        $names = permissions()->in(TestSpace::ADMIN)
            ->only(PermissionAction::VIEW)
            ->except(TestResourcePermission::ARTICLE, PermissionAction::VIEW)
            ->names();

        $this->assertCount(4, $names);
        $this->assertNotContains('backend:article:view', $names);
    }

    public function test_except_then_only_caps_the_named_subject_and_keeps_the_rest(): void
    {
        $names = permissions()->in(TestSpace::ADMIN)
            ->except(TestGuardPermission::MANAGE_ACCOUNT)
            ->only([[TestOrganization::class, [PermissionAction::VIEW]]])
            ->names();

        $this->assertContains('backend:testOrganization:view', $names);
        $this->assertNotContains('backend:testOrganization:create', $names);
        $this->assertContains('backend:article:create', $names);
        $this->assertContains('backend:manageUsers', $names);
        $this->assertNotContains('backend:manageAccount', $names);
        $this->assertContains('backend:access', $names);
    }

    public function test_except_a_subject_then_only_adds_back_its_listed_actions(): void
    {
        $names = permissions()->in(TestSpace::PORTAL)
            ->except(TestOrganization::class)
            ->only(TestOrganization::class, PermissionAction::VIEW)
            ->names();

        $this->assertSame(['portal:manageAccount', 'portal:testOrganization:view', 'portal:access'], $names);
    }

    public function test_with_access_keeps_the_access_permission_of_every_space(): void
    {
        $this->assertSame(['backend:manageUsers'], permissions()->in(TestSpace::ADMIN)->only(TestGuardPermission::MANAGE_USERS)->names());
        $this->assertSame(
            ['backend:manageUsers', 'backend:access', 'portal:access'],
            permissions()->in([TestSpace::ADMIN, TestSpace::PORTAL])->withAccess()->only(TestGuardPermission::MANAGE_USERS)->names(),
        );
    }

    public function test_only_for_and_except_for_pin_an_operation_to_one_space(): void
    {
        $names = permissions()->in([TestSpace::ADMIN, TestSpace::PORTAL])
            ->onlyFor(TestSpace::ADMIN, TestGuardPermission::MANAGE_USERS)
            ->exceptFor(TestSpace::PORTAL, TestOrganization::class)
            ->names();

        $this->assertSame(['backend:manageUsers', 'portal:manageAccount', 'portal:access'], $names);
    }

    public function test_a_subject_outside_the_space_yields_nothing_not_everything(): void
    {
        $this->assertSame([], permissions()->in(TestSpace::GUEST)->only(TestOrganization::class)->names());
        $this->assertSame([], permissions()->in(TestSpace::PORTAL)->only(TestGuardPermission::MANAGE_USERS)->names());
    }

    public function test_top_level_only_renders_a_subject_in_every_space_it_declares(): void
    {
        $this->assertSame(
            ['backend:testOrganization:view', 'portal:testOrganization:view'],
            permissions()->only(TestOrganization::class, PermissionAction::VIEW)->names(),
        );
        $this->assertSame(['backend:manageAccount', 'portal:manageAccount'], permissions()->only(TestGuardPermission::MANAGE_ACCOUNT)->names());
    }

    public function test_top_level_only_renders_a_subject_without_spaces_as_it_is(): void
    {
        $this->assertSame(['exportReports'], permissions()->only(TestExportReports::class)->names());
    }

    public function test_top_level_except_equals_the_scope_lists_joined(): void
    {
        $joined = permissions()->only(TestExportReports::class)->names();

        foreach (TestSpace::cases() as $space) {
            $joined = [...$joined, ...permissions()->in($space)->except(TestGuardPermission::RUN_REPORTS)->names()];
        }

        $this->assertSame($joined, permissions()->except(TestGuardPermission::RUN_REPORTS)->names());
    }

    public function test_lists_never_carry_the_space_placeholder(): void
    {
        foreach ([...permissions()->all(), ...permissions()->only(PermissionAction::VIEW), ...permissions()->except(TestOrganization::class)] as $name) {
            $this->assertStringNotContainsString(Permissions::SPACE_PLACEHOLDER, $name);
        }
    }

    public function test_the_query_is_iterable_countable_and_immutable(): void
    {
        $base = permissions()->in(TestSpace::GUEST);
        $query = $base->only(TestGuardPermission::RUN_REPORTS);

        $this->assertSame(['guest:runReports'], [...$query]);
        $this->assertCount(1, $query);
        $this->assertSame(['guest:runReports', 'guest:access'], $base->names());
    }

    public function test_in_needs_a_space(): void
    {
        $this->expectException(PermissionException::class);

        permissions()->in([]);
    }

    public function test_an_empty_selection_is_rejected(): void
    {
        $this->expectException(PermissionException::class);

        permissions()->in(TestSpace::ADMIN)->only([]);
    }

    public function test_an_action_the_subject_does_not_list_is_rejected(): void
    {
        $this->expectException(PermissionException::class);

        permissions()->in(TestSpace::ADMIN)->only(TestOrganization::class, TestCustomAction::READ);
    }

    public function test_actions_for_a_subject_without_actions_are_rejected(): void
    {
        $this->expectException(PermissionException::class);

        permissions()->only(TestExportReports::class, PermissionAction::VIEW);
    }

    public function test_a_bare_action_no_subject_lists_is_rejected(): void
    {
        $this->expectException(PermissionException::class);

        permissions()->only(TestCustomAction::READ);
    }

    public function test_a_second_argument_with_a_bare_action_is_rejected(): void
    {
        $this->expectException(PermissionException::class);

        permissions()->only(PermissionAction::VIEW, PermissionAction::UPDATE);
    }

    public function test_a_second_argument_with_a_pair_is_rejected(): void
    {
        $this->expectException(PermissionException::class);

        permissions()->only([[TestOrganization::class, PermissionAction::VIEW]], PermissionAction::UPDATE);
    }

    public function test_an_unregistered_subject_is_rejected(): void
    {
        $this->expectException(PermissionException::class);

        permissions()->in(TestSpace::ADMIN)->except(TestSettingPermission::BETA);
    }

    public function test_a_pair_with_something_other_than_actions_is_rejected(): void
    {
        $this->expectException(PermissionException::class);

        permissions()->only([[TestOrganization::class, [TestResourcePermission::ARTICLE]]]);
    }

    /**
     * The native and PHPDoc types rule a plain string out; the runtime guard still answers an
     * untyped caller with a PermissionException.
     */
    public function test_a_string_that_is_not_a_subject_class_is_rejected(): void
    {
        $this->expectException(PermissionException::class);

        (new ReflectionMethod(PermissionQuery::class, 'only'))->invoke(permissions()->in(TestSpace::ADMIN), 'user');
    }

    /**
     * @return list<string>
     */
    private function organizationNames(string $prefix): array
    {
        return array_map(
            static fn (PermissionAction $action): string => $prefix.':testOrganization:'.$action->value,
            PermissionAction::cases(),
        );
    }
}

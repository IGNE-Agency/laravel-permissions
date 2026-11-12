<?php

declare(strict_types=1);

namespace Igne\Permissions\Tests;

use Igne\Permissions\Exceptions\PermissionException;
use Igne\Permissions\PermissionAction;
use Igne\Permissions\SpacePermissionQuery;
use Igne\Permissions\Tests\Fixtures\TestGuardPermission;
use Igne\Permissions\Tests\Fixtures\TestLeadPermission;
use Igne\Permissions\Tests\Fixtures\TestResourcePermission;
use Igne\Permissions\Tests\Fixtures\TestSettingPermission;
use Igne\Permissions\Tests\Fixtures\TestSpace;
use Illuminate\Support\Facades\Config;
use ReflectionMethod;

final class SpacePermissionQueryTest extends PermissionTestCase
{
    public function test_with_access_alone_returns_the_whole_space_including_access(): void
    {
        $whole = permissions()->in(TestSpace::ADMIN)->withAccess()->names();

        $this->assertSame(permissions()->in(TestSpace::ADMIN)->all(), $whole);
        $this->assertContains('backend:access', $whole);
        $this->assertContains('backend:manageUsers', $whole);
        $this->assertContains('backend:article:view', $whole);
    }

    public function test_except_a_case_with_actions_removes_only_those_names(): void
    {
        $names = permissions()->in(TestSpace::ADMIN)
            ->withAccess()
            ->except(TestResourcePermission::ARTICLE, [PermissionAction::VIEW, PermissionAction::DELETE])
            ->names();

        $this->assertNotContains('backend:article:view', $names);
        $this->assertNotContains('backend:article:delete', $names);
        $this->assertContains('backend:article:create', $names);
        $this->assertContains('backend:user:view', $names);
        $this->assertContains('backend:manageUsers', $names);
        $this->assertContains('backend:access', $names);
    }

    public function test_except_with_mixed_pairs_spans_every_enum(): void
    {
        $names = permissions()->in(TestSpace::ADMIN)
            ->withAccess()
            ->except([
                TestGuardPermission::MANAGE_ACCOUNT,
                [TestResourcePermission::ARTICLE, [PermissionAction::VIEW]],
            ])
            ->names();

        $this->assertNotContains('backend:manageAccount', $names);
        $this->assertNotContains('backend:article:view', $names);
        $this->assertContains('backend:manageUsers', $names);
        $this->assertContains('backend:article:create', $names);
        $this->assertContains('backend:access', $names);
    }

    public function test_only_with_mixed_pairs_restricts_and_keeps_access(): void
    {
        $names = permissions()->in(TestSpace::ADMIN)
            ->withAccess()
            ->only([
                TestGuardPermission::MANAGE_USERS,
                [TestResourcePermission::USER, [PermissionAction::VIEW_ANY, PermissionAction::VIEW]],
            ])
            ->names();

        $this->assertSame([
            'backend:manageUsers',
            'backend:user:viewAny',
            'backend:user:view',
            'backend:access',
        ], $names);
    }

    public function test_a_pair_with_a_single_action(): void
    {
        $this->assertSame(
            ['backend:user:view', 'backend:access'],
            permissions()->in(TestSpace::ADMIN)->withAccess()->only([[TestResourcePermission::USER, PermissionAction::VIEW]])->names(),
        );
    }

    public function test_only_without_with_access_drops_access(): void
    {
        $names = permissions()->in(TestSpace::ADMIN)->withAccess();

        $this->assertSame(['backend:manageUsers', 'backend:access'], $names->only(TestGuardPermission::MANAGE_USERS)->names());
    }

    public function test_chained_only_then_except_compose(): void
    {
        $names = permissions()->in(TestSpace::ADMIN)
            ->withAccess()
            ->only(TestResourcePermission::USER)
            ->except(TestResourcePermission::USER, [PermissionAction::DELETE])
            ->names();

        $this->assertContains('backend:user:view', $names);
        $this->assertNotContains('backend:user:delete', $names);
        $this->assertContains('backend:access', $names);
        $this->assertCount(count(PermissionAction::cases()) - 1 + 1, $names);
    }

    public function test_except_then_only_adds_back_the_listed_actions(): void
    {
        $names = permissions()->in(TestSpace::ADMIN)
            ->withAccess()
            ->except(TestResourcePermission::ARTICLE)
            ->only([[TestResourcePermission::ARTICLE, [PermissionAction::VIEW]]])
            ->names();

        // The whole Article resource is excluded, then only its view action is added back.
        $this->assertContains('backend:article:view', $names);
        $this->assertNotContains('backend:article:create', $names);
        $this->assertNotContains('backend:article:delete', $names);
        // Other enums are untouched, access is kept.
        $this->assertContains('backend:user:view', $names);
        $this->assertContains('backend:manageUsers', $names);
        $this->assertContains('backend:access', $names);
    }

    public function test_only_caps_a_resource_to_its_actions_without_excepting_it_first(): void
    {
        $names = permissions()->in(TestSpace::ADMIN)
            ->withAccess()
            ->except(TestGuardPermission::MANAGE_ACCOUNT) // an unrelated except puts the chain in cap mode
            ->only([[TestResourcePermission::ARTICLE, [PermissionAction::VIEW]]])
            ->names();

        // Article is capped to view without ever being excepted.
        $this->assertContains('backend:article:view', $names);
        $this->assertNotContains('backend:article:create', $names);
        $this->assertNotContains('backend:article:delete', $names);
        // Resources not named in only() stay fully included; the unrelated except still applied.
        $this->assertContains('backend:user:create', $names);
        $this->assertContains('backend:manageUsers', $names);
        $this->assertNotContains('backend:manageAccount', $names);
        $this->assertContains('backend:access', $names);
    }

    public function test_several_spaces_return_every_space_and_a_global_except_applies_to_all(): void
    {
        $names = permissions()->in([TestSpace::ADMIN, TestSpace::PORTAL])
            ->withAccess()
            ->except(TestGuardPermission::MANAGE_ACCOUNT)
            ->names();

        $this->assertContains('backend:access', $names);
        $this->assertContains('portal:access', $names);
        $this->assertContains('backend:manageUsers', $names);
        $this->assertNotContains('backend:manageAccount', $names);
        $this->assertNotContains('portal:manageAccount', $names);
    }

    public function test_except_for_scopes_to_one_space_only(): void
    {
        $names = permissions()->in([TestSpace::ADMIN, TestSpace::PORTAL])
            ->withAccess()
            ->exceptFor(TestSpace::ADMIN, TestGuardPermission::MANAGE_ACCOUNT)
            ->names();

        $this->assertNotContains('backend:manageAccount', $names);
        $this->assertContains('portal:manageAccount', $names);
    }

    public function test_only_for_scopes_to_one_space_while_others_stay_complete(): void
    {
        $names = permissions()->in([TestSpace::ADMIN, TestSpace::PORTAL])
            ->withAccess()
            ->onlyFor(TestSpace::ADMIN, TestGuardPermission::MANAGE_USERS)
            ->names();

        $this->assertContains('backend:manageUsers', $names);
        $this->assertContains('backend:access', $names);
        $this->assertNotContains('backend:article:view', $names);
        $this->assertContains('portal:manageAccount', $names);
        $this->assertContains('portal:access', $names);
    }

    public function test_grouped_names_filter_like_any_other(): void
    {
        Config::set('permissions.types', [TestLeadPermission::class]);

        $this->assertSame(
            ['portal:leadSales:webForm:viewAny', 'portal:access'],
            permissions()->in(TestSpace::PORTAL)->withAccess()->only([[TestLeadPermission::WEB_FORM, PermissionAction::VIEW_ANY]])->names(),
        );
    }

    public function test_the_query_is_iterable_and_countable(): void
    {
        $query = permissions()->in(TestSpace::ADMIN)->withAccess()->only(TestGuardPermission::MANAGE_USERS);

        $this->assertSame(['backend:manageUsers', 'backend:access'], [...$query]);
        $this->assertCount(2, $query);
    }

    public function test_the_query_is_immutable(): void
    {
        $base = permissions()->in(TestSpace::ADMIN)->withAccess();
        $base->only(TestGuardPermission::MANAGE_USERS);

        $this->assertSame(permissions()->in(TestSpace::ADMIN)->all(), $base->names());
    }

    public function test_a_bare_action_in_a_list_is_rejected(): void
    {
        $this->expectException(PermissionException::class);
        $this->expectExceptionMessage('select permissions, not bare actions');

        $this->onlyUnchecked([PermissionAction::VIEW]);
    }

    public function test_a_pair_led_by_an_action_is_rejected(): void
    {
        $this->expectException(PermissionException::class);
        $this->expectExceptionMessage('select permissions, not bare actions');

        $this->onlyUnchecked([[PermissionAction::VIEW, PermissionAction::UPDATE]]);
    }

    public function test_a_string_is_no_longer_a_permission(): void
    {
        $this->expectException(PermissionException::class);
        $this->expectExceptionMessage('expected a permission case or a [permission, actions] pair');

        $this->onlyUnchecked([['user', [PermissionAction::VIEW]]]);
    }

    public function test_a_pair_with_something_other_than_actions_is_rejected(): void
    {
        $this->expectException(PermissionException::class);
        $this->expectExceptionMessage('each pair must be [permission, actions]');

        $this->onlyUnchecked([[TestResourcePermission::USER, [TestResourcePermission::ARTICLE]]]);
    }

    public function test_a_second_argument_with_a_list_selection_is_rejected(): void
    {
        $this->expectException(PermissionException::class);
        $this->expectExceptionMessage('pass actions inside each entry');

        permissions()->in(TestSpace::ADMIN)->withAccess()->only([TestResourcePermission::USER], [PermissionAction::VIEW]);
    }

    public function test_an_unregistered_enum_is_rejected(): void
    {
        $this->expectException(PermissionException::class);
        $this->expectExceptionMessage('is not registered in permissions.types');

        permissions()->in(TestSpace::ADMIN)->withAccess()->except(TestSettingPermission::BETA);
    }

    /**
     * Calls only() with a selection the native and PHPDoc types already rule out, to prove the
     * runtime guard answers an untyped caller with a PermissionException instead of misbehaving.
     */
    private function onlyUnchecked(mixed $selection): void
    {
        (new ReflectionMethod(SpacePermissionQuery::class, 'only'))->invoke(permissions()->in(TestSpace::ADMIN)->withAccess(), $selection);
    }
}

<?php

declare(strict_types=1);

namespace Igne\Permissions\Tests;

use Igne\Permissions\PermissionAction;
use Igne\Permissions\Tests\Fixtures\TestDocumentPermission;
use Igne\Permissions\Tests\Fixtures\TestGatedSpace;
use Igne\Permissions\Tests\Fixtures\TestGuardPermission;
use Igne\Permissions\Tests\Fixtures\TestSpace;
use Igne\Permissions\Tests\Fixtures\TestUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\PermissionMiddleware;

/**
 * OFFICE requires its access permission next to every grant; KIOSK and the default TestSpace
 * spaces do not.
 */
final class SpaceAccessRuleTest extends SpatieTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->registerSubjects(TestDocumentPermission::class);
    }

    public function test_a_grant_in_a_space_requiring_access_is_denied_without_access(): void
    {
        $this->assertFalse($this->user($this->officeView())->can($this->officeView()));
    }

    public function test_a_grant_in_a_space_requiring_access_is_allowed_with_access(): void
    {
        $this->assertTrue($this->user($this->officeAccess(), $this->officeView())->can($this->officeView()));
    }

    public function test_access_alone_grants_nothing_else(): void
    {
        $holder = $this->user($this->officeAccess());

        $this->assertTrue($holder->can($this->officeAccess()));
        $this->assertFalse($holder->can($this->officeView()));
    }

    public function test_a_space_that_does_not_require_access_is_unaffected(): void
    {
        $kioskView = permission()->in(TestGatedSpace::KIOSK)->view(TestDocumentPermission::DOCUMENT);
        $manageUsers = permission()->in(TestSpace::ADMIN)->name(TestGuardPermission::MANAGE_USERS);

        $this->assertTrue($this->user($kioskView)->can($kioskView));
        $this->assertTrue($this->user($manageUsers)->can($manageUsers));
    }

    public function test_the_placeholder_form_follows_the_rule(): void
    {
        $placeholder = permission()->view(TestDocumentPermission::DOCUMENT);

        $this->assertFalse($this->userIn(TestGatedSpace::OFFICE, $this->officeView())->can($placeholder));
        $this->assertTrue($this->userIn(TestGatedSpace::OFFICE, $this->officeAccess(), $this->officeView())->can($placeholder));
    }

    public function test_a_revoked_access_permission_denies_the_space(): void
    {
        $holder = $this->user($this->officeAccess(), $this->officeView());
        $holder->revokedPermissions = [$this->officeAccess()];

        $this->assertFalse($holder->can($this->officeView()));
    }

    public function test_the_rule_denies_before_an_app_callback_that_grants_the_name(): void
    {
        $officeView = $this->officeView();
        Gate::before(static fn (TestUser $user, string $ability): ?bool => $ability === $officeView ? true : null);

        $this->assertFalse($this->user()->can($officeView));
        $this->assertTrue($this->user($this->officeAccess())->can($officeView));
    }

    public function test_an_app_callback_that_grants_everything_grants_access_too(): void
    {
        Gate::before(static fn (): bool => true);

        $this->assertTrue($this->user()->can($this->officeView()));
    }

    public function test_the_can_middleware_answers_403_without_access(): void
    {
        Route::get('/document', static fn (): string => 'ok')->middleware('can:'.$this->officeView());

        $this->actingAs($this->user($this->officeView()))->get('/document')->assertForbidden();
        $this->actingAs($this->user($this->officeAccess(), $this->officeView()))->get('/document')->assertOk();
    }

    public function test_spaties_permission_middleware_answers_403_without_access(): void
    {
        Route::aliasMiddleware('permission', PermissionMiddleware::class);
        Route::get('/document', static fn (): string => 'ok')->middleware('permission:'.$this->officeView());

        $this->actingAs($this->user($this->officeView()))->get('/document')->assertForbidden();
        $this->actingAs($this->user($this->officeAccess(), $this->officeView()))->get('/document')->assertOk();
    }

    public function test_spaties_direct_methods_see_only_what_was_stored(): void
    {
        $this->assertTrue($this->user($this->officeView())->hasPermissionTo($this->officeView()));
    }

    private function userIn(TestGatedSpace $space, string ...$permissions): TestUser
    {
        $user = $this->user(...$permissions);
        $user->space = $space;

        return $user;
    }

    private function officeAccess(): string
    {
        return permission()->in(TestGatedSpace::OFFICE)->access();
    }

    private function officeView(): string
    {
        return permission()->in(TestGatedSpace::OFFICE)->action(PermissionAction::VIEW, TestDocumentPermission::DOCUMENT);
    }
}

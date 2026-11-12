<?php

declare(strict_types=1);

namespace Igne\Permissions\Tests;

use Igne\Permissions\Contracts\SpaceEnum;
use Igne\Permissions\Exceptions\PermissionException;
use Igne\Permissions\PermissionAction;
use Igne\Permissions\Tests\Fixtures\TestGuardPermission;
use Igne\Permissions\Tests\Fixtures\TestResourcePermission;
use Igne\Permissions\Tests\Fixtures\TestSpace;
use Igne\Permissions\Tests\Fixtures\TestUser;
use Igne\Permissions\Tests\Fixtures\TestUserWithoutSpace;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Middleware\PermissionMiddleware;

/**
 * Names built without in() through every entry point of the Gate. ARTICLE exists in the ADMIN
 * space only; MANAGE_ACCOUNT exists in ADMIN and PORTAL, so the same grant answers differently
 * per space.
 */
final class PermissionCheckTest extends SpatieTestCase
{
    public function test_can_resolves_the_placeholder_from_the_users_space(): void
    {
        $holder = $this->userIn(TestSpace::ADMIN, $this->adminArticleView());
        $other = $this->userIn(TestSpace::ADMIN);

        $this->assertTrue($holder->can($this->articleViewPlaceholder()));
        $this->assertFalse($other->can($this->articleViewPlaceholder()));
    }

    public function test_the_same_grant_answers_per_user_space(): void
    {
        $portalUser = $this->userIn(TestSpace::PORTAL, $this->portalManageAccount());
        $adminUser = $this->userIn(TestSpace::ADMIN, $this->portalManageAccount());

        $this->assertTrue($portalUser->can($this->manageAccountPlaceholder()));
        $this->assertFalse($adminUser->can($this->manageAccountPlaceholder()));
    }

    public function test_the_gates_user_decides_not_the_authenticated_one(): void
    {
        $adminUser = $this->userIn(TestSpace::ADMIN, $this->adminManageAccount());
        $portalUser = $this->userIn(TestSpace::PORTAL);

        $this->actingAs($adminUser);

        $this->assertTrue(Gate::allows($this->manageAccountPlaceholder()));
        $this->assertFalse($portalUser->can($this->manageAccountPlaceholder()));
        $this->assertFalse(Gate::forUser($portalUser)->allows($this->manageAccountPlaceholder()));

        $this->actingAs($portalUser);

        $this->assertFalse(Gate::allows($this->manageAccountPlaceholder()));
        $this->assertTrue($adminUser->can($this->manageAccountPlaceholder()));
        $this->assertTrue(Gate::forUser($adminUser)->allows($this->manageAccountPlaceholder()));
    }

    public function test_can_resolves_without_an_authenticated_user(): void
    {
        $holder = $this->userIn(TestSpace::ADMIN, $this->adminArticleView());

        $this->assertTrue(Auth::guest());
        $this->assertTrue($holder->can($this->articleViewPlaceholder()));
    }

    public function test_the_gate_denies_a_guest(): void
    {
        $this->assertTrue(Auth::guest());
        $this->assertFalse(Gate::allows($this->articleViewPlaceholder()));
    }

    public function test_a_user_without_the_contract_checks_concrete_names_but_not_the_placeholder(): void
    {
        $user = TestUserWithoutSpace::create([
            'name' => 'Test user',
            'email' => uniqid('user', true).'@example.test',
            'password' => 'secret',
        ]);
        $user->givePermissionTo($this->adminArticleView());

        $this->assertTrue($user->can($this->adminArticleView()));

        $this->expectException(PermissionException::class);

        $user->can($this->articleViewPlaceholder());
    }

    public function test_a_revoked_name_denies_the_placeholder(): void
    {
        $holder = $this->userIn(TestSpace::ADMIN, $this->adminArticleView());

        $this->assertTrue($holder->can($this->articleViewPlaceholder()));

        $holder->revokedPermissions = [$this->adminArticleView()];

        $this->assertFalse($holder->can($this->articleViewPlaceholder()));
    }

    public function test_an_explicit_space_beats_the_users_space(): void
    {
        $portalUser = $this->userIn(TestSpace::PORTAL, $this->adminManageAccount());

        $this->assertTrue($portalUser->can($this->adminManageAccount()));
        $this->assertFalse($portalUser->can($this->manageAccountPlaceholder()));
    }

    public function test_blade_can_resolves_for_the_authenticated_user(): void
    {
        $holder = $this->userIn(TestSpace::ADMIN, $this->adminArticleView());
        $other = $this->userIn(TestSpace::ADMIN);
        $template = '@can($ability) granted @else denied @endcan';

        $this->actingAs($holder);
        $this->assertSame('granted', trim(Blade::render($template, ['ability' => $this->articleViewPlaceholder()], true)));

        $this->actingAs($other);
        $this->assertSame('denied', trim(Blade::render($template, ['ability' => $this->articleViewPlaceholder()], true)));
    }

    public function test_the_can_middleware_answers_200_for_a_holder_and_403_for_another(): void
    {
        Route::get('/article', static fn (): string => 'ok')->middleware('can:'.$this->articleViewPlaceholder());
        $holder = $this->userIn(TestSpace::ADMIN, $this->adminArticleView());
        $other = $this->userIn(TestSpace::ADMIN);

        $this->actingAs($holder)->get('/article')->assertOk();
        $this->actingAs($other)->get('/article')->assertForbidden();
    }

    public function test_spaties_permission_middleware_answers_200_for_a_holder_and_403_for_another(): void
    {
        Route::aliasMiddleware('permission', PermissionMiddleware::class);
        Route::get('/article', static fn (): string => 'ok')->middleware('permission:'.$this->articleViewPlaceholder());
        $holder = $this->userIn(TestSpace::ADMIN, $this->adminArticleView());
        $other = $this->userIn(TestSpace::ADMIN);

        $this->actingAs($holder)->get('/article')->assertOk();
        $this->actingAs($other)->get('/article')->assertForbidden();
    }

    public function test_can_any_resolves_every_placeholder(): void
    {
        $holder = $this->userIn(TestSpace::ADMIN, $this->adminArticleView());
        $article = permissions()->for(TestResourcePermission::ARTICLE);

        $this->assertTrue($holder->canAny($article->any(PermissionAction::VIEW, PermissionAction::CREATE)));
        $this->assertFalse($holder->canAny($article->any(PermissionAction::CREATE, PermissionAction::UPDATE)));
    }

    public function test_spaties_direct_methods_take_the_explicit_form(): void
    {
        $holder = $this->userIn(TestSpace::ADMIN, $this->adminArticleView());

        $this->assertTrue($holder->hasPermissionTo($this->adminArticleView()));

        $this->expectException(PermissionDoesNotExist::class);

        $holder->hasPermissionTo($this->articleViewPlaceholder());
    }

    private function userIn(SpaceEnum $space, string ...$permissions): TestUser
    {
        $user = $this->user(...$permissions);
        $user->space = $space;

        return $user;
    }

    private function articleViewPlaceholder(): string
    {
        return permissions()->for(TestResourcePermission::ARTICLE)->view();
    }

    private function adminArticleView(): string
    {
        return permissions()->in(TestSpace::ADMIN)->for(TestResourcePermission::ARTICLE)->view();
    }

    private function manageAccountPlaceholder(): string
    {
        return permissions()->for(TestGuardPermission::MANAGE_ACCOUNT)->name();
    }

    private function adminManageAccount(): string
    {
        return permissions()->in(TestSpace::ADMIN)->for(TestGuardPermission::MANAGE_ACCOUNT)->name();
    }

    private function portalManageAccount(): string
    {
        return permissions()->in(TestSpace::PORTAL)->for(TestGuardPermission::MANAGE_ACCOUNT)->name();
    }
}

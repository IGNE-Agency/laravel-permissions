<?php

declare(strict_types=1);

namespace Igne\Permissions\Tests;

use Igne\Permissions\Exceptions\PermissionException;
use Igne\Permissions\PermissionAction;
use Igne\Permissions\Tests\Fixtures\TestBrokenImplicationPermission;
use Igne\Permissions\Tests\Fixtures\TestCustomAction;
use Igne\Permissions\Tests\Fixtures\TestDocumentPermission;
use Igne\Permissions\Tests\Fixtures\TestGatedSpace;
use Igne\Permissions\Tests\Fixtures\TestNotePermission;
use Igne\Permissions\Tests\Fixtures\TestUser;
use Illuminate\Support\Facades\Route;

/**
 * In TestDocumentPermission update grants view, view grants viewAny, and delete and restore grant
 * each other. Every OFFICE user also holds office access, which that space requires.
 */
final class ImpliedActionTest extends SpatieTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->registerTypes(TestDocumentPermission::class, TestNotePermission::class);
    }

    public function test_an_implying_grant_grants_the_implied_actions_transitively(): void
    {
        $editor = $this->officeUser($this->office(TestDocumentPermission::DOCUMENT, PermissionAction::UPDATE));

        $this->assertTrue($editor->can($this->office(TestDocumentPermission::DOCUMENT, PermissionAction::VIEW)));
        $this->assertTrue($editor->can($this->office(TestDocumentPermission::DOCUMENT, PermissionAction::VIEW_ANY)));
        $this->assertFalse($editor->can($this->office(TestDocumentPermission::DOCUMENT, PermissionAction::CREATE)));
    }

    public function test_an_implication_runs_one_way(): void
    {
        $viewer = $this->officeUser($this->office(TestDocumentPermission::DOCUMENT, PermissionAction::VIEW));

        $this->assertTrue($viewer->can($this->office(TestDocumentPermission::DOCUMENT, PermissionAction::VIEW_ANY)));
        $this->assertFalse($viewer->can($this->office(TestDocumentPermission::DOCUMENT, PermissionAction::UPDATE)));
    }

    public function test_an_implication_stays_within_its_permission_and_space(): void
    {
        $kioskEditor = $this->user(permissions()->in(TestGatedSpace::KIOSK)->for(TestDocumentPermission::DOCUMENT)->update());
        $officeEditor = $this->officeUser($this->office(TestDocumentPermission::DOCUMENT, PermissionAction::UPDATE));

        $this->assertTrue($kioskEditor->can(permissions()->in(TestGatedSpace::KIOSK)->for(TestDocumentPermission::DOCUMENT)->view()));
        $this->assertFalse($kioskEditor->can($this->office(TestDocumentPermission::DOCUMENT, PermissionAction::VIEW)));
        $this->assertFalse($officeEditor->can($this->office(TestDocumentPermission::CONTRACT, PermissionAction::VIEW)));
    }

    public function test_a_cycle_of_implications_ends(): void
    {
        $deleter = $this->officeUser($this->office(TestDocumentPermission::DOCUMENT, PermissionAction::DELETE));
        $other = $this->officeUser();

        $this->assertTrue($deleter->can($this->office(TestDocumentPermission::DOCUMENT, PermissionAction::RESTORE)));
        $this->assertFalse($other->can($this->office(TestDocumentPermission::DOCUMENT, PermissionAction::RESTORE)));
        $this->assertFalse($other->can($this->office(TestDocumentPermission::DOCUMENT, PermissionAction::DELETE)));
    }

    public function test_the_placeholder_form_resolves_then_applies_implications(): void
    {
        $editor = $this->officeUser($this->office(TestDocumentPermission::DOCUMENT, PermissionAction::UPDATE));
        $editor->space = TestGatedSpace::OFFICE;

        $this->assertTrue($editor->can(permissions()->for(TestDocumentPermission::DOCUMENT)->view()));
    }

    public function test_an_enum_without_spaces_implies_too(): void
    {
        $editor = $this->user(permissions()->for(TestNotePermission::NOTE)->update());

        $this->assertTrue($editor->can(permissions()->for(TestNotePermission::NOTE)->view()));
        $this->assertFalse($editor->can(permissions()->for(TestNotePermission::NOTE)->delete()));
    }

    public function test_a_revoked_implying_name_grants_nothing(): void
    {
        $editor = $this->officeUser($this->office(TestDocumentPermission::DOCUMENT, PermissionAction::UPDATE));
        $editor->revokedPermissions = [$this->office(TestDocumentPermission::DOCUMENT, PermissionAction::UPDATE)];

        $this->assertFalse($editor->can($this->office(TestDocumentPermission::DOCUMENT, PermissionAction::VIEW)));
    }

    public function test_the_space_access_rule_applies_to_implied_actions(): void
    {
        $editor = $this->user($this->office(TestDocumentPermission::DOCUMENT, PermissionAction::UPDATE));

        $this->assertFalse($editor->can($this->office(TestDocumentPermission::DOCUMENT, PermissionAction::VIEW)));
    }

    public function test_the_can_middleware_honours_implications(): void
    {
        Route::get('/document', static fn (): string => 'ok')->middleware('can:'.$this->office(TestDocumentPermission::DOCUMENT, PermissionAction::VIEW));

        $this->actingAs($this->officeUser($this->office(TestDocumentPermission::DOCUMENT, PermissionAction::UPDATE)))->get('/document')->assertOk();
        $this->actingAs($this->officeUser($this->office(TestDocumentPermission::DOCUMENT, PermissionAction::CREATE)))->get('/document')->assertForbidden();
    }

    public function test_spaties_direct_methods_see_only_what_was_stored(): void
    {
        $editor = $this->officeUser($this->office(TestDocumentPermission::DOCUMENT, PermissionAction::UPDATE));

        $this->assertFalse($editor->hasPermissionTo($this->office(TestDocumentPermission::DOCUMENT, PermissionAction::VIEW)));
    }

    public function test_an_implied_action_the_enum_does_not_list_throws(): void
    {
        $this->registerTypes(TestBrokenImplicationPermission::class);
        $writer = $this->user(permissions()->for(TestBrokenImplicationPermission::ATTACHMENT)->action(TestCustomAction::WRITE));

        $this->expectException(PermissionException::class);

        $writer->can(permissions()->for(TestBrokenImplicationPermission::ATTACHMENT)->action(TestCustomAction::READ));
    }

    private function officeUser(string ...$permissions): TestUser
    {
        return $this->user(permissions()->in(TestGatedSpace::OFFICE)->access(), ...$permissions);
    }

    private function office(TestDocumentPermission $permission, PermissionAction $action): string
    {
        return permissions()->in(TestGatedSpace::OFFICE)->for($permission)->action($action);
    }
}

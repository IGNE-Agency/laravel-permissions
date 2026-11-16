<?php

declare(strict_types=1);

namespace Igne\Permissions\Tests\Fixtures;

use Igne\Permissions\Concerns\ResolvesPermissions;
use Igne\Permissions\Concerns\UsesDefaultActions;
use Igne\Permissions\Contracts\ImpliesActions;
use Igne\Permissions\Contracts\PermissionActionEnum;
use Igne\Permissions\Contracts\PermissionEnum;
use Igne\Permissions\Contracts\SpaceEnum;
use Igne\Permissions\Contracts\UsesActions;
use Igne\Permissions\Contracts\UsesSpaces;
use Igne\Permissions\PermissionAction;

/**
 * A space-using enum in the gated spaces whose actions imply each other: update grants view, view
 * grants viewAny, and delete and restore grant each other to prove cycles end.
 */
enum TestDocumentPermission: string implements ImpliesActions, PermissionEnum, UsesActions, UsesSpaces
{
    use ResolvesPermissions;
    use UsesDefaultActions;

    case DOCUMENT = 'document';
    case CONTRACT = 'contract';

    /**
     * @return list<SpaceEnum>
     */
    public static function spaces(): array
    {
        return TestGatedSpace::cases();
    }

    /**
     * @return list<PermissionActionEnum>
     */
    public static function impliedActions(PermissionActionEnum $action): array
    {
        return match ($action) {
            PermissionAction::UPDATE => [PermissionAction::VIEW],
            PermissionAction::VIEW => [PermissionAction::VIEW_ANY],
            PermissionAction::DELETE => [PermissionAction::RESTORE],
            PermissionAction::RESTORE => [PermissionAction::DELETE],
            default => [],
        };
    }
}

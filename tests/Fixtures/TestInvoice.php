<?php

declare(strict_types=1);

namespace Igne\Permissions\Tests\Fixtures;

use Igne\Permissions\Concerns\HasPermissionKey;
use Igne\Permissions\Contracts\ImpliesActions;
use Igne\Permissions\Contracts\PermissionActionEnum;
use Igne\Permissions\Contracts\PermissionSubject;
use Igne\Permissions\Contracts\UsesActions;
use Igne\Permissions\Contracts\UsesGroup;

/**
 * A class subject without spaces, with a group and an app-defined action set:
 * "billing:testInvoice:read".
 * Write implies read.
 */
final class TestInvoice implements ImpliesActions, PermissionSubject, UsesActions, UsesGroup
{
    use HasPermissionKey;

    public static function group(): string
    {
        return 'billing';
    }

    /**
     * @return list<PermissionActionEnum>
     */
    public static function actions(): array
    {
        return TestCustomAction::cases();
    }

    /**
     * @return list<PermissionActionEnum>
     */
    public static function impliedActions(PermissionActionEnum $action): array
    {
        return $action === TestCustomAction::WRITE ? [TestCustomAction::READ] : [];
    }
}

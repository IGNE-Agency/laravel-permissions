<?php

declare(strict_types=1);

namespace Igne\Permissions\Contracts;

use Igne\Permissions\ImpliedActionResolver;

/**
 * Opt-in refinement of {@see UsesActions}: a grant of one action also grants others on the same
 * permission in the same space, as in update granting view. The rule lives here, in the schema,
 * so each role stores only the actions it was given and the rule can change without re-seeding.
 *
 * The package's Gate hook applies it ({@see ImpliedActionResolver}), transitively: update granting
 * view and view granting viewAny means update grants viewAny. Spatie's direct methods such as
 * hasPermissionTo() never reach the Gate and see only what was stored.
 */
interface ImpliesActions
{
    /**
     * The actions a grant of the given action also grants. Each must be one of the subject's own
     * actions().
     *
     * @return list<PermissionActionEnum>
     */
    public static function impliedActions(PermissionActionEnum $action): array;
}

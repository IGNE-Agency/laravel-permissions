<?php

declare(strict_types=1);

namespace Igne\Permissions\Contracts;

use BackedEnum;
use Igne\Permissions\PermissionAction;

/**
 * An action enum: the last segment of a permission name, as in "view" of "nova:document:view".
 *
 * {@see PermissionAction} is the default set. An app defines its own by implementing this
 * contract and returning the cases from its enum's actions().
 */
interface PermissionActionEnum extends BackedEnum {}

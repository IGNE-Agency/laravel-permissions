<?php

declare(strict_types=1);

namespace Igne\Permissions\Contracts;

use Igne\Permissions\PermissionDefaults;
use Igne\Permissions\Permissions;

/**
 * Opt-in capability: a permission subject scoped to spaces, rendered with a "spacePrefix:…" prefix.
 * Implement it on a {@see PermissionSubject} class or a {@see PermissionEnum}.
 *
 * The subject gets every space: the cases of the space enum in the "spaces" config key
 * ({@see PermissionDefaults::spaces()}). Implement {@see UsesCustomSpaces} instead to list
 * other spaces.
 *
 * Every space a subject declares automatically gains the built-in "access" permission
 * ({@see Permissions::ACCESS}). {@see FiltersPerSpace} narrows the spaces per space, for a whole
 * class or per enum case.
 */
interface UsesSpaces {}

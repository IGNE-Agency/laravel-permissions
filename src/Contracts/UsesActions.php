<?php

declare(strict_types=1);

namespace Igne\Permissions\Contracts;

use Igne\Permissions\PermissionDefaults;

/**
 * Opt-in capability: a permission subject or enum with an action axis, rendered as "…:permission:action".
 *
 * The subject gets the default actions: the cases of the action enum in the "actions" config key
 * ({@see PermissionDefaults::actions()}). Implement {@see UsesCustomActions} instead to list
 * other actions.
 */
interface UsesActions {}

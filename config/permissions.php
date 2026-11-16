<?php

declare(strict_types=1);

use Igne\Permissions\PermissionAction;

return [
    /*
    |--------------------------------------------------------------------------
    | Discovered folders
    |--------------------------------------------------------------------------
    |
    | The folders the package scans for permission subjects: every class that
    | implements Igne\Permissions\Contracts\PermissionSubject and every enum
    | that implements Igne\Permissions\Contracts\PermissionEnum. Only files
    | that mention one of them, or an abstract base that does, are loaded.
    |
    */
    'discover' => [app_path()],

    /*
    |--------------------------------------------------------------------------
    | Listed subjects
    |--------------------------------------------------------------------------
    |
    | Subject classes and permission enums outside the discovered folders,
    | such as those of another package.
    |
    */
    'subjects' => [],

    /*
    |--------------------------------------------------------------------------
    | Permission separator
    |--------------------------------------------------------------------------
    |
    | The character that joins the segments of a permission name.
    | Default: ':', as in nova:document:view
    |
    */
    'separator' => ':',

    /*
    |--------------------------------------------------------------------------
    | Segment normalizer
    |--------------------------------------------------------------------------
    |
    | The casing applied to every segment so the whole name is cased the same
    | way: 'camel', 'snake', 'kebab' or 'studly'.
    |
    */
    'normalizer' => 'camel',

    /*
    |--------------------------------------------------------------------------
    | Global prefix
    |--------------------------------------------------------------------------
    |
    | An optional first segment for every permission name. Null omits it.
    |
    */
    'prefix' => null,

    /*
    |--------------------------------------------------------------------------
    | Implied actions
    |--------------------------------------------------------------------------
    |
    | Per action value, the actions a grant of it also grants on the same
    | subject in the same space, checked through the Gate. It applies to every
    | subject that lists both actions, unless the subject implements
    | Igne\Permissions\Contracts\ImpliesActions. [] turns it off.
    |
    */
    'implied_actions' => [
        PermissionAction::UPDATE->value => [PermissionAction::VIEW],
        PermissionAction::VIEW->value => [PermissionAction::VIEW_ANY],
    ],

    /*
    |--------------------------------------------------------------------------
    | Required space access
    |--------------------------------------------------------------------------
    |
    | Whether every name in a space needs that space's access permission too,
    | checked through the Gate. A space enum that implements
    | Igne\Permissions\Contracts\RequiresAccess decides for its own spaces.
    |
    */
    'requires_access' => true,
];

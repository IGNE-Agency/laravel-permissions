<?php

declare(strict_types=1);

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
];

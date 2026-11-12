<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Permission types
    |--------------------------------------------------------------------------
    |
    | The permission enums the package builds names for. Each must implement
    | Igne\Permissions\Contracts\PermissionEnum. Order is irrelevant.
    |
    */
    'types' => [],

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

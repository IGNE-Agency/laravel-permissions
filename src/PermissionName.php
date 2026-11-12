<?php

declare(strict_types=1);

namespace Igne\Permissions;

use Igne\Permissions\Contracts\PermissionActionEnum;
use Igne\Permissions\Contracts\PermissionEnum;
use Igne\Permissions\Contracts\SpaceEnum;
use Igne\Permissions\Normalizer\SegmentNormalizer;

/**
 * Immutable value object for a single resolved permission string.
 *
 * Composed of up to five segments (global prefix, space prefix, group, permission, action),
 * each normalized by the same {@see SegmentNormalizer} and joined by the separator.
 * Null and empty segments are dropped.
 */
final readonly class PermissionName
{
    private function __construct(public string $value) {}

    public function __toString(): string
    {
        return $this->value;
    }

    public static function make(
        PermissionEnum|string|null $permission = null,
        ?PermissionActionEnum $action = null,
        ?SpaceEnum $space = null,
        ?string $group = null,
        ?string $prefix = null,
        string $separator = ':',
        ?SegmentNormalizer $normalizer = null,
    ): self {
        $normalize = $normalizer ?? SegmentNormalizer::default();

        $segments = [
            $prefix,
            $space?->spacePrefix(),
            $group,
            $permission instanceof PermissionEnum ? (string) $permission->value : $permission,
            $action === null ? null : (string) $action->value,
        ];

        $normalized = [];

        foreach ($segments as $segment) {
            if ($segment === null || $segment === '') {
                continue;
            }

            $normalized[] = $normalize($segment);
        }

        return new self(implode($separator, $normalized));
    }
}

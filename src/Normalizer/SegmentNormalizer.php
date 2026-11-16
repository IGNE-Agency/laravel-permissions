<?php

declare(strict_types=1);

namespace Igne\Permissions\Normalizer;

use Closure;
use Igne\Permissions\Exceptions\PermissionException;
use Illuminate\Support\Str;

/**
 * Normalizes every segment of a permission name the same way.
 *
 * Defaults to camelCase. Pick another casing through config ('camel', 'snake', 'kebab', 'studly')
 * or pass a custom closure to {@see self::using()}.
 */
final class SegmentNormalizer
{
    /**
     * @param  Closure(string): string  $callback
     */
    private function __construct(private readonly Closure $callback) {}

    public function __invoke(string $segment): string
    {
        return ($this->callback)($segment);
    }

    public static function default(): self
    {
        return self::camel();
    }

    public static function camel(): self
    {
        return new self(Str::camel(...));
    }

    public static function snake(): self
    {
        return new self(Str::snake(...));
    }

    public static function kebab(): self
    {
        return new self(Str::kebab(...));
    }

    public static function studly(): self
    {
        return new self(Str::studly(...));
    }

    /**
     * Build a normalizer from its config name; any other name throws.
     */
    public static function fromName(string $name): self
    {
        return match ($name) {
            'camel' => self::camel(),
            'snake' => self::snake(),
            'kebab' => self::kebab(),
            'studly' => self::studly(),
            default => throw PermissionException::unknownNormalizer($name),
        };
    }

    /**
     * @param  Closure(string): string  $callback
     */
    public static function using(Closure $callback): self
    {
        return new self($callback);
    }
}

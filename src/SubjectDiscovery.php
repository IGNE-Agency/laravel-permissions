<?php

declare(strict_types=1);

namespace Igne\Permissions;

use FilesystemIterator;
use Igne\Permissions\Contracts\PermissionEnum;
use Igne\Permissions\Contracts\PermissionSubject;
use Igne\Permissions\Exceptions\PermissionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

/**
 * Finds every {@see PermissionSubject} class and {@see PermissionEnum} enum in the given folders.
 *
 * Only files that mention a marker are loaded: the two contract names, then the short name of
 * every abstract class or interface found to implement one, round after round. A model extending
 * an abstract subject base is found that way without loading unrelated classes. The class name is
 * read from the file's namespace and class declaration, so the folders need no PSR-4 layout of
 * their own as long as Composer can autoload the class.
 *
 * Results are kept per folder set for the life of the process.
 */
final class SubjectDiscovery
{
    private const array CONTRACTS = [PermissionSubject::class, PermissionEnum::class];

    /**
     * @var array<string, list<string>>
     */
    private array $discovered = [];

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    public function within(array $paths): array
    {
        return $this->discovered[implode(PATH_SEPARATOR, $paths)] ??= $this->scan($paths);
    }

    /**
     * Each round loads the files that mention a marker. A concrete subject is collected; an
     * abstract class or interface becomes the next round's marker, so its children are found too.
     *
     * @param  list<string>  $paths
     * @return list<string>
     */
    private function scan(array $paths): array
    {
        $sources = $this->sources($paths);
        $markers = collect(self::CONTRACTS)->map(class_basename(...));
        $subjects = [];

        while ($markers->isNotEmpty()) {
            $matched = $sources->filter(static fn (string $source): bool => Str::contains($source, $markers->all()));
            $sources = $sources->diffKeys($matched);

            $found = $matched->map(self::subjectReflection(...))->filter();
            $isBase = static fn (ReflectionClass $class): bool => $class->isInterface() || $class->isAbstract();

            $subjects = [...$subjects, ...$found->reject($isBase)->map(static fn (ReflectionClass $class): string => $class->getName())->values()];
            $markers = $found->filter($isBase)->map(static fn (ReflectionClass $class): string => $class->getShortName());
        }

        sort($subjects);

        return $subjects;
    }

    /**
     * The contents of every PHP file in the folders, keyed by path in path order.
     *
     * @param  list<string>  $paths
     * @return Collection<string, string>
     */
    private function sources(array $paths): Collection
    {
        return collect($paths)
            ->flatMap(self::phpFiles(...))
            ->mapWithKeys(static fn (string $file): array => [$file => (string) file_get_contents($file)])
            ->sortKeys();
    }

    /**
     * @return list<string>
     */
    private static function phpFiles(string $path): array
    {
        if (! is_dir($path)) {
            throw PermissionException::invalidConfig('discover', sprintf('[%s] is not a directory', $path));
        }

        return array_values(collect(iterator_to_array(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)), false))
            ->map(static fn (mixed $file): ?string => $file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php' ? $file->getPathname() : null)
            ->filter()
            ->all());
    }

    /**
     * The declared class, interface or enum of the source when it implements a subject contract.
     *
     * @return ReflectionClass<object>|null
     */
    private static function subjectReflection(string $source): ?ReflectionClass
    {
        $class = self::declaredClass($source);

        if ($class === null || ! (class_exists($class) || interface_exists($class))) {
            return null;
        }

        $reflection = new ReflectionClass($class);

        return collect(self::CONTRACTS)->contains(static fn (string $contract): bool => $reflection->implementsInterface($contract)) ? $reflection : null;
    }

    private static function declaredClass(string $source): ?string
    {
        if (preg_match('/^\s*(?:(?:final|abstract|readonly)\s+)*(?:class|interface|enum|trait)\s+(\w+)/m', $source, $declaration) !== 1) {
            return null;
        }

        $namespace = preg_match('/^\s*namespace\s+([\w\\\\]+)\s*[;{]/m', $source, $match) === 1 ? $match[1].'\\' : '';

        return $namespace.$declaration[1];
    }
}

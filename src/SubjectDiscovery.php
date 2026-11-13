<?php

declare(strict_types=1);

namespace Igne\Permissions;

use FilesystemIterator;
use Igne\Permissions\Contracts\PermissionEnum;
use Igne\Permissions\Contracts\PermissionSubject;
use Igne\Permissions\Exceptions\PermissionException;
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
     * @var array<string, list<class-string>>
     */
    private array $discovered = [];

    /**
     * @param  list<string>  $paths
     * @return list<class-string>
     */
    public function within(array $paths): array
    {
        return $this->discovered[implode(PATH_SEPARATOR, $paths)] ??= $this->scan($paths);
    }

    /**
     * @param  list<string>  $paths
     * @return list<class-string>
     */
    private function scan(array $paths): array
    {
        $sources = $this->sources($paths);
        $markers = array_map(static fn (string $contract): string => class_basename($contract), self::CONTRACTS);
        $classes = [];

        while ($markers !== []) {
            $nextMarkers = [];

            foreach ($sources as $path => $source) {
                if (! self::mentionsAny($source, $markers)) {
                    continue;
                }

                unset($sources[$path]);
                $reflection = self::subjectReflection($source);

                if ($reflection === null) {
                    continue;
                }

                if ($reflection->isInterface() || $reflection->isAbstract()) {
                    $nextMarkers[] = $reflection->getShortName();

                    continue;
                }

                $classes[] = $reflection->getName();
            }

            $markers = $nextMarkers;
        }

        sort($classes);

        return $classes;
    }

    /**
     * The contents of every PHP file in the folders, keyed by path.
     *
     * @param  list<string>  $paths
     * @return array<string, string>
     */
    private function sources(array $paths): array
    {
        $sources = [];

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                throw PermissionException::invalidConfig('discover', sprintf('[%s] is not a directory', $path));
            }

            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));

            /** @var SplFileInfo $file */
            foreach ($files as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $sources[$file->getPathname()] = (string) file_get_contents($file->getPathname());
                }
            }
        }

        ksort($sources);

        return $sources;
    }

    /**
     * @param  list<string>  $markers
     */
    private static function mentionsAny(string $source, array $markers): bool
    {
        foreach ($markers as $marker) {
            if (str_contains($source, $marker)) {
                return true;
            }
        }

        return false;
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

        foreach (self::CONTRACTS as $contract) {
            if ($reflection->implementsInterface($contract)) {
                return $reflection;
            }
        }

        return null;
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

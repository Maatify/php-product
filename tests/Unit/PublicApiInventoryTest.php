<?php

declare(strict_types=1);

namespace Maatify\Product\Tests\Unit;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use SplFileInfo;

/**
 * Conformity between the Package Reference "Implemented Public API" table and the code:
 * every listed type exists, every method named for a service or factory exists and every
 * public method of those types is named, and every package type is either listed or internal.
 */
final class PublicApiInventoryTest extends TestCase
{
    private const string ROOT_NAMESPACE = 'Maatify\\Product\\';

    /** Types the Package Reference treats as internal (adapters, ports, implementations, helpers). */
    private const array INTERNAL_PATTERNS = [
        '/\\\\Repository\\\\/',
        '/\\\\Common\\\\Pdo\\\\/',
        '/\\\\Common\\\\Exception\\\\ProductStorageFailureFactory$/',
        '/\\\\Service\\\\(ProductService|VariantService)$/',
        '/\\\\Variant\\\\ValueObject\\\\/',
    ];

    public function testEveryListedTypeExistsAndEveryServiceMethodIsDeclared(): void
    {
        $missing = [];
        foreach ($this->rows() as [$classes, $methods]) {
            foreach ($classes as $class) {
                if (!class_exists($class) && !interface_exists($class) && !enum_exists($class)) {
                    $missing[] = 'type ' . $class;
                }
            }
            if ($methods === []) {
                continue;
            }
            $declared = [];
            foreach ($classes as $class) {
                if (!class_exists($class) && !interface_exists($class)) {
                    continue;
                }
                foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                    if ($method->class === $class && !$method->isConstructor()) {
                        $declared[] = $method->getName();
                    }
                }
            }
            foreach ($methods as $name) {
                if (!in_array($name, $declared, true)) {
                    $missing[] = 'method ' . $name;
                }
            }
            foreach ($declared as $name) {
                if (!in_array($name, $methods, true)) {
                    $missing[] = 'undocumented public method ' . $name . ' of ' . implode(', ', $classes);
                }
            }
        }

        self::assertSame([], $missing);
    }

    public function testEveryPackageTypeIsListedOrInternal(): void
    {
        $listed = [];
        foreach ($this->rows() as [$classes]) {
            foreach ($classes as $class) {
                $listed[$class] = true;
            }
        }

        $unlisted = [];
        foreach ($this->packageTypes() as $type) {
            if (isset($listed[$type])) {
                continue;
            }
            $internal = false;
            foreach (self::INTERNAL_PATTERNS as $pattern) {
                if (preg_match($pattern, $type) === 1) {
                    $internal = true;
                    break;
                }
            }
            if (!$internal) {
                $unlisted[] = $type;
            }
        }

        self::assertSame([], $unlisted, 'A public type must be listed in the Package Reference or match an internal pattern.');
    }

    /**
     * @return list<array{list<class-string>, list<string>}> Types and the method names of each table row.
     */
    private function rows(): array
    {
        $reference = (string) file_get_contents(dirname(__DIR__, 2) . '/PRODUCT_PACKAGE_REFERENCE.md');
        $start = strpos($reference, '## Implemented Public API');
        $end = strpos($reference, '### Behavior and guarantees');
        self::assertIsInt($start);
        self::assertIsInt($end);
        $section = substr($reference, $start, $end - $start);

        $rows = [];
        foreach (explode("\n", $section) as $line) {
            $cells = array_map('trim', explode('|', trim($line, " \t|")));
            if (!str_starts_with($line, '| `') || count($cells) < 2) {
                continue;
            }
            preg_match_all('/`([A-Za-z]+(?:\\\\[A-Za-z]+)+)`/', $cells[0], $types);
            /** @var list<class-string> $classes */
            $classes = array_map(static fn(string $type): string => self::ROOT_NAMESPACE . $type, $types[1]);
            preg_match_all('/`(\w+)\(/', $cells[1], $methods);
            $rows[] = [$classes, array_values(array_unique($methods[1]))];
        }
        self::assertNotSame([], $rows);

        return $rows;
    }

    /** @return list<string> */
    private function packageTypes(): array
    {
        $root = dirname(__DIR__, 2) . '/src';
        $types = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
                $relative = substr($file->getPathname(), strlen($root) + 1, -4);
                $types[] = self::ROOT_NAMESPACE . str_replace('/', '\\', $relative);
            }
        }
        sort($types);

        return $types;
    }
}

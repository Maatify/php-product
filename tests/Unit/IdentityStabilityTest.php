<?php

declare(strict_types=1);

namespace Maatify\Product\Tests\Unit;

use FilesystemIterator;
use Maatify\Product\Content\Service\OptionContentServiceInterface;
use Maatify\Product\Content\Service\OptionValueContentServiceInterface;
use Maatify\Product\Content\Service\ProductContentServiceInterface;
use Maatify\Product\ImageAssignment\Service\ImageAssignmentServiceInterface;
use Maatify\Product\ImageRole\Service\ImageRoleServiceInterface;
use Maatify\Product\Option\Service\OptionServiceInterface;
use Maatify\Product\OptionValue\Service\OptionValueServiceInterface;
use Maatify\Product\Product\Service\ProductServiceInterface;
use Maatify\Product\Variant\Service\VariantServiceInterface;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

/**
 * Identity stability is enforced by the application, not by database triggers
 * (architecture 4.1: ordinary update commands do not mutate `id` or `code`). These tests
 * prove that no package write path can change an identity column, so a future write path
 * that does must fail them.
 */
final class IdentityStabilityTest extends TestCase
{
    private const array IDENTITY_COLUMNS = [
        'id', 'code', 'role_key', 'composition_fingerprint', 'product_id', 'variant_id', 'option_id',
        'option_value_id', 'language_code', 'platform', 'media_asset_id', 'role_id',
    ];

    /**
     * A public service method that would replace an identity. Unanchored, so a longer name such as
     * `updateCodeAndStatus` is caught too; only `updateIdentifiers` (the secondary SKU and barcode,
     * which are not identities) is excluded.
     */
    private const string IDENTITY_METHOD = '/::(update|rename|change|replace|set)(?:Code|Identity|Key|Id(?!entifiers\\b))/i';

    public function testNoUpdateStatementSetsAnIdentityColumn(): void
    {
        $offences = [];
        $scanned = [];
        foreach ($this->sourceFiles() as $file) {
            $source = (string) file_get_contents($file);
            // A table name is a literal `maa_product_*` or the Content owner's trusted table constant.
            if (preg_match_all('/UPDATE\s+`?(?:maa_product_\w+|\'\s*\.\s*\$this->owner->table\(\)\s*\.\s*\')`?\s+SET\s+(.*?)\s+WHERE/is', $source, $matches) === 0) {
                continue;
            }
            if (str_contains($file, '/Content/')) {
                $scanned['content'] = ($scanned['content'] ?? 0) + count($matches[1]);
            }
            foreach ($matches[1] as $assignments) {
                foreach (self::IDENTITY_COLUMNS as $column) {
                    if (preg_match('/`?\b' . $column . '`?\s*=/i', $assignments) === 1) {
                        $offences[] = $file . ': sets ' . $column;
                    }
                }
            }
        }

        self::assertSame([], $offences);
        self::assertGreaterThanOrEqual(4, $scanned['content'] ?? 0, 'The Content UPDATE statements must be scanned.');
    }

    public function testNoPublicServiceMethodCanReplaceAnIdentity(): void
    {
        $methods = [];
        foreach ([
            ProductServiceInterface::class,
            VariantServiceInterface::class,
            OptionServiceInterface::class,
            OptionValueServiceInterface::class,
            ImageRoleServiceInterface::class,
            ImageAssignmentServiceInterface::class,
            ProductContentServiceInterface::class,
            OptionContentServiceInterface::class,
            OptionValueContentServiceInterface::class,
        ] as $interface) {
            foreach ((new ReflectionClass($interface))->getMethods() as $method) {
                $methods[] = $interface . '::' . $method->getName();
            }
        }

        $forbidden = array_filter(
            $methods,
            static fn(string $name): bool => preg_match(self::IDENTITY_METHOD, $name) === 1,
        );

        self::assertSame([], array_values($forbidden));
    }

    public function testTheIdentityMethodGuardStaysStrict(): void
    {
        foreach (['updateId', 'updateIdentity', 'updateCode', 'updateCodeAndStatus', 'setKey', 'setIdentityColumn', 'renameCode', 'changeKeys', 'replaceId'] as $name) {
            self::assertSame(1, preg_match(self::IDENTITY_METHOD, 'Service::' . $name), $name . ' must be rejected.');
        }
        foreach (['updateIdentifiers', 'updateStatus', 'updateDisplayOrder', 'setDefault'] as $name) {
            self::assertSame(0, preg_match(self::IDENTITY_METHOD, 'Service::' . $name), $name . ' must be allowed.');
        }
    }

    /** @return list<string> */
    private function sourceFiles(): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/src', FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}

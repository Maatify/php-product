<?php

declare(strict_types=1);

/**
 * Example 3: read the Variants of a Product for management and for consumers.
 *
 * Input -> Public Call -> Result -> Boundary:
 *  - Input:    a Product code supplied by the Host.
 *  - Calls:    ProductServiceInterface::create(), VariantServiceInterface::getByIdForManagement(),
 *              listByProductIdForManagement(), listByProductId().
 *  - Result:   a VariantDTO and VariantCollectionDTO; consumer reads return only visible Variants.
 *  - Boundary: a Variant is consumer-visible only while its Product and the Variant are active
 *              and its composition is valid; activating a Product is the Host's concern here.
 *
 * Environment: see example 01.
 */

use Maatify\Product\Factory\ProductFactory;
use Maatify\Product\Product\Command\CreateProductCommand;
use Maatify\Product\Variant\DTO\VariantListCriteriaDTO;
use Maatify\Product\Variant\Enum\VariantStatusEnum;
use Maatify\Product\Variant\Exception\VariantNotFoundException;
use Maatify\SharedCommon\Infrastructure\SystemClock;

$autoload = getenv('PRODUCT_EXAMPLE_AUTOLOAD');
require is_string($autoload) && $autoload !== '' ? $autoload : __DIR__ . '/../vendor/autoload.php';

$setting = static function (string $name): string {
    $value = getenv($name);
    if (!is_string($value) || $value === '') {
        fwrite(STDERR, "Set {$name} (see the header of example 01).\n");
        exit(2);
    }

    return $value;
};

$pdo = new PDO(
    $setting('PRODUCT_EXAMPLE_DSN'),
    $setting('PRODUCT_EXAMPLE_USER'),
    $setting('PRODUCT_EXAMPLE_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false],
);
$clock = new SystemClock(new DateTimeZone('UTC'));
$products = ProductFactory::createProductService($pdo, $clock);
$variants = ProductFactory::createVariantService($pdo, $clock);

$created = $products->create(new CreateProductCommand('VARIANTS-' . bin2hex(random_bytes(4))));

// Management read: sees the Variant whatever the Product's status.
$managed = $variants->getByIdForManagement($created->variantId);
echo "management read: variant {$managed->id} of product {$managed->productId} is {$managed->status->value}, composition pairs: " . count($managed->composition) . "\n";

$list = $variants->listByProductIdForManagement($created->productId, new VariantListCriteriaDTO(VariantStatusEnum::ACTIVE));
echo 'management list (active): ' . count($list) . "\n";

// Consumer read: the Product is inactive, so nothing is visible.
echo 'consumer list while the Product is inactive: ' . count($variants->listByProductId($created->productId)) . "\n";
try {
    $variants->getById($created->variantId);
    throw new RuntimeException('The Variant of an inactive Product must not be consumer-visible.');
} catch (VariantNotFoundException) {
    echo "consumer read: not visible\n";
}

if (count($list) !== 1 || count($variants->listByProductId($created->productId)) !== 0) {
    throw new RuntimeException('Unexpected Variant read results.');
}

echo "OK: example 03 completed\n";

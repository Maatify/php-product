<?php

declare(strict_types=1);

/**
 * Example 4: make a Product configurable, resolve a shopper's choices, and go back to simple.
 *
 * Input -> Public Call -> Result -> Boundary:
 *  - Input:    prepared Options and Values (created inactive) with their labels, a Product name, and
 *              the combinations the Host offers.
 *  - Calls:    OptionServiceInterface::create(), OptionValueServiceInterface::create(),
 *              ProductContentServiceInterface::create(), OptionContentServiceInterface::create(),
 *              OptionValueContentServiceInterface::create(), listVisible(),
 *              ProductServiceInterface::reconfigure(), updateStatus(),
 *              VariantServiceInterface::resolveByComposition().
 *  - Result:   a ProductReconfigurationResultDTO with the created and deactivated Variant identities,
 *              and the VariantDTO a complete selection resolves to.
 *  - Boundary: reconfiguration is one atomic Product-local operation over a complete target state; an
 *              existing composition is reused only when the command names its Variant. Every active
 *              Option and Value needs a label and an active Product needs a name, all Content records;
 *              a consumer Content read is exact and never falls back to another language.
 *
 * Environment: see example 01.
 */

use Maatify\Product\Common\Exception\ProductInvalidArgumentException;
use Maatify\Product\Content\Command\CreateOptionContentCommand;
use Maatify\Product\Content\Command\CreateOptionValueContentCommand;
use Maatify\Product\Content\Command\CreateProductContentCommand;
use Maatify\Product\Content\ValueObject\ExactContentLanguage;
use Maatify\Product\Factory\ProductFactory;
use Maatify\Product\Option\Command\CreateOptionCommand;
use Maatify\Product\OptionValue\Command\CreateOptionValueCommand;
use Maatify\Product\Product\Command\CreateProductCommand;
use Maatify\Product\Product\Command\ReconfigureOptionTarget;
use Maatify\Product\Product\Command\ReconfigureProductCommand;
use Maatify\Product\Product\Command\ReconfigureVariantTarget;
use Maatify\Product\Product\Enum\ProductStatusEnum;
use Maatify\Product\Variant\DTO\VariantCompositionCriteriaDTO;
use Maatify\Product\Variant\Exception\VariantCompositionConflictException;
use Maatify\Product\Variant\ValueObject\OptionValueSelection;
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
$options = ProductFactory::createOptionService($pdo, $clock);
$values = ProductFactory::createOptionValueService($pdo, $clock);
$variants = ProductFactory::createVariantService($pdo, $clock);
$productContent = ProductFactory::createProductContentService($pdo, $clock);
$optionContent = ProductFactory::createOptionContentService($pdo, $clock);
$valueContent = ProductFactory::createOptionValueContentService($pdo, $clock);

$created = $products->create(new CreateProductCommand('SHIRT-' . bin2hex(random_bytes(4))));
$productId = $created->productId;

// Prepare the configuration while it is inactive: Options and Values are created inactive.
$color = $options->create(new CreateOptionCommand($productId, 'color'))->optionId;
$size = $options->create(new CreateOptionCommand($productId, 'size'))->optionId;
$red = $values->create(new CreateOptionValueCommand($color, 'red'))->optionValueId;
$blue = $values->create(new CreateOptionValueCommand($color, 'blue'))->optionValueId;
$medium = $values->create(new CreateOptionValueCommand($size, 'm'))->optionValueId;

// The Product name and the labels are Content records: one per language, `null` is the unlocalized one.
$productContent->create(new CreateProductContentCommand($productId, null, 'Shirt', 'A plain shirt'));
$productContent->create(new CreateProductContentCommand($productId, 'ar', 'قميص'));
$optionContent->create(new CreateOptionContentCommand($color, null, 'Color'));
$optionContent->create(new CreateOptionContentCommand($size, null, 'Size'));
foreach ([[$red, 'Red'], [$blue, 'Blue'], [$medium, 'Medium']] as [$valueId, $label]) {
    $valueContent->create(new CreateOptionValueContentCommand($valueId, null, $label));
}

// Simple -> configurable: the complete target state, with only the combinations the Host offers.
$offered = [
    new ReconfigureVariantTarget([new OptionValueSelection($color, $red), new OptionValueSelection($size, $medium)]),
    new ReconfigureVariantTarget([new OptionValueSelection($size, $medium), new OptionValueSelection($color, $blue)]),
];
$result = $products->reconfigure(new ReconfigureProductCommand(
    $productId,
    [new ReconfigureOptionTarget($color, [$red, $blue]), new ReconfigureOptionTarget($size, [$medium])],
    $offered,
));
echo 'created Variants: ' . implode(', ', $result->createdVariantIds) . '; deactivated: ' . implode(', ', $result->deactivatedVariantIds) . "\n";
$products->updateStatus($productId, ProductStatusEnum::ACTIVE);

// The shopper picks blue and M, in any order; resolution returns the one visible Variant.
$resolved = $variants->resolveByComposition($productId, new VariantCompositionCriteriaDTO([
    new OptionValueSelection($size, $medium),
    new OptionValueSelection($color, $blue),
]));
echo "resolved variant: {$resolved->id}\n";

// Content reads are exact: Arabic exists, English does not, and nothing falls back to the unlocalized name.
$arabic = $productContent->listVisible($productId, ExactContentLanguage::exactLanguage('ar'));
$english = $productContent->listVisible($productId, ExactContentLanguage::exactLanguage('en'));
if (count($arabic) !== 1 || !$english->isEmpty()) {
    throw new RuntimeException('Content reads must be exact.');
}
echo 'name (ar): ' . $arabic->items()[0]->name . "; name (en): none\n";

try {
    $variants->resolveByComposition($productId, new VariantCompositionCriteriaDTO([new OptionValueSelection($color, $red)]));
    throw new RuntimeException('A partial selection must be rejected.');
} catch (ProductInvalidArgumentException) {
    echo "partial selection: rejected\n";
}

// Repeating an existing composition without naming its Variant is a conflict: identities are never duplicated.
try {
    $products->reconfigure(new ReconfigureProductCommand(
        $productId,
        [new ReconfigureOptionTarget($color, [$red]), new ReconfigureOptionTarget($size, [$medium])],
        [new ReconfigureVariantTarget([new OptionValueSelection($color, $red), new OptionValueSelection($size, $medium)])],
    ));
    throw new RuntimeException('An existing composition must conflict.');
} catch (VariantCompositionConflictException) {
    echo "existing composition without reuseVariantId: conflict\n";
}

// Configurable -> simple: reuse the original empty-composition Variant by identity.
$back = $products->reconfigure(new ReconfigureProductCommand($productId, [], [new ReconfigureVariantTarget([], $created->variantId)]));
if ($back->reactivatedVariantIds !== [$created->variantId] || count($back->deactivatedVariantIds) !== 2) {
    throw new RuntimeException('Unexpected transition result.');
}
echo 'back to simple: ' . $variants->resolveByComposition($productId, new VariantCompositionCriteriaDTO())->id . "\n";

echo "OK: example 04 completed\n";

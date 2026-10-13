<?php

declare(strict_types=1);

/**
 * Example 5: what a storefront reads to present a configurable Product.
 *
 * Input -> Public Call -> Result -> Boundary:
 *  - Input:    an active, configured Product with labels, a name per language, and exact-scope images.
 *  - Calls:    OptionServiceInterface::listVisibleByProductId(), OptionValueServiceInterface::listVisibleByOptionId(),
 *              ProductContentServiceInterface::listVisible(), OptionContentServiceInterface::listVisible(),
 *              ImageAssignmentServiceInterface::listVisibleByExactScope().
 *  - Result:   the selectable Options and Values, the exact-language Content, and the images of one exact scope.
 *  - Boundary: consumer reads apply the visibility rules and never fall back: no language fallback, no
 *              Product-level images for a Variant, and no default chosen by the read. The Host owns fallback.
 *
 * Environment: see example 01.
 */

use Maatify\Product\Content\Command\CreateOptionContentCommand;
use Maatify\Product\Content\Command\CreateOptionValueContentCommand;
use Maatify\Product\Content\Command\CreateProductContentCommand;
use Maatify\Product\Content\ValueObject\ExactContentLanguage;
use Maatify\Product\Factory\ProductFactory;
use Maatify\Product\ImageAssignment\Command\CreateImageAssignmentCommand;
use Maatify\Product\ImageAssignment\DTO\ImageAssignmentExactScopeCriteriaDTO;
use Maatify\Product\ImageRole\Command\CreateImageRoleCommand;
use Maatify\Product\Option\Command\CreateOptionCommand;
use Maatify\Product\OptionValue\Command\CreateOptionValueCommand;
use Maatify\Product\Product\Command\CreateProductCommand;
use Maatify\Product\Product\Command\ReconfigureOptionTarget;
use Maatify\Product\Product\Command\ReconfigureProductCommand;
use Maatify\Product\Product\Command\ReconfigureVariantTarget;
use Maatify\Product\Product\Enum\ProductStatusEnum;
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
$productContent = ProductFactory::createProductContentService($pdo, $clock);
$optionContent = ProductFactory::createOptionContentService($pdo, $clock);
$valueContent = ProductFactory::createOptionValueContentService($pdo, $clock);
$roles = ProductFactory::createImageRoleService($pdo, $clock);
$images = ProductFactory::createImageAssignmentService($pdo, $clock);

// Catalog setup (management side): a colour-configurable Product with labels, names, and images.
$suffix = bin2hex(random_bytes(4));
$productId = $products->create(new CreateProductCommand('MUG-' . $suffix))->productId;
$color = $options->create(new CreateOptionCommand($productId, 'color'))->optionId;
$white = $values->create(new CreateOptionValueCommand($color, 'white'))->optionValueId;
$black = $values->create(new CreateOptionValueCommand($color, 'black'))->optionValueId;
$productContent->create(new CreateProductContentCommand($productId, null, 'Mug'));
$productContent->create(new CreateProductContentCommand($productId, 'ar', 'كوب'));
$optionContent->create(new CreateOptionContentCommand($color, null, 'Colour'));
$valueContent->create(new CreateOptionValueContentCommand($white, null, 'White'));
$valueContent->create(new CreateOptionValueContentCommand($black, null, 'Black'));
$result = $products->reconfigure(new ReconfigureProductCommand($productId, [new ReconfigureOptionTarget($color, [$white, $black])], [
    new ReconfigureVariantTarget([new OptionValueSelection($color, $white)]),
    new ReconfigureVariantTarget([new OptionValueSelection($color, $black)]),
]));
$whiteVariant = $result->createdVariantIds[0];
$products->updateStatus($productId, ProductStatusEnum::ACTIVE);
$hero = $roles->create(new CreateImageRoleCommand('hero-' . $suffix))->roleId;
$images->create(new CreateImageAssignmentCommand($productId, null, 1001, $hero));
$images->create(new CreateImageAssignmentCommand($productId, $whiteVariant, 1002, $hero));

// Presentation (consumer side): selectable Options and Values with their exact labels.
foreach ($options->listVisibleByProductId($productId) as $option) {
    $label = $optionContent->listVisible($option->id, ExactContentLanguage::unlocalizedOnly())->items()[0]->name ?? $option->code;
    $choices = [];
    foreach ($values->listVisibleByOptionId($option->id) as $value) {
        $choices[] = $valueContent->listVisible($value->id, ExactContentLanguage::unlocalizedOnly())->items()[0]->name ?? $value->code;
    }
    echo $label . ': ' . implode(', ', $choices) . "\n";
}

// Exact Content: Arabic exists; French does not, and the Host decides whether to fall back.
$arabic = $productContent->listVisible($productId, ExactContentLanguage::exactLanguage('ar'));
$french = $productContent->listVisible($productId, ExactContentLanguage::exactLanguage('fr'));
if (count($arabic) !== 1 || !$french->isEmpty()) {
    throw new RuntimeException('Content reads must be exact.');
}

// Exact-scope images: the white Variant has its own hero image; the black Variant has none and gets no Product-level fallback.
$productHero = $images->listVisibleByExactScope(new ImageAssignmentExactScopeCriteriaDTO($productId, null, $hero, null, null));
$whiteHero = $images->listVisibleByExactScope(new ImageAssignmentExactScopeCriteriaDTO($productId, $whiteVariant, $hero, null, null));
$blackHero = $images->listVisibleByExactScope(new ImageAssignmentExactScopeCriteriaDTO($productId, $result->createdVariantIds[1], $hero, null, null));
if (count($productHero) !== 1 || count($whiteHero) !== 1 || !$blackHero->isEmpty()) {
    throw new RuntimeException('Image reads must be exact.');
}
echo 'hero images: product ' . $productHero->items()[0]->mediaAssetId . ', white variant ' . $whiteHero->items()[0]->mediaAssetId . ", black variant none\n";

echo "OK: example 05 completed\n";

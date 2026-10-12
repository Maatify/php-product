<?php

declare(strict_types=1);

namespace Maatify\Product\Factory;

use Maatify\Persistence\Pdo\Ordering\ScopedOrderingManager;
use Maatify\Persistence\Pdo\Transaction\PdoTransactionRunner;
use Maatify\Product\Common\Exception\ProductInvalidArgumentException;
use Maatify\Product\Product\Repository\Pdo\PdoProductCommandRepository;
use Maatify\Product\Product\Repository\Pdo\PdoProductQueryReader;
use Maatify\Product\Product\Service\ProductService;
use Maatify\Product\Product\Service\ProductServiceInterface;
use Maatify\Product\Variant\Repository\Pdo\PdoVariantCommandRepository;
use Maatify\SharedCommon\Contracts\ClockInterface;
use PDO;

/**
 * Framework-neutral construction of the public Product services.
 *
 * The Host supplies the PDO connection to a database where `schema/product.sql`
 * has been installed, and its Clock. The factory holds no container, state, or
 * global.
 */
final class ProductFactory
{
    private function __construct() {}

    /**
     * Builds the Product service on the given connection.
     *
     * @param PDO $pdo Connection with `PDO::ERRMODE_EXCEPTION`. All writes of one operation use it,
     *                 so a transaction the Host has already started on it is joined, never committed.
     * @param ClockInterface $clock Host Clock whose instant is stored as the record timestamp.
     *
     * @throws ProductInvalidArgumentException When the connection does not report errors as exceptions.
     */
    public static function createProductService(PDO $pdo, ClockInterface $clock): ProductServiceInterface
    {
        if ($pdo->getAttribute(PDO::ATTR_ERRMODE) !== PDO::ERRMODE_EXCEPTION) {
            throw ProductInvalidArgumentException::pdoMustThrowExceptions();
        }

        return new ProductService(
            new PdoProductCommandRepository($pdo),
            new PdoVariantCommandRepository($pdo, new ScopedOrderingManager()),
            new PdoProductQueryReader($pdo),
            new PdoTransactionRunner($pdo),
            $clock,
        );
    }
}

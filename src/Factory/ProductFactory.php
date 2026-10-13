<?php

declare(strict_types=1);

namespace Maatify\Product\Factory;

use Maatify\Persistence\Pdo\Ordering\ScopedOrderingManager;
use Maatify\Persistence\Pdo\Transaction\PdoSavepointTransactionRunner;
use Maatify\Product\Common\Exception\ProductInvalidArgumentException;
use Maatify\Product\ImageAssignment\Repository\Pdo\PdoImageAssignmentCommandRepository;
use Maatify\Product\ImageAssignment\Repository\Pdo\PdoImageAssignmentQueryReader;
use Maatify\Product\ImageAssignment\Service\ImageAssignmentService;
use Maatify\Product\ImageAssignment\Service\ImageAssignmentServiceInterface;
use Maatify\Product\ImageRole\Repository\Pdo\PdoImageRoleCommandRepository;
use Maatify\Product\ImageRole\Repository\Pdo\PdoImageRoleQueryReader;
use Maatify\Product\ImageRole\Service\ImageRoleService;
use Maatify\Product\ImageRole\Service\ImageRoleServiceInterface;
use Maatify\Product\Option\Repository\Pdo\PdoOptionCommandRepository;
use Maatify\Product\Option\Repository\Pdo\PdoOptionQueryReader;
use Maatify\Product\Option\Service\OptionService;
use Maatify\Product\Option\Service\OptionServiceInterface;
use Maatify\Product\OptionValue\Repository\Pdo\PdoOptionValueCommandRepository;
use Maatify\Product\OptionValue\Repository\Pdo\PdoOptionValueQueryReader;
use Maatify\Product\OptionValue\Service\OptionValueService;
use Maatify\Product\OptionValue\Service\OptionValueServiceInterface;
use Maatify\Product\Product\Repository\Pdo\PdoProductCommandRepository;
use Maatify\Product\Product\Repository\Pdo\PdoProductConfigurationRepository;
use Maatify\Product\Product\Repository\Pdo\PdoProductQueryReader;
use Maatify\Product\Product\Service\ProductService;
use Maatify\Product\Product\Service\ProductServiceInterface;
use Maatify\Product\Variant\Repository\Pdo\PdoVariantCommandRepository;
use Maatify\Product\Variant\Repository\Pdo\PdoVariantQueryReader;
use Maatify\Product\Variant\Service\VariantService;
use Maatify\Product\Variant\Service\VariantServiceInterface;
use Maatify\SharedCommon\Contracts\ClockInterface;
use PDO;

/**
 * Framework-neutral construction of the public Product services.
 *
 * The Host supplies the PDO connection to a database where `schema/product.sql`
 * has been installed, and its Clock. The factory holds no container, state, or
 * global. Every service built on one connection shares its transactions: a
 * transaction the Host has already started on it is never committed or rolled back
 * by the package; each operation runs in its own savepoint inside it, and a failed
 * operation is rolled back to that savepoint, so none of its writes reach the Host's commit.
 *
 * Every builder takes the same two arguments: the PDO connection, which must use
 * `PDO::ERRMODE_EXCEPTION` and carries all writes of one operation, and the Host Clock, whose
 * instant is stored as the record timestamp and whose timezone hydrates the returned timestamps.
 */
final class ProductFactory
{
    private function __construct() {}

    /**
     * Builds the Product service: creation, reads, lifecycle, and reconfiguration.
     *
     * @throws ProductInvalidArgumentException When the connection does not report errors as exceptions.
     */
    public static function createProductService(PDO $pdo, ClockInterface $clock): ProductServiceInterface
    {
        self::requireExceptionMode($pdo);
        $ordering = new ScopedOrderingManager();

        return new ProductService(
            new PdoProductCommandRepository($pdo),
            new PdoVariantCommandRepository($pdo, $ordering),
            new PdoProductQueryReader($pdo, $clock),
            new PdoProductConfigurationRepository($pdo, $ordering),
            new PdoSavepointTransactionRunner($pdo),
            $clock,
        );
    }

    /**
     * Builds the Variant service: reads, composition resolution, and lifecycle.
     *
     * @throws ProductInvalidArgumentException When the connection does not report errors as exceptions.
     */
    public static function createVariantService(PDO $pdo, ClockInterface $clock): VariantServiceInterface
    {
        self::requireExceptionMode($pdo);
        $ordering = new ScopedOrderingManager();

        return new VariantService(
            new PdoVariantQueryReader($pdo, $clock),
            new PdoVariantCommandRepository($pdo, $ordering),
            new PdoProductConfigurationRepository($pdo, $ordering),
            new PdoSavepointTransactionRunner($pdo),
            $clock,
        );
    }

    /**
     * Builds the Option service: creation, lifecycle, and management lookup.
     *
     * @throws ProductInvalidArgumentException When the connection does not report errors as exceptions.
     */
    public static function createOptionService(PDO $pdo, ClockInterface $clock): OptionServiceInterface
    {
        self::requireExceptionMode($pdo);
        $ordering = new ScopedOrderingManager();

        return new OptionService(
            new PdoOptionCommandRepository($pdo, $ordering),
            new PdoOptionQueryReader($pdo, $clock),
            new PdoProductCommandRepository($pdo),
            new PdoProductConfigurationRepository($pdo, $ordering),
            new PdoSavepointTransactionRunner($pdo),
            $clock,
        );
    }

    /**
     * Builds the Option Value service: creation, lifecycle, and management lookup.
     *
     * @throws ProductInvalidArgumentException When the connection does not report errors as exceptions.
     */
    public static function createOptionValueService(PDO $pdo, ClockInterface $clock): OptionValueServiceInterface
    {
        self::requireExceptionMode($pdo);
        $ordering = new ScopedOrderingManager();

        return new OptionValueService(
            new PdoOptionValueCommandRepository($pdo, $ordering),
            new PdoOptionValueQueryReader($pdo, $clock),
            new PdoOptionCommandRepository($pdo, $ordering),
            new PdoProductCommandRepository($pdo),
            new PdoProductConfigurationRepository($pdo, $ordering),
            new PdoSavepointTransactionRunner($pdo),
            $clock,
        );
    }

    /**
     * Builds the Image Role service: creation, lifecycle, and management lookup.
     *
     * @throws ProductInvalidArgumentException When the connection does not report errors as exceptions.
     */
    public static function createImageRoleService(PDO $pdo, ClockInterface $clock): ImageRoleServiceInterface
    {
        self::requireExceptionMode($pdo);

        return new ImageRoleService(
            new PdoImageRoleCommandRepository($pdo),
            new PdoImageRoleQueryReader($pdo, $clock),
            new PdoSavepointTransactionRunner($pdo),
            $clock,
        );
    }

    /**
     * Builds the Image Assignment service: creation, default operations, lifecycle, and management lookup.
     *
     * @throws ProductInvalidArgumentException When the connection does not report errors as exceptions.
     */
    public static function createImageAssignmentService(PDO $pdo, ClockInterface $clock): ImageAssignmentServiceInterface
    {
        self::requireExceptionMode($pdo);
        $ordering = new ScopedOrderingManager();

        return new ImageAssignmentService(
            new PdoImageAssignmentCommandRepository($pdo, $ordering),
            new PdoImageAssignmentQueryReader($pdo, $clock),
            new PdoProductCommandRepository($pdo),
            new PdoVariantCommandRepository($pdo, $ordering),
            new PdoImageRoleCommandRepository($pdo),
            new PdoSavepointTransactionRunner($pdo),
            $clock,
        );
    }

    private static function requireExceptionMode(PDO $pdo): void
    {
        if ($pdo->getAttribute(PDO::ATTR_ERRMODE) !== PDO::ERRMODE_EXCEPTION) {
            throw ProductInvalidArgumentException::pdoMustThrowExceptions();
        }
    }
}

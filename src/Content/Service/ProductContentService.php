<?php

declare(strict_types=1);

namespace Maatify\Product\Content\Service;

use Maatify\Persistence\Pdo\Pagination\PageRequest;
use Maatify\Persistence\Pdo\Pagination\PageResult;
use Maatify\Product\Common\Enum\ProductDeletedStateEnum;
use Maatify\Product\Content\Command\CreateProductContentCommand;
use Maatify\Product\Content\Command\UpdateProductContentCommand;
use Maatify\Product\Content\DTO\ContentFieldsDTO;
use Maatify\Product\Content\DTO\ContentRecordDTO;
use Maatify\Product\Content\DTO\CreatedProductContentDTO;
use Maatify\Product\Content\DTO\ProductContentCollectionDTO;
use Maatify\Product\Content\DTO\ProductContentDTO;
use Maatify\Product\Content\DTO\ProductContentListCriteriaDTO;
use Maatify\Product\Content\ValueObject\ExactContentLanguage;

/**
 * Product Content service: delegates to the shared {@see ContentLifecycle} and maps its records.
 *
 * @internal Not part of the Public Runtime API.
 */
final readonly class ProductContentService implements ProductContentServiceInterface
{
    public function __construct(private ContentLifecycle $content) {}

    public function create(CreateProductContentCommand $command): CreatedProductContentDTO
    {
        return new CreatedProductContentDTO($this->content->create(
            $command->productId,
            $command->languageCode,
            new ContentFieldsDTO($command->name, $command->shortDescription, $command->description),
        ));
    }

    public function update(UpdateProductContentCommand $command): void
    {
        $this->content->update($command->contentId, new ContentFieldsDTO($command->name, $command->shortDescription, $command->description));
    }

    public function softDelete(int $contentId): void
    {
        $this->content->softDelete($contentId);
    }

    public function restore(int $contentId): void
    {
        $this->content->restore($contentId);
    }

    public function getByIdForManagement(
        int $contentId,
        ProductDeletedStateEnum $deletedState = ProductDeletedStateEnum::NON_DELETED,
    ): ProductContentDTO {
        return self::map($this->content->getByIdForManagement($contentId, $deletedState));
    }

    public function listForManagement(ProductContentListCriteriaDTO $criteria): ProductContentCollectionDTO
    {
        return new ProductContentCollectionDTO(array_map(
            self::map(...),
            $this->content->listForManagement($criteria->productId, $criteria->language, $criteria->deletedState, $criteria->maxResults),
        ));
    }

    public function paginateForManagement(ProductContentListCriteriaDTO $criteria, PageRequest $request): PageResult
    {
        return $this->content->paginateForManagement($criteria->productId, $criteria->language, $criteria->deletedState, $request, self::map(...));
    }

    public function listVisible(int $productId, ExactContentLanguage $language): ProductContentCollectionDTO
    {
        return new ProductContentCollectionDTO(array_map(self::map(...), $this->content->listVisible($productId, $language)));
    }

    private static function map(ContentRecordDTO $record): ProductContentDTO
    {
        return new ProductContentDTO(
            id: $record->id,
            productId: $record->ownerId,
            languageCode: $record->languageCode,
            name: $record->fields->name,
            shortDescription: $record->fields->shortDescription,
            description: $record->fields->description,
            createdAt: $record->createdAt,
            updatedAt: $record->updatedAt,
            deletedAt: $record->deletedAt,
        );
    }
}

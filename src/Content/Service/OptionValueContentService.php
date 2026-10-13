<?php

declare(strict_types=1);

namespace Maatify\Product\Content\Service;

use Maatify\Persistence\Pdo\Pagination\PageRequest;
use Maatify\Persistence\Pdo\Pagination\PageResult;
use Maatify\Product\Common\Enum\ProductDeletedStateEnum;
use Maatify\Product\Content\Command\CreateOptionValueContentCommand;
use Maatify\Product\Content\Command\UpdateOptionValueContentCommand;
use Maatify\Product\Content\DTO\ContentFieldsDTO;
use Maatify\Product\Content\DTO\ContentRecordDTO;
use Maatify\Product\Content\DTO\CreatedOptionValueContentDTO;
use Maatify\Product\Content\DTO\OptionValueContentCollectionDTO;
use Maatify\Product\Content\DTO\OptionValueContentDTO;
use Maatify\Product\Content\DTO\OptionValueContentListCriteriaDTO;
use Maatify\Product\Content\ValueObject\ExactContentLanguage;

/**
 * Option Value Content service: delegates to the shared {@see ContentLifecycle} and maps its records.
 *
 * @internal Not part of the Public Runtime API.
 */
final readonly class OptionValueContentService implements OptionValueContentServiceInterface
{
    public function __construct(private ContentLifecycle $content) {}

    public function create(CreateOptionValueContentCommand $command): CreatedOptionValueContentDTO
    {
        return new CreatedOptionValueContentDTO($this->content->create(
            $command->optionValueId,
            $command->languageCode,
            new ContentFieldsDTO($command->name),
        ));
    }

    public function update(UpdateOptionValueContentCommand $command): void
    {
        $this->content->update($command->contentId, new ContentFieldsDTO($command->name));
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
    ): OptionValueContentDTO {
        return self::map($this->content->getByIdForManagement($contentId, $deletedState));
    }

    public function listForManagement(OptionValueContentListCriteriaDTO $criteria): OptionValueContentCollectionDTO
    {
        return new OptionValueContentCollectionDTO(array_map(
            self::map(...),
            $this->content->listForManagement($criteria->optionValueId, $criteria->language, $criteria->deletedState, $criteria->maxResults),
        ));
    }

    public function paginateForManagement(OptionValueContentListCriteriaDTO $criteria, PageRequest $request): PageResult
    {
        return $this->content->paginateForManagement($criteria->optionValueId, $criteria->language, $criteria->deletedState, $request, self::map(...));
    }

    public function listVisible(int $optionValueId, ExactContentLanguage $language): OptionValueContentCollectionDTO
    {
        return new OptionValueContentCollectionDTO(array_map(self::map(...), $this->content->listVisible($optionValueId, $language)));
    }

    private static function map(ContentRecordDTO $record): OptionValueContentDTO
    {
        return new OptionValueContentDTO(
            id: $record->id,
            optionValueId: $record->ownerId,
            languageCode: $record->languageCode,
            name: $record->fields->name,
            createdAt: $record->createdAt,
            updatedAt: $record->updatedAt,
            deletedAt: $record->deletedAt,
        );
    }
}

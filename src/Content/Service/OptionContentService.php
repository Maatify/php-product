<?php

declare(strict_types=1);

namespace Maatify\Product\Content\Service;

use Maatify\Persistence\Pdo\Pagination\PageRequest;
use Maatify\Persistence\Pdo\Pagination\PageResult;
use Maatify\Product\Common\Enum\ProductDeletedStateEnum;
use Maatify\Product\Content\Command\CreateOptionContentCommand;
use Maatify\Product\Content\Command\UpdateOptionContentCommand;
use Maatify\Product\Content\DTO\ContentFieldsDTO;
use Maatify\Product\Content\DTO\ContentRecordDTO;
use Maatify\Product\Content\DTO\CreatedOptionContentDTO;
use Maatify\Product\Content\DTO\OptionContentCollectionDTO;
use Maatify\Product\Content\DTO\OptionContentDTO;
use Maatify\Product\Content\DTO\OptionContentListCriteriaDTO;
use Maatify\Product\Content\ValueObject\ExactContentLanguage;

/**
 * Option Content service: delegates to the shared {@see ContentLifecycle} and maps its records.
 *
 * @internal Not part of the Public Runtime API.
 */
final readonly class OptionContentService implements OptionContentServiceInterface
{
    public function __construct(private ContentLifecycle $content) {}

    public function create(CreateOptionContentCommand $command): CreatedOptionContentDTO
    {
        return new CreatedOptionContentDTO($this->content->create(
            $command->optionId,
            $command->languageCode,
            new ContentFieldsDTO($command->name),
        ));
    }

    public function update(UpdateOptionContentCommand $command): void
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
    ): OptionContentDTO {
        return self::map($this->content->getByIdForManagement($contentId, $deletedState));
    }

    public function listForManagement(OptionContentListCriteriaDTO $criteria): OptionContentCollectionDTO
    {
        return new OptionContentCollectionDTO(array_map(
            self::map(...),
            $this->content->listForManagement($criteria->optionId, $criteria->language, $criteria->deletedState, $criteria->maxResults),
        ));
    }

    public function paginateForManagement(OptionContentListCriteriaDTO $criteria, PageRequest $request): PageResult
    {
        return $this->content->paginateForManagement($criteria->optionId, $criteria->language, $criteria->deletedState, $request, self::map(...));
    }

    public function listVisible(int $optionId, ExactContentLanguage $language): OptionContentCollectionDTO
    {
        return new OptionContentCollectionDTO(array_map(self::map(...), $this->content->listVisible($optionId, $language)));
    }

    private static function map(ContentRecordDTO $record): OptionContentDTO
    {
        return new OptionContentDTO(
            id: $record->id,
            optionId: $record->ownerId,
            languageCode: $record->languageCode,
            name: $record->fields->name,
            createdAt: $record->createdAt,
            updatedAt: $record->updatedAt,
            deletedAt: $record->deletedAt,
        );
    }
}

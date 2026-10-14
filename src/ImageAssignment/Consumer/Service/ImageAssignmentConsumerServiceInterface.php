<?php

declare(strict_types=1);

namespace Maatify\Product\ImageAssignment\Consumer\Service;

use Maatify\Product\ImageAssignment\Consumer\Criteria\ImageAssignmentExactScopeCriteriaDTO;
use Maatify\Product\ImageAssignment\DTO\ImageAssignmentCollectionDTO;

/**
 * Consumer contract for Image Assignments: reads only the visible assignments of one exact scope
 * and writes nothing.
 */
interface ImageAssignmentConsumerServiceInterface
{
    /**
     * Consumer read of the assignments of one exact scope: the Product level (`variantId` null) or one
     * Variant of the Product, and the exact role, language, and platform, where `null` is exactly `NULL`
     * and strings compare under the collation. Nothing is inherited between the Product and Variant
     * levels. An assignment is returned only when it is not deleted, its Product is active and not
     * deleted, its target Variant is active and not deleted, and its Role, when set, is active and not
     * deleted; otherwise the collection is empty. Ordered by display order then identity, at most
     * `maxResults`. The Media Asset is never resolved, and `isDefault` is returned as stored; the read
     * selects no default.
     */
    public function listVisibleByExactScope(ImageAssignmentExactScopeCriteriaDTO $criteria): ImageAssignmentCollectionDTO;
}

<?php

declare(strict_types=1);

namespace Maatify\Product\Tests\Integration\Schema;

final class PrimaryScopeTest extends SchemaTestCase
{
    public function testOnlyOneActivePrimaryPerExactScope(): void
    {
        $this->assignment(['media_asset_id' => 20, 'is_primary' => 1]);
        $this->assertInsertRejected(self::DUPLICATE_KEY, 'maa_product_media_assignments', [
            'product_id' => $this->p1, 'media_asset_id' => 21, 'is_primary' => 1, 'display_order' => 2,
        ], 'second primary in the same exact scope');
        $this->assignment(['media_asset_id' => 21]);
        self::assertSame(1, (int) self::scalar('SELECT COUNT(*) FROM maa_product_media_assignments WHERE is_primary = 1 AND product_id = :p AND variant_id IS NULL', ['p' => $this->p1]));
    }

    public function testPrimariesInDifferentExactScopesAreIndependent(): void
    {
        $this->assignment(['media_asset_id' => 20, 'is_primary' => 1]);
        $this->assignment(['media_asset_id' => 22, 'is_primary' => 1, 'language_code' => 'en']);
        $this->assignment(['media_asset_id' => 23, 'is_primary' => 1, 'role_id' => $this->role]);
        $this->assignment(['media_asset_id' => 24, 'is_primary' => 1, 'platform' => 'web']);
        $this->assignment(['media_asset_id' => 25, 'is_primary' => 1, 'variant_id' => $this->emptyVariant1]);
        $this->assignment(['media_asset_id' => 26, 'is_primary' => 1, 'product_id' => $this->p2, 'variant_id' => $this->emptyVariant2]);
        self::assertSame(6, (int) self::scalar('SELECT COUNT(*) FROM maa_product_media_assignments WHERE is_primary = 1'));
    }

    public function testCaseVariantOfAScopeCollides(): void
    {
        $this->assignment(['media_asset_id' => 22, 'is_primary' => 1, 'language_code' => 'en']);
        $this->assertInsertRejected(self::DUPLICATE_KEY, 'maa_product_media_assignments', [
            'product_id' => $this->p1, 'media_asset_id' => 26, 'language_code' => 'EN', 'is_primary' => 1, 'display_order' => 3,
        ]);
    }

    public function testASoftDeletedRowCanNeverBePrimary(): void
    {
        $id = $this->assignment(['media_asset_id' => 27, 'is_primary' => 1]);
        $this->assertRejected(self::CHECK_VIOLATED, 'UPDATE maa_product_media_assignments SET deleted_at = :t WHERE id = :i', ['t' => self::TS, 'i' => $id]);
        $this->assertInsertRejected(self::CHECK_VIOLATED, 'maa_product_media_assignments', [
            'product_id' => $this->p1, 'media_asset_id' => 28, 'is_primary' => 1, 'display_order' => 1, 'deleted_at' => self::TS,
        ]);
    }

    public function testAtomicReplacementDeleteAndRestoreKeepASinglePrimaryWithoutPromotion(): void
    {
        $first = $this->assignment(['media_asset_id' => 20, 'is_primary' => 1]);
        $second = $this->assignment(['media_asset_id' => 21]);

        $this->execute('UPDATE maa_product_media_assignments SET is_primary = 0 WHERE id = :i', ['i' => $first]);
        $this->execute('UPDATE maa_product_media_assignments SET is_primary = 1 WHERE id = :i', ['i' => $second]);

        $this->execute('UPDATE maa_product_media_assignments SET is_primary = 0, deleted_at = :t WHERE id = :i', ['t' => self::TS, 'i' => $second]);
        self::assertSame(0, (int) self::scalar('SELECT COUNT(*) FROM maa_product_media_assignments WHERE is_primary = 1 AND deleted_at IS NULL'), 'No automatic promotion.');

        $this->execute('UPDATE maa_product_media_assignments SET deleted_at = NULL WHERE id = :i', ['i' => $second]);
        self::assertSame('0', (string) self::scalar('SELECT is_primary FROM maa_product_media_assignments WHERE id = :i', ['i' => $second]), 'Restore leaves the row non-primary.');

        $this->execute('UPDATE maa_product_media_assignments SET is_primary = 1 WHERE id = :i', ['i' => $first]);
        $this->assertRejected(self::DUPLICATE_KEY, 'UPDATE maa_product_media_assignments SET is_primary = 1 WHERE id = :i', ['i' => $second], 'restore-as-primary conflicts');
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds unique indexes on ak_supplier_product (supplier_id, code) and
 * (supplier_id, barcode) so the importer can never create two rows for the same
 * supplier + code/barcode (the root cause of duplicate products).
 *
 * ⚠️ This migration DELETES pre-existing duplicate supplier_product rows so the
 * unique indexes can be created. Per (supplier_id, code) and (supplier_id,
 * barcode) group it keeps ONE row — the most recently confirmed by the feed
 * (checked_at DESC), then the in-stock one (in_stock DESC), then the newest
 * (id DESC) — and deletes the rest. Back up the DB before running.
 *
 * Empty-string code/barcode are normalised to NULL first: MySQL unique indexes
 * treat NULLs as distinct (so many code-less rows per supplier stay allowed) but
 * empty strings as equal (which would wrongly collide).
 */
class AddUniqueSupplierCodeToSupplierProductTable extends Migration
{
    private $table = 'ak_supplier_product';

    private $codeIndex = 'ak_supplier_product_supplier_id_code_unique';
    private $barcodeIndex = 'ak_supplier_product_supplier_id_barcode_unique';

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 1) Normalise blank codes/barcodes to NULL so they do not collide en
        //    masse under the unique index.
        DB::statement("UPDATE {$this->table} SET code = NULL WHERE code = ''");
        DB::statement("UPDATE {$this->table} SET barcode = NULL WHERE barcode = ''");

        // 2) Remove pre-existing duplicate rows on each key (keep the best row).
        $this->deleteDuplicates('code');
        $this->deleteDuplicates('barcode');

        // 3) Add the unique indexes (guarded so a per-file re-run is safe).
        if (!$this->indexExists($this->codeIndex)) {
            DB::statement("ALTER TABLE {$this->table}
                ADD CONSTRAINT {$this->codeIndex} UNIQUE (supplier_id, code)");
        }

        if (!$this->indexExists($this->barcodeIndex)) {
            DB::statement("ALTER TABLE {$this->table}
                ADD CONSTRAINT {$this->barcodeIndex} UNIQUE (supplier_id, barcode)");
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if ($this->indexExists($this->codeIndex)) {
            DB::statement("ALTER TABLE {$this->table} DROP INDEX {$this->codeIndex}");
        }

        if ($this->indexExists($this->barcodeIndex)) {
            DB::statement("ALTER TABLE {$this->table} DROP INDEX {$this->barcodeIndex}");
        }
    }

    /**
     * Delete all but the "best" row in each (supplier_id, $column) group.
     * Survivor: latest checked_at, then in stock, then newest id. The extra
     * derived-table wrapper materialises the sub-select so MySQL allows deleting
     * from a table referenced in its own subquery (avoids error 1093).
     *
     * @param  string $column  'code' or 'barcode'
     * @return void
     */
    private function deleteDuplicates(string $column)
    {
        DB::statement("
            DELETE FROM {$this->table}
            WHERE id IN (
                SELECT id FROM (
                    SELECT id,
                           ROW_NUMBER() OVER (
                               PARTITION BY supplier_id, {$column}
                               ORDER BY checked_at DESC, in_stock DESC, id DESC
                           ) AS rn
                    FROM {$this->table}
                    WHERE {$column} IS NOT NULL
                      AND supplier_id IS NOT NULL
                ) ranked
                WHERE ranked.rn > 1
            )
        ");
    }

    /**
     * Whether an index with the given name already exists on the table.
     *
     * @param  string $indexName
     * @return bool
     */
    private function indexExists(string $indexName): bool
    {
        $rows = DB::select("
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = ?
              AND index_name = ?
            LIMIT 1
        ", [$this->table, $indexName]);

        return !empty($rows);
    }
}

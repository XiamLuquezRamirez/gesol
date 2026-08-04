<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * El costo estimado de un item de oficina pasa a ser opcional.
     * Se usa SQL crudo para no depender de doctrine/dbal (change()).
     */
    public function up(): void
    {
        // Solo MySQL/MariaDB: SQLite (tests) ya toma la columna nullable desde la
        // migracion de creacion, que fue actualizada.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE items_oficina MODIFY costo_estimado DECIMAL(14,2) NULL');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE items_oficina MODIFY costo_estimado DECIMAL(14,2) NOT NULL DEFAULT 0');
        }
    }
};

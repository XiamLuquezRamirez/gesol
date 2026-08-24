<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE asignaciones_viaticos MODIFY rubro ENUM('desayuno','almuerzo','cena','merienda','gasolina','transporte')");
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            // Laravel's enum() genera un CHECK constraint en SQLite. No se puede ALTER
            // un CHECK directamente, asi que reescribimos el DDL almacenado en sqlite_master.
            $this->rewriteSqliteRubroCheck(
                ['desayuno','almuerzo','cena','merienda','gasolina'],
                ['desayuno','almuerzo','cena','merienda','gasolina','transporte']
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE asignaciones_viaticos MODIFY rubro ENUM('desayuno','almuerzo','cena','merienda','gasolina')");
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            $this->rewriteSqliteRubroCheck(
                ['desayuno','almuerzo','cena','merienda','gasolina','transporte'],
                ['desayuno','almuerzo','cena','merienda','gasolina']
            );
        }
    }

    /**
     * Reescribe el CHECK del enum `rubro` en la definicion de la tabla almacenada
     * en sqlite_master, sustituyendo la lista de valores permitidos.
     */
    private function rewriteSqliteRubroCheck(array $from, array $to): void
    {
        $quote = fn (array $vals) => implode(', ', array_map(fn ($v) => "'".$v."'", $vals));
        $fromList = $quote($from);
        $toList   = $quote($to);

        $row = DB::selectOne(
            "SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'asignaciones_viaticos'"
        );

        if (! $row || $row->sql === null) {
            return;
        }

        $newSql = str_replace($fromList, $toList, $row->sql);

        if ($newSql === $row->sql) {
            return;
        }

        DB::statement('PRAGMA writable_schema = ON');
        DB::update(
            "UPDATE sqlite_master SET sql = ? WHERE type = 'table' AND name = 'asignaciones_viaticos'",
            [$newSql]
        );
        DB::statement('PRAGMA writable_schema = OFF');
        // Forzar a SQLite a releer el esquema modificado.
        DB::statement('PRAGMA schema_version = schema_version');
    }
};

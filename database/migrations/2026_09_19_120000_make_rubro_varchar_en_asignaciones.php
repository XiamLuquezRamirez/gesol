<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Cambia `asignaciones_viaticos.rubro` de ENUM (lista cerrada) a VARCHAR (texto libre).
 *
 * Motivo: los rubros son configurables en Parametros (tabla tarifas_viaticos). Un enum
 * cerrado en la columna rechazaba rubros nuevos (p. ej. "Peaje"), causando error 500 al
 * liquidar. Con VARCHAR se admite cualquier rubro configurado. La validacion de que el
 * rubro exista se mantiene en el FormRequest (exists:tarifas_viaticos,rubro).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE asignaciones_viaticos MODIFY rubro VARCHAR(50) NOT NULL");
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            // En SQLite el enum() de Laravel dejo un CHECK constraint en el DDL.
            // Se reescribe la definicion de la tabla quitando ese CHECK de `rubro`.
            $row = DB::selectOne(
                "SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'asignaciones_viaticos'"
            );
            if (! $row || $row->sql === null) {
                return;
            }
            // Quitar el fragmento  check ("rubro" in ('...','...'))  del DDL.
            $newSql = preg_replace('/\s*check\s*\(\s*"rubro"\s+in\s*\([^)]*\)\s*\)/i', '', $row->sql);
            if ($newSql === null || $newSql === $row->sql) {
                return;
            }
            DB::statement('PRAGMA writable_schema = ON');
            DB::update(
                "UPDATE sqlite_master SET sql = ? WHERE type = 'table' AND name = 'asignaciones_viaticos'",
                [$newSql]
            );
            DB::statement('PRAGMA writable_schema = OFF');
            DB::statement('PRAGMA schema_version = schema_version');
        }
    }

    public function down(): void
    {
        // Volver a un enum cerrado (los 6 rubros base). Rubros personalizados existentes
        // podrian violar el enum; por eso el down es best-effort para entornos de desarrollo.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE asignaciones_viaticos MODIFY rubro ENUM('desayuno','almuerzo','cena','merienda','gasolina','transporte') NOT NULL");
        }
    }
};

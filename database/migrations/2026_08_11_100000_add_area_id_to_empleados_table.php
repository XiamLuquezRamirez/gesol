<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // La columna ya existe en la base de desarrollo; la guardia evita el error
        // en dev y crea la columna en entornos limpios (CI, SQLite de tests).
        if (! Schema::hasColumn('empleados', 'area_id')) {
            Schema::table('empleados', function (Blueprint $table) {
                $table->foreignId('area_id')->nullable()->after('id')
                    ->constrained('areas')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('empleados', 'area_id')) {
            Schema::table('empleados', function (Blueprint $table) {
                $table->dropConstrainedForeignId('area_id');
            });
        }
    }
};

<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asignaciones_viaticos', function (Blueprint $table) {
            $table->foreignId('ajuste_comision_id')->nullable()->after('viajero_comision_id')
                  ->constrained('ajustes_comision')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // En SQLite, dropConstrainedForeignId recrea la tabla; legacy_alter_table
        // evita que el rename intermedio deje FK colgantes en tablas hijas.
        // El PRAGMA es solo de SQLite; en MySQL el drop funciona directo.
        $sqlite = DB::getDriverName() === 'sqlite';

        if ($sqlite) {
            DB::statement('PRAGMA legacy_alter_table = ON');
        }

        Schema::table('asignaciones_viaticos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ajuste_comision_id');
        });

        if ($sqlite) {
            DB::statement('PRAGMA legacy_alter_table = OFF');
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Columnas de viajero externo (ambos drivers las soportan vía Schema::table).
        Schema::table('viajeros_comision', function (Blueprint $table) {
            if (! Schema::hasColumn('viajeros_comision', 'nombre_externo')) {
                $table->string('nombre_externo')->nullable()->after('empleado_id');
            }
            if (! Schema::hasColumn('viajeros_comision', 'identificacion_externo')) {
                $table->string('identificacion_externo', 50)->nullable()->after('nombre_externo');
            }
        });

        // empleado_id -> nullable. doctrine/dbal no instalado: driver-aware.
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE viajeros_comision MODIFY empleado_id BIGINT UNSIGNED NULL');
        } elseif ($driver === 'sqlite') {
            $this->recrearTablaSqlite();
        }
    }

    public function down(): void
    {
        Schema::table('viajeros_comision', function (Blueprint $table) {
            if (Schema::hasColumn('viajeros_comision', 'identificacion_externo')) {
                $table->dropColumn('identificacion_externo');
            }
            if (Schema::hasColumn('viajeros_comision', 'nombre_externo')) {
                $table->dropColumn('nombre_externo');
            }
        });
    }

    private function recrearTablaSqlite(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');

        // Sin esto, el rename de SQLite reescribe las FK de tablas hijas
        // (asignaciones_viaticos) para apuntar a viajeros_comision_old, que
        // luego se elimina -> FK colgante. legacy_alter_table lo evita.
        DB::statement('PRAGMA legacy_alter_table = ON');

        Schema::rename('viajeros_comision', 'viajeros_comision_old');

        DB::statement('PRAGMA legacy_alter_table = OFF');

        Schema::create('viajeros_comision', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_viaticos_id')->constrained('solicitudes_viaticos')->cascadeOnDelete();
            $table->foreignId('empleado_id')->nullable()->constrained('empleados');
            $table->foreignId('contrato_id')->nullable()->constrained('contratos')->nullOnDelete();
            $table->string('nombre_externo')->nullable();
            $table->string('identificacion_externo', 50)->nullable();
            $table->string('rol_en_comision')->nullable();
            $table->text('motivo');
            $table->date('fecha_salida');
            $table->string('hora_salida', 5);
            $table->date('fecha_regreso');
            $table->string('hora_regreso', 5);
            $table->enum('tipo_pago', ['efectivo', 'transferencia'])->default('efectivo');
            $table->timestamps();
        });

        $columnas = 'id, solicitud_viaticos_id, empleado_id, contrato_id, nombre_externo, identificacion_externo, rol_en_comision, motivo, fecha_salida, hora_salida, fecha_regreso, hora_regreso, tipo_pago, created_at, updated_at';
        DB::statement("INSERT INTO viajeros_comision ($columnas) SELECT $columnas FROM viajeros_comision_old");

        Schema::drop('viajeros_comision_old');

        DB::statement('PRAGMA foreign_keys = ON');
    }
};

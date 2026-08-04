<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotizaciones_oficina', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_oficina_id')->constrained('solicitudes_oficina')->cascadeOnDelete();
            $table->string('path');
            $table->string('nombre_original');
            $table->timestamps();
        });

        // Migrar la cotizacion unica existente (columna antigua) a la tabla de adjuntos.
        if (Schema::hasColumn('solicitudes_oficina', 'cotizacion_path')) {
            DB::table('solicitudes_oficina')
                ->whereNotNull('cotizacion_path')
                ->orderBy('id')
                ->each(function ($row) {
                    DB::table('cotizaciones_oficina')->insert([
                        'solicitud_oficina_id' => $row->id,
                        'path'                 => $row->cotizacion_path,
                        'nombre_original'      => basename($row->cotizacion_path),
                        'created_at'           => now(),
                        'updated_at'           => now(),
                    ]);
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cotizaciones_oficina');
    }
};

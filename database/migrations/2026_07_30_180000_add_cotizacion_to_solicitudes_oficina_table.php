<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_oficina', function (Blueprint $table) {
            if (!Schema::hasColumn('solicitudes_oficina', 'cotizacion_path')) {
                $table->string('cotizacion_path')->nullable()->after('comprobante');
            }
            if (!Schema::hasColumn('solicitudes_oficina', 'comentario_contador')) {
                $table->text('comentario_contador')->nullable()->after('cotizacion_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_oficina', function (Blueprint $table) {
            $table->dropColumn(['cotizacion_path', 'comentario_contador']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('viajeros_comision', 'salida_confirmada')) {
            Schema::table('viajeros_comision', function (Blueprint $table) {
                $table->boolean('salida_confirmada')->default(false)->after('tipo_pago');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('viajeros_comision', 'salida_confirmada')) {
            Schema::table('viajeros_comision', function (Blueprint $table) {
                $table->dropColumn('salida_confirmada');
            });
        }
    }
};

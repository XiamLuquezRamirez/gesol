<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('viajeros_comision', function (Blueprint $table) {
            $table->enum('tipo_pago', ['efectivo', 'transferencia'])->default('efectivo')->after('hora_regreso');
        });
    }

    public function down(): void
    {
        Schema::table('viajeros_comision', function (Blueprint $table) {
            $table->dropColumn('tipo_pago');
        });
    }
};

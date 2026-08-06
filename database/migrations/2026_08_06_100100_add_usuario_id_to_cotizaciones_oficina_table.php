<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizaciones_oficina', function (Blueprint $table) {
            $table->foreignId('usuario_id')->nullable()->after('solicitud_oficina_id')
                ->constrained('usuarios')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cotizaciones_oficina', function (Blueprint $table) {
            $table->dropConstrainedForeignId('usuario_id');
        });
    }
};

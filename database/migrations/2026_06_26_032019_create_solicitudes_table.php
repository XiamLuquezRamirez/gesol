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
        Schema::create('solicitudes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tipo_solicitud_id')->constrained('tipos_solicitud');
            $table->foreignId('solicitante_id')->constrained('usuarios');
            $table->foreignId('area_id')->nullable()->constrained('areas');
            $table->morphs('solicitable');
            $table->string('estado');
            $table->string('radicado')->unique();
            $table->decimal('total', 14, 2)->default(0);
            $table->index(['tipo_solicitud_id', 'estado'], 'idx_solicitudes_tipo_estado');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitudes');
    }
};

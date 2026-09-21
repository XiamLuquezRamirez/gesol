<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotizaciones_obra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_obra_id')->constrained('solicitudes_obra')->cascadeOnDelete();
            $table->string('tipo')->default('cotizacion');
            $table->string('path');
            $table->string('nombre_original');
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('cotizaciones_obra'); }
};

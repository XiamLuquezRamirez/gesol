<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beneficiarios_oficina', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_oficina_id')->constrained('solicitudes_oficina')->cascadeOnDelete();
            $table->foreignId('empleado_id')->constrained('empleados');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficiarios_oficina');
    }
};

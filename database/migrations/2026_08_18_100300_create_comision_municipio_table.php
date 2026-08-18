<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comision_municipio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_viaticos_id')->constrained('solicitudes_viaticos')->cascadeOnDelete();
            $table->foreignId('municipio_id')->constrained('municipios');
            $table->timestamps();
            $table->unique(['solicitud_viaticos_id', 'municipio_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comision_municipio');
    }
};

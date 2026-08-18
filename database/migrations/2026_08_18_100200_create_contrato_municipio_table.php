<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contrato_municipio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_id')->constrained('contratos')->cascadeOnDelete();
            $table->foreignId('municipio_id')->constrained('municipios');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contrato_municipio');
    }
};

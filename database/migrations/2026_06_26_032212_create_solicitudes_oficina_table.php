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
        Schema::create('solicitudes_oficina', function (Blueprint $table) {
            $table->id();
            $table->string('beneficiario');
            $table->enum('urgencia', ['baja','media','alta'])->default('media');
            $table->text('justificacion');
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('valor_pagado', 14, 2)->nullable();
            $table->date('fecha_pago')->nullable();
            $table->string('comprobante')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitudes_oficina');
    }
};

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
        Schema::create('asignaciones_viaticos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('viajero_comision_id')->constrained('viajeros_comision')->cascadeOnDelete();
            $table->enum('rubro', ['desayuno','almuerzo','cena','merienda','gasolina']);
            $table->decimal('valor_unitario', 14, 2);
            $table->unsignedInteger('dias')->default(1);
            $table->decimal('subtotal', 14, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asignaciones_viaticos');
    }
};

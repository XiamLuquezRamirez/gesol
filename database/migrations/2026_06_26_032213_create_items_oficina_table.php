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
        Schema::create('items_oficina', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_oficina_id')->constrained('solicitudes_oficina')->cascadeOnDelete();
            $table->string('nombre');
            $table->enum('categoria', ['producto','servicio']);
            $table->unsignedInteger('cantidad')->default(1);
            $table->decimal('costo_estimado', 14, 2);
            $table->decimal('subtotal', 14, 2);
            $table->string('notas')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items_oficina');
    }
};

<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items_obra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_obra_id')->constrained('solicitudes_obra')->cascadeOnDelete();
            $table->string('especificacion');
            $table->string('unidad')->nullable();
            $table->decimal('cantidad', 10, 2)->default(1);
            $table->string('sede')->nullable();
            $table->decimal('valor_unitario', 14, 2)->nullable();
            $table->decimal('subtotal', 14, 2)->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('items_obra'); }
};

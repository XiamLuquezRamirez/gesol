<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_obra', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_solicitante');
            $table->date('fecha_solicitud');
            $table->date('fecha_entrega')->nullable();
            $table->foreignId('contrato_id')->nullable()->constrained('contratos')->nullOnDelete();
            $table->text('observacion')->nullable();
            $table->decimal('total_a_pagar', 14, 2)->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('solicitudes_obra'); }
};

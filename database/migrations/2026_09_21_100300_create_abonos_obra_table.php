<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abonos_obra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_obra_id')->constrained('solicitudes_obra')->cascadeOnDelete();
            $table->decimal('monto', 14, 2);
            $table->string('retencion_tipo')->nullable();
            $table->decimal('retencion_valor', 14, 2)->nullable();
            $table->decimal('retencion_monto', 14, 2)->default(0);
            $table->foreignId('retencion_por')->nullable()->constrained('usuarios');
            $table->date('fecha_pago');
            $table->string('soporte_path');
            $table->string('soporte_nombre');
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios');
            $table->text('observacion')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('abonos_obra'); }
};

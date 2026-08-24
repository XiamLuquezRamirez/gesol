<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ajustes_comision', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->constrained('solicitudes')->cascadeOnDelete();
            $table->foreignId('viajero_comision_id')->constrained('viajeros_comision')->cascadeOnDelete();
            $table->foreignId('solicitado_por')->constrained('usuarios');
            $table->enum('tipo', ['fechas', 'rubro']);
            $table->text('motivo');
            $table->enum('estado', ['pendiente_liquidacion', 'liquidado', 'aprobado', 'devuelto'])
                  ->default('pendiente_liquidacion');
            $table->json('fechas_antes')->nullable();
            $table->json('fechas_despues')->nullable();
            $table->string('rubro')->nullable();
            $table->integer('cantidad')->nullable();
            $table->decimal('total_delta', 14, 2)->default(0);
            $table->text('motivo_devolucion')->nullable();
            $table->foreignId('liquidado_por')->nullable()->constrained('usuarios');
            $table->timestamp('liquidado_en')->nullable();
            $table->foreignId('aprobado_por')->nullable()->constrained('usuarios');
            $table->timestamp('aprobado_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ajustes_comision');
    }
};

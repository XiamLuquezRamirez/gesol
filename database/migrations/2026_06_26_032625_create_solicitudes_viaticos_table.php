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
        Schema::create('solicitudes_viaticos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_comision');
            $table->string('municipio_destino');
            $table->text('observacion')->nullable();
            $table->decimal('total', 14, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitudes_viaticos');
    }
};

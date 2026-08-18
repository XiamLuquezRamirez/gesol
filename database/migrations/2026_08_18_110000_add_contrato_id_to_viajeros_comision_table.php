<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('viajeros_comision', 'contrato_id')) {
            Schema::table('viajeros_comision', function (Blueprint $table) {
                $table->foreignId('contrato_id')->nullable()->after('empleado_id')
                    ->constrained('contratos')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('viajeros_comision', 'contrato_id')) {
            Schema::table('viajeros_comision', function (Blueprint $table) {
                $table->dropConstrainedForeignId('contrato_id');
            });
        }
    }
};

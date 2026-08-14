<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('solicitudes_oficina', 'total_a_pagar')) {
            Schema::table('solicitudes_oficina', function (Blueprint $table) {
                $table->decimal('total_a_pagar', 14, 2)->nullable()->after('total');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('solicitudes_oficina', 'total_a_pagar')) {
            Schema::table('solicitudes_oficina', function (Blueprint $table) {
                $table->dropColumn('total_a_pagar');
            });
        }
    }
};

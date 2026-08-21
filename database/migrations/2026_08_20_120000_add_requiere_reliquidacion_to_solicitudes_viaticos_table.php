<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('solicitudes_viaticos', 'requiere_reliquidacion')) {
            Schema::table('solicitudes_viaticos', function (Blueprint $table) {
                $table->boolean('requiere_reliquidacion')->default(false)->after('total');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('solicitudes_viaticos', 'requiere_reliquidacion')) {
            Schema::table('solicitudes_viaticos', function (Blueprint $table) {
                $table->dropColumn('requiere_reliquidacion');
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('solicitudes', 'estado_previo')) {
            Schema::table('solicitudes', function (Blueprint $table) {
                $table->string('estado_previo')->nullable()->after('estado');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('solicitudes', 'estado_previo')) {
            Schema::table('solicitudes', function (Blueprint $table) {
                $table->dropColumn('estado_previo');
            });
        }
    }
};

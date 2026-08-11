<?php

namespace App\Console\Commands;

use App\Models\CotizacionOficina;
use App\Models\Solicitud;
use App\Models\SolicitudOficina;
use App\Models\SolicitudViaticos;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LimpiarSolicitudes extends Command
{
    /**
     * Borra todas las solicitudes de prueba (oficina y viaticos) con sus
     * datos dependientes, archivos fisicos y notificaciones asociadas.
     * NO toca usuarios, roles, empleados, areas, tarifas ni tipos de solicitud.
     */
    protected $signature = 'solicitudes:limpiar {--force : No pedir confirmacion (para automatizacion)}';

    protected $description = 'Elimina las solicitudes de prueba (oficina y viaticos) y sus adjuntos/notificaciones, conservando la configuracion base.';

    public function handle(): int
    {
        $totalSolicitudes = Solicitud::count();
        $totalOficina     = SolicitudOficina::count();
        $totalViaticos    = SolicitudViaticos::count();

        if ($totalSolicitudes === 0 && $totalOficina === 0 && $totalViaticos === 0) {
            $this->info('No hay solicitudes que limpiar. El sistema ya esta vacio.');
            return self::SUCCESS;
        }

        $this->warn('Se van a ELIMINAR de forma irreversible:');
        $this->line("  • {$totalSolicitudes} solicitudes (con sus transiciones)");
        $this->line("  • {$totalOficina} cabeceras de oficina (items y cotizaciones en cascada)");
        $this->line("  • {$totalViaticos} comisiones de viaticos (viajeros y asignaciones en cascada)");
        $this->line('  • Archivos fisicos de cotizaciones y notificaciones de solicitudes');
        $this->line('Se conservan: usuarios, roles, empleados, areas, tarifas y tipos de solicitud.');

        if (! $this->option('force') && ! $this->confirm('¿Continuar?')) {
            $this->info('Operacion cancelada. No se borro nada.');
            return self::SUCCESS;
        }

        // 1) Archivos fisicos de cotizaciones (antes de perder los paths).
        $paths = CotizacionOficina::pluck('path')->filter()->all();
        foreach ($paths as $path) {
            Storage::disk('local')->delete($path);
        }
        $this->line(count($paths).' archivo(s) de cotizacion eliminados del disco.');

        // 2) Borrado transaccional de los registros. Las FK con cascadeOnDelete
        //    arrastran items, cotizaciones, transiciones, viajeros y asignaciones.
        DB::transaction(function () {
            // La tabla polimorfica 'solicitudes' no tiene FK hacia las cabeceras,
            // asi que se borra cada lado explicitamente.
            Solicitud::query()->delete();       // -> transiciones_solicitud (cascade)
            SolicitudOficina::query()->delete(); // -> items_oficina, cotizaciones_oficina (cascade)
            SolicitudViaticos::query()->delete(); // -> viajeros_comision -> asignaciones_viaticos (cascade)

            // 3) Notificaciones: en Gesol todas provienen de solicitudes
            //    (AvisoTransicionNotification y ComisionCerradaNotification),
            //    por lo que se vacia la tabla completa.
            DB::table('notifications')->delete();
        });

        $this->info('Solicitudes limpiadas correctamente. La configuracion base quedo intacta.');
        return self::SUCCESS;
    }
}

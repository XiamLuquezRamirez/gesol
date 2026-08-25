<?php

namespace App\Console\Commands;

use App\Models\{Area, Empleados};
use App\Models\ViajeroComision;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reemplaza los datos de las tablas `areas` y `empleados` a partir de un export JSON
 * (formato phpMyAdmin "Export to JSON") con filas {identificacion, nombres, apellidos,
 * telefono, email, departamento}.
 *
 * Estrategia:
 *  - Areas: se reemplazan por los departamentos unicos del JSON (nombre tal cual).
 *    Antes de borrar las areas actuales se comprueba que ninguna solicitud las use
 *    (FK solicitudes.area_id ON DELETE RESTRICT).
 *  - Empleados: UPSERT por `identificacion` para preservar el `id` de los empleados
 *    referenciados por comisiones (viajeros_comision.empleado_id ON DELETE RESTRICT).
 *    Los empleados que dejan de existir en el JSON se borran solo si no estan
 *    referenciados; si lo estan, se conservan y se avisa.
 *
 * Todo corre en una transaccion: si algo falla, no se aplica nada.
 */
class ImportarEmpleadosDepartamentos extends Command
{
    protected $signature = 'gesol:importar-empleados {archivo : Ruta al JSON exportado} {--dry-run : Solo mostrar el plan, sin escribir}';

    protected $description = 'Reemplaza areas y empleados desde un export JSON (upsert por identificacion)';

    public function handle(): int
    {
        $archivo = $this->argument('archivo');
        if (! is_file($archivo)) {
            $this->error("No existe el archivo: {$archivo}");
            return self::FAILURE;
        }

        $filas = $this->leerFilas($archivo);
        if ($filas === null) {
            return self::FAILURE;
        }

        // Normalizar filas y derivar departamentos unicos.
        $empleadosJson = [];
        $departamentos = [];
        foreach ($filas as $f) {
            $dep = trim($f['departamento'] ?? '');
            $ident = trim((string) ($f['identificacion'] ?? ''));
            if ($dep === '' || $ident === '') {
                $this->warn('Fila omitida (sin departamento o identificacion): '.json_encode($f, JSON_UNESCAPED_UNICODE));
                continue;
            }
            $departamentos[$dep] = true;
            $empleadosJson[] = [
                'identificacion' => $ident,
                'nombres'        => trim($f['nombres'] ?? ''),
                'apellidos'      => trim($f['apellidos'] ?? ''),
                'email'          => $f['email'] ?? null,
                'telefono'       => $f['telefono'] ?? null,
                'departamento'   => $dep,
            ];
        }
        $departamentos = array_keys($departamentos);

        $this->info('Empleados en el JSON: '.count($empleadosJson));
        $this->info('Departamentos (areas) unicos: '.count($departamentos));
        foreach ($departamentos as $d) {
            $this->line('  - '.$d);
        }

        // Comprobar referencias que impiden borrar las areas de departamento.
        // Las institucionales (es_general=1) se conservan, asi que no cuentan.
        $idsDepartamento = Area::where('es_general', false)->pluck('id');
        $areasUsadas = DB::table('solicitudes')
            ->whereNotNull('area_id')
            ->whereIn('area_id', $idsDepartamento)
            ->distinct()->pluck('area_id');
        if ($areasUsadas->isNotEmpty()) {
            $this->error('Hay solicitudes que referencian areas de departamento (area_id): '.$areasUsadas->implode(', ').'. Aborta para no romper la FK.');
            return self::FAILURE;
        }

        // Empleados actuales referenciados por comisiones (no se pueden borrar).
        $empleadosReferenciados = ViajeroComision::whereNotNull('empleado_id')->pluck('empleado_id')->unique();
        $identsJson = collect($empleadosJson)->pluck('identificacion');

        if ($this->option('dry-run')) {
            $this->comment('DRY RUN — no se escribe nada.');
            $this->mostrarPlan($empleadosJson, $empleadosReferenciados, $identsJson);
            return self::SUCCESS;
        }

        try {
            DB::transaction(function () use ($departamentos, $empleadosJson, $empleadosReferenciados) {
                // 1) Reemplazar SOLO las areas de departamento por las del JSON.
                //    El area institucional (es_general=1) NO viene en el JSON de
                //    departamentos y gestiona las solicitudes de toda la oficina
                //    (papeleria, aseo) sin beneficiario especifico: se conserva.
                Area::where('es_general', false)->delete();
                $areaIdPorNombre = [];
                foreach ($departamentos as $nombre) {
                    $area = Area::create([
                        'nombre'      => $nombre,
                        'descripcion' => null,
                        'es_general'  => false,
                    ]);
                    $areaIdPorNombre[$nombre] = $area->id;
                }

                // Garantizar que exista el area institucional (General).
                Area::firstOrCreate(
                    ['es_general' => true],
                    ['nombre' => 'General', 'descripcion' => 'Solicitudes institucionales (papelería, aseo)']
                );

                // 2) Upsert de empleados por identificacion (preserva ids referenciados).
                $identsMantener = [];
                foreach ($empleadosJson as $e) {
                    $identsMantener[] = $e['identificacion'];
                    Empleados::updateOrCreate(
                        ['identificacion' => $e['identificacion']],
                        [
                            'area_id'   => $areaIdPorNombre[$e['departamento']],
                            'nombres'   => $e['nombres'],
                            'apellidos' => $e['apellidos'],
                            'email'     => $e['email'],
                            'telefono'  => $e['telefono'],
                        ]
                    );
                }

                // 3) Borrar empleados que ya no estan en el JSON, salvo los referenciados.
                $sobrantes = Empleados::whereNotIn('identificacion', $identsMantener)->get();
                foreach ($sobrantes as $emp) {
                    if ($empleadosReferenciados->contains($emp->id)) {
                        $this->warn("Empleado {$emp->nombres} {$emp->apellidos} (id {$emp->id}) esta referenciado por una comision y NO esta en el JSON: se conserva.");
                        continue;
                    }
                    $emp->delete();
                }
            });
        } catch (\Throwable $e) {
            $this->error('Fallo la importacion (rollback): '.$e->getMessage());
            return self::FAILURE;
        }

        $this->info('Listo. Areas: '.Area::count().' | Empleados: '.Empleados::count());
        return self::SUCCESS;
    }

    /** Lee el archivo y devuelve el array de filas de datos, o null si el formato es invalido. */
    private function leerFilas(string $archivo): ?array
    {
        $json = json_decode(file_get_contents($archivo), true);
        if (! is_array($json)) {
            $this->error('El JSON no es valido.');
            return null;
        }
        foreach ($json as $bloque) {
            if (($bloque['type'] ?? null) === 'table' && isset($bloque['data']) && is_array($bloque['data'])) {
                return $bloque['data'];
            }
        }
        // Formato alternativo: array plano de empleados.
        if (isset($json[0]['identificacion'])) {
            return $json;
        }
        $this->error('No se encontro el bloque de datos (type=table) en el JSON.');
        return null;
    }

    private function mostrarPlan(array $empleadosJson, $empleadosReferenciados, $identsJson): void
    {
        $sobrantes = Empleados::whereNotIn('identificacion', $identsJson)->get();
        $this->line('Empleados actuales que se borrarian: '.$sobrantes->count());
        foreach ($sobrantes as $emp) {
            $ref = $empleadosReferenciados->contains($emp->id) ? ' [REFERENCIADO — se conserva]' : '';
            $this->line("  - {$emp->identificacion} {$emp->nombres} {$emp->apellidos}{$ref}");
        }
    }
}

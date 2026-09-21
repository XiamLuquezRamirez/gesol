<?php

namespace App\Console\Commands;

use App\Models\{Contrato, Municipio};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Trae los proyectos de la base `workboard` (tabla `proyectos`) y los inserta en
 * `gesol` como contratos:
 *   - objeto      = nombre completo del proyecto (tal cual).
 *   - descripcion = resumen identificable = "tipo de proyecto" (primeras palabras
 *                   clave del nombre) + " - " + municipio.
 *   - municipios  = se relaciona el municipio del campo `proyectos.municipio`,
 *                   emparejado por nombre (normalizado) contra la tabla `municipios`.
 *
 * La conexion a workboard se arma en tiempo de ejecucion a partir de la conexion
 * mysql actual (mismo host/credenciales), cambiando solo la base de datos, para no
 * tener que declarar una segunda conexion permanente en config/database.php.
 *
 * Idempotente: hace UPSERT por `objeto` (el nombre completo, unico por proyecto),
 * asi que re-ejecutarlo no duplica contratos. Todo corre en una transaccion.
 */
class ImportarContratosWorkboard extends Command
{
    protected $signature = 'gesol:importar-contratos
        {--base=workboard : Nombre de la base de datos origen}
        {--dry-run : Solo mostrar el plan, sin escribir}';

    protected $description = 'Importa los proyectos de workboard como contratos en gesol, relacionando el municipio';

    public function handle(): int
    {
        $baseOrigen = $this->option('base');
        $dry = (bool) $this->option('dry-run');

        // Conexion dinamica a la base origen (reusa host/credenciales del mysql actual).
        config(['database.connections.workboard_origen' => array_merge(
            config('database.connections.mysql'),
            ['database' => $baseOrigen]
        )]);

        try {
            $proyectos = DB::connection('workboard_origen')
                ->table('proyectos')
                ->select('id', 'nombre', 'municipio')
                ->orderBy('id')
                ->get();
        } catch (\Throwable $e) {
            $this->error("No se pudo leer {$baseOrigen}.proyectos: ".$e->getMessage());
            return self::FAILURE;
        }

        if ($proyectos->isEmpty()) {
            $this->warn("La tabla {$baseOrigen}.proyectos no tiene filas. Nada que importar.");
            return self::SUCCESS;
        }

        $this->info("Proyectos encontrados en {$baseOrigen}: {$proyectos->count()}");
        $this->newLine();

        // Se resuelve cada plan (contrato + municipio) antes de escribir, para poder
        // mostrar el dry-run y detectar municipios sin coincidencia.
        $planes = [];
        $sinMunicipio = [];

        foreach ($proyectos as $p) {
            $objeto = trim($p->nombre);
            $descripcion = $this->descripcionResumida($p->nombre, $p->municipio);
            $municipio = $this->buscarMunicipio($p->municipio);

            if (! $municipio) {
                $sinMunicipio[] = $p->municipio;
            }

            $planes[] = [
                'objeto'        => $objeto,
                'descripcion'   => $descripcion,
                'municipio_txt' => $p->municipio,
                'municipio_id'  => $municipio?->id,
            ];

            $this->line(sprintf(
                "  • %-58s | %s",
                Str::limit($descripcion, 56),
                $municipio ? "municipio: {$municipio->nombre} (#{$municipio->id})" : "SIN MUNICIPIO: {$p->municipio}"
            ));
        }

        $this->newLine();
        if (! empty($sinMunicipio)) {
            $this->warn('Municipios sin coincidencia en la tabla `municipios`: '.implode(', ', array_unique($sinMunicipio)));
            $this->warn('Esos contratos se crearan igual, pero sin municipio relacionado.');
            $this->newLine();
        }

        if ($dry) {
            $this->info('DRY-RUN: no se escribio nada. Quita --dry-run para aplicar.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($planes) {
            foreach ($planes as $plan) {
                // UPSERT por `objeto` (nombre completo, unico por proyecto): no duplica.
                $contrato = Contrato::updateOrCreate(
                    ['objeto' => $plan['objeto']],
                    ['descripcion' => $plan['descripcion']]
                );

                // Relacion con el municipio (sync: deja exactamente el que corresponde).
                if ($plan['municipio_id']) {
                    $contrato->municipios()->sync([$plan['municipio_id']]);
                }
            }
        });

        $this->info('Contratos importados correctamente ('.count($planes).').');
        return self::SUCCESS;
    }

    /**
     * Descripcion resumida e identificable: primeras palabras clave del nombre
     * (el "tipo" del proyecto, hasta el primer conector largo) + " - " + municipio.
     * Ej: "IMPLEMENTACION DE AMBIENTES EDUCATIVOS TECNOLOGICOS PARA..." en Riohacha
     *     -> "Implementacion de ambientes educativos - Riohacha".
     */
    private function descripcionResumida(string $nombre, ?string $municipio): string
    {
        $nombre = trim(preg_replace('/\s+/', ' ', $nombre));

        // Cortar el nombre en el primer conector que introduce el detalle largo.
        // Se conserva lo anterior como "tipo" del proyecto.
        $tipo = preg_split('/\s+(PARA|EN|DE LAS|DE LOS|DEL MUNICIPIO|MEDIANTE)\s+/iu', $nombre)[0] ?? $nombre;

        // Limitar a un maximo de palabras para que sea corto y legible.
        $palabras = explode(' ', $tipo);
        if (count($palabras) > 6) {
            $tipo = implode(' ', array_slice($palabras, 0, 6));
        }

        $tipo = Str::of($tipo)->lower()->ucfirst()->trim()->toString();

        $muni = $municipio ? Str::of($municipio)->lower()->title()->toString() : null;

        return $muni ? "{$tipo} - {$muni}" : $tipo;
    }

    /**
     * Empareja el nombre del municipio de workboard con la tabla `municipios` de gesol.
     * Match por nombre normalizado (sin tildes/mayusculas) para tolerar diferencias
     * de acentuacion entre ambas bases.
     */
    private function buscarMunicipio(?string $nombre): ?Municipio
    {
        if (! $nombre) {
            return null;
        }

        $objetivo = $this->normalizar($nombre);

        // Primer intento: match exacto por nombre (rapido, cubre la mayoria).
        $exacto = Municipio::whereRaw('UPPER(nombre) = ?', [mb_strtoupper(trim($nombre))])->first();
        if ($exacto) {
            return $exacto;
        }

        // Segundo intento: normalizando tildes en PHP (mas tolerante).
        return Municipio::all(['id', 'nombre'])
            ->first(fn ($m) => $this->normalizar($m->nombre) === $objetivo);
    }

    /** Quita tildes, pasa a mayusculas y colapsa espacios para comparar nombres. */
    private function normalizar(string $texto): string
    {
        $texto = mb_strtoupper(trim($texto));
        $texto = strtr($texto, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N', 'Ü' => 'U',
        ]);
        return preg_replace('/\s+/', ' ', $texto);
    }
}

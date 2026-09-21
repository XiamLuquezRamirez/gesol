<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class AjusteComision extends Model
{
    protected $table = 'ajustes_comision';
    protected $fillable = [
        'solicitud_id', 'viajero_comision_id', 'solicitado_por', 'tipo', 'motivo', 'estado',
        'fechas_antes', 'fechas_despues', 'rubro', 'cantidad', 'total_delta',
        'motivo_devolucion', 'liquidado_por', 'liquidado_en', 'aprobado_por', 'aprobado_en',
    ];
    protected $casts = [
        'fechas_antes'  => 'array',
        'fechas_despues' => 'array',
        'total_delta'   => 'decimal:2',
        'liquidado_en'  => 'datetime',
        'aprobado_en'   => 'datetime',
        'cantidad'      => 'integer',
    ];

    public function solicitud()   { return $this->belongsTo(Solicitud::class, 'solicitud_id'); }
    public function viajero()     { return $this->belongsTo(ViajeroComision::class, 'viajero_comision_id'); }
    public function solicitante() { return $this->belongsTo(Usuario::class, 'solicitado_por'); }
    public function liquidador()  { return $this->belongsTo(Usuario::class, 'liquidado_por'); }
    public function aprobador()   { return $this->belongsTo(Usuario::class, 'aprobado_por'); }
    public function asignaciones() { return $this->hasMany(AsignacionViatico::class, 'ajuste_comision_id'); }

    /** Suma los subtotales de sus asignaciones anexas y persiste en total_delta. */
    public function recalcularTotalDelta(): void
    {
        $total = $this->asignaciones()->sum('subtotal');
        $this->updateQuietly(['total_delta' => $total]);
    }

    /**
     * Estados de ajuste que estan "pendientes por aprobar/liquidar" para el rol
     * del usuario: el contador debe liquidar los pendiente_liquidacion/devuelto;
     * el lider de contabilidad debe aprobar los liquidado. Otros roles: ninguno.
     * Fuente unica de verdad usada por el listado y el conteo del inicio.
     */
    public static function estadosPendientesPara(Usuario $usuario): array
    {
        if ($usuario->hasRole('contabilidad_lider')) {
            return ['liquidado'];
        }
        if ($usuario->hasRole('contador')) {
            return ['pendiente_liquidacion', 'devuelto'];
        }

        return [];
    }

    /**
     * Ids (unicos) de las solicitudes que tienen un ajuste pendiente relevante al
     * rol del usuario. Vacio si el rol no gestiona ajustes. Una sola consulta.
     */
    public static function solicitudesConPendientePara(Usuario $usuario): Collection
    {
        $estados = self::estadosPendientesPara($usuario);
        if (empty($estados)) {
            return collect();
        }

        return self::whereIn('estado', $estados)->pluck('solicitud_id')->unique()->values();
    }
}

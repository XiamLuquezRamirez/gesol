<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}

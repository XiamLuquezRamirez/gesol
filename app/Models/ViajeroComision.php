<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ViajeroComision extends Model
{
    protected $table    = 'viajeros_comision';
    protected $fillable = [
        'solicitud_viaticos_id','empleado_id','contrato_id',
        'nombre_externo','identificacion_externo',
        'rol_en_comision','motivo','fecha_salida','hora_salida','fecha_regreso','hora_regreso','tipo_pago',
    ];
    protected $casts = ['fecha_salida'=>'date','fecha_regreso'=>'date'];

    public function empleado()          { return $this->belongsTo(Empleados::class, 'empleado_id'); }
    public function contrato()          { return $this->belongsTo(Contrato::class, 'contrato_id'); }
    public function solicitudViaticos() { return $this->belongsTo(SolicitudViaticos::class, 'solicitud_viaticos_id'); }
    public function asignaciones()      { return $this->hasMany(AsignacionViatico::class, 'viajero_comision_id'); }

    public function archivos()
    {
        return $this->hasMany(ArchivoViajero::class, 'viajero_comision_id');
    }

    /** Nombre a mostrar: empleado de la BD o nombre libre del viajero externo. */
    public function getNombreMostradoAttribute(): string
    {
        if ($this->empleado) {
            return trim(($this->empleado->nombres ?? '').' '.($this->empleado->apellidos ?? ''));
        }
        return $this->nombre_externo ?? '';
    }

    /** Identificación a mostrar: la del empleado o la del externo. */
    public function getIdentificacionMostradaAttribute(): ?string
    {
        return $this->empleado?->identificacion ?? $this->identificacion_externo;
    }
}

<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ViajeroComision extends Model
{
    protected $table    = 'viajeros_comision';
    protected $fillable = [
        'solicitud_viaticos_id','empleado_id','rol_en_comision',
        'motivo','fecha_salida','hora_salida','fecha_regreso','hora_regreso','tipo_pago',
    ];
    protected $casts = ['fecha_salida'=>'date','fecha_regreso'=>'date'];

    public function empleado()          { return $this->belongsTo(Empleados::class, 'empleado_id'); }
    public function solicitudViaticos() { return $this->belongsTo(SolicitudViaticos::class, 'solicitud_viaticos_id'); }
    public function asignaciones()      { return $this->hasMany(AsignacionViatico::class, 'viajero_comision_id'); }
}

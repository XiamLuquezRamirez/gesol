<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ViajeroComision extends Model
{
    protected $table = 'viajeros_comision';
    protected $fillable = ['solicitud_viaticos_id','usuario_id','rol_en_comision'];

    public function usuario()           { return $this->belongsTo(Usuario::class, 'usuario_id'); }
    public function solicitudViaticos() { return $this->belongsTo(SolicitudViaticos::class, 'solicitud_viaticos_id'); }
    public function asignaciones()      { return $this->hasMany(AsignacionViatico::class, 'viajero_comision_id'); }
}

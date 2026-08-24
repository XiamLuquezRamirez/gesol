<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SolicitudViaticos extends Model
{
    protected $table = 'solicitudes_viaticos';
    protected $fillable = ['nombre_comision','municipio_destino','observacion','total','requiere_reliquidacion'];
    protected $casts = ['requiere_reliquidacion' => 'boolean'];

    public function viajeros()  { return $this->hasMany(ViajeroComision::class, 'solicitud_viaticos_id'); }

    public function municipios()
    {
        return $this->belongsToMany(Municipio::class, 'comision_municipio', 'solicitud_viaticos_id', 'municipio_id')
            ->withTimestamps();
    }

    public function solicitud() { return $this->morphOne(Solicitud::class, 'solicitable'); }

    public function recalcularTotal(): void
    {
        $total = $this->viajeros()
            ->join('asignaciones_viaticos','viajeros_comision.id','=','asignaciones_viaticos.viajero_comision_id')
            ->whereNull('asignaciones_viaticos.ajuste_comision_id') // excluir anexos
            ->sum('asignaciones_viaticos.subtotal');
        $this->updateQuietly(['total' => $total]);
        $this->solicitud()->update(['total' => $total]);
    }
}

<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SolicitudViaticos extends Model
{
    protected $table = 'solicitudes_viaticos';
    protected $fillable = ['nombre_comision','municipio_destino','motivo','fecha_salida','fecha_regreso','total'];
    protected $casts = ['fecha_salida'=>'date','fecha_regreso'=>'date'];

    public function viajeros()  { return $this->hasMany(ViajeroComision::class, 'solicitud_viaticos_id'); }
    public function solicitud() { return $this->morphOne(Solicitud::class, 'solicitable'); }

    public function recalcularTotal(): void
    {
        $total = $this->viajeros()
            ->join('asignaciones_viaticos','viajeros_comision.id','=','asignaciones_viaticos.viajero_comision_id')
            ->sum('asignaciones_viaticos.subtotal');
        $this->updateQuietly(['total' => $total]);
        $this->solicitud()->update(['total' => $total]);
    }
}

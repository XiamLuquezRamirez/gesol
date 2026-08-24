<?php
namespace App\Models;
use App\Enums\Rubro;
use Illuminate\Database\Eloquent\Model;

class AsignacionViatico extends Model
{
    protected $table = 'asignaciones_viaticos';
    protected $fillable = ['viajero_comision_id','ajuste_comision_id','rubro','valor_unitario','dias','subtotal'];
    protected $casts = ['rubro' => Rubro::class];

    protected static function booted(): void
    {
        static::saving(fn(AsignacionViatico $a)  => $a->subtotal = $a->valor_unitario * $a->dias);
        static::saved(fn(AsignacionViatico $a)   => $a->recalcularOrigen());
        static::deleted(fn(AsignacionViatico $a) => $a->recalcularOrigen());
    }

    /**
     * Recalcula el total del contenedor correcto: si la asignacion pertenece a un
     * ajuste (anexo), recalcula el total_delta del ajuste; si no, el total de la
     * comision (que a su vez excluye los anexos).
     */
    private function recalcularOrigen(): void
    {
        if ($this->ajuste_comision_id) {
            $this->ajusteComision?->recalcularTotalDelta();
            return;
        }
        $this->viajeroComision->solicitudViaticos->recalcularTotal();
    }

    public function viajeroComision() { return $this->belongsTo(ViajeroComision::class, 'viajero_comision_id'); }
    public function ajusteComision()  { return $this->belongsTo(AjusteComision::class, 'ajuste_comision_id'); }
}

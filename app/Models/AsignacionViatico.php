<?php
namespace App\Models;
use App\Enums\Rubro;
use Illuminate\Database\Eloquent\Model;

class AsignacionViatico extends Model
{
    protected $table = 'asignaciones_viaticos';
    protected $fillable = ['viajero_comision_id','rubro','valor_unitario','dias','subtotal'];
    protected $casts = ['rubro' => Rubro::class];

    protected static function booted(): void
    {
        static::saving(fn(AsignacionViatico $a)  => $a->subtotal = $a->valor_unitario * $a->dias);
        static::saved(fn(AsignacionViatico $a)   => $a->viajeroComision->solicitudViaticos->recalcularTotal());
        static::deleted(fn(AsignacionViatico $a) => $a->viajeroComision->solicitudViaticos->recalcularTotal());
    }

    public function viajeroComision() { return $this->belongsTo(ViajeroComision::class, 'viajero_comision_id'); }
}

<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SolicitudObra extends Model
{
    protected $table = 'solicitudes_obra';
    protected $fillable = ['nombre_solicitante','fecha_solicitud','fecha_entrega','contrato_id','observacion','total_a_pagar'];
    protected $casts = ['fecha_solicitud' => 'date', 'fecha_entrega' => 'date', 'total_a_pagar' => 'decimal:2'];

    public function items()        { return $this->hasMany(ItemObra::class, 'solicitud_obra_id'); }
    public function cotizaciones() { return $this->hasMany(CotizacionObra::class, 'solicitud_obra_id'); }
    public function abonos()       { return $this->hasMany(AbonoObra::class, 'solicitud_obra_id'); }
    public function contrato()     { return $this->belongsTo(Contrato::class, 'contrato_id'); }
    public function solicitud()    { return $this->morphOne(Solicitud::class, 'solicitable'); }

    public function totalPagado(): float
    {
        return (float) $this->abonos()->sum('monto');
    }
    public function saldoPendiente(): float
    {
        if ($this->total_a_pagar === null) return 0.0;
        return (float) $this->total_a_pagar - $this->totalPagado();
    }
    public function estaPagadaCompleta(): bool
    {
        return $this->total_a_pagar !== null && $this->totalPagado() >= (float) $this->total_a_pagar;
    }
    /** Suma de subtotales de los items ya cotizados (no persiste; el total real es total_a_pagar). */
    public function totalItems(): float
    {
        return (float) $this->items()->sum('subtotal');
    }
}

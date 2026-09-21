<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ItemObra extends Model
{
    protected $table = 'items_obra';
    protected $fillable = ['solicitud_obra_id','especificacion','unidad','cantidad','sede','valor_unitario','subtotal'];
    protected $casts = ['cantidad' => 'decimal:2', 'valor_unitario' => 'decimal:2', 'subtotal' => 'decimal:2'];

    protected static function booted(): void
    {
        static::saving(function (ItemObra $i) {
            if ($i->valor_unitario !== null) {
                $i->subtotal = (float) $i->cantidad * (float) $i->valor_unitario;
            }
        });
    }
    public function solicitudObra() { return $this->belongsTo(SolicitudObra::class, 'solicitud_obra_id'); }
}

<?php
namespace App\Models;
use App\Enums\CategoriaItem;
use Illuminate\Database\Eloquent\Model;

class ItemOficina extends Model
{
    protected $table = 'items_oficina';
    protected $fillable = ['solicitud_oficina_id','nombre','categoria','cantidad','costo_estimado','subtotal','notas'];
    protected $casts = ['categoria' => CategoriaItem::class];

    protected static function booted(): void
    {
        static::saving(function (ItemOficina $item) {
            $item->subtotal = $item->cantidad * $item->costo_estimado;
        });
        static::saved(fn(ItemOficina $item)   => $item->solicitudOficina->recalcularTotal());
        static::deleted(fn(ItemOficina $item) => $item->solicitudOficina->recalcularTotal());
    }

    public function solicitudOficina()
    {
        return $this->belongsTo(SolicitudOficina::class, 'solicitud_oficina_id');
    }
}

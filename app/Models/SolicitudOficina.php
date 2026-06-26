<?php
namespace App\Models;
use App\Enums\UrgenciaOficina;
use Illuminate\Database\Eloquent\Model;

class SolicitudOficina extends Model
{
    protected $table = 'solicitudes_oficina';
    protected $fillable = ['beneficiario_id','urgencia','justificacion','total','valor_pagado','fecha_pago','comprobante'];
    protected $casts = ['urgencia'=>UrgenciaOficina::class, 'fecha_pago'=>'date'];

    public function beneficiario() { return $this->belongsTo(Usuario::class, 'beneficiario_id'); }
    public function items()        { return $this->hasMany(ItemOficina::class, 'solicitud_oficina_id'); }
    public function solicitud()    { return $this->morphOne(Solicitud::class, 'solicitable'); }

    public function recalcularTotal(): void
    {
        $total = $this->items()->sum('subtotal');
        $this->updateQuietly(['total' => $total]);
        $this->solicitud()->update(['total' => $total]);
    }
}

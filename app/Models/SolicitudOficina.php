<?php

namespace App\Models;

use App\Enums\UrgenciaOficina;
use Illuminate\Database\Eloquent\Model;

class SolicitudOficina extends Model
{
    protected $table = 'solicitudes_oficina';
    protected $fillable = ['beneficiario', 'urgencia', 'justificacion', 'total', 'valor_pagado', 'fecha_pago', 'comprobante', 'cotizacion_path', 'comentario_contador'];
    protected $casts = ['urgencia' => UrgenciaOficina::class, 'fecha_pago' => 'date'];

    public function items()
    {
        return $this->hasMany(ItemOficina::class, 'solicitud_oficina_id');
    }
    public function cotizaciones()
    {
        return $this->hasMany(CotizacionOficina::class, 'solicitud_oficina_id');
    }

    public function beneficiarios()
    {
        return $this->belongsToMany(Empleados::class, 'beneficiarios_oficina', 'solicitud_oficina_id', 'empleado_id')
            ->withTimestamps();
    }

    public function abonos()
    {
        return $this->hasMany(AbonoOficina::class, 'solicitud_oficina_id');
    }

    public function totalPagado(): float
    {
        return (float) $this->abonos()->sum('monto');
    }

    public function saldo(): float
    {
        return (float) $this->total - $this->totalPagado();
    }

    public function solicitud()
    {
        return $this->morphOne(Solicitud::class, 'solicitable');
    }

    public function recalcularTotal(): void
    {
        $total = $this->items()->sum('subtotal');
        $this->updateQuietly(['total' => $total]);
        $this->solicitud()->update(['total' => $total]);
    }
}

<?php

namespace App\Models;

use App\Enums\UrgenciaOficina;
use Illuminate\Database\Eloquent\Model;

class SolicitudOficina extends Model
{
    protected $table = 'solicitudes_oficina';
    protected $fillable = ['beneficiario', 'urgencia', 'justificacion', 'total', 'total_a_pagar', 'valor_pagado', 'fecha_pago', 'comprobante', 'cotizacion_path', 'comentario_contador'];
    protected $casts = ['urgencia' => UrgenciaOficina::class, 'fecha_pago' => 'date', 'total_a_pagar' => 'decimal:2'];

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
        // Reusa la coleccion ya cargada (evita una query SUM por fila en listados
        // con eager loading); solo agrega en BD si la relacion no viene cargada.
        $suma = $this->relationLoaded('abonos')
            ? $this->abonos->sum('monto')
            : $this->abonos()->sum('monto');

        return (float) $suma;
    }

    public function saldoPendiente(): float
    {
        // Antes de fijar el total real (sin pagos aun), no hay saldo aplicable.
        if ($this->total_a_pagar === null) {
            return 0.0;
        }
        return (float) $this->total_a_pagar - $this->totalPagado();
    }

    public function estaPagadaCompleta(): bool
    {
        return $this->total_a_pagar !== null && $this->totalPagado() >= (float) $this->total_a_pagar;
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

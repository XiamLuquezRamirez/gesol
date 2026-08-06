<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbonoOficina extends Model
{
    protected $table = 'abonos_oficina';
    protected $fillable = [
        'solicitud_oficina_id', 'monto', 'fecha_pago',
        'soporte_path', 'soporte_nombre', 'usuario_id', 'observacion',
    ];
    protected $casts = [
        'fecha_pago' => 'date',
        'monto'      => 'decimal:2',
    ];

    public function solicitudOficina()
    {
        return $this->belongsTo(SolicitudOficina::class, 'solicitud_oficina_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}

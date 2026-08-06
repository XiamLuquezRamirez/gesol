<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CotizacionOficina extends Model
{
    protected $table = 'cotizaciones_oficina';
    protected $fillable = ['solicitud_oficina_id', 'usuario_id', 'path', 'nombre_original'];

    public function solicitudOficina()
    {
        return $this->belongsTo(SolicitudOficina::class, 'solicitud_oficina_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}

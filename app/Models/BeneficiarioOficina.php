<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BeneficiarioOficina extends Model
{
    protected $table = 'beneficiarios_oficina';
    protected $fillable = ['solicitud_oficina_id', 'empleado_id'];

    public function solicitudOficina()
    {
        return $this->belongsTo(SolicitudOficina::class, 'solicitud_oficina_id');
    }

    public function empleado()
    {
        return $this->belongsTo(Empleados::class, 'empleado_id');
    }
}

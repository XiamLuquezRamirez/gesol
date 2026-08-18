<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Municipio extends Model
{
    protected $table = 'municipios';
    protected $fillable = ['nombre'];

    public function comisiones()
    {
        return $this->belongsToMany(SolicitudViaticos::class, 'comision_municipio', 'municipio_id', 'solicitud_viaticos_id')
            ->withTimestamps();
    }

    public function contratos()
    {
        return $this->belongsToMany(Contrato::class, 'contrato_municipio', 'municipio_id', 'contrato_id')
            ->withTimestamps();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contrato extends Model
{
    protected $table = 'contratos';
    protected $fillable = ['descripcion', 'objeto'];

    public function municipios()
    {
        return $this->belongsToMany(Municipio::class, 'contrato_municipio', 'contrato_id', 'municipio_id')
            ->withTimestamps();
    }
}

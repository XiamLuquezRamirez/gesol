<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArchivoViajero extends Model
{
    protected $table = 'archivos_viajero';
    protected $fillable = ['viajero_comision_id', 'tipo', 'path', 'nombre', 'usuario_id'];

    public function viajero()
    {
        return $this->belongsTo(ViajeroComision::class, 'viajero_comision_id');
    }
}

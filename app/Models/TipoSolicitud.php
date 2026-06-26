<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class TipoSolicitud extends Model
{
    protected $table = 'tipos_solicitud';
    protected $fillable = ['clave','nombre','estado_inicial','estados','transiciones'];
    protected $casts = [
        'estados'     => 'array',
        'transiciones'=> 'array',
    ];

    public function solicitudes()
    {
        return $this->hasMany(Solicitud::class, 'tipo_solicitud_id');
    }
}

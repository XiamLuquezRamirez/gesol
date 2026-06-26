<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class TransicionSolicitud extends Model
{
    protected $table = 'transiciones_solicitud';
    public $timestamps = false;
    const CREATED_AT = 'created_at';

    protected $fillable = ['solicitud_id','estado_origen','estado_destino','accion','usuario_id','comentario','metadatos'];
    protected $casts = ['metadatos' => 'array', 'created_at' => 'datetime'];

    public function solicitud() { return $this->belongsTo(Solicitud::class, 'solicitud_id'); }
    public function usuario()   { return $this->belongsTo(Usuario::class, 'usuario_id'); }
}

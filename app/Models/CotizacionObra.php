<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CotizacionObra extends Model
{
    protected $table = 'cotizaciones_obra';
    protected $fillable = ['solicitud_obra_id','tipo','path','nombre_original','usuario_id'];
    public function solicitudObra() { return $this->belongsTo(SolicitudObra::class, 'solicitud_obra_id'); }
    public function usuario()       { return $this->belongsTo(Usuario::class, 'usuario_id'); }
}

<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class AbonoObra extends Model
{
    protected $table = 'abonos_obra';
    protected $fillable = ['solicitud_obra_id','monto','retencion_tipo','retencion_valor','retencion_monto','retencion_por','fecha_pago','soporte_path','soporte_nombre','usuario_id','observacion'];
    protected $casts = ['fecha_pago' => 'date', 'monto' => 'decimal:2', 'retencion_valor' => 'decimal:2', 'retencion_monto' => 'decimal:2'];

    public function solicitudObra() { return $this->belongsTo(SolicitudObra::class, 'solicitud_obra_id'); }
    public function usuario()       { return $this->belongsTo(Usuario::class, 'usuario_id'); }
    public function retenedor()     { return $this->belongsTo(Usuario::class, 'retencion_por'); }
}

<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Solicitud extends Model
{
    protected $table = 'solicitudes';
    protected $fillable = ['tipo_solicitud_id','solicitante_id','area_id','solicitable_type','solicitable_id','estado','estado_previo','radicado','total'];

    protected static function booted(): void
    {
        static::creating(function (Solicitud $solicitud) {
            if (empty($solicitud->radicado)) {
                $solicitud->radicado = static::generarRadicado($solicitud->tipoSolicitud);
            }
            if (empty($solicitud->estado)) {
                $solicitud->estado = $solicitud->tipoSolicitud->estado_inicial;
            }
        });
    }

    public static function generarRadicado(TipoSolicitud $tipo): string
    {
        $clave    = strtoupper($tipo->clave);
        $anio     = now()->year;
        $secuencia = static::whereYear('created_at', $anio)
            ->where('tipo_solicitud_id', $tipo->id)
            ->count() + 1;
        return sprintf('%s-%d-%05d', $clave, $anio, $secuencia);
    }

    public function tipoSolicitud()   { return $this->belongsTo(TipoSolicitud::class, 'tipo_solicitud_id'); }
    public function solicitante()     { return $this->belongsTo(Usuario::class, 'solicitante_id'); }
    public function area()            { return $this->belongsTo(Area::class, 'area_id'); }
    public function solicitable()     { return $this->morphTo(); }
    public function transiciones()    { return $this->hasMany(TransicionSolicitud::class, 'solicitud_id')->orderBy('created_at'); }
}

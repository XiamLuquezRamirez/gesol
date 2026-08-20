<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArchivoViajero extends Model
{
    protected $table = 'archivos_viajero';
    protected $fillable = ['viajero_comision_id', 'tipo', 'path', 'nombre', 'usuario_id'];

    // Se exponen al front (modal de comprobantes) sin filtrar la ruta interna del disco.
    protected $appends = ['autor'];
    protected $hidden  = ['path'];

    public function viajero()
    {
        return $this->belongsTo(ViajeroComision::class, 'viajero_comision_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    /** Nombre de quien subió el archivo (o null si no quedó registrado). */
    public function getAutorAttribute(): ?string
    {
        return $this->usuario?->name;
    }
}

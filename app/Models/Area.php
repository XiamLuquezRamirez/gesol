<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Area extends Model
{
    protected $table = 'areas';
    protected $fillable = ['nombre', 'descripcion'];

    public function solicitudes()
    {
        return $this->hasMany(Solicitud::class, 'area_id');
    }

    public function empleados()
    {
        return $this->hasMany(Empleados::class, 'area_id');
    }
}

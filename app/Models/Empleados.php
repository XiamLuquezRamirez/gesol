<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Empleados extends Model
{
    use HasFactory;

    protected $table = 'empleados';

    protected $fillable = [
        'area_id',
        'identificacion',
        'nombres',
        'apellidos',
        'email',
        'telefono',
    ];

    public function area()
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

}

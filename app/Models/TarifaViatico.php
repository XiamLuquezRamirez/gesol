<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class TarifaViatico extends Model
{
    protected $table = 'tarifas_viaticos';
    protected $fillable = ['rubro','valor_sugerido'];
}

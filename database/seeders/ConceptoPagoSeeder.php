<?php
namespace Database\Seeders;
use App\Models\ConceptoPago;
use Illuminate\Database\Seeder;

class ConceptoPagoSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Gasolina', 'Lavado de carro', 'Peaje', 'Parqueadero'] as $nombre) {
            ConceptoPago::firstOrCreate(['nombre' => $nombre], ['activo' => true]);
        }
    }
}

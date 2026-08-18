<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesSeeder::class,
            AreaSeeder::class,
            TipoSolicitudSeeder::class,
            TarifaViaticosSeeder::class,
            MunicipiosSeeder::class,
            AdminSeeder::class,
            UsuariosDemoSeeder::class,
            EmpleadosSeeder::class,
        ]);
    }
}

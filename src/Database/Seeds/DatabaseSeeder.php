<?php
namespace DreamFactory\Core\Database\Seeds;

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Model::unguard();

        $this->call(ServiceSeeder::class);
        $this->call(AdminSeeder::class);
        $this->call(AppSeeder::class);
    }
}

<?php

namespace Shoptimised\AiVisibility\Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            DemoRetailerSeeder::class,
        ]);
    }
}

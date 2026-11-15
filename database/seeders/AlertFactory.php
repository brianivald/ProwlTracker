<?php

namespace Database\Seeders;

use App\Models\Alert;
use Illuminate\Database\Seeder;

class AlertFactory extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Alert::factory()->count(20)->create();
    }
}

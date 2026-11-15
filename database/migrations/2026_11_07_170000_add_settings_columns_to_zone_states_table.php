<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Parámetros operativos configurables desde el panel web por zona:
     * horario laboral, días hábiles y tiempos de merodeo / enfriamiento.
     */
    public function up(): void
    {
        Schema::table('zone_states', function (Blueprint $table) {
            $table->unsignedTinyInteger('work_start_hour')->default(8);
            $table->unsignedTinyInteger('work_end_hour')->default(20);
            $table->json('work_days')->nullable();
            $table->float('loitering_seconds_work')->default(15.0);
            $table->float('loitering_seconds_off')->default(6.0);
            $table->float('transit_seconds')->default(3.0);
            $table->float('alert_cooldown_seconds')->default(30.0);
        });
    }

    public function down(): void
    {
        Schema::table('zone_states', function (Blueprint $table) {
            $table->dropColumn([
                'work_start_hour',
                'work_end_hour',
                'work_days',
                'loitering_seconds_work',
                'loitering_seconds_off',
                'transit_seconds',
                'alert_cooldown_seconds',
            ]);
        });
    }
};


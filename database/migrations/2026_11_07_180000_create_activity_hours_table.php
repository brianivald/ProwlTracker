<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Actividad real del laboratorio: una fila por zona + día + hora en la que
     * el nodo de visión confirmó personas con YOLO. Es la materia prima del
     * horario adaptativo (ver App\Services\ScheduleLearner).
     */
    public function up(): void
    {
        Schema::create('activity_hours', function (Blueprint $table) {
            $table->id();
            $table->string('zone');
            $table->date('date');
            $table->unsignedTinyInteger('hour');
            $table->unsignedInteger('samples')->default(0);
            $table->unsignedInteger('armed_samples')->default(0);
            $table->unsignedSmallInteger('max_persons')->default(0);
            $table->timestamps();

            $table->unique(['zone', 'date', 'hour']);
        });

        Schema::table('zone_states', function (Blueprint $table) {
            // false = solo sugerir en el Dashboard; true = aplicar solo.
            $table->boolean('adaptive_auto')->default(false);
            $table->date('adaptive_evaluated_on')->nullable();
            $table->timestamp('adaptive_applied_at')->nullable();
            $table->string('adaptive_previous_schedule')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_hours');

        Schema::table('zone_states', function (Blueprint $table) {
            $table->dropColumn([
                'adaptive_auto',
                'adaptive_evaluated_on',
                'adaptive_applied_at',
                'adaptive_previous_schedule',
            ]);
        });
    }
};


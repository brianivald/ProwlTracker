<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Estado manual por zona (laboratorio). `mode_override` es null cuando
     * la zona sigue el horario automático; 'armed' u 'occupied' cuando el
     * personal lo fuerza desde el panel. `override_until` null = sin vencimiento.
     */
    public function up(): void
    {
        Schema::create('zone_states', function (Blueprint $table) {
            $table->id();
            $table->string('zone')->unique();
            $table->string('mode_override')->nullable();
            $table->timestamp('override_until')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zone_states');
    }
};


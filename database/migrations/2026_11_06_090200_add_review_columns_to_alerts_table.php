<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Revisión humana de cada alerta: nueva | personal | intrusion | falsa_alarma.
     */
    public function up(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->string('status')->default('nueva')->after('image_path');
            $table->timestamp('reviewed_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->dropColumn(['status', 'reviewed_at']);
        });
    }
};


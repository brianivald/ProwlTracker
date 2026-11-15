<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zone_states', function (Blueprint $table) {
            $table->boolean('notify_enabled')->default(false)->after('adaptive_previous_schedule');
            $table->boolean('notify_only_armed')->default(true)->after('notify_enabled');
            $table->unsignedSmallInteger('notify_cooldown_minutes')->default(15)->after('notify_only_armed');
            $table->boolean('notify_email_enabled')->default(false)->after('notify_cooldown_minutes');
            $table->string('notify_email_to', 180)->nullable()->after('notify_email_enabled');
            $table->boolean('notify_telegram_enabled')->default(false)->after('notify_email_to');
            $table->string('notify_telegram_chat_id', 100)->nullable()->after('notify_telegram_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('zone_states', function (Blueprint $table) {
            $table->dropColumn([
                'notify_enabled',
                'notify_only_armed',
                'notify_cooldown_minutes',
                'notify_email_enabled',
                'notify_email_to',
                'notify_telegram_enabled',
                'notify_telegram_chat_id',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sequential_naming_settings', function (Blueprint $table) {
            $table->id();
            $table->string('prefix')->default('server1u');
            $table->unsignedBigInteger('counter')->default(0);
            $table->boolean('is_enabled')->default(false);
            $table->timestamps();
        });

        // Seed default row
        \Illuminate\Support\Facades\DB::table('sequential_naming_settings')->insert([
            'prefix' => 'server1u',
            'counter' => 0,
            'is_enabled' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('sequential_naming_settings');
    }
};

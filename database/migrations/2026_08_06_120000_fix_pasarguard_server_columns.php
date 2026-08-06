<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('ms_servers')) {
            Schema::table('ms_servers', function (Blueprint $table) {
                if (!Schema::hasColumn('ms_servers', 'pasarguard_node_hostname')) {
                    $table->string('pasarguard_node_hostname')->nullable()->after('password');
                }
                $table->string('type', 50)->default('xui')->change();
                $table->integer('inbound_id')->nullable()->change();
            });
        }

        if (Schema::hasTable('vpn_servers')) {
            Schema::table('vpn_servers', function (Blueprint $table) {
                $table->string('type', 50)->default('sanaei')->change();
            });
        }

        if (Schema::hasTable('plans')) {
            Schema::table('plans', function (Blueprint $table) {
                $table->string('server_type', 50)->default('all')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('ms_servers')) {
            Schema::table('ms_servers', function (Blueprint $table) {
                if (Schema::hasColumn('ms_servers', 'pasarguard_node_hostname')) {
                    $table->dropColumn('pasarguard_node_hostname');
                }
            });
        }
    }
};

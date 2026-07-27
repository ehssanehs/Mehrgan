<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'is_imported')) {
                $table->boolean('is_imported')->default(false)->after('reserved_slot');
            }
            if (!Schema::hasColumn('orders', 'import_meta')) {
                $table->json('import_meta')->nullable()->after('is_imported');
            }
            // Ensure panel_client_id has index for duplicate protection
            if (Schema::hasColumn('orders', 'panel_client_id')) {
                // Add index if not exists - check via doctrine? Simplified: try to add
                try {
                    $table->index('panel_client_id', 'orders_panel_client_id_index');
                } catch (\Exception $e) {
                    // ignore if already exists
                }
            }
            if (!Schema::hasColumn('orders', 'renews_order_id')) {
                $table->unsignedBigInteger('renews_order_id')->nullable()->after('server_id');
            }
            if (!Schema::hasColumn('orders', 'show_renewal_notification')) {
                // This column may already exist in other migration? Check User table?
                // We ensure orders has it? Actually it's user table, but keep safe.
            }
        });

        // Ensure users table has show_renewal_notification if not exists (referenced in OrderResource)
        if (!Schema::hasColumn('users', 'show_renewal_notification')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('show_renewal_notification')->default(false);
            });
        }
        if (!Schema::hasColumn('users', 'bot_state')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('bot_state')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'is_imported')) {
                $table->dropColumn('is_imported');
            }
            if (Schema::hasColumn('orders', 'import_meta')) {
                $table->dropColumn('import_meta');
            }
            // Keep renews_order_id if it existed before? Only drop if we added it and no other migration uses it
            // We will not drop renews_order_id to avoid breaking
        });
    }
};

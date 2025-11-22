<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'user_address_id')) {
                $table->foreignId('user_address_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('user_addresses')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('orders', 'shipping_name')) {
                $table->string('shipping_name')->nullable()->after('user_address_id');
            }

            if (!Schema::hasColumn('orders', 'shipping_mobile')) {
                $table->string('shipping_mobile', 50)->nullable()->after('shipping_name');
            }

            if (!Schema::hasColumn('orders', 'shipping_postal_code')) {
                $table->string('shipping_postal_code', 20)->nullable()->after('shipping_mobile');
            }

            if (!Schema::hasColumn('orders', 'shipping_address')) {
                $table->text('shipping_address')->nullable()->after('shipping_postal_code');
            }

            if (!Schema::hasColumn('orders', 'customer_note')) {
                $table->text('customer_note')->nullable()->after('shipping_address');
            }

            if (!Schema::hasColumn('orders', 'tracking_code')) {
                $table->string('tracking_code')->nullable()->after('customer_note');
            }

            if (!Schema::hasColumn('orders', 'tracking_url')) {
                $table->string('tracking_url')->nullable()->after('tracking_code');
            }

            if (!Schema::hasColumn('orders', 'finance_id')) {
                $table->unsignedBigInteger('finance_id')->nullable()->after('tracking_url');
            }

            if (!Schema::hasColumn('orders', 'refunded_at')) {
                $table->dateTime('refunded_at')->nullable()->after('paid_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'user_address_id')) {
                $table->dropConstrainedForeignId('user_address_id');
            }

            $dropColumns = [
                'shipping_name',
                'shipping_mobile',
                'shipping_postal_code',
                'shipping_address',
                'customer_note',
                'tracking_code',
                'tracking_url',
                'finance_id',
                'refunded_at',
            ];

            foreach ($dropColumns as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};


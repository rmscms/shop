<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shop_ai_proposals')) {
            Schema::create('shop_ai_proposals', function (Blueprint $table) {
                $table->bigIncrements('id');
                // product, category, brand ... (see AiResourceRegistry); no FK so any resource type fits
                $table->string('resource_type', 30);
                $table->unsignedBigInteger('resource_id');
                // pending, applied, rejected, superseded, failed
                $table->string('status', 20)->default('pending')->index();
                $table->unsignedBigInteger('access_id')->nullable();
                $table->string('actor', 190)->nullable();
                $table->string('source', 100)->nullable();
                $table->text('note')->nullable();
                $table->json('changes');
                $table->json('before')->nullable();
                $table->json('applied_fields')->nullable();
                $table->text('error')->nullable();
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('applied_at')->nullable();
                $table->timestamps();

                $table->index(['resource_type', 'resource_id', 'status']);
            });
        }

        if (Schema::hasTable('shop_ai_activities')) {
            Schema::table('shop_ai_activities', function (Blueprint $table) {
                if (! Schema::hasColumn('shop_ai_activities', 'resource_type')) {
                    $table->string('resource_type', 30)->nullable()->after('product_id');
                }
                if (! Schema::hasColumn('shop_ai_activities', 'resource_id')) {
                    $table->unsignedBigInteger('resource_id')->nullable()->after('resource_type');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_ai_proposals');
        if (Schema::hasTable('shop_ai_activities')) {
            Schema::table('shop_ai_activities', function (Blueprint $table) {
                foreach (['resource_type', 'resource_id'] as $col) {
                    if (Schema::hasColumn('shop_ai_activities', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cdr_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('batch_id', 36)->unique();      // UUID
            $table->string('name');                          // nombre descriptivo
            $table->string('phone_main', 20)->nullable();
            $table->string('filename')->nullable();
            $table->string('encoding', 20)->nullable();
            $table->integer('total_records')->default(0);
            $table->integer('records_with_location')->default(0);
            $table->integer('voice_count')->default(0);
            $table->integer('sms_count')->default(0);
            $table->integer('data_count')->default(0);
            $table->timestamp('date_from')->nullable();
            $table->timestamp('date_to')->nullable();
            $table->json('cross_analysis')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'imported_by']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cdr_batches');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');                       // "Secretaría de Seguridad X"
            $table->string('slug')->unique();             // "sspe-bcs"
            $table->string('contact_email')->nullable();
            $table->string('logo')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();         // config por tenant
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};

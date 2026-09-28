<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            // Identificación fiscal: multipaís. La etiqueta cambia según el país
            // (NIT en Bolivia, RUC en Perú, RFC en México…). Ver config/barbora.php.
            $table->string('tax_id', 32)->nullable()->unique();
            $table->string('tax_id_label', 20)->default('NIT');
            $table->char('country', 2)->default('BO');      // ISO 3166-1 alpha-2
            $table->char('currency', 3)->default('BOB');    // ISO 4217
            $table->string('timezone', 64)->default('America/La_Paz');

            $table->string('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('logo')->nullable();
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};

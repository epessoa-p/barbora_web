<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Varias fotos por servicio (los cortes de los cuadros de la pared), cada
     * una con su comentario. La primera es la portada: se copia a
     * `services.photo`, que sigue siendo lo que leen la lista y el PDF.
     *
     * La foto que ya tenía cada servicio pasa a ser la primera de su galería.
     */
    public function up(): void
    {
        if (! Schema::hasTable('service_photos')) {
            Schema::create('service_photos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('service_id')->constrained()->cascadeOnDelete();
                $table->string('path');
                $table->string('caption', 120)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['service_id', 'sort_order']);
            });
        }

        DB::table('services')
            ->whereNotNull('photo')
            ->whereNotExists(fn ($q) => $q->from('service_photos')->whereColumn('service_photos.service_id', 'services.id'))
            ->orderBy('id')
            ->each(fn ($s) => DB::table('service_photos')->insert([
                'company_id' => $s->company_id,
                'service_id' => $s->id,
                'path' => $s->photo,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('service_photos');
    }
};

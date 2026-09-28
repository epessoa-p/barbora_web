<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();

            // NULL = rol del sistema (catálogo del operador del SaaS, compartido por
            // todas las empresas y sólo editable por el superadmin).
            // Con valor = rol propio de esa empresa: sólo ella lo ve y lo edita.
            // Ver ARQUITECTURA §5.1.
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('slug');
            $table->string('description')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // El slug sólo es único dentro de su dueño: dos barberías pueden tener
            // cada una su rol "supervisor" sin pisarse.
            $table->unique(['company_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};

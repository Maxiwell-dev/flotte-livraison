<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliverers', function (Blueprint $table) {
            $table->id('id_livreur');
            $table->string('prenom');
            $table->string('nom');
            $table->string('telephone');
            $table->boolean('est_actif')->default(true);

            // Clé étrangère vers VEHICULE[cite: 3]
            $table->string('im_vehicule')->nullable();
            $table->foreign('im_vehicule')->references('im_vehicule')->on('vehicles')->nullOnDelete();

            // Clé étrangère vers ZONE[cite: 3]
            $table->string('code_zone')->nullable();
            $table->foreign('code_zone')->references('code_zone')->on('zones')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliverers');
    }
};
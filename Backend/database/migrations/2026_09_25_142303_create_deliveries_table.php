<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id('id_course');
            
            $table->foreignId('id_livreur')
                  ->nullable()
                  ->constrained('deliverers', 'id_livreur')
                  ->nullOnDelete();

            $table->string('adresse_depart');
            $table->string('adresse_arrivee');
            $table->decimal('montant', 10, 2);
            
            $table->enum('statut', ['en_attente', 'prise_en_charge', 'livree', 'annulee'])
                  ->default('en_attente');

            $table->timestamp('date_affectation')->nullable();
            $table->timestamp('date_prise_en_charge')->nullable();
            $table->timestamp('date_livraison')->nullable();
            $table->timestamp('date_annulation')->nullable();
            $table->text('motif_annulation')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
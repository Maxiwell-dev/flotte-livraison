<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_histories', function (Blueprint $table) {
            $table->id('id_historique');
            
            $table->foreignId('id_course')
                  ->constrained('deliveries', 'id_course')
                  ->cascadeOnDelete();

            $table->string('ancien_statut')->nullable();
            $table->string('nouveau_statut');
            
            $table->foreignId('id_utilisateur')
                  ->nullable()
                  ->constrained('users', 'id')
                  ->nullOnDelete();

            $table->timestamp('date_changement')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_histories');
    }
};
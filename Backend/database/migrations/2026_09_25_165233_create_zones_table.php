<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zones', function (Blueprint $table) {
            $table->string('code_zone')->primary(); // Clé primaire string
            $table->string('nom_zone');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zones');
    }
};
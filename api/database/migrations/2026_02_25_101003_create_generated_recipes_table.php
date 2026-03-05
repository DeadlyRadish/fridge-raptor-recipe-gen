<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generated_recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('ingredients');        // [{"name":"курица","quantity":500,"unit":"г"}]
            $table->json('instructions');       // ["Нарезать курицу","Обжарить..."]
            $table->string('cooking_time')->default('30 минут');
            $table->string('difficulty')->default('средне');
            $table->json('preferences')->nullable();
            $table->json('products_used')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_recipes');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('contact_books', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Имя книги (можно изменить)
            $table->string('department_ou')->unique(); // OU из distinguishedname (например, IT_Department)
            $table->string('distinguishedname_pattern')->nullable(); // Полный паттерн для определения принадлежности
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_books');
    }
};

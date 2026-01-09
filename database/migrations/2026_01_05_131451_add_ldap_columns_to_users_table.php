<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('guid')->nullable();
            $table->string('domain')->nullable();
            $table->string('username')->nullable();
            $table->string('distinguishedname')->nullable();
            $table->string('department')->nullable();
            $table->string('position')->nullable();
            $table->string('mailnickname')->unique();
            $table->string('status')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'guid',
                'domain',
                'username',
                'distinguishedname',
                'department',
                'position',
                'mailnickname',
                'status',
            ]);
        });
    }
};

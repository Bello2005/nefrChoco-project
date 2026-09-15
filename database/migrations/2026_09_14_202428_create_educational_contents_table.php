<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('educational_contents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type');
            $table->string('url_or_path');
            $table->string('ecnt_category');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('educational_contents');
    }
};

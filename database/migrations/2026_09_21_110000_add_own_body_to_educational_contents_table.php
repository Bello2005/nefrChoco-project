<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contenido propio, escrito en la plataforma, en vez de un enlace a otro sitio.
 *
 * Un enlace externo no se puede cachear: el service worker solo intercepta el
 * mismo origen, así que el material prometido "sin conexión continua" hoy se
 * pierde en cuanto el paciente se queda sin señal. Un artículo guardado como
 * texto pesa decenas de kilobytes y sí viaja con la aplicación.
 *
 * `url_or_path` pasa a ser opcional: un contenido tiene cuerpo propio o enlace,
 * y los videos siguen siendo enlace porque no hay forma honesta de precargarlos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('educational_contents', function (Blueprint $table) {
            $table->text('body')->nullable()->after('type');
            $table->boolean('available_offline')->default(false)->after('body');
            $table->string('url_or_path')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('educational_contents', function (Blueprint $table) {
            $table->dropColumn(['body', 'available_offline']);
        });
    }
};

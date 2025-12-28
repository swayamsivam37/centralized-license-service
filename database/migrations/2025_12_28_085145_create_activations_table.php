<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('license_key_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('instance_id');

            $table->timestamp('activated_at');
            $table->timestamp('deactivated_at')->nullable();

            $table->timestamps();

            $table->unique(['license_key_id', 'instance_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activations');
    }
};

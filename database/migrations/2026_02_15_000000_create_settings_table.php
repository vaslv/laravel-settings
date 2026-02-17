<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create($this->tableName(), function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('key')->unique();
            $table->string('group')->nullable()->index();
            $table->string('type');
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->tableName());
    }

    private function tableName(): string
    {
        return (string) Config::get('settings.table', 'settings');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table($this->tableName(), function (Blueprint $table): void {
            $table->dropColumn('encrypted');
        });
    }

    public function up(): void
    {
        Schema::table($this->tableName(), function (Blueprint $table): void {
            $table->boolean('encrypted')->default(false)->after('type');
        });

        // Rows written before this column existed carry no marker. Encryption used to
        // be a global switch, so every existing row is encrypted exactly when the
        // switch is currently on. Backfill from it, otherwise those rows would be
        // read as plaintext and hand ciphertext back to the application.
        if ((bool) Config::get('settings.encryption.enabled', false)) {
            DB::table($this->tableName())
                ->whereNotNull('value')
                ->where('value', '!=', '')
                ->update(['encrypted' => true]);
        }
    }

    private function tableName(): string
    {
        return (string) Config::get('settings.table', 'settings');
    }
};

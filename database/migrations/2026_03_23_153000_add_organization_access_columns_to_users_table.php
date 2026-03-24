<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username')->nullable()->unique()->after('name');
            $table->string('role', 32)->default('hospital_admin')->after('password');
            $table->foreignId('tenant_id')->nullable()->after('role')->constrained()->nullOnDelete();
            $table->foreignId('hospital_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('hospital_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('hospital_id');
            $table->dropConstrainedForeignId('tenant_id');
            $table->dropColumn(['username', 'role', 'is_active']);
        });
    }
};

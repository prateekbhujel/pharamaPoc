<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_clusters', function (Blueprint $table): void {
            $table->id();
            $table->string('area');
            $table->string('city');
            $table->string('district');
            $table->string('province');
            $table->string('postal_code');
            $table->string('email_domain');
            $table->timestamps();
        });

        Schema::create('tenants', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('billing_email');
            $table->string('contact_phone');
            $table->timestamps();
        });

        Schema::create('hospitals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_cluster_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('registration_number')->unique();
            $table->string('contact_email');
            $table->timestamps();

            $table->index(['tenant_id', 'location_cluster_id']);
        });

        Schema::create('pharmacies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_cluster_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('license_number')->unique();
            $table->string('contact_email');
            $table->timestamps();

            $table->index(['hospital_id', 'location_cluster_id']);
        });

        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('country');
            $table->unsignedSmallInteger('lead_time_days')->default(2);
            $table->timestamps();
        });

        Schema::create('manufacturers', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('country');
            $table->timestamps();
        });

        Schema::create('medicines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('manufacturer_id')->constrained()->cascadeOnDelete();
            $table->string('sku')->unique();
            $table->string('brand_name');
            $table->string('generic_name');
            $table->string('dosage_form');
            $table->string('strength');
            $table->string('pack_size');
            $table->decimal('unit_price', 10, 2);
            $table->boolean('is_cold_chain')->default(false);
            $table->timestamps();

            $table->index(['category_id', 'supplier_id']);
        });

        Schema::create('patients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('location_cluster_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('full_name');
            $table->string('gender', 16);
            $table->date('date_of_birth');
            $table->string('insurance_provider')->nullable();
            $table->string('contact_email')->nullable();
            $table->timestamps();
        });

        Schema::create('prescribers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('specialty');
            $table->string('contact_email')->nullable();
            $table->timestamps();

            $table->index('hospital_id');
        });

        Schema::create('sales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pharmacy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('prescriber_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invoice_number')->unique();
            $table->string('payment_method', 32);
            $table->string('payment_status', 24);
            $table->timestamp('sold_at');
            $table->decimal('gross_amount', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2);
            $table->timestamps();

            $table->index(['sold_at', 'pharmacy_id']);
            $table->index(['payment_status', 'sold_at']);
        });

        Schema::create('sale_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_id')->constrained()->cascadeOnDelete();
            $table->string('batch_number');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('line_total', 12, 2);
            $table->date('expires_at')->nullable();
            $table->timestamps();

            $table->index(['sale_id', 'medicine_id']);
            $table->index('medicine_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('prescribers');
        Schema::dropIfExists('patients');
        Schema::dropIfExists('medicines');
        Schema::dropIfExists('manufacturers');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('pharmacies');
        Schema::dropIfExists('hospitals');
        Schema::dropIfExists('tenants');
        Schema::dropIfExists('location_clusters');
    }
};

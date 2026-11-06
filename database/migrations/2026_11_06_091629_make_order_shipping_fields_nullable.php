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
        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipping_name')->nullable()->change();
            $table->string('shipping_address_line1')->nullable()->change();
            $table->string('shipping_city')->nullable()->change();
            $table->string('shipping_postal_code')->nullable()->change();
            $table->string('shipping_country')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipping_name')->nullable(false)->change();
            $table->string('shipping_address_line1')->nullable(false)->change();
            $table->string('shipping_city')->nullable(false)->change();
            $table->string('shipping_postal_code')->nullable(false)->change();
            $table->string('shipping_country')->nullable(false)->change();
        });
    }
};

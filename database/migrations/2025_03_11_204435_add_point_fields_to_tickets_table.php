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
        Schema::table('tickets', function (Blueprint $table) {
            $table->unsignedInteger('point')->default(0)->comment('Số điểm tích lũy khi mua vé');
            $table->unsignedInteger('point_discount')->default(0)->comment('Số điểm được sử dụng để giảm giá');
            $table->string('rank_at_booking')->comment('Hạng thành viên của user tại thời điểm đặt vé');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            //
        });
    }
};

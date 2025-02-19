<?php

use App\Models\Cinema;
use App\Models\Movie;
use App\Models\Room;
use App\Models\Showtime;
use App\Models\User;
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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained();
            $table->foreignIdFor(Cinema::class);
            $table->foreignIdFor(Room::class)->constrained();
            $table->foreignIdFor(Movie::class);
            $table->foreignIdFor(Showtime::class)->constrained();
            $table->string('voucher_code')->nullable();
            $table->unsignedInteger('voucher_discount')->nullable();
            $table->string('payment_name')->comment('phương thức thanh toán');
            $table->string('code')->unique()->comment('Mã code quét Qr hoặc mã vạch');
            $table->unsignedBigInteger('total_price')->comment('giá cuối khi đã trừ point và voucher');
            $table->string('status')->default('Chưa xuất vé');
            $table->string('staff')->nullable()->comment('lấy theo type của user');
            $table->dateTime('expiry')->comment('hạn sử dụng');
            $table->timestamps();
        });
    }
    

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movie__versions');
    }
};

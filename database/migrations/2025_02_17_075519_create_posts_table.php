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
        Schema::create('posts', function (Blueprint $table) {
            $table->id(); // Tạo khóa chính (bigIncrements)
            $table->unsignedBigInteger('user_id'); // Khóa ngoại
            $table->string('title'); // Tiêu đề bài viết
            $table->string('slug')->unique(); // Slug duy nhất
            $table->string('img_post')->nullable(); // Ảnh bài viết, có thể null
            $table->text('description'); // Mô tả ngắn
            $table->longText('content'); // Nội dung bài viết
            $table->boolean('is_active'); // Trạng thái bài viết
            $table->integer('view_count')->default(0); // Lượt xem
            $table->timestamps(); // Tạo 2 cột created_at và updated_at

            // Định nghĩa khóa ngoại
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};

Các bạn kéo code về thì làm theo các bước sau rồi mới chạy code
- composer update
- tạo file .env, copy toàn bộ nội dung trong file .env.example sang rồi thay giá trị tương ứng vào(có thể lấy trong file .env 2)
- Riêng APP_KEY chạy lệnh: php artisan key:gen
- php artisan migrate
- php artisan storage:link
- Chạy câu lệnh: "composer install" để dùng được gói Laravel UI
- npm run build 
-có các seeder dữ liệu có thể chạy các lệnh: "php artisan db:seed" 
Hoặc chạy từ bảng trong seed với cú pháp: php artisan db:seed --class=TensTableSeeder

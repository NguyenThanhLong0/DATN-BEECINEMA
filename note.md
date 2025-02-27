Các bạn kéo code về thì làm theo các bước sau rồi mới chạy code
- Chạy câu lệnh: "composer install" để dùng được gói Laravel UI
-cài thư viện tự động sinh slug với cú pháp: composer require cviebrock/eloquent-sluggable
- tạo file .env, copy toàn bộ nội dung trong file .env.example sang rồi thay giá trị tương ứng vào(có thể lấy trong file .env 2)
- Riêng APP_KEY chạy lệnh: php artisan key:gen
- php artisan migrate
- php artisan storage:link
- npm run build 
-có các seeder dữ liệu có thể chạy các lệnh: "php artisan db:seed" 
Hoặc chạy từ bảng trong seed với cú pháp: php artisan db:seed --class=TensTableSeeder

chạy lệnh: php artisan make:queue để tạo bảng job
chạy lệnh php artisan queue:work để chạy 
Nếu không chạy lệnh trên thì dữ liệu sẽ đẩy lên bảng job

sửa cái này trong env thành cái này :
 QUEUE_CONNECTION=database
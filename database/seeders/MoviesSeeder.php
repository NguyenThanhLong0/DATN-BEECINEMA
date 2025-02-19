<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Movie;
use Illuminate\Support\Str;

use Illuminate\Support\Facades\DB;

class MoviesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Movie::create([
        //     'name' => 'Avengers: Endgame',
        //     'slug' => 'avengers-endgame',
        //     'category' => 'Action, Adventure',
        //     'img_thumbnail' => 'avengers-endgame-thumbnail.jpg',
        //     'description' => 'The epic conclusion to the Marvel Cinematic Universe.',
        //     'director' => 'Anthony Russo, Joe Russo',
        //     'cast' => 'Robert Downey Jr., Chris Evans, Mark Ruffalo',
        //     'rating' => 'T13',
        //     'duration' => 180,
        //     'release_date' => '2019-04-26',
        //     'end_date' => '2022-04-26',
        //     'trailer_url' => 'https://www.youtube.com/watch?v=TcMBFSGVi1c',
        //     'surcharge' => 20,
        //     'surcharge_desc' => '3D surcharge',
        //     'is_active' => true,
        //     'is_hot' => true,
        //     'is_special' => false,
        //     'is_publish' => true
        // ]);
    //20 bản ghi movie và 40 bản ghi movie_version
    $url_youtubes = [
        'VmJ4oB3Xguo',
        'XuX2HKeMkVw',
        'SGg9DxLFCtc',
        'm6MF1MqsDhc',
        'dNwuFYhwTAk',
        '4oxoPMxBO6s',
        'b1Yqng0uSWM',
        'IK-eb2AbKQ',
        'Tx5JuN-5n8U',
        'kMjlJkmt5nk',
        'gTo9JwsmjT4',
        '4rgYUipGJNo'
    ];
    $booleans = [
        true,
        false,
        false,
        false,
        false,
        false,
        false,
        false,
    ];

    $ratings = ['P',  'T13', 'T16', 'T18', 'K'];
    $categories = [
        "Hành động, kịch tính",
        "Phiêu lưu, khám phá",
        "Kinh dị",
        "Khoa học viễn tưởng",
        "Tình cảm",
        "Hài hước",
        "Kịch, Hồi Hộp",
        "Hoạt hình",
        "Tâm lý",
        "Âm nhạc, phiêu lưu",
    ];
    $movieNames =  [
        "Moana 2: Hành Trình Của Moana",
        "Thợ Săn Thủ Lĩnh",
        "Nhím Sonic III",
        "Spring Garden: Ai Oán Trong Vườn Xuân",
        "Tee Yod: Quỷ Ăn Tạng II",
        "Vùng Đất Bị Nguyền Rủa",
        "Gladiator: Võ Sĩ Giác Đấu II",
        "Elli và Bí Ẩn Chiếc Tàu Ma",
        "Sắc Màu Của Hạnh Phúc",
        "OZI: Phi Vụ Rừng Xanh",
        "Tee Yod: Quỷ Ăn Tạng",
        "Venom: Kèo Cuối",
        "Ngày Xưa Có Một Chuyện Tình",
        "Cười Xuyên Biên Giới",
        "Thiên Đường Quả Báo",
        "Cu Li Không Bao Giờ Khóc",
        "RED ONE: Mật mã đỏ",
        "Vây Hãm Tại Đài Bắc",
        'Học Viện Anh Hùng',
        "Linh Miêu",
        'Công Tử Bạc Liêu',
        "CAPTAIN AMERICA: BRAVE NEW WORLD",
        "Địa Đạo: Mặt Trời Trong Bóng Tối",
        "Thám Tử Kiên: Kỳ Án Không Đầu",
        'Mufasa: Vua Sư Tử'
    ];
        for ($i = 0; $i < 25; $i++) {
            $releaseDate = fake()->dateTimeBetween(now()->subMonths(5), now()->addMonths(2));
            $endDate = fake()->dateTimeBetween($releaseDate, now()->addMonths(5));
            $rating = $ratings[array_rand($ratings)];
            $x = ($i % 21) + 1;

            $img = "images/movies/" . $x . ".png";
            $movie = DB::table('movies')->insertGetId([
                'name' => $movieNames[$i],
                'slug' => Str::slug($movieNames[$i]),
                'category' =>  $categories[array_rand($categories)],
                'img_thumbnail' => asset($img),
                'description' => Str::limit(fake()->paragraph, 250),
                'director' => fake()->name,
                'cast' => fake()->name(),
                'rating' => $rating,
                'duration' => fake()->numberBetween(60, 180),
                'release_date' => $releaseDate,
                'end_date' => $endDate,
                'trailer_url' => $url_youtubes[array_rand($url_youtubes)],
                'is_active' => true,
                'is_hot' => $booleans[rand(0, 7)],
                'is_special' => $booleans[rand(0, 7)],
                'is_publish' => true,
                'surcharge' => [10000, 20000][array_rand([10000, 20000])],

            ]);
            
        }

    }
}

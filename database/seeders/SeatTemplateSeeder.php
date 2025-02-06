<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\SeatTemplate;


class SeatTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'matrix_id' => 1,
                'name' => 'Template Standard',
                'seat_structure' => json_decode('[{"coordinates_x":"2","coordinates_y":"A","type_seat_id":"1"},{"coordinates_x":"3","coordinates_y":"A","type_seat_id":"1"},{"coordinates_x":"4","coordinates_y":"A","type_seat_id":"1"},{"coordinates_x":"5","coordinates_y":"A","type_seat_id":"1"},{"coordinates_x":"6","coordinates_y":"A","type_seat_id":"1"},{"coordinates_x":"7","coordinates_y":"A","type_seat_id":"1"},{"coordinates_x":"8","coordinates_y":"A","type_seat_id":"1"},{"coordinates_x":"9","coordinates_y":"A","type_seat_id":"1"},{"coordinates_x":"10","coordinates_y":"A","type_seat_id":"1"},{"coordinates_x":"11","coordinates_y":"A","type_seat_id":"1"},{"coordinates_x":"1","coordinates_y":"B","type_seat_id":"1"},{"coordinates_x":"2","coordinates_y":"B","type_seat_id":"1"},{"coordinates_x":"3","coordinates_y":"B","type_seat_id":"1"},{"coordinates_x":"4","coordinates_y":"B","type_seat_id":"1"},{"coordinates_x":"5","coordinates_y":"B","type_seat_id":"1"},{"coordinates_x":"6","coordinates_y":"B","type_seat_id":"1"},{"coordinates_x":"7","coordinates_y":"B","type_seat_id":"1"},{"coordinates_x":"8","coordinates_y":"B","type_seat_id":"1"},{"coordinates_x":"9","coordinates_y":"B","type_seat_id":"1"},{"coordinates_x":"10","coordinates_y":"B","type_seat_id":"1"},{"coordinates_x":"11","coordinates_y":"B","type_seat_id":"1"},{"coordinates_x":"12","coordinates_y":"B","type_seat_id":"1"},{"coordinates_x":"1","coordinates_y":"C","type_seat_id":"1"},{"coordinates_x":"2","coordinates_y":"C","type_seat_id":"1"},{"coordinates_x":"3","coordinates_y":"C","type_seat_id":"1"},{"coordinates_x":"4","coordinates_y":"C","type_seat_id":"1"},{"coordinates_x":"5","coordinates_y":"C","type_seat_id":"1"},{"coordinates_x":"6","coordinates_y":"C","type_seat_id":"1"},{"coordinates_x":"7","coordinates_y":"C","type_seat_id":"1"},{"coordinates_x":"8","coordinates_y":"C","type_seat_id":"1"},{"coordinates_x":"9","coordinates_y":"C","type_seat_id":"1"},{"coordinates_x":"10","coordinates_y":"C","type_seat_id":"1"},{"coordinates_x":"11","coordinates_y":"C","type_seat_id":"1"},{"coordinates_x":"12","coordinates_y":"C","type_seat_id":"1"},{"coordinates_x":"1","coordinates_y":"E","type_seat_id":"2"},{"coordinates_x":"2","coordinates_y":"E","type_seat_id":"2"},{"coordinates_x":"3","coordinates_y":"E","type_seat_id":"2"},{"coordinates_x":"4","coordinates_y":"E","type_seat_id":"2"},{"coordinates_x":"5","coordinates_y":"E","type_seat_id":"2"},{"coordinates_x":"6","coordinates_y":"E","type_seat_id":"2"},{"coordinates_x":"7","coordinates_y":"E","type_seat_id":"2"},{"coordinates_x":"8","coordinates_y":"E","type_seat_id":"2"},{"coordinates_x":"9","coordinates_y":"E","type_seat_id":"2"},{"coordinates_x":"10","coordinates_y":"E","type_seat_id":"2"},{"coordinates_x":"11","coordinates_y":"E","type_seat_id":"2"},{"coordinates_x":"12","coordinates_y":"E","type_seat_id":"2"},{"coordinates_x":"1","coordinates_y":"F","type_seat_id":"2"},{"coordinates_x":"2","coordinates_y":"F","type_seat_id":"2"},{"coordinates_x":"3","coordinates_y":"F","type_seat_id":"2"},{"coordinates_x":"4","coordinates_y":"F","type_seat_id":"2"},{"coordinates_x":"5","coordinates_y":"F","type_seat_id":"2"},{"coordinates_x":"6","coordinates_y":"F","type_seat_id":"2"},{"coordinates_x":"7","coordinates_y":"F","type_seat_id":"2"},{"coordinates_x":"8","coordinates_y":"F","type_seat_id":"2"},{"coordinates_x":"9","coordinates_y":"F","type_seat_id":"2"},{"coordinates_x":"10","coordinates_y":"F","type_seat_id":"2"},{"coordinates_x":"11","coordinates_y":"F","type_seat_id":"2"},{"coordinates_x":"12","coordinates_y":"F","type_seat_id":"2"}]'),
                'description' => 'Mẫu sơ đồ ghế tiêu chuẩn.',
                'row_regular' => 4,
                'row_vip' => null,
                'row_double' => null,
                'is_active' => true,
                'is_publish' => true,
            ],
            [
                'matrix_id' => 2,
                'name' => 'Template Large',
                'seat_structure' => json_decode('[{"coordinates_x":"2","coordinates_y":"A","type_seat_id":"1"},{"coordinates_x":"3","coordinates_y":"A","type_seat_id":"1"},{"coordinates_x":"4","coordinates_y":"A","type_seat_id":"1"},{"coordinates_x":"5","coordinates_y":"A","type_seat_id":"1"},{"coordinates_x":"6","coordinates_y":"A","type_seat_id":"1"},{"coordinates_x":"7","coordinates_y":"A","type_seat_id":"1"},{"coordinates_x":"8","coordinates_y":"A","type_seat_id":"1"},{"coordinates_x":"9","coordinates_y":"A","type_seat_id":"1"},{"coordinates_x":"10","coordinates_y":"A","type_seat_id":"1"},{"coordinates_x":"11","coordinates_y":"A","type_seat_id":"1"},{"coordinates_x":"12","coordinates_y":"A","type_seat_id":"1"},{"coordinates_x":"13","coordinates_y":"A","type_seat_id":"1"},{"coordinates_x":"1","coordinates_y":"B","type_seat_id":"1"},{"coordinates_x":"2","coordinates_y":"B","type_seat_id":"1"},{"coordinates_x":"3","coordinates_y":"B","type_seat_id":"1"},{"coordinates_x":"4","coordinates_y":"B","type_seat_id":"1"},{"coordinates_x":"5","coordinates_y":"B","type_seat_id":"1"},{"coordinates_x":"6","coordinates_y":"B","type_seat_id":"1"},{"coordinates_x":"7","coordinates_y":"B","type_seat_id":"1"},{"coordinates_x":"8","coordinates_y":"B","type_seat_id":"1"},{"coordinates_x":"9","coordinates_y":"B","type_seat_id":"1"},{"coordinates_x":"10","coordinates_y":"B","type_seat_id":"1"},{"coordinates_x":"11","coordinates_y":"B","type_seat_id":"1"},{"coordinates_x":"12","coordinates_y":"B","type_seat_id":"1"},{"coordinates_x":"13","coordinates_y":"B","type_seat_id":"1"}]'),
                'description' => 'Mẫu sơ đồ ghế lớn.',
                'row_regular' => 6,
                'row_vip' => 4,
                'row_double' => 2,
                'is_active' => true,
                'is_publish' => true,
            ],
        ];

        foreach ($templates as $template) {
            SeatTemplate::create($template);
        }
    }
}






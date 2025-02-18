<?php

namespace App\Http\Requests\Api;

use App\Models\Movie;
use App\Models\MovieVersion;
use App\Models\Room;
use App\Models\Showtime;
use App\Models\TypeRoom;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

// class StoreShowtimeRequest extends FormRequest
// {
//     /**
//      * Determine if the user is authorized to make this request.
//      */
//     public function authorize(): bool
//     {
//         return true;
//     }

//     /**
//      * Get the validation rules that apply to the request.
//      *
//      * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
//      */
//     public function rules(): array
//     {
//         if ($this->input('auto_generate_showtimes') === 'on') {
//             return [
//                 'room_id' => ['required', 'exists:rooms,id'], // Kiểm tra phòng tồn tại
//                 'movie_id' => ['required', 'exists:movies,id'], // Kiểm tra phim tồn tại
//                 'movie_version_id' => ['required', 'exists:movie_versions,id'], // Kiểm tra phiên bản phim tồn tại
//                 'date' => ['required', 'date', 'after_or_equal:today'], // Ngày chiếu phải từ hôm nay trở đi
//                 'start_hour' => ['required', 'date_format:H:i'], // Kiểm tra định dạng giờ mở cửa
//                 'end_hour' => ['required', 'date_format:H:i', 'after:start_hour'], // Giờ đóng cửa phải sau giờ mở cửa
//             ];
//         } else {
//             return [
//                 'room_id' => [
//                     'required',
//                     'exists:rooms,id',
//                     function ($attribute, $value, $fail) {
//                         // Kiểm tra nếu movie_id hợp lệ
//                         $movieId = $this->input('movie_id');
//                         if (!$movieId || !Movie::where('id', $movieId)->exists()) {
//                             $fail("Phim không tồn tại.");
//                             return;
//                         }

//                         $movie = Movie::find($movieId);
//                         if (!$movie) {
//                             $fail("Không tìm thấy phim.");
//                             return;
//                         }

//                         // Kiểm tra nếu start_time hợp lệ
//                         $startTimeInput = $this->input('start_time');
//                         if (!$startTimeInput) {
//                             $fail("Vui lòng nhập giờ chiếu.");
//                             return;
//                         }

//                         $startTime = Carbon::parse($this->date . ' ' . $startTimeInput);
//                         $endTime = $startTime->copy()->addMinutes($movie->duration + 15);

//                         // Kiểm tra nếu room_id và date hợp lệ trước khi query
//                         if (!$this->input('room_id') || !$this->input('date')) {
//                             $fail("Vui lòng nhập phòng và ngày chiếu hợp lệ.");
//                             return;
//                         }

//                         $existingShowtimes = Showtime::where('room_id', $this->room_id)
//                             ->where('date', $this->date)
//                             ->get();

//                         foreach ($existingShowtimes as $showtime) {
//                             $existingStartTime = Carbon::parse($showtime->start_time);
//                             $existingEndTime = Carbon::parse($showtime->end_time);

//                             // Kiểm tra nếu thời gian suất chiếu mới trùng với suất chiếu cũ
//                             if ($startTime->lt($existingEndTime) && $endTime->gt($existingStartTime)) {
//                                 $fail("Suất chiếu bị trùng với suất chiếu khác từ {$existingStartTime->format('H:i')} - {$existingEndTime->format('H:i')}.");
//                                 return;
//                             }
//                         }
//                     },
//                 ],
//                 'movie_id' => 'required|exists:movies,id', // Kiểm tra phim tồn tại
//                 'movie_version_id' => 'required|exists:movie_versions,id', // Kiểm tra phiên bản phim tồn tại
//                 'date' => 'required|date|after_or_equal:today', // Ngày chiếu phải từ hôm nay trở đi
//                 'start_time' => [
//                     'required',
//                     function ($attribute, $value, $fail) {
//                         $movieId = $this->input('movie_id');
//                         if (!$movieId || !Movie::where('id', $movieId)->exists()) {
//                             $fail("Phim không tồn tại.");
//                             return;
//                         }

//                         $movie = Movie::find($movieId);
//                         if (!$movie) {
//                             $fail("Không tìm thấy phim.");
//                             return;
//                         }

//                         $startTime = Carbon::parse($this->date . ' ' . $value);
//                         $endTime = $startTime->copy()->addMinutes($movie->duration + 15);

//                         // Kiểm tra giờ chiếu không được là quá khứ
//                         if ($startTime->lt(Carbon::now()->addHour())) {
//                             $fail("Giờ chiếu phải từ hiện tại cộng thêm 1 tiếng trở đi.");
//                             return;
//                         }

//                         // Kiểm tra trùng lặp trong database
//                         $existingShowtimes = Showtime::where('room_id', $this->room_id)
//                             ->where('date', $this->date)
//                             ->get();

//                         foreach ($existingShowtimes as $showtime) {
//                             $existingStartTime = Carbon::parse($showtime->start_time);
//                             $existingEndTime = Carbon::parse($showtime->end_time);

//                             if ($startTime->lt($existingEndTime) && $endTime->gt($existingStartTime)) {
//                                 $fail("Suất chiếu bạn chọn trùng với một suất chiếu đã tồn tại từ {$existingStartTime->format('H:i')} - {$existingEndTime->format('H:i')}.");
//                                 return;
//                             }
//                         }
//                     },
//                 ],
//             ];
//         }
//     }

//     /**
//      * Custom messages for validation errors.
//      */
//     public function messages()
//     {
//         return [
//             'room_id.required' => 'Vui lòng chọn phòng.',
//             'room_id.exists' => 'Phòng đã chọn không tồn tại.',
//             'movie_id.required' => 'Vui lòng chọn phim.',
//             'movie_id.exists' => 'Phim đã chọn không tồn tại.',
//             'movie_version_id.required' => 'Vui lòng chọn phiên bản phim.',
//             'movie_version_id.exists' => 'Phiên bản phim đã chọn không tồn tại.',
//             'date.required' => 'Vui lòng chọn ngày chiếu.',
//             'date.date' => 'Ngày chiếu không hợp lệ.',
//             'date.after_or_equal' => 'Ngày chiếu phải từ hôm nay trở đi.',
//             'start_hour.required' => 'Vui lòng nhập giờ mở cửa.',
//             'start_hour.date_format' => 'Giờ mở cửa không đúng định dạng (HH:MM).',
//             'end_hour.required' => 'Vui lòng nhập giờ đóng cửa.',
//             'end_hour.date_format' => 'Giờ đóng cửa không đúng định dạng (HH:MM).',
//             'end_hour.after' => 'Giờ đóng cửa phải sau giờ mở cửa.',
//             'start_time.required' => 'Vui lòng nhập giờ chiếu.',
//             'start_time.date_format' => 'Giờ chiếu không hợp lệ (định dạng phải là HH:MM).',
//         ];
//     }

//     /**
//      * Handle a failed validation attempt.
//      */
//     public function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
//     {
//         throw new \Illuminate\Validation\ValidationException($validator, response()->json([
//             'message' => 'Validation failed',
//             'errors' => $validator->errors(),
//         ], 422));
//     }
// }


class StoreShowtimeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if ($this->input('auto_generate_showtimes') === 'on') {
            return [
                'room_id' => ['required', 'exists:rooms,id'],
                'movie_id' => ['required', 'exists:movies,id'],
                'movie_version_id' => ['required', 'exists:movie_versions,id'],
                'date' => ['required', 'date', 'after_or_equal:today'],
                'start_hour' => ['required', 'date_format:H:i'],
                'end_hour' => ['required', 'date_format:H:i', 'after:start_hour'],
            ];
        } else {
            return [
                'room_id' => [
                    'required',
                    'exists:rooms,id',
                    function ($attribute, $value, $fail) {
                        // Kiểm tra movie_id
                        $movie = Movie::find($this->input('movie_id'));
                        if (!$movie) {
                            $fail("Phim không tồn tại.");
                            return;
                        }

                        // Kiểm tra room_id
                        $room = Room::find($this->input('room_id'));
                        if (!$room) {
                            $fail("Phòng không tồn tại.");
                            return;
                        }

                        // Kiểm tra movie_version_id
                        $movieVersion = MovieVersion::find($this->input('movie_version_id'));
                        if (!$movieVersion) {
                            $fail("Phiên bản phim không tồn tại.");
                            return;
                        }

                        // Kiểm tra type_room_id từ room
                        $typeRoom = TypeRoom::find($room->type_room_id);
                        if (!$typeRoom) {
                            $fail("Loại phòng không tồn tại.");
                            return;
                        }

                        // Tạo thời gian suất chiếu
                        $startTime = Carbon::parse($this->date . ' ' . $this->input('start_time'));
                        $endTime = $startTime->copy()->addMinutes($movie->duration + 15);

                        // Kiểm tra suất chiếu có trùng không
                        $existingShowtimes = Showtime::where('room_id', $this->room_id)
                            ->where('date', $this->date)
                            ->get();

                        foreach ($existingShowtimes as $showtime) {
                            $existingStartTime = Carbon::parse($showtime->start_time);
                            $existingEndTime = Carbon::parse($showtime->end_time);

                            if ($startTime->lt($existingEndTime) && $endTime->gt($existingStartTime)) {
                                $fail("Suất chiếu bạn chọn trùng với suất chiếu khác từ {$existingStartTime->format('H:i')} - {$existingEndTime->format('H:i')}.");
                                return;
                            }
                        }
                    },
                ],
                'movie_id' => 'required|exists:movies,id',
                'movie_version_id' => 'required|exists:movie_versions,id',
                'date' => 'required|date|after_or_equal:today',
                'start_time' => 'required|date_format:H:i',
            ];
        }
    }


    /**
     * Custom messages for validation errors.
     */
    public function messages()
    {
        return [
            'room_id.required' => 'Vui lòng chọn phòng.',
            'room_id.exists' => 'Phòng đã chọn không tồn tại.',
            'movie_id.required' => 'Vui lòng chọn phim.',
            'movie_id.exists' => 'Phim đã chọn không tồn tại.',
            'movie_version_id.required' => 'Vui lòng chọn phiên bản phim.',
            'movie_version_id.exists' => 'Phiên bản phim đã chọn không tồn tại.',
            'date.required' => 'Vui lòng chọn ngày chiếu.',
            'date.date' => 'Ngày chiếu không hợp lệ.',
            'date.after_or_equal' => 'Ngày chiếu phải từ hôm nay trở đi.',
            'start_hour.required' => 'Vui lòng nhập giờ mở cửa.',
            'start_hour.date_format' => 'Giờ mở cửa không đúng định dạng (HH:MM).',
            'end_hour.required' => 'Vui lòng nhập giờ đóng cửa.',
            'end_hour.date_format' => 'Giờ đóng cửa không đúng định dạng (HH:MM).',
            'end_hour.after' => 'Giờ đóng cửa phải sau giờ mở cửa.',
            'start_time.required' => 'Vui lòng nhập giờ chiếu.',
            'start_time.date_format' => 'Giờ chiếu không hợp lệ (định dạng phải là HH:MM).',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    public function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        throw new \Illuminate\Validation\ValidationException($validator, response()->json([
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
        ], 422));
    }
}

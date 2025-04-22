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


class StoreShowtimeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'movie_id' => 'required|exists:movies,id',
            'movie_version_id' => 'required|exists:movie_versions,id',
            'showtimes' => 'required|array',
            'showtimes.*.date' => 'required|date_format:Y-m-d',
            'showtimes.*.room_id' => 'required|exists:rooms,id',
            'showtimes.*.cinema_id' => 'required|exists:cinemas,id',
            'showtimes.*.showtimes' => 'required|array',
            'showtimes.*.showtimes.*.start_time' => 'required|date_format:H:i',
            'showtimes.*.showtimes.*.end_time' => 'required|date_format:H:i|after:showtimes.*.showtimes.*.start_time',
            'showtimes.*.showtimes.*.type' => [
                'required',
                Rule::in(['generated']), // Chỉ chấp nhận type = 'generated'
            ],
            // 'showtimes.*.showtimes.*.overlapping' => 'required|boolean',
        ];
    }

    public function messages()
    {
        return [
            'movie_id.required' => 'Vui lòng cung cấp ID phim.',
            'movie_id.exists' => 'Phim không tồn tại.',
            'movie_version_id.required' => 'Vui lòng cung cấp ID phiên bản phim.',
            'movie_version_id.exists' => 'Phiên bản phim không tồn tại.',
            'showtimes.required' => 'Danh sách suất chiếu không được để trống.',
            'showtimes.*.date.required' => 'Ngày chiếu là bắt buộc.',
            'showtimes.*.date.date_format' => 'Ngày chiếu phải có định dạng Y-m-d.',
            'showtimes.*.room_id.required' => 'ID phòng chiếu là bắt buộc.',
            'showtimes.*.room_id.exists' => 'Phòng chiếu không tồn tại.',
            'showtimes.*.cinema_id.required' => 'ID rạp chiếu là bắt buộc.',
            'showtimes.*.cinema_id.exists' => 'Rạp chiếu không tồn tại.',
            'showtimes.*.showtimes.required' => 'Danh sách suất chiếu trong ngày không được để trống.',
            'showtimes.*.showtimes.*.start_time.required' => 'Giờ bắt đầu là bắt buộc.',
            'showtimes.*.showtimes.*.start_time.date_format' => 'Giờ bắt đầu phải có định dạng H:i.',
            'showtimes.*.showtimes.*.end_time.required' => 'Giờ kết thúc là bắt buộc.',
            'showtimes.*.showtimes.*.end_time.date_format' => 'Giờ kết thúc phải có định dạng H:i.',
            'showtimes.*.showtimes.*.end_time.after' => 'Giờ kết thúc phải sau giờ bắt đầu.',
            'showtimes.*.showtimes.*.type.required' => 'Loại suất chiếu là bắt buộc.',
            'showtimes.*.showtimes.*.type.in' => 'Loại suất chiếu phải là "generated".',
            // 'showtimes.*.showtimes.*.overlapping.required' => 'Trạng thái overlapping là bắt buộc.',
            // 'showtimes.*.showtimes.*.overlapping.boolean' => 'Trạng thái overlapping phải là true hoặc false.',
        ];
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */


    // public function rules(): array
    // {
    //     if (filter_var($this->input('auto_generate_showtimes'), FILTER_VALIDATE_BOOLEAN)) {
    //         // Kiểm tra điều kiện giờ bắt đầu và giờ kết thúc
    //         return [
    //             'cinema_ids' => ['required', 'array', 'min:1'],
    //             'cinema_ids.*' => ['required', 'exists:cinemas,id'],
    //             'rooms' => ['required', 'array', 'min:1'],
    //             'rooms.*.room_id' => ['required', 'exists:rooms,id'],
    //             'movie_id' => ['required', 'exists:movies,id'],
    //             'movie_version_id' => ['required', 'exists:movie_versions,id'],
    //             'start_date' => ['required', 'date', 'after_or_equal:today'],
    //             'end_date' => ['required', 'date', 'after_or_equal:start_date'],
    //             'start_hour' => ['required', 'date_format:H:i'],
    //             'end_hour' => [
    //                 'required',
    //                 'date_format:H:i',
    //                 'after:start_hour',
    //                 // Thêm rule kiểm tra thời gian cách nhau ít nhất 12 tiếng
    //                 function ($attribute, $value, $fail) {
    //                     $startTime = Carbon::parse($this->input('start_date') . ' ' . $this->input('start_hour'));
    //                     $endTime = Carbon::parse($this->input('end_date') . ' ' . $value);

    //                     if ($endTime->diffInHours($startTime) < 12) {
    //                         $fail("Giờ kết thúc phải cách giờ bắt đầu ít nhất 12 tiếng.");
    //                     }
    //                 }
    //             ],
    //         ];
    //     } else {
    //         return [
    //             'cinema_ids' => [
    //                 'required',
    //                 'array',
    //                 'min:1',
    //                 function ($attribute, $value, $fail) {
    //                     if (empty($value)) {
    //                         $fail("Vui lòng chọn ít nhất một cinema.");
    //                         return;
    //                     }

    //                     $movie = Movie::find($this->input('movie_id'));
    //                     if (!$movie) {
    //                         $fail("Phim không tồn tại.");
    //                         return;
    //                     }

    //                     $rooms = $this->input('rooms');
    //                     $errors = []; // Mảng lỗi sẽ chứa các lỗi trùng suất chiếu

    //                     foreach ($rooms as $roomData) {
    //                         $room = Room::find($roomData['room_id']);
    //                         if (!$room) {
    //                             $fail("Phòng không tồn tại.");
    //                             return;
    //                         }

    //                         // Kiểm tra phòng có thuộc một trong các cinema_ids đã chọn không
    //                         $cinemaIds = $this->input('cinema_ids');
    //                         if (!in_array($room->cinema_id, $cinemaIds)) {
    //                             $fail("Phòng không thuộc rạp đã chọn.");
    //                             return;
    //                         }
    //                     }

    //                     $movieVersion = MovieVersion::find($this->input('movie_version_id'));
    //                     if (!$movieVersion) {
    //                         $fail("Phiên bản phim không tồn tại.");
    //                         return;
    //                     }

    //                     $showtimes = $this->input('showtimes');
    //                     foreach ($showtimes as $showtimeData) {
    //                         $startTime = Carbon::parse($this->start_date . ' ' . $showtimeData['start_time']);
    //                         $endTime = $startTime->copy()->addMinutes($movie->duration + 15);

    //                         foreach ($rooms as $roomData) {
    //                             $existingShowtimes = Showtime::where('room_id', $roomData['room_id'])
    //                                 ->where('date', $this->start_date)
    //                                 ->get();

    //                             foreach ($existingShowtimes as $showtime) {
    //                                 $existingStartTime = Carbon::parse($showtime->start_time);
    //                                 $existingEndTime = Carbon::parse($showtime->end_time);

    //                                 if ($startTime->lt($existingEndTime) && $endTime->gt($existingStartTime)) {
    //                                     $errors[] = "Suất chiếu bạn chọn trùng với suất chiếu khác tại rạp {$showtime->cinema_id}, phòng {$roomData['room_id']} từ {$existingStartTime->format('H:i')} - {$existingEndTime->format('H:i')}";
    //                                 }
    //                             }
    //                         }
    //                     }

    //                     if (!empty($errors)) {
    //                         $fail(implode(', ', $errors)); // Trả về tất cả các lỗi trùng suất chiếu
    //                     }
    //                 },
    //             ],
    //             'movie_id' => 'required|exists:movies,id',
    //             'movie_version_id' => 'required|exists:movie_versions,id',
    //             'start_date' => ['required', 'date', 'after_or_equal:today'],
    //             'end_date' => ['required', 'date', 'after_or_equal:start_date'],
    //             'start_time' => 'nullable|date_format:H:i',
    //             'showtimes' => ['required', 'array', 'min:1'],
    //             'showtimes.*.start_time' => ['required', 'date_format:H:i'],
    //         ];
    //     }
    // }


    // public function messages()
    // {
    //     return [
    //         'cinema_ids.required' => 'Vui lòng chọn ít nhất một rạp.',
    //         'cinema_ids.array' => 'Cinema IDs phải là mảng.',
    //         'cinema_ids.*.required' => 'Mỗi cinema phải được chọn.',
    //         'cinema_ids.*.exists' => 'Cinema không tồn tại.',
    //         'rooms.required' => 'Vui lòng chọn ít nhất một phòng.',
    //         'rooms.*.room_id.required' => 'Vui lòng chọn phòng.',
    //         'rooms.*.room_id.exists' => 'Phòng không tồn tại.',
    //         'movie_id.required' => 'Vui lòng chọn phim.',
    //         'movie_id.exists' => 'Phim không tồn tại.',
    //         'movie_version_id.required' => 'Vui lòng chọn phiên bản phim.',
    //         'movie_version_id.exists' => 'Phiên bản phim không tồn tại.',
    //         'start_date.required' => 'Vui lòng nhập ngày bắt đầu.',
    //         'start_date.date' => 'Ngày bắt đầu không hợp lệ.',
    //         'start_date.after_or_equal' => 'Ngày bắt đầu phải từ hôm nay trở đi.',
    //         'end_date.required' => 'Vui lòng nhập ngày kết thúc.',
    //         'end_date.date' => 'Ngày kết thúc không hợp lệ.',
    //         'end_date.after_or_equal' => 'Ngày kết thúc phải lớn hơn hoặc bằng ngày bắt đầu.',
    //         'start_hour.required' => 'Vui lòng nhập giờ bắt đầu.',
    //         'start_hour.date_format' => 'Giờ bắt đầu không đúng định dạng (HH:MM).',
    //         'end_hour.required' => 'Vui lòng nhập giờ kết thúc.',
    //         'end_hour.date_format' => 'Giờ kết thúc không đúng định dạng (HH:MM).',
    //         'end_hour.after' => 'Giờ kết thúc phải sau giờ bắt đầu.',
    //         'showtimes.required' => 'Vui lòng nhập danh sách suất chiếu.',
    //         'showtimes.*.start_time.required' => 'Vui lòng nhập giờ bắt đầu suất chiếu.',
    //         'showtimes.*.start_time.date_format' => 'Giờ bắt đầu suất chiếu không hợp lệ (định dạng phải là HH:MM).',
    //     ];
    // }



    public function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        throw new \Illuminate\Validation\ValidationException($validator, response()->json([
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
        ], 422));
    }
}

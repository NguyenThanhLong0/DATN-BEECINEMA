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

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if (filter_var($this->input('auto_generate_showtimes'), FILTER_VALIDATE_BOOLEAN)) {
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
                'start_time' => 'nullable|date_format:H:i',
                'showtimes' => ['required', 'array', 'min:1'], // Xác nhận showtimes là mảng
                'showtimes.*.start_time' => ['required', 'date_format:H:i'],
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
            // Danh sách suất chiếu
            'showtimes.required' => 'Vui lòng nhập danh sách suất chiếu.',
            'showtimes.array' => 'Danh sách suất chiếu phải là một mảng hợp lệ.',
            'showtimes.min' => 'Phải có ít nhất một suất chiếu.',
            // Giờ suất chiếu trong danh sách
            'showtimes.*.start_time.required' => 'Vui lòng nhập giờ bắt đầu suất chiếu.',
            'showtimes.*.start_time.date_format' => 'Giờ bắt đầu suất chiếu không hợp lệ (định dạng phải là HH:MM).',
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

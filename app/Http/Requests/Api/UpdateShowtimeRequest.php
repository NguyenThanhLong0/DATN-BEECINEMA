<?php

namespace App\Http\Requests\Api;

use App\Models\Movie;
use App\Models\Showtime;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class UpdateShowtimeRequest extends FormRequest
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
        $id = $this->route('showtime')->id;
        return [

            'room_id' => [
                'required',
            ],
            'movie_id' => 'required',

            'movie_version_id' => 'required|exists:movie_versions,id',
            'date' => 'required|date|after_or_equal:today',   //ngăn chặn chọn ngày trog quá khứ

            'start_time' => [
                'required',
                function ($attribute, $value, $fail) {
                    // $startTime = Carbon::parse($this->date . ' ' . $value);
                    $movie = Movie::findOrFail($this->input('movie_id'));
                    $startTime = Carbon::parse($this->date . ' ' . $value);
                    $endTime = $startTime->copy()->addMinutes($movie->duration + 15);

                    // Kiểm tra giờ chiếu trong tương lai
                    if ($startTime->isPast()) {
                        $fail("Giờ chiếu phải nằm trong tương lai.");
                    }

                    // Lấy các suất chiếu hiện tại trong phòng và ngày
                    $existingShowtimes = Showtime::where('room_id', $this->room_id)
                        ->where('date', $this->date)
                        ->where('id', '!=', $this->route('showtime')->id)
                        ->get();

                    // Kiểm tra trùng với các suất chiếu hiện có
                    foreach ($existingShowtimes as $showtime) {
                        $existingStartTime = Carbon::parse($showtime->start_time);
                        $existingEndTime = Carbon::parse($showtime->end_time);

                        // Nếu thời gian bắt đầu nằm giữa bất kỳ suất chiếu nào khác
                        // if (
                        //     $startTime->between($existingStartTime, $existingEndTime) ||
                        //     $existingStartTime->between($startTime, $startTime->copy()->addMinutes($this->movie_duration))
                        // ) {
                        //     $fail("Giờ chiếu $value bị trùng lặp với suất chiếu khác trong phòng.");
                        //     return;
                        // }
                        if (

                            $startTime->lt($existingEndTime) && $endTime->gt($existingStartTime) ||
                            $startTime == $existingEndTime ||
                            $endTime == $existingStartTime

                        ) {
                            $fail("Giờ chiếu từ $value - $this->end_time bị trùng lặp với suất chiếu " . $existingStartTime->format('H:i') . " - " . $existingEndTime->format('H:i') . ".");
                            return;
                        }
                    }
                },
            ],
            'end_time' => 'required',

        ];
    }


    public function messages()
    {
        return [
            'movie_id.required' => "Vui lòng chọn phim",
            'cinema_id.required' => "Vui lòng chọn Tên rạp",
            'branch_id.required' => "Vui lòng chọn Chi nhánh",
            'room_id.required' => 'Vui lòng chọn phòng.',
            'room_id.exists' => 'Phòng đã chọn không tồn tại.',
            'movie_version_id.required' => 'Vui lòng chọn phiên bản phim.',
            'movie_version_id.exists' => 'Phiên bản phim đã chọn không tồn tại.',
            'date.required' => 'Vui lòng chọn ngày chiếu.',
            'date.date' => 'Ngày chiếu không hợp lệ.',
            'date.after_or_equal' => 'Ngày chiếu phải từ hôm nay trở đi.',

            'start_time.required' => 'Vui lòng chọn giờ chiếu.',
            'start_time.date_format' => 'Giờ chiếu không hợp lệ (định dạng phải là HH:MM).',
            'start_time.before' => 'Giờ chiếu phải trước giờ kết thúc.',
            'start_time.unique' => 'Giờ chiếu trùng lặp với suất chiếu khác.',
            'end_time.required' => 'Vui lòng nhập giờ kết thúc.',
            'end_time.date_format' => 'Giờ kết thúc không hợp lệ (định dạng phải là HH:MM).',
            'end_time.after' => 'Giờ kết thúc phải sau giờ chiếu.',
        ];
    }
}

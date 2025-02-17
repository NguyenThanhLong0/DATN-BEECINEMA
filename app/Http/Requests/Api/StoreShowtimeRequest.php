<?php

namespace App\Http\Requests\Api;

use App\Models\Movie;
use App\Models\Showtime;
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

        if ($this->input('auto_generate_showtimes') === 'on') {
            return [
                'room_id' => [
                    'required',
                    'exists:rooms,id',
                    Rule::unique('showtimes')->where(function ($query) {
                        return $query->where('date', $this->date)
                            ->where('start_time', $this->start_time)
                            ->where('room_id', $this->room_id);
                    }),
                ],
                'movie_id' => 'required',
                // 'cinema_id' => 'required',
                'movie_version_id' => 'required|exists:movie_versions,id',
                'date' => 'required|date|after_or_equal:today',


                'start_hour' => [
                    'required',
                    function ($attribute, $value, $fail) {
                        $startHour = Carbon::parse($this->date . ' ' . $value);
                        $endHour = Carbon::parse($this->date . ' ' . $this->end_hour);

                        // Kiểm tra giờ mở cửa phải nằm trong tương lai
                        // if ($startHour->isPast()) {
                        //     $fail("Giờ mở cửa phải nằm trong tương lai.");
                        //     return;
                        // }

                        if ($startHour->lt(Carbon::now()->addHour())) {
                            $fail("Giờ mở cửa phải từ hiện tại cộng thêm 1 tiếng trở đi(Không được nhập giờ quá khứ!)");
                            return;
                        }

                        // Lấy các suất chiếu đã tồn tại trong phòng, ngày được chọn
                        $existingShowtimes = Showtime::where('room_id', $this->room_id)
                            ->where('date', $this->date)
                            ->get();

                        foreach ($existingShowtimes as $showtime) {
                            $existingStartTime = Carbon::parse($showtime->start_time);
                            $existingEndTime = Carbon::parse($showtime->end_time);

                            // Kiểm tra nếu khoảng giờ mở cửa và đóng cửa giao nhau với bất kỳ suất chiếu nào
                            if (
                                $startHour->between($existingStartTime, $existingEndTime) || // Giờ mở cửa nằm trong khoảng thời gian suất chiếu
                                $endHour->between($existingStartTime, $existingEndTime) ||   // Giờ đóng cửa nằm trong khoảng thời gian suất chiếu
                                $existingStartTime->between($startHour, $endHour) ||         // Giờ bắt đầu của suất chiếu nằm trong khoảng giờ mở cửa
                                $existingEndTime->between($startHour, $endHour)              // Giờ kết thúc của suất chiếu nằm trong khoảng giờ mở cửa
                            ) {
                                $fail("Khoảng thời gian từ $value đến {$this->end_hour} bị trùng lặp với suất chiếu khác trong phòng.");
                                return;
                            }
                        }
                    },
                ],
                'end_hour' => [
                    'required',
                    'after:start_hour',
                    function ($attribute, $value, $fail) {
                        $movie = Movie::findOrFail($this->input('movie_id'));
                        $startHour = Carbon::parse($this->date . ' ' . $this->start_hour);
                        $endHour = Carbon::parse($this->date . ' ' . $value);

                        // Lấy các suất chiếu đã tồn tại trong phòng, ngày được chọn
                        $existingShowtimes = Showtime::where('room_id', $this->room_id)
                            ->where('date', $this->date)
                            ->get();

                        if ($startHour->copy()->addMinutes($movie->duration + 15) > $endHour) {
                            $fail("Giờ đóng cửa quá sớm. Không thể tạo được ít nhất 1 suất chiếu !");
                            return;
                        }

                        foreach ($existingShowtimes as $showtime) {
                            $existingStartTime = Carbon::parse($showtime->start_time);
                            $existingEndTime = Carbon::parse($showtime->end_time);

                            // Kiểm tra nếu khoảng giờ mở cửa và đóng cửa giao nhau với bất kỳ suất chiếu nào
                            if (
                                $startHour->between($existingStartTime, $existingEndTime) || // Giờ mở cửa nằm trong khoảng thời gian suất chiếu
                                $endHour->between($existingStartTime, $existingEndTime) ||   // Giờ đóng cửa nằm trong khoảng thời gian suất chiếu
                                $existingStartTime->between($startHour, $endHour) ||         // Giờ bắt đầu của suất chiếu nằm trong khoảng giờ mở cửa
                                $existingEndTime->between($startHour, $endHour)              // Giờ kết thúc của suất chiếu nằm trong khoảng giờ mở cửa
                            ) {
                                $fail("Khoảng thời gian từ {$this->start_hour} đến $value bị trùng lặp với suất chiếu khác trong phòng.");
                                return;
                            }
                        }
                    },
                ],
            ];
        }
        if ($this->input('auto_generate_showtimes') != 'on') {
            return [
                'room_id' => [
                    'required',
                    'exists:rooms,id',
                    Rule::unique('showtimes')->where(function ($query) {
                        return $query->where('date', $this->date)
                            ->where('start_time', $this->start_time)
                            ->where('room_id', $this->room_id);
                    }),
                ],
                'movie_id' => 'required',
                // 'cinema_id' => 'required',
                'movie_version_id' => 'required|exists:movie_versions,id',
                'date' => 'required|date|after_or_equal:today',

                'start_time.*' => [
                    'required',
                    function ($attribute, $value, $fail) {
                        $movie = Movie::findOrFail($this->input('movie_id'));
                        $startTime = Carbon::parse($this->date . ' ' . $value);
                        $endTime = $startTime->copy()->addMinutes($movie->duration + 15);


                        // Kiểm tra giờ chiếu phải từ hiện tại cộng thêm 1 tiếng trở đi
                        if ($startTime->lt(Carbon::now()->addHour())) {
                            $fail("Giờ chiếu phải từ hiện tại cộng thêm 1 tiếng trở đi(Không được nhập giờ quá khứ!)");
                            return;
                        }


                        // Kiểm tra trùng lặp trong dữ liệu đã gửi từ form
                        $startTimeArray = $this->input('start_time', []);
                        $endTimeArray = $this->input('end_time', []);

                        foreach ($startTimeArray as $index => $time) {
                            if ($value === $time) {
                                continue; // Bỏ qua chính nó
                            }

                            $existingStartTime = Carbon::parse($this->date . ' ' . $time);
                            $existingEndTime = isset($endTimeArray[$index])
                                ? Carbon::parse($this->date . ' ' . $endTimeArray[$index])
                                : null;

                            // Nếu thời gian mới giao nhau với bất kỳ khoảng thời gian nào trước đó
                            if (

                                $startTime->between($existingStartTime, $existingEndTime) ||
                                ($existingEndTime && $existingStartTime->between($startTime, $startTime->copy()->addMinutes($this->movie_duration + 15)))
                            ) {
                                $fail("Giờ chiếu $value bị trùng lặp với hàng trước đó trong danh sách.");
                                return;
                            }
                        }

                        // Kiểm tra trùng lặp với các suất chiếu trong database
                        $existingShowtimes = Showtime::where('room_id', $this->room_id)
                            ->where('date', $this->date)
                            ->get();

                        // Kiểm tra trùng với các suất chiếu hiện có
                        foreach ($existingShowtimes as $showtime) {
                            $existingStartTime = Carbon::parse($showtime->start_time);
                            $existingEndTime = Carbon::parse($showtime->end_time);

                            if (
                                // $startTime->between($existingStartTime, $existingEndTime) ||
                                // $endTime->between($existingStartTime, $existingEndTime) ||
                                $startTime->lt($existingEndTime) && $endTime->gt($existingStartTime) ||
                                $startTime == $existingEndTime ||
                                $endTime == $existingStartTime

                            ) {
                                $fail("Suất chiếu bạn chọn trùng với một suất chiếu đã tồn tại từ " . $existingStartTime->format('H:i') . " - " . $existingEndTime->format('H:i') . ".");
                                return;
                            }
                        }
                    },
                    // Code cũ
                ],


            ];
        }
        return [];
    }

    public function messages()
    {
        return [
            'room_id.required' => 'Vui lòng chọn phòng.',
            'room_id.exists' => 'Phòng đã chọn không tồn tại.',
            'room_id.unique' => 'Suất chiếu đã tồn tại cho phòng này vào thời gian bạn đã chọn.',
            'movie_id.required' => 'Vui lòng chọn phim.',
            'cinema_id.required' => 'Vui lòng chọn tên rạp.',
            'movie_version_id.required' => 'Vui lòng chọn phiên bản phim.',
            'movie_version_id.exists' => 'Phiên bản phim đã chọn không tồn tại.',
            'date.required' => 'Vui lòng chọn ngày chiếu.',
            'date.date' => 'Ngày chiếu không hợp lệ.',
            'date.after_or_equal' => 'Ngày chiếu phải từ hôm nay trở đi.',
            'start_time.*.required' => 'Vui lòng nhập giờ chiếu.',
            'start_time.*.date_format' => 'Giờ chiếu không hợp lệ (định dạng phải là HH:MM).',
            'start_time.*.after' => 'Giờ chiếu phải lớn hơn thời gian hiện tại.',
            'start_time.*.required_unless' => 'Giờ chiếu là bắt buộc nếu không bật tính năng tự động tạo suất chiếu.',
            'end_hour.required_if' => 'Vui lòng nhập giờ đóng cửa khi sử dụng tự động tạo suất chiếu.',
            'end_hour.after' => 'Giờ đóng cửa phải lớn hơn giờ mở cửa.',
            'start_hour.required' => 'Vui lòng nhập giờ mở cửa.',
            'end_hour.required' => 'Vui lòng nhập giờ đóng cửa.',
            'start_hour.required_if' => 'Vui lòng nhập giờ mở cửa khi sử dụng tự động tạo suất chiếu.',
        ];
    }
}

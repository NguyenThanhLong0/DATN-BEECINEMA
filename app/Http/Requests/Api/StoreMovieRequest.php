<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Movie;
use Illuminate\Validation\Rule;

class StoreMovieRequest extends FormRequest
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
        if ($this->input('action') === 'draft') {
            // Trả về mảng rỗng nếu là lưu nháp, không validate gì cả
            return [
                'name' => 'required|unique:movies|max:255',
            ];
        }
        return [
            'name' => 'required|unique:movies|max:255',
            'category' => 'required|max:255',
            'img_thumbnail' => 'required|image|max:2048',
            'description' => 'required|max:255', // Giới hạn mô tả.
            'director' => 'required|max:255',
            'cast' => 'required|max:255', // Giới hạn danh sách diễn viên.
            'duration' => 'required|integer|min:30|max:180',
            'release_date' => 'required|date|after_or_equal:today|before_or_equal:' . now()->addYears(2)->toDateString(),
            'end_date' => 'required|after:release_date',
            'trailer_url' => 'required|max:255', // Giới hạn URL trailer.
            'versions' => 'required|array',
            'versions.*' => [
                'required',
                Rule::in(array_column(Movie::VERSIONS, 'name')),
            ],
            'surcharge' => 'nullable|integer|min:0',
            'surcharge_desc' => 'nullable|max:255'
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tên phim.',
            'name.unique' => 'Phim đã tồn tại trong hệ thống.',
            'name.max' => 'Tên phim không được vượt quá 255 ký tự.',
            'category.required' => 'Vui lòng nhập danh mục.',
            'category.max' => 'Danh mục không được vượt quá 255 ký tự.',
            'img_thumbnail.required' => 'Vui lòng chọn ảnh thumbnail.',
            'img_thumbnail.image' => 'File phải là một hình ảnh.',
            'img_thumbnail.max' => 'Ảnh thumbnail không được vượt quá 2MB.',
            'description.required' => 'Mô tả không bắt buộc.',
            'description.max' => 'Mô tả không được vượt quá 255 ký tự.',
            'director.required' => 'Vui lòng nhập tên đạo diễn.',
            'director.max' => 'Tên đạo diễn không được vượt quá 255 ký tự.',
            'cast.required' => 'Vui lòng nhập danh sách diễn viên.',
            'cast.max' => 'Danh sách diễn viên không được vượt quá 255 ký tự.',
            'duration.required' => 'Vui lòng nhập thời lượng.',
            'duration.integer' => 'Thời lượng phải là số nguyên.',
            'duration.min' => 'Thời lượng tối thiểu phải là 30 phút.',
            'duration.max' => 'Thời lượng tối đa không quá 180 phút.',
            'release_date.required' => 'Vui lòng nhập ngày khởi chiếu.',
            'release_date.date' => 'Ngày khởi chiếu không hợp lệ.',
            'release_date.after_or_equal' => 'Ngày khởi chiếu phải là hôm nay hoặc trong tương lai.',
            'release_date.before_or_equal' => 'Ngày khởi chiếu không được quá 2 năm từ hôm nay.',
            'end_date.required' => 'Vui lòng nhập ngày kết thúc.',
            'end_date.after' => 'Ngày kết thúc phải sau ngày khởi chiếu.',
            'trailer_url.required' => 'Vui lòng nhập code Youtube.',
            'trailer_url.max' => 'URL trailer không được vượt quá 255 ký tự.',
            'versions.required' => 'Vui lòng chọn ít nhất một phiên bản.',
            'versions.array' => 'Phiên bản phải là một mảng.',
            'versions.*.in' => 'Giá trị không hợp lệ trong phiên bản.',
            'surcharge.nullable' => 'Phí thu thêm không bắt buộc.',
            'surcharge.integer' => 'Giá thu thêm phải là số nguyên.',
            'surcharge.min' => 'Giá thu thêm phải là số nguyên dương.',
        ];
    }
}


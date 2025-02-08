<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SeatTemplate;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;


// class SeatTemplateController extends Controller
// {
//     /**
//      * Lấy danh sách mẫu sơ đồ ghế.
//      */
//     public function index()
//     {
//         return response()->json(SeatTemplate::paginate(10));
//     }

//     /**
//      * Thêm mới mẫu sơ đồ ghế.
//      */
//     public function store(Request $request)
//     {
//         $matrixIds = array_column(SeatTemplate::MATRIXS, 'id');
//         $maxtrix = SeatTemplate::getMatrixById($request->matrix_id);

//         $validator = Validator::make($request->all(), [
//             'name' => 'required|string|max:255|unique:seat_templates',
//             'matrix_id' => ['required', Rule::in($matrixIds)],
//             'row_regular' => 'required|integer|min:0|max:' . $maxtrix['max_row'],
//             'row_vip' => 'required|integer|min:0|max:' . $maxtrix['max_row'],
//             'row_double' => 'required|integer|min:0|max:' . $maxtrix['max_row'],
//             'description' => 'required|string|max:255'
//         ]);

//         if ($validator->fails()) {
//             return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
//         }

//         try {
//             $data = $request->only(['name', 'matrix_id', 'seat_structure', 'description', 'row_regular', 'row_vip', 'row_double']);
//             $seatTemplate = SeatTemplate::create($data);

//             return response()->json(['message' => 'Thêm mới thành công!', 'seatTemplate' => $seatTemplate], Response::HTTP_CREATED);
//         } catch (\Throwable $th) {
//             return response()->json(['errors' => $th->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
//         }
//     }

//     /**
//      * Cập nhật mẫu sơ đồ ghế.
//      */
//     public function update(Request $request, SeatTemplate $seatTemplate)
//     {
//         $matrixIds = array_column(SeatTemplate::MATRIXS, 'id');
//         $maxtrix = SeatTemplate::getMatrixById($request->matrix_id ?? $seatTemplate->matrix_id);

//         $validator = Validator::make($request->all(), [
//             'name' => 'required|string|max:255|unique:seat_templates,name,' . $seatTemplate->id,
//             'matrix_id' => !$seatTemplate->is_publish ? ['required', Rule::in($matrixIds)] : 'nullable',
//             'description' => 'required|string|max:255',
//             'row_regular' => 'required|integer|min:0|max:' . $maxtrix['max_row'],
//             'row_vip' => 'required|integer|min:0|max:' . $maxtrix['max_row'],
//             'row_double' => 'required|integer|min:0|max:' . $maxtrix['max_row']
//         ]);

//         if ($validator->fails()) {
//             return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
//         }

//         try {
//             $seatTemplate->update($request->all());
//             return response()->json(['message' => 'Cập nhật thành công!', 'seatTemplate' => $seatTemplate]);
//         } catch (\Throwable $th) {
//             return response()->json(['errors' => $th->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
//         }
//     }

//     /**
//      * Thay đổi trạng thái kích hoạt.
//      */
//     public function changeActive(Request $request)
//     {
//         try {
//             $seatTemplate = SeatTemplate::findOrFail($request->id);
//             if ($seatTemplate->is_publish) {
//                 $seatTemplate->update(['is_active' => $request->is_active]);
//                 return response()->json(['message' => 'Cập nhật trạng thái thành công!', 'seatTemplate' => $seatTemplate]);
//             }
//             return response()->json(['message' => 'Template chưa được publish.'], Response::HTTP_BAD_REQUEST);
//         } catch (\Throwable $th) {
//             return response()->json(['message' => 'Có lỗi xảy ra, vui lòng thử lại.'], Response::HTTP_INTERNAL_SERVER_ERROR);
//         }
//     }
// }




class SeatTemplateController extends Controller
{
    public function index()
    {
        // return response()->json(SeatTemplate::all());
        return response()->json(SeatTemplate::paginate(10));
    }
    /**
     * Thêm mới mẫu sơ đồ ghế.
     */
    public function store(Request $request)
    {
        $matrixIds = array_column(SeatTemplate::MATRIXS, 'id');

        $maxtrix = SeatTemplate::getMatrixById($request->matrix_id);

        // Validator
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:seat_templates',
            'matrix_id' => ['required', Rule::in($matrixIds)],
            'row_regular' => 'required|integer|min:0|max:' . $maxtrix['max_row'],
            'row_vip' => 'required|integer|min:0|max:' . $maxtrix['max_row'],
            'row_double' => 'required|integer|min:0|max:' . $maxtrix['max_row'],
            'description' => 'required|string|max:255'
        ], [
            'name.required' => 'Vui lòng nhập tên mẫu.',
            'name.unique' => 'Tên mẫu đã tồn tại.',
            'name.string' => 'Tên mẫu phải là kiểu chuỗi.',
            'name.max' => 'Độ dài tên mẫu không được vượt quá 255 ký tự.',
            'row_regular.required' => 'Vui lòng nhập số lượng hàng ghế thường.',
            'row_regular.integer'  => 'Hàng ghế thường phải là số nguyên.',
            'row_regular.max'      => 'Hàng ghế thường không được vượt quá ' . $maxtrix['max_row'] . '.',
            'row_vip.required'     => 'Vui lòng nhập số lượng hàng ghế VIP.',
            'row_vip.integer'      => 'Hàng ghế VIP phải là số nguyên.',
            'row_vip.max'          => 'Hàng ghế VIP không được vượt quá ' . $maxtrix['max_row'] . '.',
            'row_double.required'  => 'Vui lòng nhập số lượng hàng ghế đôi.',
            'row_double.integer'   => 'Hàng ghế đôi phải là số nguyên.',
            'row_double.max'       => 'Hàng ghế đôi không được vượt quá ' . $maxtrix['max_row'] . '.',
            'description.required' => 'Vui lòng nhập mô tả.',
        ]);

        // Custom validation logic
        $validator->after(function ($validator) use ($request, $maxtrix) {

            // Tính tổng số hàng ghế
            $totalRows = $request->row_regular + $request->row_vip + $request->row_double;

            // Kiểm tra lỗi chi tiết cho từng trường trước
            if (
                $validator->errors()->has('row_regular') ||
                $validator->errors()->has('row_vip') ||
                $validator->errors()->has('row_double')
            ) {
                // Lấy lỗi đầu tiên trong các trường hàng ghế
                $error_message = $validator->errors()->first('row_regular') ?:
                    $validator->errors()->first('row_vip') ?:
                    $validator->errors()->first('row_double');

                // Thêm lỗi tổng quát cho trường "rows"
                $validator->errors()->add('rows', $error_message);
            }

            // Kiểm tra tổng số hàng ghế có khớp với max_row hay không
            if ($totalRows !== $maxtrix['max_row']) {
                $validator->errors()->add('rows', 'Tổng số hàng ghế phải bằng ' . $maxtrix['max_row'] . '.');
            }
        });

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $seatTemplate = SeatTemplate::create($request->all());
            return response()->json(['message' => 'Thêm mới thành công!', 'seatTemplate' => $seatTemplate], Response::HTTP_CREATED);
        } catch (\Throwable $th) {
            return response()->json(['errors' => $th->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Cập nhật mẫu sơ đồ ghế.
     */
    public function update(Request $request, SeatTemplate $seatTemplate)
    {
        // Lấy danh sách ID ma trận
        $matrixIds = array_column(SeatTemplate::MATRIXS, 'id');
        $maxtrix = SeatTemplate::getMatrixById($request->matrix_id ?? $seatTemplate->matrix_id);
        // Xác thực dữ liệu đầu vào
        $rules = [
            'name' => 'required|string|max:255|unique:seat_templates,name,' . $seatTemplate->id,
            'description' => 'required|string|max:255',
            'matrix_id' => !$seatTemplate->is_publish ? ['required', Rule::in($matrixIds)] : 'nullable',
        ];

        if (!$seatTemplate->is_publish) {
            $rules['row_regular'] = 'required|integer|min:0|max:' . $maxtrix['max_row'];
            $rules['row_vip'] = 'required|integer|min:0|max:' . $maxtrix['max_row'];
            $rules['row_double'] = 'required|integer|min:0|max:' . $maxtrix['max_row'];
        }

        // Thông báo lỗi tùy chỉnh
        $messages = [
            'name.required' => 'Vui lòng nhập tên mẫu.',
            'name.unique' => 'Tên mẫu đã tồn tại.',
            'name.string' => 'Tên mẫu phải là kiểu chuỗi.',
            'name.max' => 'Độ dài tên mẫu không được vượt quá 255 ký tự.',
            'row_regular.required' => 'Vui lòng nhập số lượng hàng ghế.',
            'row_regular.integer'  => 'Hàng ghế phải là một số nguyên.',
            'row_regular.min'      => 'Hàng ghế phải lớn hơn hoặc bằng 0.',
            'row_regular.max'      => 'Hàng ghế phải nhỏ hơn hoặc bằng ' . $maxtrix['max_row'] . '.',


            'row_vip.required'     => 'Vui lòng nhập số lượng hàng ghế.',
            'row_vip.integer'      => 'Hàng ghế phải là một số nguyên.',
            'row_vip.min'          => 'Hàng ghế phải lớn hơn hoặc bằng 0.',
            'row_vip.max'      => 'Hàng ghế phải nhỏ hơn hoặc bằng ' . $maxtrix['max_row'] . '.',


            'row_double.required'  => 'Vui lòng nhập số lượng hàng ghế.',
            'row_double.integer'   => 'Hàng ghế phải là một số nguyên.',
            'row_double.min'       => 'Hàng ghế phải lớn hơn hoặc bằng 0.',
            'row_double.max'      => 'Hàng ghế phải nhỏ hơn hoặc bằng ' . $maxtrix['max_row'] . '.',
            'description.required' => 'Vui lòng nhập mô tả.',
            'description.string' => 'Mô tả phải là kiểu chuỗi.',
            'description.max' => 'Độ dài mô tả không được vượt quá 255 ký tự.',
            'matrix_id.required' => "Vui lòng chọn ma trận ghế",
            'matrix_id.in' => 'Ma trận ghế không hợp lệ.'
        ];

        // Thực hiện validate
        $validator = Validator::make($request->all(), $rules, $messages);

        if (!$seatTemplate->is_publish) {
            $validator->after(function ($validator) use ($request, $maxtrix) {
                $total = $request->row_regular + $request->row_vip + $request->row_double;

                if (
                    $validator->errors()->has('row_regular') ||
                    $validator->errors()->has('row_vip') ||
                    $validator->errors()->has('row_double')
                ) {
                    // Lấy lỗi đầu tiên của từng trường
                    $error_message = $validator->errors()->first('row_regular') ?:
                        $validator->errors()->first('row_vip') ?:
                        $validator->errors()->first('row_double');

                    $validator->errors()->add('rows', $error_message);
                }

                if ($total !== $maxtrix['max_row']) {
                    $validator->errors()->add('rows', 'Tổng số hàng ghế phải bằng ' . $maxtrix['max_row'] . '.');
                }
            });
        }

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY); // 422
        }

        try {
            DB::transaction(function () use ($request, $seatTemplate) {
                // Cập nhật thông tin mẫu ghế
                $dataSeatTemplate = [
                    'name' => $request->name,
                    'description' => $request->description,
                    'is_publish' => $request->input('is_publish', $seatTemplate->is_publish), //thêm cập nhật is_public
                ];

                // Chỉ thêm matrix_id và structure_seat nếu chưa publish
                if (!$seatTemplate->is_publish) {
                    $dataSeatTemplate['matrix_id'] = $request->matrix_id;
                    $dataSeatTemplate['row_regular'] = $request->row_regular;
                    $dataSeatTemplate['row_vip'] = $request->row_vip;
                    $dataSeatTemplate['row_double'] = $request->row_double;

                    // Kiểm tra nếu matrix_id thay đổi, thì cần phải reset structure_seat
                    if (
                        $seatTemplate->matrix_id !== $request->matrix_id ||
                        $seatTemplate->row_regular !== $request->row_regular ||
                        $seatTemplate->row_vip !== $request->row_vip ||
                        $seatTemplate->row_double !== $request->row_double
                    ) {
                        $dataSeatTemplate['seat_structure'] = null;
                    }
                }

                // Cập nhật seatTemplate với dữ liệu mới
                $seatTemplate->update($dataSeatTemplate);
            });
            session()->flash('success', 'Thao tác thành công');
            return response()->json(['message' => "Thao tác thành công"], Response::HTTP_OK); // 200

        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR); // 500
        }
    }

    /**
     * Thay đổi trạng thái kích hoạt.
     */
    public function changeActive(Request $request)
    {
        try {
            $seatTemplate = SeatTemplate::findOrFail($request->id);
            if ($seatTemplate->is_publish) {
                $seatTemplate->update(['is_active' => $request->is_active]);
                return response()->json(['message' => 'Cập nhật trạng thái thành công!', 'seatTemplate' => $seatTemplate]);
            }
            return response()->json(['message' => 'Template chưa được publish.']);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Có lỗi xảy ra: ' . $th->getMessage()]);
        }
    }


    public function show(SeatTemplate $seatTemplate)
    {
        try {
            return response()->json($seatTemplate);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Có lỗi xảy ra, vui lòng thử lại.']);
        }
    }

    public function destroy(SeatTemplate $seatTemplate)
    {
        try {
            $seatTemplate->delete();
            return response()->json(['message' => 'Xóa thành công!'], Response::HTTP_OK);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Có lỗi xảy ra, vui lòng thử lại.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

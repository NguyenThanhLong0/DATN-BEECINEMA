<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
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
        $seatTemplates = SeatTemplate::paginate(10);

        $seatTemplates->getCollection()->transform(function ($seatTemplate) {
            // Lấy thông tin chi tiết của matrix và gán vào `matrix_id`
            $seatTemplate->matrix_id = SeatTemplate::getMatrixById($seatTemplate->matrix_id);

            // Giải mã `seat_structure` nếu tồn tại
            $seatStructure = $seatTemplate->seat_structure;
            if (is_string($seatStructure)) {
                $seatStructure = json_decode($seatStructure, true) ?: json_decode(stripslashes($seatStructure), true);
            }

            // Xử lý tạo `seat_map` nếu `seat_structure` không rỗng
            $seatMap = [];
            $totalSeats = 0;

            // Giả sử danh sách loại ghế có dạng này:
            $typeSeats = [
                1 => 'regular',
                2 => 'vip',
                3 => 'double'
            ];

            if ($seatStructure) {
                foreach ($seatStructure as $seat) {
                    $coordinates_y = $seat['coordinates_y'];
                    $coordinates_x = $seat['coordinates_x'];
                    $type_seat_id = $seat['type_seat_id'];
                    $type_seat = isset($typeSeats[$type_seat_id]) ? $typeSeats[$type_seat_id] : 'Không xác định';

                    // Tạo hàng nếu chưa tồn tại
                    if (!isset($seatMap[$coordinates_y])) {
                        $seatMap[$coordinates_y] = [
                            'row' => $coordinates_y,
                            'type' => $type_seat, // Gán loại ghế cho cả row
                            'seats' => []
                        ];
                    }

                    // Xử lý ghế đôi và ghế thường
                    if ($type_seat_id == 3) {  // Ghế đôi
                        $seatName = $coordinates_y . $coordinates_x . " " . $coordinates_y . ($coordinates_x + 1);
                        $seatMap[$coordinates_y]['seats'][] = [
                            'coordinates_x' => $coordinates_x,
                            'coordinates_y' => $coordinates_y,
                            'name' => $seatName
                        ];
                        $totalSeats += 2;
                    } else {  // Ghế thường
                        $seatMap[$coordinates_y]['seats'][] = [
                            'coordinates_x' => $coordinates_x,
                            'coordinates_y' => $coordinates_y,
                            'name' => $coordinates_y . $coordinates_x
                        ];
                        $totalSeats++;
                    }
                }
            }


            // Chuyển đổi seatMap sang dạng danh sách
            $seatTemplate->seat_map = array_values($seatMap);
            $seatTemplate->total_seats = $totalSeats;

            return $seatTemplate;
        });

        return response()->json($seatTemplates);
    }

    public function show(SeatTemplate $seatTemplate)
    {
        // Lấy thông tin chi tiết của matrix và gán vào `matrix_id`
        $seatTemplate->matrix_id = SeatTemplate::getMatrixById($seatTemplate->matrix_id);

        // Giải mã `seat_structure` nếu tồn tại
        $seatStructure = $seatTemplate->seat_structure;
        if (is_string($seatStructure)) {
            $seatStructure = json_decode($seatStructure, true) ?: json_decode(stripslashes($seatStructure), true);
        }

        // Tạo `seat_map`
        $seatMap = [];
        $totalSeats = 0;

        // Giả sử danh sách loại ghế có dạng này:
        $typeSeats = [
            1 => 'regular',
            2 => 'vip',
            3 => 'double'
        ];

        if ($seatStructure) {
            foreach ($seatStructure as $seat) {
                $coordinates_y = $seat['coordinates_y'];
                $coordinates_x = $seat['coordinates_x'];
                $type_seat_id = $seat['type_seat_id'];
                $type_seat = isset($typeSeats[$type_seat_id]) ? $typeSeats[$type_seat_id] : 'Không xác định';

                // Tạo hàng nếu chưa tồn tại
                if (!isset($seatMap[$coordinates_y])) {
                    $seatMap[$coordinates_y] = [
                        'row' => $coordinates_y,
                        'type' => $type_seat, // Gán loại ghế cho cả row
                        'seats' => []
                    ];
                }

                // Xử lý ghế đôi và ghế thường
                if ($type_seat_id == 3) {  // Ghế đôi
                    $seatName = $coordinates_y . $coordinates_x . " " . $coordinates_y . ($coordinates_x + 1);
                    $seatMap[$coordinates_y]['seats'][] = [
                        'coordinates_x' => $coordinates_x,
                        'coordinates_y' => $coordinates_y,
                        'name' => $seatName
                    ];
                    $totalSeats += 2;
                } else {  // Ghế thường
                    $seatMap[$coordinates_y]['seats'][] = [
                        'coordinates_x' => $coordinates_x,
                        'coordinates_y' => $coordinates_y,
                        'name' => $coordinates_y . $coordinates_x
                    ];
                    $totalSeats++;
                }
            }
        }


        $seatTemplate->seat_map = array_values($seatMap);
        $seatTemplate->total_seats = $totalSeats;

        return response()->json($seatTemplate);
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

    // public function update(Request $request, SeatTemplate $seatTemplate)
    // {
    //     // Lấy danh sách ID ma trận
    //     $matrixIds = array_column(SeatTemplate::MATRIXS, 'id');
    //     $maxtrix = SeatTemplate::getMatrixById($request->matrix_id ?? $seatTemplate->matrix_id);

    //     // Xác thực dữ liệu đầu vào
    //     $rules = [
    //         'name' => 'sometimes|required|string|max:255|unique:seat_templates,name,' . $seatTemplate->id,
    //         'description' => 'sometimes|required|string|max:255',
    //         'matrix_id' => !$seatTemplate->is_publish ? ['required', Rule::in($matrixIds)] : 'nullable',
    //         'is_publish' => 'nullable|boolean',
    //         'is_active' => 'nullable|boolean',
    //     ];

    //     if (!$seatTemplate->is_publish) {
    //         $rules['row_regular'] = 'sometimes|required|integer|min:0|max:' . $maxtrix['max_row'];
    //         $rules['row_vip'] = 'sometimes|required|integer|min:0|max:' . $maxtrix['max_row'];
    //         $rules['row_double'] = 'sometimes|required|integer|min:0|max:' . $maxtrix['max_row'];
    //     }

    //     // Thông báo lỗi tùy chỉnh
    //     $messages = [
    //         'name.required' => 'Vui lòng nhập tên mẫu.',
    //         'name.unique' => 'Tên mẫu đã tồn tại.',
    //         'name.max' => 'Tên mẫu không được vượt quá 255 ký tự.',
    //         'row_regular.required' => 'Vui lòng nhập số lượng hàng ghế thường.',
    //         'row_vip.required' => 'Vui lòng nhập số lượng hàng ghế VIP.',
    //         'row_double.required' => 'Vui lòng nhập số lượng hàng ghế đôi.',
    //         'description.required' => 'Vui lòng nhập mô tả.',
    //         'matrix_id.required' => 'Vui lòng chọn ma trận ghế.',
    //     ];

    //     // Thực hiện validate
    //     $validator = Validator::make($request->all(), $rules, $messages);

    //     // Kiểm tra tổng số hàng ghế nếu mẫu chưa được publish
    //     if (!$seatTemplate->is_publish) {
    //         $validator->after(function ($validator) use ($request, $maxtrix) {
    //             $total = $request->row_regular + $request->row_vip + $request->row_double;
    //             if ($total !== $maxtrix['max_row']) {
    //                 $validator->errors()->add('rows', 'Tổng số hàng ghế phải bằng ' . $maxtrix['max_row'] . '.');
    //             }
    //         });
    //     }

    //     // Trả về lỗi nếu validate không thành công
    //     if ($validator->fails()) {
    //         return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
    //     }

    //     try {
    //         DB::transaction(function () use ($request, $seatTemplate) {
    //             // Cập nhật `is_publish` trước
    //             if ($request->has('is_publish')) {
    //                 $seatTemplate->update(['is_publish' => $request->is_publish]);
    //             }

    //             // Làm mới dữ liệu
    //             $seatTemplate->refresh();

    //             // Chặn cập nhật `is_active` nếu mẫu chưa được publish
    //             if ($request->has('is_active')) {
    //                 if ($request->is_active == 1 && !$seatTemplate->is_publish) {
    //                     throw new \Exception('Template chưa được publish, không thể kích hoạt.');
    //                 }

    //                 // Cập nhật `is_active`
    //                 $seatTemplate->update(['is_active' => $request->is_active]);
    //             }

    //             // Chuẩn bị dữ liệu cập nhật
    //             $dataSeatTemplate = [
    //                 'name' => $request->name,
    //                 'description' => $request->description,
    //             ];

    //             if (!$seatTemplate->is_publish) {
    //                 $dataSeatTemplate['matrix_id'] = $request->matrix_id;
    //                 $dataSeatTemplate['row_regular'] = $request->row_regular;
    //                 $dataSeatTemplate['row_vip'] = $request->row_vip;
    //                 $dataSeatTemplate['row_double'] = $request->row_double;

    //                 // Xử lý cấu trúc ghế nếu có
    //                 if ($request->has('seat_structure')) {
    //                     $seatStructure = $request->input('seat_structure');

    //                     if (is_string($seatStructure)) {
    //                         $decoded = json_decode($seatStructure, true);
    //                         if (json_last_error() === JSON_ERROR_NONE) {
    //                             $seatStructure = $decoded;
    //                         }
    //                     }

    //                     $dataSeatTemplate['seat_structure'] = json_encode($seatStructure);
    //                 }

    //                 // Reset `seat_structure` nếu các thông tin hàng ghế hoặc matrix thay đổi
    //                 if (
    //                     $seatTemplate->matrix_id !== $request->matrix_id ||
    //                     $seatTemplate->row_regular !== $request->row_regular ||
    //                     $seatTemplate->row_vip !== $request->row_vip ||
    //                     $seatTemplate->row_double !== $request->row_double
    //                 ) {
    //                     $dataSeatTemplate['seat_structure'] = $dataSeatTemplate['seat_structure'] ?? null;
    //                 }
    //             }

    //             // Cập nhật dữ liệu
    //             $seatTemplate->update($dataSeatTemplate);
    //         });

    //         return response()->json(['message' => 'Cập nhật thành công!', 'seatTemplate' => $seatTemplate], Response::HTTP_OK);
    //     } catch (\Throwable $th) {
    //         return response()->json(['error' => $th->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
    //     }
    // }


public function update(Request $request, SeatTemplate $seatTemplate)
{
    // Lấy danh sách ID ma trận
    $matrixIds = array_column(SeatTemplate::MATRIXS, 'id');
    $maxtrix = SeatTemplate::getMatrixById($request->matrix_id ?? $seatTemplate->matrix_id);

    // Xác thực dữ liệu đầu vào
    $rules = [
        'name' => 'sometimes|required|string|max:255|unique:seat_templates,name,' . $seatTemplate->id,
        'description' => 'sometimes|required|string|max:255',
        'matrix_id' => !$seatTemplate->is_publish ? ['required', Rule::in($matrixIds)] : 'nullable',
        'is_publish' => 'nullable|boolean',
        'is_active' => 'nullable|boolean',
    ];

    if (!$seatTemplate->is_publish) {
        $rules['row_regular'] = 'sometimes|required|integer|min:0|max:' . $maxtrix['max_row'];
        $rules['row_vip'] = 'sometimes|required|integer|min:0|max:' . $maxtrix['max_row'];
        $rules['row_double'] = 'sometimes|required|integer|min:0|max:' . $maxtrix['max_row'];
    }

    // Thông báo lỗi tùy chỉnh
    $messages = [
        'name.required' => 'Vui lòng nhập tên mẫu.',
        'name.unique' => 'Tên mẫu đã tồn tại.',
        'name.max' => 'Tên mẫu không được vượt quá 255 ký tự.',
        'row_regular.required' => 'Vui lòng nhập số lượng hàng ghế thường.',
        'row_vip.required' => 'Vui lòng nhập số lượng hàng ghế VIP.',
        'row_double.required' => 'Vui lòng nhập số lượng hàng ghế đôi.',
        'description.required' => 'Vui lòng nhập mô tả.',
        'matrix_id.required' => 'Vui lòng chọn ma trận ghế.',
    ];

    // Thực hiện validate
    $validator = Validator::make($request->all(), $rules, $messages);

    // Kiểm tra tổng số hàng ghế nếu mẫu chưa được publish
    if (!$seatTemplate->is_publish) {
        $validator->after(function ($validator) use ($request, $maxtrix) {
            $total = $request->row_regular + $request->row_vip + $request->row_double;
            if ($total !== $maxtrix['max_row']) {
                $validator->errors()->add('rows', 'Tổng số hàng ghế phải bằng ' . $maxtrix['max_row'] . '.');
            }
        });
    }

    // Trả về lỗi nếu validate không thành công
    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    try {
        DB::transaction(function () use ($request, $seatTemplate) {
            // Cập nhật `is_publish` nếu có trong request
            if ($request->has('is_publish')) {
                $seatTemplate->update(['is_publish' => $request->is_publish]);
            }

            // Làm mới dữ liệu từ database
            $seatTemplate->refresh();

            // Chặn cập nhật `is_active` nếu chưa publish
            if ($request->has('is_active')) {
                if ($request->is_active == 1 && !$seatTemplate->is_publish) {
                    throw new \Exception('Template chưa được publish, không thể kích hoạt.');
                }

                $seatTemplate->update(['is_active' => $request->is_active]);
            }

            // Cập nhật dữ liệu còn lại
            $dataSeatTemplate = [
                'name' => $request->name,
                'description' => $request->description,
            ];

            if (!$seatTemplate->is_publish) {
                $dataSeatTemplate['matrix_id'] = $request->matrix_id;
                $dataSeatTemplate['row_regular'] = $request->row_regular;
                $dataSeatTemplate['row_vip'] = $request->row_vip;
                $dataSeatTemplate['row_double'] = $request->row_double;
            }

            // Xử lý seat_structure
            if ($request->has('seat_structure')) {
                $seatStructure = $request->input('seat_structure');

                // Giải mã JSON nếu cần
                if (is_string($seatStructure)) {
                    $decoded = json_decode($seatStructure, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $seatStructure = $decoded;
                    }
                }

                $dataSeatTemplate['seat_structure'] = json_encode($seatStructure);
            } else {
                // Giữ nguyên seat_structure nếu request không có dữ liệu mới
                $dataSeatTemplate['seat_structure'] = $seatTemplate->seat_structure;
            }

            // Đảm bảo seat_structure không bị null khi xuất bản
            if ($seatTemplate->is_publish && empty($dataSeatTemplate['seat_structure'])) {
                throw new \Exception('Dữ liệu ghế bị mất khi xuất bản, vui lòng kiểm tra lại.');
            }

            // Cập nhật dữ liệu vào database
            $seatTemplate->update($dataSeatTemplate);
        });

        return response()->json(['message' => 'Cập nhật thành công!', 'seatTemplate' => $seatTemplate], Response::HTTP_OK);
    } catch (\Throwable $th) {
        return response()->json(['error' => $th->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}

    /**
     * Thay đổi trạng thái kích hoạt.
     */
    public function changeActive(Request $request)
    {
        try {
            $seatTemplate = SeatTemplate::findOrFail($request->id);

            // Kiểm tra nếu chưa publish mà muốn kích hoạt
            if (!$seatTemplate->is_publish && $request->is_active == 1) {
                return response()->json(['message' => 'Template chưa publish, không thể kích hoạt.'], Response::HTTP_BAD_REQUEST);
            }

            // Cập nhật trạng thái nếu hợp lệ
            $seatTemplate->update(['is_active' => $request->is_active]);

            return response()->json(['message' => 'Cập nhật trạng thái thành công!', 'seatTemplate' => $seatTemplate]);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Có lỗi xảy ra: ' . $th->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    public function destroy(SeatTemplate $seatTemplate)
    {
        try {
            // Kiểm tra nếu `is_publish` là 1 thì không cho phép xóa
            if ($seatTemplate->is_publish) {
                return response()->json(['message' => 'Không thể xóa, template đã được publish.'], Response::HTTP_BAD_REQUEST);
            }

            // Kiểm tra nếu `seat_template` đang được sử dụng trong phòng nào đó
            $roomCount = Room::where('seat_template_id', $seatTemplate->id)->count();
            if ($roomCount > 0) {
                return response()->json(['message' => 'Không thể xóa, template này đang được sử dụng trong phòng khác.'], Response::HTTP_BAD_REQUEST);
            }

            // Xóa template nếu các điều kiện thỏa mãn
            $seatTemplate->delete();

            return response()->json(['message' => 'Xóa thành công!'], Response::HTTP_OK);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Có lỗi xảy ra, vui lòng thử lại.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    public function getMatrixById($id)
    {
        $matrix = SeatTemplate::getMatrixById($id);
        if ($matrix) {
            return response()->json($matrix);
        }
        return response()->json(['message' => 'Không tìm thấy dữ liệu.'], 404);
    }

    /**
     * Lấy danh sách tất cả MATRIXS.
     */
    // Lấy toàn bộ matrix
    public function getAllMatrix()
    {
        return response()->json(SeatTemplate::MATRIXS, 200);
    }
}

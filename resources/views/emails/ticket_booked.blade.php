<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vé đã được đặt thành công</title>
</head>

<body
    style="background-color: #eff2f5; padding: 20px; margin: 0; font-family: Arial, sans-serif; box-sizing: border-box; font-size: 14px; text-align: center;">
    <table
        style="max-width: 600px; background-color: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); padding: 20px; margin: 0 auto; box-sizing: border-box;">
        <tbody>
            <!-- Logo -->
            <tr style="text-align: center; padding: 15px; background-color: white">
                <td>
                    <img style="max-width: 120px; margin-bottom: 10px"
                        src="https://res.cloudinary.com/du2m9ntlf/image/upload/v1741266249/beecinema/vfzfjzcls6udqqpacqt2.png"
                        alt="VNPAY Logo" />
                </td>
            </tr>
            <!-- Thông tin phim & rạp -->
            <tr>
                <td style="text-align: center; padding: 15px 20px; background-color: white;">
                    <div style="font-size: 20px; font-weight: bold; margin-bottom: 5px; color: #337AB7">
                        {{ $ticket->movie->name }} <!-- Tên phim -->
                    </div>
                    <div style="color: #000000; font-size: 14px; font-weight: bold; margin-bottom: 10px">
                        {{ $ticket->cinema->name }} <!-- Rạp chiếu -->
                    </div>
                    <div style="font-size: 12px; color: #555555; line-height: 1.4">
                        {{ $ticket->cinema->address }} <!-- Địa chỉ rạp -->
                    </div>
                </td>
            </tr>
            <!-- Divider -->
            <tr>
                <td>
                    <img src="https://res.cloudinary.com/du2m9ntlf/image/upload/v1741266249/beecinema/hazj8ufm7n2d25ni6vie.png"
                        alt="Divider" style="width: 100%" />
                </td>
            </tr>
            <!-- Mã vé & QR Code -->
            <tr>
                <td>
                    <div style="text-align: center; padding: 10px 20px">
                        <div style="font-size: 12px; color: #666; margin-bottom: 5px">MÃ VÉ (RESERVATION CODE)</div>
                        <div style="font-size: 18px; font-weight: bold; color: #000; margin-bottom: 15px;">
                            {{ $ticket->code }}
                        </div>
                        <div style="font-size: 12px; color: #555555; line-height: 1.4">
                            {{ $ticket->cinema->address }}
                        </div>
                    </div>
                    <div style="width: 150px; height: 150px; margin: 0 auto 15px">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data={{ $ticket->code }}"
                            alt="QR Code" width="150" height="150" />
                    </div>
                </td>
            </tr>
            <!-- Suất chiếu -->
            <tr>
                <td style="text-align: center; padding: 10px 20px">
                    <div style="font-size: 12px; color: #666; margin-bottom: 5px">SUẤT CHIẾU (SESSION)</div>
                    <div style="font-size: 16px; font-weight: bold">
                        {{ \Carbon\Carbon::parse($ticket->showtime->start_time)->format('d/m/Y H:i') }}
                    </div>
                </td>
            </tr>
            <!-- Thông tin xuất trình -->
            <tr>
                <td style="text-align: center; padding: 15px 20px; font-size: 12px; color: #333; line-height: 1.5;">
                    <div>
                        Quý khách vui lòng tới quầy dịch vụ xuất trình mã vé này để được nhận vé.
                    </div>
                    <div style="color: #666; font-style: italic; margin-top: 5px">
                        Please go to the service counter and present your booking code to receive the physical ticket in person.
                    </div>
                </td>
            </tr>
            <!-- Divider -->
            <tr>
                <td>
                    <img src="https://res.cloudinary.com/du2m9ntlf/image/upload/v1741266249/beecinema/hazj8ufm7n2d25ni6vie.png"
                        alt="Divider" style="width: 100%" />
                </td>
            </tr>
            <!-- Thông tin chi tiết vé -->
            <tr>
                <td>
                    <table style="width: 100%; border-collapse: collapse">
                        <thead>
                            <tr>
                                <th colspan="2" style="text-align: left">
                                    <div style="font-size: 14px; font-weight: bold; color: #333; margin-bottom: 10px">
                                        CHI TIẾT VÉ (TICKET DETAILS)
                                    </div>
                                </th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr style="border-bottom: 1px dashed #ddd">
                                <td style="font-size: 12px; color: #555555; padding: 12px 0; text-align: left;">
                                    Phòng<br /><span style="font-size: 10px; color: #888888">Hall</span></td>
                                <td style="color: #000000; font-size: 12px; font-weight: bold; text-align: right; padding: 12px 0;">
                                    {{ $ticket->room->name }}
                                </td>
                            </tr>
                            <tr style="border-bottom: 1px dashed #ddd">
                                <td style="font-size: 12px; color: #555555; padding: 12px 0; text-align: left;">
                                    Ghế<br /><span style="font-size: 10px; color: #888888">Seat</span></td>
                                <td style="color: #000000; font-size: 12px; font-weight: bold; text-align: right; padding: 12px 0;">
                                    @foreach ($ticket->ticketSeats as $ticketSeat)
                                        {{ $ticketSeat->seat->name }}@if (!$loop->last)
                                            ,
                                        @endif
                                    @endforeach
                                </td>
                            </tr>
                            <tr style="border-bottom: 1px dashed #ddd">
                                <td style="font-size: 12px; color: #555555; padding: 12px 0; text-align: left;">Thời gian
                                    thanh toán<br /><span style="font-size: 10px; color: #888888">Payment time</span></td>
                                <td style="color: #000000; font-size: 12px; font-weight: bold; text-align: right; padding: 12px 0;">
                                    {{ \Carbon\Carbon::parse($ticket->created_at)->format('d/m/Y H:i:s') }}
                                </td>
                            </tr>
                            <tr style="border-bottom: 1px dashed #ddd">
                                <td style="font-size: 12px; color: #555555; padding: 12px 0; text-align: left;">
                                    Tiền vé<br /><span style="font-size: 10px; color: #888">Ticket amount</span>
                                </td>
                                <td style="font-size: 12px; font-weight: bold; text-align: right; padding: 12px 0;">
                                    {{ isset($paymentData['seat_amount']) ? number_format($paymentData['seat_amount'], 0, ',', '.') : 'NULL' }}
                                    VND
                                </td>
                            </tr>

                            <tr style="border-bottom: 1px dashed #ddd">
                                <td style="font-size: 12px; color: #555; padding: 12px 0; text-align: left;">
                                    Tiền combo bỏng nước<br /><span style="font-size: 10px; color: #888">Concession
                                        amount</span>
                                </td>
                                <td style="font-size: 12px; font-weight: bold; text-align: right; padding: 12px 0;">
                                    {{ isset($paymentData['combo_amount']) ? number_format($paymentData['combo_amount'], 0, ',', '.') : 'NULL' }}
                                    VND
                                </td>
                            </tr>

                            <tr style="border-bottom: 1px dashed #ddd">
                                <td style="font-size: 12px; color: #555555; padding: 12px 0; text-align: left;">
                                    Tổng tiền<br /><span style="font-size: 10px; color: #888888">Total amount</span>
                                </td>
                                <td style="color: #000000; font-size: 12px; font-weight: bold; text-align: right; padding: 12px 0;">
                                    {{ isset($paymentData['total_price_before_discount']) ? number_format($paymentData['total_price_before_discount'], 0, ',', '.') : 'NULL' }}
                                    VND
                                </td>
                            </tr>

                            <tr style="border-bottom: 1px dashed #ddd">
                                <td style="font-size: 12px; color: #555555; padding: 12px 0; text-align: left;">
                                    Số tiền giảm giá<br /><span style="font-size: 10px; color: #888888">Discount
                                        amount</span>
                                </td>
                                <td style="color: #000000; font-size: 12px; font-weight: bold; text-align: right; padding: 12px 0;">
                                    {{ isset($paymentData['total_discount']) ? number_format($paymentData['total_discount'], 0, ',', '.') : 'NULL' }}
                                    VND
                                </td>
                            </tr>

                            <tr>
                                <td style="font-size: 12px; color: #555555; padding: 12px 0; text-align: left;">
                                    Số tiền thanh toán<br /><span style="font-size: 10px; color: #888888">Payment
                                        amount</span>
                                </td>
                                <td style="color: #000000; font-size: 12px; font-weight: bold; text-align: right; padding: 12px 0;">
                                    {{ number_format($ticket->total_price, 0, ',', '.') }} VND
                                </td>
                            </tr>
                            <!-- Divider -->
                            <tr>
                                <td colspan="2">
                                    <img src="https://res.cloudinary.com/du2m9ntlf/image/upload/v1741266249/beecinema/hazj8ufm7n2d25ni6vie.png"
                                        alt="Divider" style="width: 100%" />
                                </td>
                            </tr>
                            <!-- Ghi chú -->
                            <tr>
                                <td colspan="2">
                                    <div style="padding: 15px 20px">
                                        <div style="font-size: 14px; font-weight: bold; margin-bottom: 10px">
                                            Lưu ý / Note:
                                        </div>
                                        <div style="font-size: 12px; color: #333; line-height: 1.5">
                                            Vé đã mua không thể hủy, đổi hoặc trả lại. Vui lòng tới rạp theo
                                            lịch đã mua hoặc trước giờ chiếu ít nhất 15-30 phút để nhận vé.
                                            Vé chỉ có giá trị cho suất chiếu đã mua. Cảm ơn quý khách đã sử
                                            dụng dịch vụ của VNPAY. Chúc bạn xem phim vui vẻ!
                                        </div>
                                        <div style="font-size: 11px; color: #666; font-style: italic; margin-top: 10px;">
                                            The purchased movie ticket cannot be cancelled, exchanged or
                                            refunded. If you have any question or problems with this order,
                                            you can contact Theater Manager or see our Condition to purchase
                                            and use movie tickets for more information. Thank you for
                                            choosing VNPAY ticket and enjoy the movie!
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <!-- Thông tin hỗ trợ -->
                            <tr>
                                <td colspan="2" style="text-align: center; padding: 15px 20px; font-size: 12px; color: #333;">
                                    Vui lòng liên hệ bộ phận chăm sóc khách hàng để được hỗ trợ
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <table style="width: 50%; margin: 0 auto">
                                        <tr>
                                            <td><img src="https://res.cloudinary.com/du2m9ntlf/image/upload/v1741266249/beecinema/w185gonv7tpb9eoluzfb.png"
                                                    alt="Phone" width="30" /></td>
                                            <td><b>*6789</b></td>
                                            <td><img src="https://res.cloudinary.com/du2m9ntlf/image/upload/v1741266249/beecinema/xxlsjpyobo2ntfkqajnm.png"
                                                    alt="Email" width="30" /></td>
                                            <td><a href="mailto:cs@vnpay.vn"><b>cs@vnpay.vn</b></a></td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
        </tbody>
    </table>
</body>

</html>
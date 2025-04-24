<!DOCTYPE html>
<html lang="vi">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Thông báo điểm sắp hết hạn</title>
  </head>
  <body
    style="
      margin: 0;
      padding: 0;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background-color: #ffffff;
    "
  >
    <table
      role="presentation"
      cellspacing="0"
      cellpadding="0"
      border="0"
      width="100%"
      style="max-width: 600px; margin: 0 auto"
    >
      <!-- Top Border -->
      <tr>
        <td
          style="
            height: 8px;
            background: linear-gradient(to right, #ff7e5f, #feb47b);
          "
        ></td>
      </tr>

      <!-- Header -->
      <tr>
        <td style="padding: 30px 30px 20px">
          <table
            role="presentation"
            cellspacing="0"
            cellpadding="0"
            border="0"
            width="100%"
          >
            <tr>
              <td width="50%">
                <img
                  src="https://res.cloudinary.com/du2m9ntlf/image/upload/v1741266249/beecinema/vfzfjzcls6udqqpacqt2.png"
                  alt="Logo"
                  style="max-width: 150px; height: auto"
                />
              </td>
              <td
                width="50%"
                style="text-align: right; color: #888888; font-size: 12px"
              >
                {{ $expiredAt->format('d/m/Y') }}
              </td>
            </tr>
          </table>
        </td>
      </tr>

      <!-- Main Content -->
      <tr>
        <td style="padding: 0 30px">
          <table
            role="presentation"
            cellspacing="0"
            cellpadding="0"
            border="0"
            width="100%"
          >
            <tr>
              <td style="padding-bottom: 30px">
                <h1
                  style="
                    margin: 0;
                    font-size: 28px;
                    font-weight: 300;
                    color: #333333;
                  "
                >
                  Điểm của bạn
                  <span style="color: #ff7e5f; font-weight: 600"
                    >sắp hết hạn</span
                  >
                </h1>
              </td>
            </tr>
          </table>
        </td>
      </tr>

      <!-- Points Card -->
      <tr>
        <td style="padding: 0 30px 30px">
          <table
            role="presentation"
            cellspacing="0"
            cellpadding="0"
            border="0"
            width="100%"
            style="
              background: linear-gradient(135deg, #ff7e5f, #feb47b);
              border-radius: 12px;
              overflow: hidden;
            "
          >
            <tr>
              <td style="padding: 30px; text-align: center; color: #ffffff">
<p style="margin: 0 0 5px; font-size: 16px; opacity: 0.9">
                  Số điểm sắp hết hạn
                </p>
                <h2 style="margin: 0 0 20px; font-size: 48px; font-weight: 700">
                  {{ $points }}
                </h2>
                <p style="margin: 0; font-size: 14px; opacity: 0.8">
                  Hạn sử dụng: {{ $expiredAt->format('d/m/Y') }}
                </p>
              </td>
            </tr>
          </table>
        </td>
      </tr>

      <!-- Message -->
      <tr>
        <td style="padding: 0 30px 30px">
          <table
            role="presentation"
            cellspacing="0"
            cellpadding="0"
            border="0"
            width="100%"
          >
            <tr>
              <td
                style="
                  padding: 0 0 20px;
                  color: #555555;
                  font-size: 16px;
                  line-height: 1.6;
                "
              >
                <p>Xin chào,</p>
                <p>
                  Chúng tôi nhận thấy bạn có một số điểm sắp hết hạn trong tài
                  khoản của mình. Đừng để những điểm thưởng này trôi qua mà
                  không sử dụng!
                </p>
                <p>
                  Hãy dùng điểm của bạn để đặt vé xem phim với giá ưu đãi hơn.
                </p>
              </td>
            </tr>
          </table>
        </td>
      </tr>

      <!-- CTA Button -->
      <tr>
        <td style="padding: 0 30px 40px">
          <table
            role="presentation"
            cellspacing="0"
            cellpadding="0"
            border="0"
            style="margin: 0 auto"
          >
            <tr>
              <td
                style="
                  background-color: #333333;
                  border-radius: 30px;
                  padding: 15px 40px;
                "
              >
              <a
              href="{{ config('app.frontend_url')}}"
              target="_blank"
              style="
                color: #ffffff;
                text-decoration: none;
                font-weight: 600;
                font-size: 16px;
                display: inline-block;
              "
            >
              SỬ DỤNG ĐIỂM NGAY
            </a>
              </td>
            </tr>
          </table>
        </td>
      </tr>

      <!-- Divider -->
      <tr>
        <td style="padding: 0 30px">
          <table
            role="presentation"
            cellspacing="0"
            cellpadding="0"
            border="0"
            width="100%"
          >
            <tr>
              <td style="height: 1px; background-color: #eeeeee"></td>
            </tr>
          </table>
        </td>
      </tr>
      <!-- Footer -->
      <tr>
        <td
          style="padding: 30px; background-color: #f9f9f9; text-align: center"
        >
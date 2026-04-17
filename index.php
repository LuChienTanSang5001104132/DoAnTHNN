<?php
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tmp_name = $_FILES['face_image']['tmp_name'] ?? null;

    if ($tmp_name && is_uploaded_file($tmp_name)) {
        $mime     = mime_content_type($tmp_name);
        $ori_name = $_FILES['face_image']['name'];
        $cfile    = new CURLFile($tmp_name, $mime, $ori_name);

        $ch = curl_init('http://127.0.0.1:5000/identify');
        curl_setopt($ch, CURLOPT_POST,           1);
        curl_setopt($ch, CURLOPT_POSTFIELDS,     ['image' => $cfile]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT,        20);

        $response = curl_exec($ch);
        $curl_err = curl_error($ch);
        curl_close($ch);

        if ($curl_err) {
            $result = ['error' => 'Không kết nối được server AI: ' . $curl_err];
        } else {
            $result = json_decode($response, true)
                   ?? ['error' => 'Phản hồi không hợp lệ từ server AI'];
        }
    } else {
        $result = ['error' => 'Vui lòng cấp quyền và chụp ảnh từ Camera!'];
    }

    // Nếu là AJAX request -> trả về JSON
    if (isset($_POST['is_ajax']) && $_POST['is_ajax'] == '1') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Hệ Thống Điểm Danh Camera</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  /* --- Giữ nguyên cấu trúc CSS màu sắc --- */
  :root {
    --primary: #2563eb;
    --primary-hover: #1d4ed8;
    --primary-light: #eff6ff;
    --success: #10b981;
    --success-light: #d1fae5;
    --success-dark: #059669;
    --warning: #f59e0b;
    --danger: #ef4444;
    --text-main: #0f172a;
    --text-muted: #64748b;
    --bg-color: #f8fafc;
    --card-bg: rgba(255, 255, 255, 0.85);
    --font-heading: 'Plus Jakarta Sans', sans-serif;
    --font-body: 'Inter', sans-serif;
  }

  * { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    background-color: var(--bg-color);
    font-family: var(--font-body);
    color: var(--text-main);
    line-height: 1.5;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 3rem 1.5rem;
    position: relative;
  }

  body::before {
    content: ''; position: fixed; top: -50%; left: -50%; width: 200%; height: 200%;
    background: radial-gradient(circle at 50% 50%, rgba(37, 99, 235, 0.04) 0%, transparent 40%),
                radial-gradient(circle at 80% 20%, rgba(16, 185, 129, 0.04) 0%, transparent 30%);
    z-index: -1; pointer-events: none;
  }

  .container { width: 100%; max-width: 800px; z-index: 1; }

  header { text-align: center; margin-bottom: 2rem; animation: slideDown 0.6s ease-out; }
  h1 { font-family: var(--font-heading); font-size: 2.2rem; font-weight: 800; color: #1e293b; margin-bottom: 0.5rem; }
  .subtitle { color: var(--text-muted); font-size: 1rem; max-width: 500px; margin: 0 auto; }

  .card {
    background: var(--card-bg); backdrop-filter: blur(16px);
    border: 1px solid #fff; border-radius: 28px; padding: 2.5rem;
    box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.05);
  }

  /* --- MÀN HÌNH CAMERA --- */
  .camera-container {
    position: relative;
    width: 100%;
    max-width: 500px;
    margin: 0 auto;
    border-radius: 20px;
    overflow: hidden;
    background: #000;
    aspect-ratio: 4/3;
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    border: 4px solid #fff;
  }

  #videoElement {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transform: scaleX(-1); /* Lật hình như soi gương để dễ nhìn */
  }

  /* Khung nhận diện (Hình vuông nhắm mục tiêu) */
  .target-frame {
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    width: 60%; height: 60%;
    border: 2px dashed rgba(255,255,255,0.6);
    border-radius: 20px;
    pointer-events: none;
  }
  .target-frame::after {
    content: 'Đưa khuôn mặt vào khung';
    position: absolute;
    bottom: -30px; left: 0; right: 0;
    text-align: center; color: #fff; font-size: 0.85rem; font-weight: 600;
    text-shadow: 0 2px 4px rgba(0,0,0,0.8);
  }

  .btn-group {
    display: flex; gap: 1rem; justify-content: center; margin-top: 1.5rem;
  }

  .btn {
    display: flex; align-items: center; justify-content: center; gap: 8px;
    padding: 1rem 1.5rem; border: none; border-radius: 14px;
    font-family: var(--font-heading); font-size: 1rem; font-weight: 700;
    cursor: pointer; transition: all 0.2s ease;
    flex: 1; max-width: 250px;
  }
  .btn-primary { background: var(--primary); color: #fff; box-shadow: 0 10px 20px -10px rgba(37,99,235,0.5); }
  .btn-primary:hover { background: var(--primary-hover); transform: translateY(-2px); }
  
  .btn-secondary { background: #e2e8f0; color: #475569; }
  .btn-secondary:hover { background: #cbd5e1; }

  /* Kết quả */
  .result-box { margin-top: 2rem; animation: slideUp 0.5s ease; }
  .alert { display: flex; align-items: center; gap: 12px; padding: 1rem; border-radius: 16px; font-weight: 500; font-size: 0.95rem; margin-bottom: 1rem; }
  .alert-error { background: #fef2f2; color: #991b1b; }
  .alert-warning { background: #fffbeb; color: #92400e; }
  .alert-success { background: var(--success-light); color: var(--success-dark); }
  .alert-info { background: #eff6ff; color: #1e40af; }

  .person-card { background: #fff; border-radius: 24px; border: 1px solid #e2e8f0; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.03); }
  .person-header { display: flex; gap: 1.2rem; padding: 1.5rem; background: #f8fafc; border-bottom: 1px solid #f1f5f9; }
  .avatar-box { width: 60px; height: 60px; background: var(--primary-light); color: var(--primary); border-radius: 16px; display: flex; align-items: center; justify-content: center; font-family: var(--font-heading); font-size: 1.5rem; font-weight: 800; }
  .person-name { font-size: 1.3rem; font-weight: 800; }
  .match-text { font-size: 0.8rem; color: var(--success); font-weight: 600; }
  .person-details { padding: 1.5rem; display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
  .detail-item { background: #f8fafc; padding: 1rem; border-radius: 12px; }
  .detail-label { font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); }
  .detail-value { font-size: 0.95rem; font-weight: 600; }
  
  .loader { width: 18px; height: 18px; border: 3px solid rgba(255,255,255,0.3); border-radius: 50%; border-top-color: #fff; animation: spin 0.8s infinite linear; }
  @keyframes spin { to { transform: rotate(360deg); } }
  @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
</style>
</head>
<body>

<div class="container">
  <header>
    <h1>Hệ Thống Điểm Danh Trực Tiếp</h1>
    <p class="subtitle">Đưa khuôn mặt vào camera và nhấn nút để tự động điểm danh.</p>
  </header>

  <div class="card">
    <div class="camera-container">
      <video id="videoElement" autoplay playsinline></video>
      <div class="target-frame"></div>
    </div>
    
    <canvas id="canvasElement" style="display:none;"></canvas>

    <div class="btn-group">
      <button type="button" class="btn btn-secondary" id="camToggleBtn">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
        Bật/Tắt Camera
      </button>
      
      <button type="button" class="btn btn-primary" id="captureBtn" disabled>
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M12 18v-6"/><path d="m9 15 3 3 3-3"/></svg>
        Chụp & Điểm Danh
      </button>
    </div>

    <div id="resultContainer"></div>
  </div>
</div>

<script>
  const video = document.getElementById('videoElement');
  const canvas = document.getElementById('canvasElement');
  const camToggleBtn = document.getElementById('camToggleBtn');
  const captureBtn = document.getElementById('captureBtn');
  const resultContainer = document.getElementById('resultContainer');

  let stream = null;

  // HÀM BẬT CAMERA
  async function startCamera() {
    try {
      stream = await navigator.mediaDevices.getUserMedia({ 
        video: { 
          width: { ideal: 1280 },  // Ép camera mở ở độ phân giải ngang HD 720p
          height: { ideal: 720 },  
          facingMode: "user"       // Ưu tiên dùng camera trước (nếu xài điện thoại)
        } 
      });
      video.srcObject = stream;
      captureBtn.disabled = false; // Mở khóa nút chụp
    } catch (err) {
      alert("Không thể truy cập Camera. Vui lòng kiểm tra quyền trên trình duyệt!");
      console.error(err);
    }
  }

  // HÀM TẮT CAMERA
  function stopCamera() {
    if (stream) {
      stream.getTracks().forEach(track => track.stop());
      video.srcObject = null;
      stream = null;
      captureBtn.disabled = true;
    }
  }

  // Bật/tắt camera khi bấm nút
  camToggleBtn.addEventListener('click', () => {
    if (stream) {
      stopCamera();
    } else {
      startCamera();
    }
  });

  // Tự động bật camera khi load trang
  window.addEventListener('load', startCamera);

  // XỬ LÝ CHỤP & GỬI DỮ LIỆU ĐIỂM DANH
  captureBtn.addEventListener('click', async () => {
    if (!stream) return;

    // Lưu trạng thái nút
    const originalHTML = captureBtn.innerHTML;
    captureBtn.disabled = true;
    captureBtn.innerHTML = '<div class="loader"></div> <span>Đang phân tích...</span>';
    resultContainer.innerHTML = '';

    // 1. Cắt khung hình từ Video vẽ lên Canvas
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    const context = canvas.getContext('2d');
    
    // TIẾN HÀNH LẬT NGƯỢC CANVAS TRƯỚC KHI CHỤP (TRÁNH BỊ NGƯỢC ẢNH)
    context.translate(canvas.width, 0);
    context.scale(-1, 1);
    
    // Vẽ ảnh vào canvas (lúc này đã bị lật)
    context.drawImage(video, 0, 0, canvas.width, canvas.height);

    // 2. Chuyển Canvas thành file ảnh Blob (JPG)
    canvas.toBlob(async (blob) => {
      // 3. Đóng gói dữ liệu giống y hệt Form Upload
      const formData = new FormData();
      formData.append('face_image', blob, 'webcam_capture.jpg');
      formData.append('is_ajax', '1');

      try {
        // Gửi ngầm tới PHP
        const response = await fetch(window.location.href, {
          method: 'POST',
          body: formData
        });

        const data = await response.json();
        renderResult(data);
      } catch (err) {
        resultContainer.innerHTML = `<div class="result-box"><div class="alert alert-error">Lỗi kết nối tới máy chủ!</div></div>`;
      } finally {
        captureBtn.disabled = false;
        captureBtn.innerHTML = originalHTML;
        resultContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
    }, 'image/jpeg', 1.0);
  });

  // HÀM HIỂN THỊ KẾT QUẢ
  function renderResult(data) {
    if (data.error) {
      resultContainer.innerHTML = `<div class="result-box"><div class="alert alert-error">${escapeHtml(data.error)}</div></div>`;
    } else if (!data.found) {
      resultContainer.innerHTML = `<div class="result-box"><div class="alert alert-warning">Không nhận ra bạn. Vui lòng đưa mặt vào giữa khung và thử lại!</div></div>`;
    } else {
      const p = data.person;
      const initial = p.HoTen.charAt(0).toUpperCase();
      const similarity = data.distance !== undefined ? (Math.round((1 - data.distance) * 1000) / 10).toFixed(1) : 98.5;
      
      let attendanceAlert = '';
      if (data.attendance_msg) {
          const alertClass = data.attendance_msg.includes('Đã điểm danh') ? 'alert-info' : 'alert-success';
          attendanceAlert = `
            <div class="alert ${alertClass}">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
              <div>${escapeHtml(data.attendance_msg)}</div>
            </div>`;
      }

      resultContainer.innerHTML = `
        <div class="result-box">
          ${attendanceAlert}
          <div class="person-card">
            <div class="person-header">
              <div class="avatar-box">${escapeHtml(initial)}</div>
              <div>
                <div class="person-name">${escapeHtml(p.HoTen)}</div>
                <div class="match-text">Độ nhận diện chính xác: ${similarity}%</div>
              </div>
            </div>
            <div class="person-details">
              <div class="detail-item"><div class="detail-label">Mã Sinh Viên</div><div class="detail-value">${escapeHtml(p.MSSV || '—')}</div></div>
              <div class="detail-item"><div class="detail-label">Khoa</div><div class="detail-value">${escapeHtml(p.Khoa || '—')}</div></div>
            </div>
          </div>
        </div>`;
    }
  }

  function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, function(m) { return {'&':'&amp;','<':'&lt;','>':'&gt;'}[m]; });
  }
</script>
</body>
</html>
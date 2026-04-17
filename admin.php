<?php
$serverName = "localhost";
$database   = "THONGTIN";

try {
    $conn = new PDO("odbc:Driver={ODBC Driver 17 for SQL Server};Server=localhost;Database=THONGTIN;Trusted_Connection=yes;");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("<h2 style='color:red; text-align:center;'>Lỗi kết nối CSDL!</h2> <br>" . $e->getMessage());
}

// Hàm giải mã thông minh tự động lật Byte
function decodeVN($bin) {
    if (empty($bin)) return '';
    
    // 1. Nếu PHP trả về chuỗi Hexadecimal (chỉ toàn số và chữ A-F), ta phải dịch nó về Byte thật trước
    if (ctype_xdigit($bin)) {
        $bin = hex2bin($bin);
    }
    
    // 2. Dịch từ chuẩn SQL Server (UTF-16LE) sang chuẩn Web (UTF-8)
    return mb_convert_encoding($bin, 'UTF-8', 'UTF-16LE');
}

$stmtTotal = $conn->query("SELECT COUNT(*) FROM SinhVien");
$totalStudents = $stmtTotal->fetchColumn();

$stmtToday = $conn->query("SELECT COUNT(*) FROM DIEM_DANH WHERE CAST(ThoiGian AS DATE) = CAST(GETDATE() AS DATE)");
$totalToday = $stmtToday->fetchColumn();

$stmtChart = $conn->query("
    SELECT CAST(ThoiGian AS DATE) as Ngay, COUNT(*) as SoLuong 
    FROM DIEM_DANH 
    WHERE ThoiGian >= DATEADD(day, -7, GETDATE())
    GROUP BY CAST(ThoiGian AS DATE)
    ORDER BY Ngay ASC
");
$chartData = $stmtChart->fetchAll(PDO::FETCH_ASSOC);

$chartLabels = [];
$chartValues = [];
foreach ($chartData as $row) {
    $dateObj = DateTime::createFromFormat('Y-m-d', $row['Ngay']);
    $chartLabels[] = $dateObj ? $dateObj->format('d/m') : $row['Ngay'];
    $chartValues[] = $row['SoLuong'];
}

$stmtList = $conn->query("
    SELECT TOP 50 
        d.ID_DiemDanh, 
        CAST(sv.HoTen AS VARBINARY(MAX)) as HoTen_Bin, 
        d.MSSV, 
        CAST(sv.Khoa AS VARBINARY(MAX)) as Khoa_Bin, 
        d.ThoiGian, 
        CAST(d.TrangThai AS VARBINARY(MAX)) as TrangThai_Bin, 
        d.AnhMinhChung 
    FROM DIEM_DANH d 
    JOIN SinhVien sv ON d.MSSV = sv.MSSV 
    ORDER BY d.ThoiGian DESC
");
$records = $stmtList->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Hệ Thống Điểm Danh</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
    :root {
        --primary: #2563eb; --primary-light: #eff6ff;
        --success: #10b981; --success-light: #d1fae5; --success-dark: #059669;
        --bg-color: #f8fafc; --card-bg: #ffffff;
        --text-main: #0f172a; --text-muted: #64748b;
        --border: #e2e8f0;
        --font-heading: 'Plus Jakarta Sans', sans-serif;
        --font-body: 'Inter', sans-serif;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { background-color: var(--bg-color); font-family: var(--font-body); color: var(--text-main); line-height: 1.5; padding: 2rem; }
    
    .navbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; background: var(--card-bg); padding: 1rem 2rem; border-radius: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
    .navbar h1 { font-family: var(--font-heading); font-size: 1.5rem; font-weight: 800; color: var(--primary); display: flex; align-items: center; gap: 10px; }
    .nav-links a { text-decoration: none; font-weight: 600; background: var(--primary-light); padding: 10px 20px; border-radius: 12px; color: var(--primary); transition: 0.2s; }
    .nav-links a:hover { background: var(--primary); color: #fff; }

    .grid-container { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem; }
    
    .stat-card { background: var(--card-bg); padding: 2rem; border-radius: 24px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.02); border: 1px solid var(--border); display: flex; flex-direction: column; justify-content: center; position: relative; overflow: hidden; }
    .stat-card::before { content:''; position: absolute; top:0; left:0; width: 6px; height: 100%; background: var(--primary); border-radius: 6px 0 0 6px; }
    .stat-card:nth-child(2)::before { background: var(--success); }
    .stat-card:nth-child(3)::before { background: #8b5cf6; }
    
    .stat-title { font-size: 0.9rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem; }
    .stat-value { font-family: var(--font-heading); font-size: 2.5rem; font-weight: 800; color: var(--text-main); }

    .content-grid { display: grid; grid-template-columns: 1fr; gap: 2rem; }
    
    .panel { background: var(--card-bg); padding: 2rem; border-radius: 24px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.02); border: 1px solid var(--border); }
    .panel-title { font-family: var(--font-heading); font-size: 1.25rem; font-weight: 700; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; }

    .table-container { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; text-align: left; }
    th { font-size: 0.85rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; border-bottom: 2px solid var(--border); padding: 1rem; white-space: nowrap; }
    td { padding: 1rem; border-bottom: 1px solid var(--border); font-weight: 500; font-size: 0.95rem; vertical-align: middle; }
    tr:hover td { background-color: #f8fafc; }
    
    .proof-img { width: 50px; height: 50px; object-fit: cover; border-radius: 10px; cursor: pointer; border: 2px solid var(--primary-light); transition: 0.2s; }
    .proof-img:hover { transform: scale(1.1); box-shadow: 0 4px 12px rgba(37,99,235,0.2); }
    
    .status-badge { display: inline-block; padding: 6px 12px; border-radius: 100px; font-size: 0.75rem; font-weight: 700; background: var(--success-light); color: var(--success-dark); }

    #imageModal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 999; justify-content: center; align-items: center; padding: 2rem; backdrop-filter: blur(5px); }
    #imageModal img { max-width: 100%; max-height: 90vh; border-radius: 20px; box-shadow: 0 25px 50px rgba(0,0,0,0.5); }
    .close-modal { position: absolute; top: 20px; right: 30px; color: #fff; font-size: 3rem; cursor: pointer; font-family: sans-serif; font-weight: 300; }
</style>
</head>
<body>

<div class="navbar">
    <h1>
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg>
        Trang điều kiển của Admin
    </h1>
    <div class="nav-links">
        <a href="index.php">← Quay lại Màn hình Điểm danh</a>
    </div>
</div>

<div class="grid-container">
    <div class="stat-card">
        <div class="stat-title">Tổng số Sinh viên</div>
        <div class="stat-value"><?= number_format($totalStudents) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-title">Lượt Điểm danh Hôm nay</div>
        <div class="stat-value"><?= number_format($totalToday) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-title">Trạng thái Hệ thống</div>
        <div class="stat-value" style="color: var(--success);">Đang hoạt động</div>
    </div>
</div>

<div class="content-grid">
    <div class="panel">
        <div class="panel-title">Lưu lượng Điểm danh (7 ngày gần nhất)</div>
        <div style="height: 300px;">
            <canvas id="attendanceChart"></canvas>
        </div>
    </div>

    <div class="panel">
        <div class="panel-title">
            Lịch sử Điểm danh mới nhất
            <span style="font-size: 0.9rem; font-weight: 500; color: var(--text-muted);">Hiển thị 50 lượt gần nhất</span>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Ảnh</th>
                        <th>Họ và Tên</th>
                        <th>MSSV</th>
                        <th>Khoa / Ngành</th>
                        <th>Thời Gian Điểm Danh</th>
                        <th>Trạng Thái</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($records) > 0): ?>
                        <?php foreach($records as $row): 
                            $timeStr = ($row['ThoiGian'] instanceof DateTime) 
                                     ? $row['ThoiGian']->format('H:i:s - d/m/Y') 
                                     : date('H:i:s - d/m/Y', strtotime($row['ThoiGian']));
                                     
                            // TIẾN HÀNH GIẢI MÃ
                            $hoTen = decodeVN($row['HoTen_Bin']);
                            $khoa = decodeVN($row['Khoa_Bin']);
                            $trangThai = decodeVN($row['TrangThai_Bin']);
                        ?>
                        <tr>
                            <td>
                                <?php if (!empty($row['AnhMinhChung'])): ?>
                                    <img src="Save_Image/<?= htmlspecialchars($row['AnhMinhChung']) ?>" 
                                         class="proof-img" 
                                         onclick="openModal(this.src)" 
                                         alt="Proof">
                                <?php else: ?>
                                    <span style="color: #ccc;">Không có</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-weight: 700; color: var(--text-main);"><?= htmlspecialchars($hoTen) ?></td>
                            <td><?= htmlspecialchars($row['MSSV']) ?></td>
                            <td style="color: var(--text-muted);"><?= htmlspecialchars($khoa) ?></td>
                            <td><?= $timeStr ?></td>
                            <td><span class="status-badge"><?= htmlspecialchars($trangThai) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 2rem; color: var(--text-muted);">Hệ thống chưa có dữ liệu điểm danh nào.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="imageModal">
    <span class="close-modal" onclick="closeModal()">&times;</span>
    <img id="modalImg" src="">
</div>

<script>
    const ctx = document.getElementById('attendanceChart').getContext('2d');
    
    const chartLabels = <?= json_encode($chartLabels) ?>;
    const chartValues = <?= json_encode($chartValues) ?>;

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartLabels.length > 0 ? chartLabels : ['Chưa có dữ liệu'],
            datasets: [{
                label: 'Số lượt điểm danh',
                data: chartValues.length > 0 ? chartValues : [0],
                borderColor: '#2563eb',
                backgroundColor: 'rgba(37, 99, 235, 0.1)',
                borderWidth: 3,
                pointBackgroundColor: '#fff',
                pointBorderColor: '#2563eb',
                pointBorderWidth: 2,
                pointRadius: 5,
                fill: true,
                tension: 0.4 
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 } },
                x: { grid: { display: false } }
            }
        }
    });

    const modal = document.getElementById('imageModal');
    const modalImg = document.getElementById('modalImg');

    function openModal(imageSrc) {
        modal.style.display = 'flex';
        modalImg.src = imageSrc;
    }

    function closeModal() {
        modal.style.display = 'none';
        modalImg.src = '';
    }

    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeModal();
        }
    });
</script>
</body>
</html>
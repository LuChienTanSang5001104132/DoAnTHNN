from flask import Flask, request, jsonify
from facenet_pytorch import MTCNN, InceptionResnetV1
import torch
import torch.nn.functional as F
from PIL import Image
from io import BytesIO
import os
import pickle
import pyodbc
from datetime import datetime # Thư viện xử lý thời gian

app = Flask(__name__)

# Tự động chọn thiết bị (GPU nếu có, không thì CPU)
device = torch.device('cuda' if torch.cuda.is_available() else 'cpu')
print(f"Đang sử dụng thiết bị: {device}")

# Khởi tạo MTCNN để phát hiện khuôn mặt và InceptionResnetV1 để tạo embedding
mtcnn = MTCNN(image_size=160, margin=20, device=device)
resnet = InceptionResnetV1(pretrained='vggface2').eval().to(device)

KNOWN_FACES_DIR = 'Faces'
CACHE_FILE = 'embeddings.pkl'
PROOF_DIR = 'Save_Image' # Thư mục lưu ảnh minh chứng
THRESHOLD = 0.2  # Ngưỡng nhận diện (Cosine Distance)

# Đảm bảo các thư mục cần thiết tồn tại
if not os.path.exists(KNOWN_FACES_DIR):
    os.makedirs(KNOWN_FACES_DIR)
if not os.path.exists(PROOF_DIR):
    os.makedirs(PROOF_DIR)

# ── Kết nối SQL Server ──────────────────────────────────────────
def get_conn():
    try:
        return pyodbc.connect(
            'DRIVER={ODBC Driver 17 for SQL Server};'
            'SERVER=localhost;'          
            'DATABASE=THONGTIN;'         
            'Trusted_Connection=yes;'    
        )
    except Exception as e:
        print(f"Lỗi kết nối SQL Server: {e}")
        return None

# ── Hàm Ghi Nhận Điểm Danh & Ảnh Minh Chứng ─────────────────────
def mark_attendance(mssv, image_filename):
    conn = get_conn()
    if conn is None: return False, "Lỗi kết nối CSDL"
    
    try:
        cursor = conn.cursor()
        
        # 1. Kiểm tra chống Spam (Xem 5 phút gần đây có điểm danh chưa)
        cursor.execute(
            """
            SELECT TOP 1 ThoiGian FROM DIEM_DANH 
            WHERE MSSV = ? 
            ORDER BY ThoiGian DESC
            """, 
            (mssv,)
        )
        last_record = cursor.fetchone()
        
        if last_record:
            last_time = last_record[0]
            now = datetime.now()
            # Tính khoảng cách thời gian (phút)
            diff_minutes = (now - last_time).total_seconds() / 60
            if diff_minutes < 5: # Nếu chưa qua 5 phút
                conn.close()
                return True, f"Đã điểm danh trước đó vào lúc {last_time.strftime('%H:%M:%S')}"

        # 2. Nếu hợp lệ, ghi điểm danh kèm TÊN ẢNH MINH CHỨNG vào DB
        cursor.execute(
            "INSERT INTO DIEM_DANH (MSSV, TrangThai, AnhMinhChung) VALUES (?, N'Hợp lệ', ?)",
            (mssv, image_filename)
        )
        conn.commit()
        conn.close()
        
        now_str = datetime.now().strftime('%H:%M:%S %d/%m/%Y')
        return True, f"Điểm danh thành công lúc {now_str}"
        
    except Exception as e:
        print(f"Lỗi khi điểm danh: {e}")
        return False, "Lỗi hệ thống khi ghi nhận điểm danh"

# ── Tạo embedding từ ảnh ────────────────────────────────────────
def get_embedding(img_path_or_file):
    try:
        if isinstance(img_path_or_file, str):
            img = Image.open(img_path_or_file).convert('RGB')
        else:
            img = Image.open(BytesIO(img_path_or_file)).convert('RGB')
            
        face = mtcnn(img)
        if face is not None:
            with torch.no_grad():
                emb = resnet(face.unsqueeze(0).to(device))
                emb = F.normalize(emb, p=2, dim=1)
            return emb.detach()
    except Exception as e:
        print(f"  Lỗi xử lý ảnh: {e}")
    return None

# ── Load toàn bộ ảnh trong Faces/ khi khởi động ─────────────────
def load_known_faces():
    if os.path.exists(CACHE_FILE) and os.path.getsize(CACHE_FILE) > 0:
        print("Đang load dữ liệu khuôn mặt từ cache...")
        try:
            with open(CACHE_FILE, 'rb') as f:
                return pickle.load(f)
        except Exception as e:
            print(f"File cache bị lỗi ({e}). Tiến hành tạo lại...")
            os.remove(CACHE_FILE)

    print("Đang quét thư mục Faces để tính toán embedding (lần đầu)...")
    db = {}
    for person_id in os.listdir(KNOWN_FACES_DIR):
        person_dir = os.path.join(KNOWN_FACES_DIR, person_id)
        if not os.path.isdir(person_dir):
            continue

        embs = []
        for img_file in os.listdir(person_dir):
            if img_file.lower().endswith(('.png', '.jpg', '.jpeg', '.webp')):
                img_path = os.path.join(person_dir, img_file)
                emb = get_embedding(img_path)
                if emb is not None:
                    embs.append(emb)

        if embs:
            db[person_id] = torch.mean(torch.stack(embs), dim=0)
            print(f"  ✓ Đã nạp {person_id}: {len(embs)} ảnh")
        else:
            print(f"  ✗ {person_id}: Không tìm thấy mặt!")

    if db:
        with open(CACHE_FILE, 'wb') as f:
            pickle.dump(db, f)
        print(f"Đã lưu cache thành công. Tổng cộng: {len(db)} người.")
    return db

# ── Tra thông tin từ SQL Server ──────────────────────────────────
def get_person_info(person_id):
    conn = get_conn()
    if conn is None: return None
    try:
        cursor = conn.cursor()
        cursor.execute(
            'SELECT id, HoTen, MSSV, Khoa, Email, sdt, Diachi '
            'FROM SinhVien WHERE id = ?',
            (person_id,)
        )
        row = cursor.fetchone()
        conn.close()
        if row:
            return {
                'id'    : row[0],
                'HoTen' : row[1],
                'MSSV'  : row[2],
                'Khoa'  : row[3],
                'Email' : row[4],
                'sdt'   : row[5],
                'Diachi': row[6],
            }
    except Exception as e:
        print(f"Lỗi truy vấn Database: {e}")
    return None

# Khởi tạo dữ liệu khuôn mặt
known_db = load_known_faces()

# ── API nhận diện ────────────────────────────────────────────────
@app.route('/identify', methods=['POST'])
def identify():
    if 'image' not in request.files:
        return jsonify({'error': 'Không nhận được file ảnh'}), 400

    file_bytes = request.files['image'].read()
    
    # Mở ảnh bằng PIL để tính embedding và lát nữa đem lưu
    img = Image.open(BytesIO(file_bytes)).convert('RGB')
    
    # Lấy khuôn mặt từ ảnh
    face = mtcnn(img)

    # 🟢 SỬA TẠI ĐÂY: Bắt lỗi không có khuôn mặt (do đeo khẩu trang, mũ bảo hiểm...)
    if face is None:
        return jsonify({
            'found': False,
            'error': 'Không nhận diện được khuôn mặt! Vui lòng tháo khẩu trang, mũ bảo hiểm và nhìn thẳng vào camera.'
        }), 400

    with torch.no_grad():
        test_emb = F.normalize(resnet(face.unsqueeze(0).to(device)), p=2, dim=1).detach()

    best_id, best_dist = None, float('inf')
    
    # So sánh với dữ liệu đã biết
    for person_id, known_emb in known_db.items():
        dist = 1 - F.cosine_similarity(known_emb, test_emb).item()
        if dist < best_dist:
            best_dist, best_id = dist, person_id

    print(f"Kết quả so khớp: ID={best_id}, Khoảng cách={best_dist:.4f}")

    if best_dist < THRESHOLD:
        info = get_person_info(best_id)
        if info:
            # 1. TẠO TÊN FILE ẢNH MINH CHỨNG
            now_str = datetime.now().strftime('%Y%m%d_%H%M%S')
            proof_filename = f"{info['MSSV']}_{now_str}.jpg"
            proof_path = os.path.join(PROOF_DIR, proof_filename)
            
            # 2. LƯU ẢNH CHỤP VÀO THƯ MỤC
            img.save(proof_path)

            # 3. GỌI HÀM ĐIỂM DANH, TRUYỀN TÊN ẢNH VÀO CSDL
            success, msg = mark_attendance(info['MSSV'], proof_filename)
            
            return jsonify({
                'found'   : True,
                'distance': round(best_dist, 4),
                'person'  : info,
                'attendance_msg': msg  
            })

    return jsonify({
        'found'  : False,
        'message': 'Khuôn mặt không khớp với bất kỳ ai trong hệ thống'
    })

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=False)
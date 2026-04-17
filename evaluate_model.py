import os
import torch
import torch.nn.functional as F
from PIL import Image
import pickle
from facenet_pytorch import MTCNN, InceptionResnetV1
from sklearn.metrics import classification_report, accuracy_score

# 1. Khởi tạo thiết bị và model (Giống y hệt cấu hình của bạn)
device = torch.device('cuda' if torch.cuda.is_available() else 'cpu')
print(f"Đang sử dụng thiết bị: {device}")

mtcnn = MTCNN(image_size=160, margin=20, device=device)
resnet = InceptionResnetV1(pretrained='vggface2').eval().to(device)

THRESHOLD = 0.2  # Ngưỡng nhận diện của bạn
CACHE_FILE = 'embeddings.pkl'
TEST_DIR = 'Test_Faces' 

# Kiểm tra xem có thư mục Test chưa
if not os.path.exists(TEST_DIR):
    print(f"Vui lòng tạo thư mục '{TEST_DIR}' và cho ảnh test vào!")
    exit()

# 2. Load cơ sở dữ liệu khuôn mặt đã được trích xuất
try:
    with open(CACHE_FILE, 'rb') as f:
        known_db = pickle.load(f)
except Exception as e:
    print(f"Lỗi đọc file {CACHE_FILE}. Hãy chắc chắn bạn đã chạy file chính ít nhất 1 lần để tạo file này. Lỗi: {e}")
    exit()

y_true = [] # Danh sách nhãn thực tế
y_pred = [] # Danh sách nhãn model dự đoán

print("\nĐang tiến hành test từng ảnh...")
print("-" * 50)

# 3. Quét qua toàn bộ ảnh trong Test_Faces
for true_id in os.listdir(TEST_DIR):
    person_dir = os.path.join(TEST_DIR, true_id)
    if not os.path.isdir(person_dir):
        continue
        
    for img_file in os.listdir(person_dir):
        img_path = os.path.join(person_dir, img_file)
        
        try:
            img = Image.open(img_path).convert('RGB')
            face = mtcnn(img)
            
            # Mô phỏng bẫy lỗi khuôn mặt như file chính của bạn
            if face is None:
                print(f"[CẢNH BÁO] {img_file} -> Không tìm thấy khuôn mặt.")
                continue
                
            # Trích xuất đặc trưng
            with torch.no_grad():
                test_emb = F.normalize(resnet(face.unsqueeze(0).to(device)), p=2, dim=1).detach()
                
            best_id, best_dist = 'Unknown', float('inf')
            
            # So khớp với từng người trong Data
            for person_id, known_emb in known_db.items():
                dist = 1 - F.cosine_similarity(known_emb, test_emb).item()
                if dist < best_dist:
                    best_dist, best_id = dist, person_id
            
            # Nếu khoảng cách lớn hơn ngưỡng -> Không nhận diện được (người lạ)
            predicted_id = best_id if best_dist < THRESHOLD else 'Unknown'
                
            # Lưu lại kết quả để chấm điểm
            y_true.append(true_id)
            y_pred.append(predicted_id)
            
            print(f"Ảnh: {img_file:<15} | Thực tế: {true_id:<7} | Dự đoán: {predicted_id:<7} | Khoảng cách: {best_dist:.4f}")
            
        except Exception as e:
            print(f"Lỗi khi đọc file {img_file}: {e}")

# 4. Tính toán và In Báo cáo bằng thư viện sklearn
if len(y_true) > 0:
    print("\n" + "="*60)
    print(" BÁO CÁO THỐNG KÊ ĐÁNH GIÁ (EVALUATION REPORT)".center(60))
    print("="*60)
    print(f"Tổng số ảnh test hợp lệ : {len(y_true)} ảnh")
    print(f"Độ chính xác (Accuracy) : {accuracy_score(y_true, y_pred)*100:.2f}%\n")
    
    # In ra bảng Precision, Recall, F1-Score chi tiết
    print("BẢNG THÔNG SỐ CHI TIẾT:")
    report = classification_report(y_true, y_pred, zero_division=0)
    print(report)
else:
    print("Không có ảnh hợp lệ nào được test!")
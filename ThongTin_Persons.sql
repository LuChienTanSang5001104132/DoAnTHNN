CREATE DATABASE THONGTIN
GO
USE THONGTIN
CREATE TABLE SinhVien(
    id VARCHAR(50) PRIMARY KEY,
    HoTen NVARCHAR(100) NOT NULL,
    MSSV VARCHAR(50) NOT NULL UNIQUE,  
    Khoa NVARCHAR(100),
    Email VARCHAR(100),
    sdt VARCHAR(20),
    Diachi NVARCHAR(100)
);
CREATE TABLE DIEM_DANH (
    ID_DiemDanh INT IDENTITY(1,1) PRIMARY KEY, 
    MSSV VARCHAR(50) NOT NULL,
    ThoiGian DATETIME DEFAULT GETDATE(),
    TrangThai NVARCHAR(50) DEFAULT N'Hợp lệ',

    CONSTRAINT FK_DiemDanh_SinhVien 
    FOREIGN KEY (MSSV) REFERENCES SinhVien(MSSV)
    ON DELETE CASCADE 
);
INSERT INTO SinhVien(id, HoTen, MSSV, Khoa, Email, sdt, Diachi)
VALUES 
('P004', N'Nguyễn Hùng Vỹ', '50.01.104.184', N'Công nghệ  thông tin','hungvy@gmail.com','0222222222', N'Thành phố Hồ Chí Minh'),
('P001', N'Lữ Chiến Tấn Sang', '50.01.104.132', N'Công nghệ thông tin', 'luchientansang@gmail.com', '0123456789', N'Bến Tre'),
('P002', N'Văn Thị Huyền Trân', '50.01.104.165', N'Công nghệ thông tin', 'vanthihuyentran@gmail.com', '0123455555', N'Kiên Giâng'),
('P003', N'Nguyễn Ngọc Quỳnh Như', '50.01.104.112', N'Công nghệ thông tin', 'quynhnhu@gmail.com', '0111111111', N'Thành phố Hồ Chí Minh')

drop table PERSONS
select * from SinhVien


SELECT * FROM DIEM_DANH ORDER BY ThoiGian DESC;

DELETE FROM SinhVien
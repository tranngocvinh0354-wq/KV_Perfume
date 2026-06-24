<?php

// Lớp cơ sở xử lý kết nối Database bằng PDO (Áp dụng tư duy Singleton để tối ưu tài nguyên kết nối)
class Database
{
    // TODO: Thông số DB hiện đang được "hardcode". 
    // Khi triển khai lên server thực tế (Production), cần đưa các biến này vào file cấu hình môi trường (VD: .env) để tránh lộ thông tin bảo mật trên mã nguồn.
    private string $host = 'localhost';
    private string $dbname = 'kv_perfume';
    private string $username = 'root';
    private string $password = '';
    private string $charset = 'utf8mb4';

    private ?PDO $conn = null;

    // Khởi tạo và duy trì duy nhất một kết nối PDO trong suốt vòng đời của request
    public function connect(): PDO
    {
        // Kiểm tra: Nếu chưa có kết nối nào được mở thì mới tiến hành khởi tạo (Tránh mở nhiều kết nối thừa gây quá tải MySQL)
        if ($this->conn === null) {
            $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset={$this->charset}";

            try {
                $this->conn = new PDO($dsn, $this->username, $this->password);
                
                // Cấu hình các tiêu chuẩn vận hành cho PDO
                $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);      // Chuyển lỗi SQL thành Exception để dễ dàng try-catch ở tầng Model
                $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC); // Luôn trả về dữ liệu DB dưới dạng mảng kết hợp (Key-Value)
                
                // BẢO MẬT CỐT LÕI: Tắt chế độ mô phỏng Prepare Statement của PHP. 
                // Ép MySQL engine tự xử lý prepare, qua đó ngăn chặn tuyệt đối các kỹ thuật tấn công SQL Injection.
                $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
                
            } catch (PDOException $e) {
                // TODO: Ở môi trường Production, nên ghi log lỗi (error_log) thay vì in trực tiếp $e->getMessage() ra màn hình để tránh lộ cấu trúc DB.
                die('Kết nối database thất bại: ' . $e->getMessage());
            }
        }

        return $this->conn;
    }
}
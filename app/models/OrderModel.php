<?php

require_once __DIR__ . '/XLdata.php';

/**
 * Lớp OrderModel
 * Chịu trách nhiệm xử lý toàn bộ nghiệp vụ liên quan đến Đơn hàng:
 * Tạo đơn, cập nhật trạng thái, hủy đơn, hoàn kho, lưu log...
 */
class OrderModel extends XlData
{
    /**
     * @var array Luồng trạng thái hợp lệ của một đơn hàng (State Machine)
     */
    private array $statusFlow = [
        'pending'   => ['confirmed', 'cancelled'],
        'confirmed' => ['shipping', 'cancelled'],
        'shipping'  => ['delivered'],
        'delivered' => ['completed'],
        'completed' => [],
        'cancelled' => []
    ];

    /**
     * Tạo đơn hàng mới từ giỏ hàng
     */
    public function saveOrder($userId, $paymentMethod, $cartItems, $totalAmount, $voucher = null)
    {
        if (empty($cartItems)) {
            return false;
        }

        try {
            // Bắt đầu Transaction: Nếu có lỗi ở bất kỳ bước nào, toàn bộ quá trình sẽ bị Rollback
            $this->db->beginTransaction();

            $voucherId = $voucher['id'] ?? null;
            $discountAmount = (float)($voucher['discount_amount'] ?? 0);
            $shippingFee = 0; // TODO: Tính phí ship dựa trên khu vực nếu cần
            
            // Đảm bảo số tiền cuối cùng không bị âm
            $finalAmount = max(0, $totalAmount - $discountAmount + $shippingFee);
            $orderCode = 'KV' . date('YmdHis') . rand(100, 999);

            $receiverName    = $_POST['receiver_name'] ?? $_POST['fullname'] ?? $_SESSION['user']['name'] ?? 'Khách hàng';
            $receiverPhone   = $_POST['receiver_phone'] ?? $_POST['phone'] ?? $_SESSION['user']['phone'] ?? '';
            $receiverEmail   = $_SESSION['user']['email'] ?? '';
            $receiverAddress = $_POST['receiver_address'] ?? $_POST['address'] ?? '';
            $note            = $_POST['note'] ?? '';

            // Chuẩn hóa phương thức thanh toán
            $paymentMethod = $paymentMethod === 'bank' ? 'bank_transfer' : $paymentMethod;
            if (!in_array($paymentMethod, ['cod', 'bank_transfer'], true)) {
                $paymentMethod = 'cod';
            }

            // 1. LƯU BẢNG ORDERS
            $orderSql = "INSERT INTO orders (
                            user_id, voucher_id, order_code, receiver_name, receiver_phone,
                            receiver_email, receiver_address, note, total_amount, discount_amount,
                            shipping_fee, final_amount, payment_method, payment_status, status, created_at
                        ) VALUES (
                            :user_id, :voucher_id, :order_code, :receiver_name, :receiver_phone,
                            :receiver_email, :receiver_address, :note, :total_amount, :discount_amount,
                            :shipping_fee, :final_amount, :payment_method, :payment_status, :status, NOW()
                        )";

            $orderStmt = $this->db->prepare($orderSql);
            $orderStmt->execute([
                'user_id'          => (int)$userId,
                'voucher_id'       => $voucherId,
                'order_code'       => $orderCode,
                'receiver_name'    => $receiverName,
                'receiver_phone'   => $receiverPhone,
                'receiver_email'   => $receiverEmail,
                'receiver_address' => $receiverAddress,
                'note'             => $note,
                'total_amount'     => $totalAmount,
                'discount_amount'  => $discountAmount,
                'shipping_fee'     => $shippingFee,
                'final_amount'     => $finalAmount,
                'payment_method'   => $paymentMethod,
                'payment_status'   => 'unpaid',
                'status'           => 'pending'
            ]);

            $orderId = (int)$this->db->lastInsertId();

            // 2. LƯU BẢNG ORDER_ITEMS VÀ TRỪ TỒN KHO
            foreach ($cartItems as $item) {
                $productId    = (int)($item['id'] ?? $item['product_id'] ?? 0);
                $quantity     = (int)($item['quantity'] ?? 1);
                $price        = (float)($item['price'] ?? 0);
                $productName  = $item['name'] ?? '';
                $productImage = $item['image'] ?? '';
                $subtotal     = $price * $quantity;

                if ($productId <= 0 || $quantity <= 0) {
                    continue;
                }

                $itemSql = "INSERT INTO order_items (
                                order_id, product_id, product_name, product_image, price, quantity, subtotal
                            ) VALUES (
                                :order_id, :product_id, :product_name, :product_image, :price, :quantity, :subtotal
                            )";

                $itemStmt = $this->db->prepare($itemSql);
                $itemStmt->execute([
                    'order_id'      => $orderId,
                    'product_id'    => $productId,
                    'product_name'  => $productName,
                    'product_image' => $productImage,
                    'price'         => $price,
                    'quantity'      => $quantity,
                    'subtotal'      => $subtotal
                ]);

                // Trừ tồn kho (stock) và tăng số lượng đã bán (sold_quantity)
                // Dùng GREATEST để đảm bảo tồn kho không bao giờ bị âm
                $productSql = "UPDATE products
                               SET stock = GREATEST(stock - :stock_quantity, 0),
                                   sold_quantity = sold_quantity + :sold_quantity
                               WHERE id = :product_id";

                $productStmt = $this->db->prepare($productSql);
                $productStmt->execute([
                    'stock_quantity' => $quantity,
                    'sold_quantity'  => $quantity,
                    'product_id'     => $productId
                ]);
            }

            // 3. LƯU BẢNG PAYMENTS
            $paymentSql = "INSERT INTO payments (
                               order_id, amount, method, status, created_at
                           ) VALUES (
                               :order_id, :amount, :method, :status, NOW()
                           )";

            $paymentStmt = $this->db->prepare($paymentSql);
            $paymentStmt->execute([
                'order_id' => $orderId,
                'amount'   => $finalAmount,
                'method'   => $paymentMethod,
                'status'   => 'unpaid'
            ]);

            // 4. LƯU LỊCH SỬ ĐƠN HÀNG (ORDER LOG)
            $this->createOrderLog($orderId, 'pending', 'Khách hàng tạo đơn hàng', (int)$userId);

            // 5. CẬP NHẬT LƯỢT SỬ DỤNG VOUCHER (Nếu có)
            if ($voucherId !== null) {
                $voucherSql = "UPDATE vouchers
                               SET used_quantity = used_quantity + 1
                               WHERE id = :id";
                $voucherStmt = $this->db->prepare($voucherSql);
                $voucherStmt->execute(['id' => $voucherId]);
            }

            // Hoàn tất Transaction
            $this->db->commit();
            return $orderId;

        } catch (Exception $e) {
            // Nếu có bất kỳ lỗi nào xảy ra (VD: hết hàng, lỗi SQL), quay ngược toàn bộ thay đổi
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return false;
        }
    }

    /**
     * Lấy danh sách đơn hàng của một người dùng cụ thể
     */
    public function getOrdersByUser($userId)
    {
        return $this->readItem(
            "SELECT * FROM orders WHERE user_id = :user_id ORDER BY id DESC",
            ['user_id' => (int)$userId]
        );
    }

    /**
     * Lấy chi tiết các sản phẩm trong một đơn hàng
     */
    public function getOrderItems($orderId)
    {
        return $this->readItem(
            "SELECT * FROM order_items WHERE order_id = :order_id ORDER BY id ASC",
            ['order_id' => (int)$orderId]
        );
    }

    /**
     * Lấy toàn bộ đơn hàng (Dành cho trang Quản trị Admin)
     */
    public function getAllOrders($status = 'all')
    {
        $sql = "SELECT o.*, u.full_name, u.email
                FROM orders o
                LEFT JOIN users u ON o.user_id = u.id";

        $params = [];

        if ($status !== 'all') {
            $sql .= " WHERE o.status = :status";
            $params['status'] = $status;
        }

        $sql .= " ORDER BY o.id DESC";
        return $this->readItem($sql, $params);
    }

    /**
     * Lấy thông tin chi tiết của một đơn hàng cụ thể
     */
    public function getOrderById($orderId)
    {
        return $this->readOne(
            "SELECT o.*, u.full_name, u.email
             FROM orders o
             LEFT JOIN users u ON o.user_id = u.id
             WHERE o.id = :id",
            ['id' => (int)$orderId]
        );
    }

    /**
     * Lấy lịch sử thay đổi trạng thái của đơn hàng
     */
    public function getOrderLogs($orderId)
    {
        return $this->readItem(
            "SELECT l.*, u.full_name
             FROM order_status_logs l
             LEFT JOIN users u ON l.changed_by = u.id
             WHERE l.order_id = :order_id
             ORDER BY l.id DESC",
            ['order_id' => (int)$orderId]
        );
    }

    /**
     * Wrapper cho hàm updateOrderStatus (Dùng riêng cho Admin)
     */
    public function updateStatus($orderId, $newStatus, $changedBy = null)
    {
        $result = $this->updateOrderStatus(
            $orderId,
            $newStatus,
            $changedBy,
            'Admin cập nhật trạng thái đơn hàng'
        );
        return $result['success'] ?? false;
    }

    /**
     * Cập nhật trạng thái đơn hàng (Có kiểm tra luồng hợp lệ)
     */
    public function updateOrderStatus($orderId, $newStatus, $changedBy = null, $note = '')
    {
        $order = $this->getOrderById($orderId);

        if (!$order) {
            return ['success' => false, 'code' => 'not_found'];
        }

        $currentStatus = $order['status'];

        if (!isset($this->statusFlow[$currentStatus])) {
            return ['success' => false, 'code' => 'invalid_status'];
        }

        if (!in_array($newStatus, $this->statusFlow[$currentStatus], true)) {
            return ['success' => false, 'code' => 'invalid_flow'];
        }

        $updated = $this->executeItem(
            "UPDATE orders
             SET status = :status, updated_at = NOW()
             WHERE id = :id",
            [
                'status' => $newStatus,
                'id'     => (int)$orderId
            ]
        );

        if (!$updated) {
            return ['success' => false, 'code' => 'update_failed'];
        }

        $this->createOrderLog(
            $orderId,
            $newStatus,
            $note ?: 'Hệ thống cập nhật trạng thái',
            $changedBy
        );

        return ['success' => true];
    }

    /**
     * Hủy đơn hàng và tự động hoàn trả tồn kho (Stock)
     */
    public function cancelOrder($orderId, $changedBy = null, $reason = '')
    {
        $order = $this->getOrderById($orderId);

        if (!$order) {
            return ['success' => false, 'code' => 'not_found'];
        }

        $currentStatus = trim(strtolower($order['status'] ?? ''));

        // Chỉ cho phép hủy đơn khi đang ở trạng thái Chờ xác nhận hoặc Đã xác nhận
        if (!in_array($currentStatus, ['pending', 'confirmed'], true)) {
            return ['success' => false, 'code' => 'invalid_flow'];
        }

        try {
            $this->db->beginTransaction();

            // 1. Cập nhật trạng thái đơn thành Cancelled
            $stmt = $this->db->prepare(
                "UPDATE orders
                 SET status = 'cancelled',
                     cancel_reason = :reason,
                     updated_at = NOW()
                 WHERE id = :id"
            );
            $stmt->execute([
                'reason' => $reason ?: 'Khách hàng chủ động hủy đơn',
                'id'     => (int)$orderId
            ]);

            // 2. Lấy danh sách sản phẩm để HOÀN KHO (Restore Stock)
            $items = $this->getOrderItems($orderId);
            foreach ($items as $item) {
                $quantity  = (int)($item['quantity'] ?? 0);
                $productId = (int)($item['product_id'] ?? 0);

                if ($quantity <= 0 || $productId <= 0) {
                    continue;
                }

                $stmtProduct = $this->db->prepare(
                    "UPDATE products
                     SET stock = stock + :stock_quantity,
                         sold_quantity = GREATEST(sold_quantity - :sold_quantity, 0)
                     WHERE id = :product_id"
                );
                $stmtProduct->execute([
                    'stock_quantity' => $quantity,
                    'sold_quantity'  => $quantity,
                    'product_id'     => $productId
                ]);
            }

            // 3. Hoàn trả Voucher (Nếu đơn có dùng voucher)
            if (!empty($order['voucher_id'])) {
                $stmtVoucher = $this->db->prepare(
                    "UPDATE vouchers
                     SET used_quantity = GREATEST(used_quantity - 1, 0)
                     WHERE id = :voucher_id"
                );
                $stmtVoucher->execute([
                    'voucher_id' => (int)$order['voucher_id']
                ]);
            }

            $this->db->commit();

            // 4. Lưu Log (Bắt lỗi rỗng nếu hàm createOrderLog lỗi)
            try {
                $this->createOrderLog($orderId, 'cancelled', $reason ?: 'Khách hàng hủy đơn', $changedBy);
            } catch (Exception $e) {}

            return ['success' => true];

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return [
                'success' => false,
                'code'    => 'system_error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Lưu lại dấu vết (Log) khi một đơn hàng bị thay đổi trạng thái
     */
    private function createOrderLog($orderId, $status, $note = '', $changedBy = null)
    {
        return $this->executeItem(
            "INSERT INTO order_status_logs (
                order_id, status, note, changed_by, created_at
            ) VALUES (
                :order_id, :status, :note, :changed_by, NOW()
            )",
            [
                'order_id'   => (int)$orderId,
                'status'     => $status,
                'note'       => $note,
                'changed_by' => $changedBy
            ]
        );
    }
}
?>
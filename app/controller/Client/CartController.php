<?php

require_once __DIR__ . '/../../../core/Controller.php';
require_once __DIR__ . '/../../models/ProductModel.php';
require_once __DIR__ . '/../../models/OrderModel.php';
require_once __DIR__ . '/../../models/VoucherModel.php';
require_once __DIR__ . '/../../../core/MailService.php';

class CartController extends Controller
{
    private OrderModel $orderModel;
    private VoucherModel $voucherModel;

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        $this->orderModel = new OrderModel();
        $this->voucherModel = new VoucherModel();
    }

    public function add()
    {
        $id = (int)($_GET['id'] ?? 0);
        $productModel = new ProductModel();
        $product = $productModel->getProductById($id);

        if ($product) {
            if (isset($_SESSION['cart'][$id])) {
                $_SESSION['cart'][$id]['quantity']++;
            } else {
                $price = !empty($product['sale_price']) ? $product['sale_price'] : $product['price'];

                $_SESSION['cart'][$id] = [
                    'id' => (int)$product['id'],
                    'name' => $product['name'],
                    'price' => (float)$price,
                    'image' => $product['main_image'] ?? '',
                    'quantity' => 1
                ];
            }
        }

        $this->redirectBackWithCart();
    }

    public function update()
    {
        $id = (int)($_GET['id'] ?? 0);
        $amount = (int)($_GET['amount'] ?? 0);

        if (isset($_SESSION['cart'][$id])) {
            $_SESSION['cart'][$id]['quantity'] += $amount;

            if ($_SESSION['cart'][$id]['quantity'] <= 0) {
                unset($_SESSION['cart'][$id]);
            }
        }

        $this->redirectBackWithCart();
    }

    public function remove()
    {
        $id = (int)($_GET['id'] ?? 0);
        unset($_SESSION['cart'][$id]);

        $this->redirectBackWithCart();
    }

    public function checkout()
    {
        if (empty($_SESSION['user'])) {
            $_SESSION['redirect_to'] = '?url=cart/checkout';
            $_SESSION['login_error'] = 'Vui lòng đăng nhập để thanh toán.';
            header('Location: ?url=account');
            exit();
        }

        if (empty($_SESSION['cart'])) {
            $this->render('client/Checkout', [
                'error' => 'Giỏ hàng của bạn đang trống.',
                'cart' => [],
                'total' => 0,
                'discount' => 0,
                'finalTotal' => 0,
                'step' => '1',
                'formData' => []
            ]);
            return;
        }

        $step = $_GET['step'] ?? '1';

        if (!isset($_SESSION['checkout_form'])) {
            $_SESSION['checkout_form'] = [
                'fullname' => $_SESSION['user']['name'] ?? '',
                'phone' => $_SESSION['user']['phone'] ?? '',
                'address' => '',
                'paymentMethod' => 'cod',
                'voucher_code' => ''
            ];
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_SESSION['checkout_form']['fullname'] = trim($_POST['fullname'] ?? $_SESSION['checkout_form']['fullname']);
            $_SESSION['checkout_form']['phone'] = trim($_POST['phone'] ?? $_SESSION['checkout_form']['phone']);
            $_SESSION['checkout_form']['address'] = trim($_POST['address'] ?? $_SESSION['checkout_form']['address']);
            $_SESSION['checkout_form']['paymentMethod'] = $_POST['paymentMethod'] ?? $_SESSION['checkout_form']['paymentMethod'];
            $_SESSION['checkout_form']['voucher_code'] = trim($_POST['voucher_code'] ?? $_SESSION['checkout_form']['voucher_code']);

            $paymentMethod = $_SESSION['checkout_form']['paymentMethod'];

            if ($step === '1') {
                header('Location: ?url=cart/checkout&step=2');
                exit();
            }

            if ($step === '2') {
                if ($paymentMethod === 'bank' || $paymentMethod === 'bank_transfer') {
                    header('Location: ?url=cart/checkout&step=2.5');
                    exit();
                }

                header('Location: ?url=cart/checkout&step=3');
                exit();
            }

            if ($step === '2.5') {
                header('Location: ?url=cart/checkout&step=3');
                exit();
            }
        }

        $total = $this->calculateTotal();
        $voucher = null;
        $discount = 0;

        if (!empty($_SESSION['checkout_form']['voucher_code'])) {
            $voucher = $this->voucherModel->getValidVoucher($_SESSION['checkout_form']['voucher_code'], $total);
            $discount = (float)($voucher['discount_amount'] ?? 0);
        }

        $this->render('client/Checkout', [
            'step' => $step,
            'formData' => $_SESSION['checkout_form'],
            'cart' => $_SESSION['cart'],
            'total' => $total,
            'discount' => $discount,
            'finalTotal' => max(0, $total - $discount),
            'error' => ''
        ]);
    }

    public function submitOrder()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['cart']) || empty($_SESSION['user'])) {
            header('Location: ?url=cart/checkout');
            exit();
        }

        $userId = $_SESSION['user']['id'] ?? null;

        if (!$userId) {
            die('Không tìm thấy ID người dùng. Vui lòng đăng nhập lại.');
        }

        $formData = $_SESSION['checkout_form'] ?? [];
        $formData['fullname'] = trim($_POST['fullname'] ?? $formData['fullname'] ?? '');
        $formData['phone'] = trim($_POST['phone'] ?? $formData['phone'] ?? '');
        $formData['address'] = trim($_POST['address'] ?? $formData['address'] ?? '');
        $formData['paymentMethod'] = $_POST['paymentMethod'] ?? ($formData['paymentMethod'] ?? 'cod');
        $formData['voucher_code'] = trim($_POST['voucher_code'] ?? ($formData['voucher_code'] ?? ''));

        if ($formData['fullname'] === '' || $formData['phone'] === '' || $formData['address'] === '') {
            $_SESSION['checkout_form'] = $formData;
            header('Location: ?url=cart/checkout&error=missing_info');
            exit();
        }

        $_POST['receiver_name'] = $formData['fullname'];
        $_POST['receiver_phone'] = $formData['phone'];
        $_POST['receiver_address'] = $formData['address'];

        $total = $this->calculateTotal();
        $voucher = null;

        if (!empty($formData['voucher_code'])) {
            $voucher = $this->voucherModel->getValidVoucher($formData['voucher_code'], $total);
        }

        $orderResult = $this->orderModel->saveOrder(
            (int)$userId,
            $formData['paymentMethod'],
            $_SESSION['cart'],
            $total,
            $voucher
        );

        if ($orderResult) {
            $customerEmail = $_SESSION['user']['email'] ?? '';
            $customerName = $_POST['receiver_name'] ?? '';

            $orderCode = is_array($orderResult)
                ? $orderResult['code']
                : 'KV' . str_pad((string)$orderResult, 5, '0', STR_PAD_LEFT);

            if (!empty($customerEmail)) {
                MailService::sendOrderConfirmation($customerEmail, $customerName, $orderCode, $total);
            }

            MailService::sendAdminOrderNotification(
                $orderCode,
                $customerName,
                $formData['phone'],
                $formData['address'],
                $total
            );

            $_SESSION['cart'] = [];
            unset($_SESSION['checkout_form']);

            header('Location: ?url=account&order_success=1');
            exit();
        }

        die('Tạo đơn hàng thất bại.');
    }

    private function calculateTotal(): float
    {
        $total = 0;

        foreach ($_SESSION['cart'] as $item) {
            $total += (float)$item['price'] * (int)$item['quantity'];
        }

        return $total;
    }

    private function redirectBackWithCart(): void
    {
        $url = $_SERVER['HTTP_REFERER'] ?? '?url=home';
        $separator = str_contains($url, '?') ? '&' : '?';

        if (!str_contains($url, 'open_cart=')) {
            $url .= $separator . 'open_cart=1';
        }

        header('Location: ' . $url);
        exit();
    }
}
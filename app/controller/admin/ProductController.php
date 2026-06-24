<?php

require_once __DIR__ . '/../../../core/Controller.php';
require_once __DIR__ . '/../../../core/AuthMiddleware.php';
require_once __DIR__ . '/../../models/ProductModel.php';

class ProductController extends Controller
{
    private ProductModel $productModel;

    public function __construct()
    {
        AuthMiddleware::requireRole('admin');
        $this->productModel = new ProductModel();
    }

    public function index()
    {
        $type = $_GET['type'] ?? 'all';
        $allowTypes = ['all', 'active', 'hidden', 'out_of_stock'];

        if (!in_array($type, $allowTypes)) {
            $type = 'all';
        }

        $products = $this->productModel->getProducts($type);

        $this->renderAdmin('admin/product/index', [
            'title' => 'Quản lý sản phẩm',
            'products' => $products,
            'type' => $type,
            'message' => $this->getMessage($_GET['msg'] ?? ''),
            'error' => $this->getError($_GET['error'] ?? '')
        ]);
    }

    public function create()
    {
        $brands = $this->productModel->getAllBrands();
        $categories = $this->productModel->getAllCategories();
        $errors = [];
        $product = [
            'gender' => 'unisex',
            'status' => 1
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $product = $this->cleanInput();
            $errors = $this->validateProduct($product);

            if (empty($errors)) {
                try {
                    $this->productModel->createProduct($product);
                    header('Location: ?url=admin/product/index&msg=created');
                    exit;
                } catch (Exception $e) {
                    $errors[] = $this->friendlyException($e->getMessage());
                }
            }
        }

        $this->renderAdmin('admin/product/form', [
            'title' => 'Thêm sản phẩm',
            'mode' => 'create',
            'product' => $product,
            'brands' => $brands,
            'categories' => $categories,
            'errors' => $errors
        ]);
    }

    public function edit($id = null)
    {
        $id = (int)($id ?? $_GET['id'] ?? 0);

        if ($id <= 0) {
            header('Location: ?url=admin/product/index&error=invalid_id');
            exit;
        }

        $product = $this->productModel->getAdminProductById($id);

        if (!$product) {
            header('Location: ?url=admin/product/index&error=not_found');
            exit;
        }

        $brands = $this->productModel->getAllBrands();
        $categories = $this->productModel->getAllCategories();
        $errors = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $product = array_merge($product, $this->cleanInput());
            $errors = $this->validateProduct($product);

            if (empty($errors)) {
                try {
                    $this->productModel->updateProduct($id, $product);
                    header('Location: ?url=admin/product/index&msg=updated');
                    exit;
                } catch (Exception $e) {
                    $errors[] = $this->friendlyException($e->getMessage());
                }
            }
        }

        $this->renderAdmin('admin/product/form', [
            'title' => 'Sửa sản phẩm',
            'mode' => 'edit',
            'product' => $product,
            'brands' => $brands,
            'categories' => $categories,
            'errors' => $errors
        ]);
    }

    public function delete()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ?url=admin/product/index&error=method_not_allowed');
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            header('Location: ?url=admin/product/index&error=invalid_id');
            exit;
        }

        $result = $this->productModel->deleteProduct($id);

        if (is_array($result)) {
            if (!empty($result['success'])) {
                header('Location: ?url=admin/product/index&msg=deleted');
                exit;
            }

            header('Location: ?url=admin/product/index&error=cannot_delete');
            exit;
        }

        header('Location: ?url=admin/product/index&msg=deleted');
        exit;
    }

    public function lock()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ?url=admin/product/index&error=method_not_allowed');
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            header('Location: ?url=admin/product/index&error=invalid_id');
            exit;
        }

        $this->productModel->lockProduct($id);

        header('Location: ?url=admin/product/index&msg=locked');
        exit;
    }

    public function unlock()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ?url=admin/product/index&error=method_not_allowed');
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            header('Location: ?url=admin/product/index&error=invalid_id');
            exit;
        }

        $this->productModel->unlockProduct($id);

        header('Location: ?url=admin/product/index&msg=unlocked');
        exit;
    }

    public function restore()
    {
        return $this->unlock();
    }

    private function cleanInput()
    {
        return [
            'name' => trim($_POST['name'] ?? ''),
            'slug' => trim($_POST['slug'] ?? ''),
            'brand_id' => (int)($_POST['brand_id'] ?? 0),
            'category_id' => (int)($_POST['category_id'] ?? 0),
            'gender' => trim($_POST['gender'] ?? 'unisex'),
            'concentration' => trim($_POST['concentration'] ?? ''),
            'volume' => trim($_POST['volume'] ?? ''),
            'scent_group' => trim($_POST['scent_group'] ?? ''),
            'price' => isset($_POST['price']) ? (float)$_POST['price'] : 0,
            'sale_price' => isset($_POST['sale_price']) && $_POST['sale_price'] !== '' ? (float)$_POST['sale_price'] : null,
            'stock' => isset($_POST['stock']) ? (int)$_POST['stock'] : 0,
            'main_image' => trim($_POST['main_image'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'top_note' => trim($_POST['top_note'] ?? ''),
            'middle_note' => trim($_POST['middle_note'] ?? ''),
            'base_note' => trim($_POST['base_note'] ?? ''),
            'fragrance_story' => trim($_POST['fragrance_story'] ?? ''),
            'longevity' => trim($_POST['longevity'] ?? ''),
            'occasion' => trim($_POST['occasion'] ?? ''),
            'status' => isset($_POST['status']) ? (int)$_POST['status'] : 1
        ];
    }

    private function validateProduct($data)
    {
        $errors = [];

        if (($data['name'] ?? '') === '') {
            $errors[] = 'Tên sản phẩm không được để trống.';
        }

        if (($data['brand_id'] ?? 0) <= 0) {
            $errors[] = 'Vui lòng chọn thương hiệu.';
        }

        if (($data['category_id'] ?? 0) <= 0) {
            $errors[] = 'Vui lòng chọn danh mục.';
        }

        if (($data['price'] ?? 0) <= 0) {
            $errors[] = 'Giá sản phẩm phải lớn hơn 0.';
        }

        if (($data['stock'] ?? 0) < 0) {
            $errors[] = 'Tồn kho không được âm.';
        }

        if (!empty($data['sale_price'])) {
            if ($data['sale_price'] < 0) {
                $errors[] = 'Giá khuyến mãi không được âm.';
            }

            if ($data['sale_price'] >= $data['price']) {
                $errors[] = 'Giá khuyến mãi phải nhỏ hơn giá gốc.';
            }
        }

        if (!in_array(($data['gender'] ?? 'unisex'), ['male', 'female', 'unisex'])) {
            $errors[] = 'Giới tính sản phẩm không hợp lệ.';
        }

        if (!in_array((int)($data['status'] ?? 1), [0, 1])) {
            $errors[] = 'Trạng thái sản phẩm không hợp lệ.';
        }

        return $errors;
    }

    private function getMessage($key)
    {
        $messages = [
            'created' => 'Thêm sản phẩm thành công.',
            'updated' => 'Cập nhật sản phẩm thành công.',
            'deleted' => 'Xóa sản phẩm thành công.',
            'locked' => 'Đã ngưng kinh doanh sản phẩm.',
            'unlocked' => 'Đã mở bán lại sản phẩm.',
            'restored' => 'Đã mở bán lại sản phẩm.'
        ];

        return $messages[$key] ?? '';
    }

    private function getError($key)
    {
        $errors = [
            'invalid_id' => 'Mã sản phẩm không hợp lệ.',
            'not_found' => 'Không tìm thấy sản phẩm.',
            'method_not_allowed' => 'Thao tác không hợp lệ.',
            'cannot_delete' => 'Không thể xóa sản phẩm vì đã phát sinh đơn hàng, giỏ hàng hoặc đánh giá. Hệ thống chỉ cho phép ngưng kinh doanh để giữ dữ liệu lịch sử.',
            'delete_failed' => 'Xóa sản phẩm thất bại.'
        ];

        return $errors[$key] ?? '';
    }

    private function friendlyException($message)
    {
        if (stripos($message, 'Duplicate') !== false && stripos($message, 'slug') !== false) {
            return 'Slug sản phẩm đã tồn tại. Vui lòng đổi slug khác.';
        }

        if (stripos($message, 'foreign key') !== false) {
            return 'Dữ liệu thương hiệu hoặc danh mục không hợp lệ.';
        }

        return $message;
    }
}
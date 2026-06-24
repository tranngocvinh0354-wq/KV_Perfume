<?php

require_once __DIR__ . '/../../../core/Controller.php';
require_once __DIR__ . '/../../models/ProductModel.php';

class ProductController extends Controller
{
    private ProductModel $productModel;

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->productModel = new ProductModel();
    }

    public function index()
    {
        $activeFilter = $_GET['filter'] ?? 'all';
        $brandId = isset($_GET['brand_id']) ? (int)$_GET['brand_id'] : null;
        $categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : null;
        $keyword = trim($_GET['keyword'] ?? '');
        $gender = trim($_GET['gender'] ?? '');

        $products = $this->productModel->getProductsFiltered(
            $activeFilter,
            $brandId,
            $categoryId,
            $keyword,
            $gender
        );

        $this->render('client/Home', [
            'products' => $products,
            'activeFilter' => $activeFilter,
            'keyword' => $keyword,
            'brandId' => $brandId,
            'categoryId' => $categoryId,
            'gender' => $gender,
            'heroProducts' => $this->productModel->getHeroProducts(5),
            'featuredProducts' => $products,
            'newProducts' => $this->productModel->getNewProducts(8),
            'bestSellerProducts' => $this->productModel->getBestSellerProducts(8),
            'maleProducts' => $this->productModel->getProductsByGender('male', 8),
            'femaleProducts' => $this->productModel->getProductsByGender('female', 8),
            'unisexProducts' => $this->productModel->getProductsByGender('unisex', 8),
            'brands' => $this->productModel->getBrands()
        ]);
    }

    public function detail()
    {
        $id = (int)($_GET['id'] ?? 0);

        if ($id <= 0) {
            header('Location: ?url=home');
            exit();
        }

        $product = $this->productModel->getProductById($id);

        if (!$product) {
            header('Location: ?url=home');
            exit();
        }

        $reviews = $this->productModel->getReviewsByProduct($id);
        $reviewSummary = $this->productModel->getReviewSummaryByProduct($id);

        $this->render('client/ProductDetail', [
            'product' => $product,
            'reviews' => $reviews,
            'reviewSummary' => $reviewSummary
        ]);
    }
}
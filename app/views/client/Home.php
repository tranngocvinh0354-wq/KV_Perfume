<?php
/**
 * View: Trang chủ (Home Page) - Client Side
 * Mục đích: Hiển thị Slider Hero, thanh bộ lọc sản phẩm và các danh mục sản phẩm theo Collections.
 */

$baseUrl = '/CHANEL.VN-MAIN';

// Khởi tạo các biến mặc định để tránh lỗi Undefined khi truyền dữ liệu từ Controller
$products = $products ?? [];
$activeFilter = $activeFilter ?? 'all';
$keyword = $keyword ?? '';
$brandId = $brandId ?? null;
$categoryId = $categoryId ?? null;
$gender = $gender ?? '';

$heroProducts = $heroProducts ?? [];
$featuredProducts = $featuredProducts ?? [];
$newProducts = $newProducts ?? [];
$bestSellerProducts = $bestSellerProducts ?? [];
$maleProducts = $maleProducts ?? [];
$femaleProducts = $femaleProducts ?? [];
$unisexProducts = $unisexProducts ?? [];
$brands = $brands ?? [];

// ===================================================================
// CÁC HÀM HỖ TRỢ HIỂN THỊ (VIEW HELPERS)
// ===================================================================

if (!function_exists('kvProductImage')) {
    function kvProductImage(array $product): string
    {
        $baseUrl = '/CHANEL.VN-MAIN';
        $image = $product['main_image'] ?? $product['image_path'] ?? $product['image_url'] ?? $product['image'] ?? '';
        $image = trim((string)$image);

        if ($image === '') return $baseUrl . '/public/upload/products/default.webp';
        if (preg_match('/^https?:\/\//i', $image)) return $image;
        if (str_starts_with($image, $baseUrl . '/')) return $image;
        if (str_starts_with($image, '/public/')) return $baseUrl . $image;
        if (str_starts_with($image, 'public/')) return $baseUrl . '/' . $image;
        if (str_starts_with($image, '/upload/')) return $baseUrl . '/public' . $image;
        if (str_starts_with($image, 'upload/')) return $baseUrl . '/public/' . $image;
        if (!str_contains($image, '/')) return $baseUrl . '/public/upload/products/' . $image;

        return $image;
    }
}

if (!function_exists('renderProductCard')) {
    function renderProductCard(array $product): void
    {
        $id = (int)($product['id'] ?? 0);
        $name = $product['name'] ?? 'Sản phẩm';
        $image = kvProductImage($product);
        $category = $product['scent_group'] ?? $product['category_name'] ?? 'Luxury Fragrance';
        $price = !empty($product['sale_price']) ? $product['sale_price'] : ($product['price'] ?? 0);
        ?>
        <div class="product-card">
            <a href="?url=product/detail&id=<?php echo htmlspecialchars((string)$id); ?>" class="product-img-wrapper">
                <img src="<?php echo htmlspecialchars($image); ?>" alt="<?php echo htmlspecialchars($name); ?>" loading="lazy">
            </a>
            <div class="product-info">
                <p class="category-tag"><?php echo htmlspecialchars($category); ?></p>
                <h3 class="product-name"><?php echo htmlspecialchars($name); ?></h3>
                <p class="product-price"><?php echo number_format((float)$price, 0, ',', '.'); ?> VNĐ</p>
                <a href="?url=cart/add&id=<?php echo htmlspecialchars((string)$id); ?>" class="add-to-cart-btn">THÊM VÀO GIỎ</a>
            </div>
        </div>
        <?php
    }
}

if (!function_exists('renderProductSection')) {
    function renderProductSection(string $title, string $subtitle, array $items, string $viewAllUrl = ''): void
    {
        if (empty($items)) return;
        ?>
        <section class="home-section">
            <div class="section-heading">
                <p><?php echo htmlspecialchars($subtitle); ?></p>
                <h2><?php echo htmlspecialchars($title); ?></h2>
                <?php if ($viewAllUrl !== ''): ?>
                    <a href="<?php echo htmlspecialchars($viewAllUrl); ?>" class="section-view-all">Xem tất cả</a>
                <?php endif; ?>
            </div>
            <div class="products-grid">
                <?php foreach ($items as $product): renderProductCard($product); endforeach; ?>
            </div>
        </section>
        <?php
    }
}

// Fallback logic cho Hero Banner nếu không có sản phẩm được truyền vào
if (empty($heroProducts)) {
    $heroProducts = array_slice($products, 0, 5);
}

if (empty($heroProducts)) {
    $heroProducts = [
        [
            'id' => 1,
            'name' => 'KV PERFUME',
            'description' => 'Những tầng hương thanh lịch, tinh tế và đầy cuốn hút cho phong cách riêng của bạn.',
            'main_image' => '/CHANEL.VN-MAIN/public/upload/products/chanel-n5-eau-de-parfum.webp'
        ]
    ];
}

$isFiltering = $activeFilter !== 'all' || $keyword !== '' || !empty($brandId) || !empty($categoryId) || $gender !== '';
?>

<div class="home-page">
    
    <section class="hero-slider" data-hero-slider>
        <div class="hero-slider-track">
            <?php foreach ($heroProducts as $index => $item): ?>
                <?php
                $id = (int)($item['id'] ?? 1);
                $name = $item['name'] ?? 'KV PERFUME';
                $description = $item['description'] ?? 'Những tầng hương thanh lịch, tinh tế và đầy cuốn hút cho phong cách riêng của bạn.';
                $image = kvProductImage($item);
                ?>
                <article class="hero-slide <?php echo $index === 0 ? 'active' : ''; ?>" data-hero-slide>
                    <a href="?url=product/detail&id=<?php echo htmlspecialchars((string)$id); ?>" class="hero-slide-link">
                        <div class="hero-slide-image">
                            <img src="<?php echo htmlspecialchars($image); ?>" alt="<?php echo htmlspecialchars($name); ?>">
                        </div>
                        <div class="hero-slide-content">
                            <p class="hero-slide-eyebrow">SIGNATURE COLLECTION</p>
                            <h1><?php echo htmlspecialchars($name); ?></h1>
                            <p class="hero-slide-desc"><?php echo htmlspecialchars($description); ?></p>
                            <span class="hero-slide-btn">KHÁM PHÁ</span>
                        </div>
                    </a>
                </article>
            <?php endforeach; ?>
        </div>

        <button type="button" class="hero-arrow hero-arrow-left" data-hero-prev aria-label="Sản phẩm trước">‹</button>
        <button type="button" class="hero-arrow hero-arrow-right" data-hero-next aria-label="Sản phẩm tiếp theo">›</button>

        <div class="hero-dots">
            <?php foreach ($heroProducts as $index => $item): ?>
                <button type="button" class="hero-dot <?php echo $index === 0 ? 'active' : ''; ?>" data-hero-dot="<?php echo $index; ?>" aria-label="Chuyển đến banner <?php echo $index + 1; ?>"></button>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="home-filter-wrap">
        <div class="filter-section">
            <?php
            $filterOptions = [
                'all' => 'TẤT CẢ',
                'floral' => 'HƯƠNG HOA (FLORAL)',
                'woody' => 'HƯƠNG GỖ (WOODY)',
                'fresh' => 'TƯƠI MÁT (FRESH)'
            ];
            foreach ($filterOptions as $val => $label):
            ?>
                <a href="?url=home&filter=<?php echo htmlspecialchars($val); ?>" class="filter-btn <?php echo ($activeFilter === $val) ? 'active' : ''; ?>">
                    <?php echo htmlspecialchars($label); ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (!empty($brands)): ?>
        <section class="brand-strip">
            <div class="brand-strip-inner">
                <?php foreach ($brands as $brand): ?>
                    <a href="?url=home&brand_id=<?php echo htmlspecialchars((string)$brand['id']); ?>" class="brand-chip">
                        <?php echo htmlspecialchars($brand['name']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($isFiltering): ?>
        <section class="home-section">
            <div class="section-heading">
                <p>KẾT QUẢ LỌC</p>
                <h2><?php echo $keyword !== '' ? 'Tìm kiếm: ' . htmlspecialchars($keyword) : 'Danh sách sản phẩm'; ?></h2>
                <a href="?url=home" class="section-view-all">Xóa bộ lọc</a>
            </div>

            <?php if (!empty($products)): ?>
                <div class="products-grid">
                    <?php foreach ($products as $product): renderProductCard($product); endforeach; ?>
                </div>
            <?php else: ?>
                <p class="empty-products">Không có sản phẩm nào phù hợp.</p>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <?php
        renderProductSection('Sản phẩm nổi bật', 'Featured Perfumes', $featuredProducts, '?url=product');
        renderProductSection('Bán chạy', 'Best Sellers', $bestSellerProducts, '?url=product');
        renderProductSection('Nước hoa nữ', 'For Her', $femaleProducts, '?url=product&gender=female');
        renderProductSection('Nước hoa nam', 'For Him', $maleProducts, '?url=product&gender=male');
        renderProductSection('Nước hoa unisex', 'Unisex Collection', $unisexProducts, '?url=product&gender=unisex');
        renderProductSection('Sản phẩm mới', 'New Arrivals', $newProducts, '?url=product');
        ?>
    <?php endif; ?>
</div>
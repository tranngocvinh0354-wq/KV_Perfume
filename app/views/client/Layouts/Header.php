<?php
// Khởi tạo session để truy xuất thông tin giỏ hàng và trạng thái đăng nhập
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// TODO: Đưa $baseUrl vào file config global để dễ dàng thay đổi khi deploy lên server thật.
$baseUrl = '/KV_Perfume-main';

// 1. Tính tổng số lượng sản phẩm trong giỏ hàng để hiển thị lên Badge (chấm đỏ)
$cartCount = 0;
if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cartCount += (int)($item['quantity'] ?? 0);
    }
}

// 2. Định tuyến UI: Xác định URL hiện tại để làm nổi bật (active) menu đang được chọn
$currentUrl = $_GET['url'] ?? 'home';
$isHomePage = $currentUrl === 'home' || $currentUrl === '';

// Bắt cờ (flag) từ URL để tự động mở giỏ hàng trượt nếu người dùng vừa bấm "Thêm vào giỏ"
$isCartOpen = isset($_GET['open_cart']) && $_GET['open_cart'] == '1';

// 3. Thông tin định danh cơ bản của người dùng
$user = $_SESSION['user'] ?? null;
$userName = $user['full_name'] ?? $user['name'] ?? 'Khách';
$isLoggedIn = !empty($user);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KV PERFUME - Luxury Fragrance Boutique</title>

    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/public/css/index.css">
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/public/css/Home.css">
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/public/css/Checkout.css">
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/public/css/Productdetail.css">
</head>
<body>

<header class="site-header">
    <div class="header-top">
        <div class="header-search">
            <form action="index.php" method="GET">
                <input type="hidden" name="url" value="home">
                <input
                    type="text"
                    name="keyword"
                    value="<?php echo htmlspecialchars($_GET['keyword'] ?? ''); ?>"
                    placeholder="Tìm kiếm sản phẩm..."
                >
                <button type="submit" aria-label="Tìm kiếm">⌕</button>
            </form>
        </div>

        <div class="header-brand">
            <a href="?url=home" class="brand-name">KV PERFUME</a>
            <p>Luxury Fragrance Boutique</p>
        </div>

        <div class="header-actions">
            <div class="account-box">
                <span>Xin chào, <?php echo htmlspecialchars($userName); ?></span>
                <div>
                    <?php if ($isLoggedIn): ?>
                        <a href="?url=account">Tài khoản</a>
                        <span>hoặc</span>
                        <a href="?url=account/logout">Đăng xuất</a>
                    <?php else: ?>
                        <a href="?url=account">Đăng nhập</a>
                        <span>hoặc</span>
                        <a href="?url=account/registerForm">Đăng ký</a>
                    <?php endif; ?>
                </div>
            </div>

            <a href="?url=account" class="heart-btn" aria-label="Tài khoản yêu thích">♡</a>

            <button class="cart-top-btn" id="openCartBtn" type="button" aria-label="Mở giỏ hàng">
                🛒
                <span class="cart-badge"><?php echo $cartCount; ?></span>
            </button>
        </div>
    </div>

    <nav class="header-nav">
        <a href="?url=home" class="<?php echo $isHomePage ? 'active' : ''; ?>">
            TRANG CHỦ
        </a>

        <a href="?url=about" class="<?php echo $currentUrl === 'about' ? 'active' : ''; ?>">
            GIỚI THIỆU
        </a>

        <div class="nav-item-dropdown">
            <span class="dropdown-trigger">THƯƠNG HIỆU ▾</span>
            <div class="dropdown-menu">
                <a href="?url=home&brand_id=1">Chanel</a>
                <a href="?url=home&brand_id=2">Dior</a>
                <a href="?url=home&brand_id=3">YSL</a>
                <a href="?url=home&brand_id=4">Gucci</a>
            </div>
        </div>

        <div class="nav-item-dropdown">
            <span class="dropdown-trigger">NƯỚC HOA ▾</span>
            <div class="dropdown-menu">
                <a href="?url=home&gender=female">Nước hoa nữ</a>
                <a href="?url=home&gender=male">Nước hoa nam</a>
                <a href="?url=home&gender=unisex">Nước hoa unisex</a>
                <a href="?url=home&category_id=4">Gift Set</a>
            </div>
        </div>

        <a href="?url=contact" class="<?php echo $currentUrl === 'contact' ? 'active' : ''; ?>">
            LIÊN HỆ
        </a>
    </nav>
</header>

<div class="overlay <?php echo $isCartOpen ? 'active' : ''; ?>" id="cartOverlay"></div>

<div id="cart-drawer" class="cart-drawer <?php echo $isCartOpen ? 'open' : ''; ?>">
    <div class="cart-header">
        <h2>Giỏ hàng của bạn</h2>
        <button id="closeCartBtn" type="button">x</button>
    </div>

    <div class="cart-items">
        <?php if (empty($_SESSION['cart'])): ?>
            <p>Giỏ hàng trống</p>
        <?php else: ?>
            <?php foreach ($_SESSION['cart'] as $item): ?>
                <?php
                $itemId = $item['id'] ?? '';
                $itemName = $item['name'] ?? 'Sản phẩm';
                $itemImage = $item['image'] ?? '';
                $itemPrice = (float)($item['price'] ?? 0);
                $itemQuantity = (int)($item['quantity'] ?? 1);
                ?>
                <div class="cart-item">
                    <img class="cart-item-img"
                         src="<?php echo htmlspecialchars($itemImage); ?>"
                         alt="<?php echo htmlspecialchars($itemName); ?>">

                    <div class="cart-item-info">
                        <h4><?php echo htmlspecialchars($itemName); ?></h4>

                        <p class="cart-item-price">
                            <?php echo number_format($itemPrice, 0, ',', '.'); ?>đ
                        </p>

                        <div class="cart-item-quantity">
                            <a href="?url=cart/update&id=<?php echo htmlspecialchars($itemId); ?>&amount=-1" class="btn-qty">-</a>
                            <span><?php echo $itemQuantity; ?></span>
                            <a href="?url=cart/update&id=<?php echo htmlspecialchars($itemId); ?>&amount=1" class="btn-qty">+</a>
                            <a href="?url=cart/remove&id=<?php echo htmlspecialchars($itemId); ?>" class="btn-remove">Xóa</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="cart-footer">
        <div class="cart-total">
            <span>Tổng cộng:</span>
            <span>
                <?php
                // Khối logic tính tổng tiền (Sub-total) trước khi áp mã giảm giá
                $total = 0;
                if (!empty($_SESSION['cart'])) {
                    foreach ($_SESSION['cart'] as $item) {
                        $total += ((float)($item['price'] ?? 0)) * ((int)($item['quantity'] ?? 1));
                    }
                }
                echo number_format($total, 0, ',', '.');
                ?>đ
            </span>
        </div>

        <a href="?url=cart/checkout" class="checkout-btn">
            THANH TOÁN
        </a>
    </div>
</div>
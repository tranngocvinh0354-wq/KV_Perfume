<div class="admin-page">
    <div class="admin-page-header">
        <div>
            <p class="admin-eyebrow">KV PERFUME ADMIN</p>
            <h1>Quản lý sản phẩm</h1>
            <p>
                Sản phẩm đã phát sinh đơn hàng, giỏ hàng hoặc đánh giá sẽ không được xóa cứng.
                Khi cần ngưng bán, admin dùng chức năng <strong>Ngưng bán</strong>.
            </p>
        </div>

        <a class="admin-btn primary" href="?url=admin/product/create">+ Thêm sản phẩm</a>
    </div>

    <?php if (!empty($message)): ?>
        <div class="admin-alert success">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="admin-alert error">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <div class="admin-toolbar">
        <a class="admin-filter <?php echo (($type ?? 'all') === 'all') ? 'active' : ''; ?>"
           href="?url=admin/product/index&type=all">
            Tất cả
        </a>

        <a class="admin-filter <?php echo (($type ?? '') === 'active') ? 'active' : ''; ?>"
           href="?url=admin/product/index&type=active">
            Đang bán
        </a>

        <a class="admin-filter <?php echo (($type ?? '') === 'hidden') ? 'active' : ''; ?>"
           href="?url=admin/product/index&type=hidden">
            Ngưng bán
        </a>

        <a class="admin-filter <?php echo (($type ?? '') === 'out_of_stock') ? 'active' : ''; ?>"
           href="?url=admin/product/index&type=out_of_stock">
            Hết hàng
        </a>
    </div>

    <div class="admin-card">
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Ảnh</th>
                        <th>Sản phẩm</th>
                        <th>Thương hiệu</th>
                        <th>Danh mục</th>
                        <th>Nhóm hương</th>
                        <th>Giá</th>
                        <th>Tồn kho</th>
                        <th>Trạng thái</th>
                        <th class="text-right">Thao tác</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="9" class="admin-empty">
                                Không có sản phẩm nào phù hợp.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($products as $product): ?>
                        <?php
                            $id = (int)($product['id'] ?? 0);
                            $name = $product['name'] ?? '';
                            $image = $product['main_image'] ?? '';
                            $status = (int)($product['status'] ?? 0);
                            $stock = (int)($product['stock'] ?? 0);
                            $price = (float)($product['price'] ?? 0);
                            $salePrice = $product['sale_price'] ?? null;
                        ?>

                        <tr>
                            <td>
                                <?php if (!empty($image)): ?>
                                    <img class="admin-product-img"
                                         src="<?php echo htmlspecialchars($image); ?>"
                                         alt="<?php echo htmlspecialchars($name); ?>">
                                <?php else: ?>
                                    <div class="admin-product-img placeholder">No image</div>
                                <?php endif; ?>
                            </td>

                            <td>
                                <strong><?php echo htmlspecialchars($name); ?></strong>
                                <span><?php echo htmlspecialchars($product['slug'] ?? ''); ?></span>
                            </td>

                            <td><?php echo htmlspecialchars($product['brand_name'] ?? ''); ?></td>

                            <td><?php echo htmlspecialchars($product['category_name'] ?? ''); ?></td>

                            <td><?php echo htmlspecialchars($product['scent_group'] ?? ''); ?></td>

                            <td>
                                <?php if (!empty($salePrice)): ?>
                                    <strong>
                                        <?php echo number_format((float)$salePrice, 0, ',', '.'); ?> VNĐ
                                    </strong>
                                    <span class="admin-old-price">
                                        <?php echo number_format($price, 0, ',', '.'); ?> VNĐ
                                    </span>
                                <?php else: ?>
                                    <strong>
                                        <?php echo number_format($price, 0, ',', '.'); ?> VNĐ
                                    </strong>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if ($stock <= 0): ?>
                                    <span class="admin-badge danger">Hết hàng</span>
                                <?php elseif ($stock <= 5): ?>
                                    <span class="admin-badge warning"><?php echo $stock; ?> còn lại</span>
                                <?php else: ?>
                                    <?php echo $stock; ?>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if ($status === 1): ?>
                                    <span class="admin-badge active">Đang bán</span>
                                <?php else: ?>
                                    <span class="admin-badge hidden">Ngưng bán</span>
                                <?php endif; ?>
                            </td>

                            <td class="text-right">
                                <a class="admin-btn small"
                                   href="?url=admin/product/edit/<?php echo $id; ?>">
                                    Sửa
                                </a>

                                <?php if ($status === 1): ?>
                                    <form method="post"
                                          action="?url=admin/product/lock"
                                          class="inline-form"
                                          onsubmit="return confirm('Ngưng bán sản phẩm này? Sản phẩm sẽ bị ẩn khỏi website nhưng vẫn giữ trong database để bảo toàn đơn hàng.');">
                                        <input type="hidden" name="id" value="<?php echo $id; ?>">
                                        <button class="admin-btn small warning" type="submit">
                                            Ngưng bán
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="post"
                                          action="?url=admin/product/unlock"
                                          class="inline-form"
                                          onsubmit="return confirm('Mở bán lại sản phẩm này?');">
                                        <input type="hidden" name="id" value="<?php echo $id; ?>">
                                        <button class="admin-btn small success" type="submit">
                                            Mở bán
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <form method="post"
                                      action="?url=admin/product/delete"
                                      class="inline-form"
                                      onsubmit="return confirm('Xóa cứng sản phẩm này? Chỉ thực hiện được nếu sản phẩm chưa có đơn hàng, giỏ hàng hoặc đánh giá. Nếu đã phát sinh dữ liệu, hệ thống sẽ từ chối xóa.');">
                                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                                    <button class="admin-btn small danger" type="submit">
                                        Xóa
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php

declare(strict_types=1);

namespace Mall\Services;

use Mall\Core\DatabaseManager;
use Mall\Core\HtmlSanitizer;
use PDO;

class CatalogService
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly HtmlSanitizer $sanitizer
    ) {
    }

    public function homeSections(): array
    {
        return [
            'featured_products' => $this->searchProducts(['page' => 1, 'page_size' => 8])['data'],
            'new_arrivals' => $this->flaggedProducts('is_new_arrival'),
            'recommended_courses' => $this->flaggedProducts('is_recommended_course', true),
            'hot_activities' => $this->listActivities(true),
            'categories' => $this->categoriesTree(),
            'brands' => $this->brands(),
        ];
    }

    public function navigationData(): array
    {
        return [
            'activities' => $this->listActivities(true),
            'new_products' => $this->flaggedProducts('is_new_arrival'),
            'recommended_courses' => $this->flaggedProducts('is_recommended_course', true),
        ];
    }

    public function searchProducts(array $filters = []): array
    {
        $pdo = $this->db->mall();
        $page = max(1, (int) ($filters['page'] ?? 1));
        $pageSize = max(1, min(20, (int) ($filters['page_size'] ?? 8)));
        $offset = ($page - 1) * $pageSize;
        $includeOffSale = !empty($filters['include_off_sale']);

        $where = ['1 = 1'];
        $params = [];

        if (!$includeOffSale) {
            $where[] = 'p.is_on_sale = 1';
        }

        if (!empty($filters['keyword'])) {
            $where[] = '(p.name LIKE :keyword OR p.summary LIKE :keyword OR p.subtitle LIKE :keyword)';
            $params[':keyword'] = '%' . trim((string) $filters['keyword']) . '%';
        }
        if (!empty($filters['brand'])) {
            $where[] = 'p.brand = :brand';
            $params[':brand'] = trim((string) $filters['brand']);
        }
        if (!empty($filters['category_id'])) {
            $where[] = 'p.category_id = :category_id';
            $params[':category_id'] = (int) $filters['category_id'];
        }
        if (!empty($filters['rating'])) {
            $where[] = 'p.rating >= :rating';
            $params[':rating'] = (float) $filters['rating'];
        }
        if ($filters['price_min'] ?? '' !== '') {
            $where[] = 'p.price >= :price_min';
            $params[':price_min'] = (float) $filters['price_min'];
        }
        if ($filters['price_max'] ?? '' !== '') {
            $where[] = 'p.price <= :price_max';
            $params[':price_max'] = (float) $filters['price_max'];
        }
        if (!empty($filters['created_from'])) {
            $where[] = 'DATE(p.created_at) >= :created_from';
            $params[':created_from'] = $filters['created_from'];
        }

        $sortMap = [
            'sales' => 'p.sales_count DESC, p.id DESC',
            'price_asc' => 'p.price ASC, p.id DESC',
            'price_desc' => 'p.price DESC, p.id DESC',
            'newest' => 'p.created_at DESC, p.id DESC',
        ];
        $sort = $sortMap[$filters['sort'] ?? 'newest'] ?? $sortMap['newest'];

        $whereSql = implode(' AND ', $where);

        $countStmt = $pdo->prepare(
            'SELECT COUNT(*) AS total
             FROM products p
             WHERE ' . $whereSql
        );
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value);
        }
        $countStmt->execute();
        $total = (int) (($countStmt->fetch()['total'] ?? 0));

        $sql =
            'SELECT p.*, c.name AS category_name
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE ' . $whereSql .
            ' ORDER BY ' . $sort .
            ' LIMIT :limit OFFSET :offset';
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $items = [];
        foreach ($stmt->fetchAll() as $row) {
            $items[] = $this->hydrateProduct($row, true);
        }

        return [
            'data' => $items,
            'meta' => [
                'page' => $page,
                'page_size' => $pageSize,
                'total' => $total,
                'has_more' => $offset + $pageSize < $total,
            ],
            'filters' => [
                'brands' => $this->brands(),
                'categories' => $this->categoriesTree(),
            ],
        ];
    }

    public function findProductBySlug(string $slug): ?array
    {
        $stmt = $this->db->mall()->prepare(
            'SELECT p.*, c.name AS category_name
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.slug = :slug OR p.name = :slug
             LIMIT 1'
        );
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        return $this->hydrateProduct($row, false);
    }

    public function quickView(int $productId): ?array
    {
        $stmt = $this->db->mall()->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $productId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $product = $this->hydrateProduct($row, false);
        return [
            'id' => $product['id'],
            'name' => $product['name'],
            'subtitle' => $product['subtitle'],
            'cover_image' => $product['cover_image'],
            'price' => $product['price'],
            'market_price' => $product['market_price'],
            'quick_view_text' => $product['quick_view_text'],
            'skus' => $product['skus'],
            'stock_total' => $product['stock_total'],
        ];
    }

    public function categoriesTree(): array
    {
        $items = $this->categoriesFlat();
        $indexed = [];
        foreach ($items as $item) {
            $item['children'] = [];
            $indexed[$item['id']] = $item;
        }

        $tree = [];
        foreach ($indexed as $id => $item) {
            $parentId = (int) ($item['parent_id'] ?? 0);
            if ($parentId > 0 && isset($indexed[$parentId])) {
                $indexed[$parentId]['children'][] = &$indexed[$id];
            } else {
                $tree[] = &$indexed[$id];
            }
        }

        return $tree;
    }

    public function categoriesFlat(): array
    {
        $stmt = $this->db->mall()->query(
            'SELECT id, parent_id, name, slug, level, sort_order, is_visible, type, icon_image
             FROM categories
             ORDER BY sort_order ASC, id ASC'
        );
        return $stmt->fetchAll();
    }

    public function saveCategory(array $data, ?int $categoryId = null): array
    {
        $pdo = $this->db->mall();
        $payload = [
            ':parent_id' => (int) ($data['parent_id'] ?? 0),
            ':name' => trim((string) $data['name']),
            ':slug' => trim((string) ($data['slug'] ?? slugify((string) $data['name']))),
            ':level' => (int) ($data['level'] ?? 1),
            ':sort_order' => (int) ($data['sort_order'] ?? 0),
            ':is_visible' => !empty($data['is_visible']) ? 1 : 0,
            ':type' => $data['type'] ?? 'product',
            ':icon_image' => trim((string) ($data['icon_image'] ?? '')),
            ':updated_at' => now(),
        ];

        if ($categoryId) {
            $sql = 'UPDATE categories
                    SET parent_id = :parent_id, name = :name, slug = :slug, level = :level, sort_order = :sort_order,
                        is_visible = :is_visible, type = :type, icon_image = :icon_image, updated_at = :updated_at
                    WHERE id = :id';
            $payload[':id'] = $categoryId;
        } else {
            $sql = 'INSERT INTO categories (parent_id, name, slug, level, sort_order, is_visible, type, icon_image, created_at, updated_at)
                    VALUES (:parent_id, :name, :slug, :level, :sort_order, :is_visible, :type, :icon_image, :created_at, :updated_at)';
            $payload[':created_at'] = now();
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($payload);
        $id = $categoryId ?: (int) $pdo->lastInsertId();

        $query = $pdo->prepare('SELECT id, parent_id, name, slug, level, sort_order, is_visible, type, icon_image FROM categories WHERE id = :id');
        $query->execute([':id' => $id]);
        return $query->fetch() ?: [];
    }

    public function sortCategories(array $items): void
    {
        $stmt = $this->db->mall()->prepare('UPDATE categories SET parent_id = :parent_id, sort_order = :sort_order WHERE id = :id');
        foreach ($items as $item) {
            $stmt->execute([
                ':parent_id' => (int) ($item['parent_id'] ?? 0),
                ':sort_order' => (int) ($item['sort_order'] ?? 0),
                ':id' => (int) $item['id'],
            ]);
        }
    }

    public function saveProduct(array $data, ?int $productId = null): array
    {
        $pdo = $this->db->mall();
        $pdo->beginTransaction();

        try {
            $skus = is_array($data['skus'] ?? null) ? $data['skus'] : [];
            $stockTotal = 0;
            foreach ($skus as $sku) {
                $stockTotal += (int) ($sku['stock'] ?? 0);
            }
            if ($stockTotal === 0) {
                $stockTotal = (int) ($data['stock_total'] ?? 0);
            }

            $summary = trim((string) ($data['summary'] ?? $data['subtitle'] ?? ''));
            $subtitle = trim((string) ($data['subtitle'] ?? $summary));

            $payload = [
                ':category_id' => (int) $data['category_id'],
                ':name' => trim((string) $data['name']),
                ':slug' => trim((string) ($data['slug'] ?? slugify((string) $data['name']))),
                ':summary' => $summary,
                ':subtitle' => $subtitle,
                ':brand' => trim((string) ($data['brand'] ?? '')),
                ':price' => round((float) ($data['price'] ?? 0), 2),
                ':market_price' => round((float) ($data['market_price'] ?? 0), 2),
                ':rating' => round((float) ($data['rating'] ?? 4.8), 1),
                ':sales_count' => (int) ($data['sales_count'] ?? 0),
                ':stock_total' => $stockTotal,
                ':is_on_sale' => !empty($data['is_on_sale']) ? 1 : 0,
                ':support_member_discount' => !empty($data['support_member_discount']) ? 1 : 0,
                ':is_course' => !empty($data['is_course']) ? 1 : 0,
                ':is_recommended_course' => !empty($data['is_recommended_course']) ? 1 : 0,
                ':is_new_arrival' => !empty($data['is_new_arrival']) ? 1 : 0,
                ':quick_view_text' => trim((string) ($data['quick_view_text'] ?? '')),
                ':cover_image' => trim((string) ($data['cover_image'] ?? '')),
                ':gallery_json' => json_encode_unicode($data['gallery'] ?? []),
                ':detail_html' => $this->sanitizer->clean((string) ($data['detail_html'] ?? '')),
                ':updated_at' => now(),
            ];

            if ($productId) {
                $sql = 'UPDATE products
                        SET category_id = :category_id, name = :name, slug = :slug, summary = :summary, subtitle = :subtitle, brand = :brand,
                            price = :price, market_price = :market_price, rating = :rating, sales_count = :sales_count,
                            stock_total = :stock_total, is_on_sale = :is_on_sale, support_member_discount = :support_member_discount,
                            is_course = :is_course, is_recommended_course = :is_recommended_course, is_new_arrival = :is_new_arrival,
                            quick_view_text = :quick_view_text, cover_image = :cover_image, gallery_json = :gallery_json,
                            detail_html = :detail_html, updated_at = :updated_at
                        WHERE id = :id';
                $payload[':id'] = $productId;
            } else {
                $sql = 'INSERT INTO products (category_id, name, slug, summary, subtitle, brand, price, market_price, rating, sales_count,
                            stock_total, is_on_sale, support_member_discount, is_course, is_recommended_course, is_new_arrival,
                            quick_view_text, cover_image, gallery_json, detail_html, created_at, updated_at)
                        VALUES (:category_id, :name, :slug, :summary, :subtitle, :brand, :price, :market_price, :rating, :sales_count,
                            :stock_total, :is_on_sale, :support_member_discount, :is_course, :is_recommended_course, :is_new_arrival,
                            :quick_view_text, :cover_image, :gallery_json, :detail_html, :created_at, :updated_at)';
                $payload[':created_at'] = now();
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($payload);
            $productId = $productId ?: (int) $pdo->lastInsertId();

            $pdo->prepare('DELETE FROM product_skus WHERE product_id = :product_id')->execute([':product_id' => $productId]);
            if ($skus !== []) {
                $insertSku = $pdo->prepare(
                    'INSERT INTO product_skus (product_id, sku_code, attribute_json, price, stock, cover_image, created_at, updated_at)
                     VALUES (:product_id, :sku_code, :attribute_json, :price, :stock, :cover_image, :created_at, :updated_at)'
                );
                foreach ($skus as $sku) {
                    $insertSku->execute([
                        ':product_id' => $productId,
                        ':sku_code' => $sku['sku_code'] ?? strtoupper('SKU' . $productId . bin2hex(random_bytes(2))),
                        ':attribute_json' => json_encode_unicode($sku['attributes'] ?? []),
                        ':price' => round((float) ($sku['price'] ?? $data['price']), 2),
                        ':stock' => (int) ($sku['stock'] ?? 0),
                        ':cover_image' => trim((string) ($sku['cover_image'] ?? $data['cover_image'] ?? '')),
                        ':created_at' => now(),
                        ':updated_at' => now(),
                    ]);
                }
            }

            $pdo->commit();
            return $this->findProductById($productId);
        } catch (\Throwable $throwable) {
            $pdo->rollBack();
            throw $throwable;
        }
    }

    public function batchUpdateProducts(array $productIds, string $action, mixed $value = null): void
    {
        if ($productIds === []) {
            return;
        }

        $placeholders = implode(', ', array_fill(0, count($productIds), '?'));
        $pdo = $this->db->mall();

        if ($action === 'shelf_on' || $action === 'shelf_off') {
            $stmt = $pdo->prepare("UPDATE products SET is_on_sale = ?, updated_at = ? WHERE id IN ($placeholders)");
            $params = array_merge([$action === 'shelf_on' ? 1 : 0, now()], array_map('intval', $productIds));
            $stmt->execute($params);
            return;
        }

        if ($action === 'price_adjust') {
            $stmt = $pdo->prepare("UPDATE products SET price = ?, updated_at = ? WHERE id IN ($placeholders)");
            $params = array_merge([round((float) $value, 2), now()], array_map('intval', $productIds));
            $stmt->execute($params);
        }
    }

    public function listActivities(bool $onlyActive = false): array
    {
        $sql = 'SELECT id, title, summary, thumbnail_image, content_html, display_order, is_active, starts_at, ends_at
                FROM activities';
        if ($onlyActive) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= ' ORDER BY display_order ASC, id DESC';
        $stmt = $this->db->mall()->query($sql);
        return $stmt->fetchAll();
    }

    public function saveActivity(array $data, ?int $activityId = null): array
    {
        $pdo = $this->db->mall();
        $payload = [
            ':title' => trim((string) $data['title']),
            ':summary' => trim((string) ($data['summary'] ?? '')),
            ':thumbnail_image' => trim((string) ($data['thumbnail_image'] ?? '')),
            ':content_html' => $this->sanitizer->clean((string) ($data['content_html'] ?? '')),
            ':display_order' => (int) ($data['display_order'] ?? 0),
            ':is_active' => !empty($data['is_active']) ? 1 : 0,
            ':starts_at' => $data['starts_at'] ?? null,
            ':ends_at' => $data['ends_at'] ?? null,
            ':updated_at' => now(),
        ];

        if ($activityId) {
            $sql = 'UPDATE activities SET title = :title, summary = :summary, thumbnail_image = :thumbnail_image,
                    content_html = :content_html, display_order = :display_order, is_active = :is_active,
                    starts_at = :starts_at, ends_at = :ends_at, updated_at = :updated_at WHERE id = :id';
            $payload[':id'] = $activityId;
        } else {
            $sql = 'INSERT INTO activities (title, summary, thumbnail_image, content_html, display_order, is_active, starts_at, ends_at, created_at, updated_at)
                    VALUES (:title, :summary, :thumbnail_image, :content_html, :display_order, :is_active, :starts_at, :ends_at, :created_at, :updated_at)';
            $payload[':created_at'] = now();
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($payload);
        $id = $activityId ?: (int) $pdo->lastInsertId();
        $query = $pdo->prepare('SELECT id, title, summary, thumbnail_image, content_html, display_order, is_active, starts_at, ends_at FROM activities WHERE id = :id');
        $query->execute([':id' => $id]);
        return $query->fetch() ?: [];
    }

    public function findActivityById(int $activityId): ?array
    {
        $stmt = $this->db->mall()->prepare(
            'SELECT id, title, summary, thumbnail_image, content_html, display_order, is_active, starts_at, ends_at
             FROM activities
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $activityId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function brands(): array
    {
        $stmt = $this->db->mall()->query('SELECT DISTINCT brand FROM products WHERE brand <> "" ORDER BY brand ASC');
        return array_map(static fn (array $row) => $row['brand'], $stmt->fetchAll());
    }

    public function findProductById(int $productId): ?array
    {
        $stmt = $this->db->mall()->prepare('SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.id = :id');
        $stmt->execute([':id' => $productId]);
        $row = $stmt->fetch();
        return $row ? $this->hydrateProduct($row, false) : null;
    }

    private function flaggedProducts(string $flag, bool $isCourse = false): array
    {
        $allowedFlags = ['is_new_arrival', 'is_recommended_course'];
        if (!in_array($flag, $allowedFlags, true)) {
            return [];
        }

        $sql = 'SELECT * FROM products WHERE ' . $flag . ' = 1 AND is_on_sale = 1';
        if ($isCourse) {
            $sql .= ' AND is_course = 1';
        }
        $sql .= ' ORDER BY created_at DESC LIMIT 6';
        $stmt = $this->db->mall()->query($sql);
        $items = [];
        foreach ($stmt->fetchAll() as $row) {
            $items[] = $this->hydrateProduct($row, true);
        }
        return $items;
    }

    private function hydrateProduct(array $row, bool $summaryOnly): array
    {
        $product = $row;
        $product['gallery'] = json_decode((string) ($row['gallery_json'] ?? '[]'), true) ?: [];
        $product['detail_html'] = (string) ($row['detail_html'] ?? '');
        $product['summary'] = trim((string) ($row['summary'] ?? ''));
        if ($product['summary'] === '') {
            $product['summary'] = trim((string) ($row['subtitle'] ?? ''));
        }
        $product['price'] = (float) ($row['price'] ?? 0);
        $product['market_price'] = (float) ($row['market_price'] ?? 0);
        $product['rating'] = (float) ($row['rating'] ?? 0);
        $product['stock_total'] = (int) ($row['stock_total'] ?? 0);
        $product['is_on_sale'] = (int) ($row['is_on_sale'] ?? 0);
        $product['support_member_discount'] = (int) ($row['support_member_discount'] ?? 0);
        $product['is_course'] = (int) ($row['is_course'] ?? 0);
        $product['is_new_arrival'] = (int) ($row['is_new_arrival'] ?? 0);
        $product['is_recommended_course'] = (int) ($row['is_recommended_course'] ?? 0);

        if (!$summaryOnly) {
            $stmt = $this->db->mall()->prepare(
                'SELECT id, sku_code, attribute_json, price, stock, cover_image
                 FROM product_skus
                 WHERE product_id = :product_id
                 ORDER BY id ASC'
            );
            $stmt->execute([':product_id' => $row['id']]);
            $product['skus'] = array_map(static function (array $sku): array {
                $sku['attributes'] = json_decode((string) ($sku['attribute_json'] ?? '[]'), true) ?: [];
                $sku['price'] = (float) ($sku['price'] ?? 0);
                $sku['stock'] = (int) ($sku['stock'] ?? 0);
                return $sku;
            }, $stmt->fetchAll());
        } else {
            $product['skus'] = [];
        }

        return $product;
    }
}

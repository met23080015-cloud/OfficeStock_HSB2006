<?php
declare(strict_types=1);

final class InventoryService
{
    public static function move(
        string $type,
        array $items,
        ?int $supplierId = null,
        ?int $departmentId = null,
        ?string $note = null,
        ?int $requestId = null
    ): array {
        Auth::requireRole('WAREHOUSE');
        if (!in_array($type, ['IN','OUT','REQUEST_ISSUE'], true)) {
            throw new DomainException('Invalid inventory transaction type.');
        }
        if ($type === 'OUT' && (!$departmentId || $departmentId <= 0)) {
            throw new DomainException('Direct stock out requires a destination department.');
        }
        if ($type === 'IN' && $supplierId) {
            self::ensureActiveSupplier($supplierId);
        }
        if ($departmentId) {
            self::ensureActiveDepartment($departmentId);
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $reference = next_code($type === 'IN' ? 'IN' : ($type === 'REQUEST_ISSUE' ? 'ISSUE' : 'OUT'));
            $header = $pdo->prepare(
                "INSERT INTO inventory_transactions
                 (reference_code,type,supplier_id,department_id,request_id,note,created_by)
                 VALUES(?,?,?,?,?,?,?)"
            );
            $header->execute([
                $reference, $type, $supplierId, $departmentId, $requestId,
                $note ? trim($note) : null, (int)Auth::requireUser()['id'],
            ]);
            $txId = (int)$pdo->lastInsertId();

            $lock = $pdo->prepare(
                "SELECT i.quantity,p.name,p.unit_cost,p.status
                 FROM inventory i
                 JOIN products p ON p.id=i.product_id
                 WHERE i.product_id=? FOR UPDATE"
            );
            $insertItem = $pdo->prepare(
                "INSERT INTO inventory_transaction_items
                 (transaction_id,product_id,quantity,unit_cost)
                 VALUES(?,?,?,?)"
            );
            $updateStock = $pdo->prepare(
                "UPDATE inventory SET quantity=?,updated_at=UTC_TIMESTAMP() WHERE product_id=?"
            );

            foreach ($items as $item) {
                $pid = Validator::positiveInt($item['product_id'] ?? null, 'product_id');
                $qty = Validator::positiveInt($item['quantity'] ?? null, 'quantity');

                $lock->execute([$pid]);
                $stock = $lock->fetch();
                if (!$stock || $stock['status'] !== 'ACTIVE') {
                    throw new RuntimeException('A selected product is unavailable.');
                }

                $current = (int)$stock['quantity'];
                if ($type === 'IN') {
                    $new = $current + $qty;
                } else {
                    if ($qty > $current) {
                        throw new RuntimeException(
                            sprintf('Insufficient stock for "%s". Available: %d, requested: %d.',
                                $stock['name'], $current, $qty)
                        );
                    }
                    $new = $current - $qty;
                }

                $insertItem->execute([$txId, $pid, $qty, $stock['unit_cost']]);
                $updateStock->execute([$new, $pid]);
            }

            $pdo->commit();
            Audit::log('STOCK_'.$type, 'inventory_transaction', $txId, ['reference'=>$reference]);

            return ['transaction_id'=>$txId,'reference_code'=>$reference];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function inventory(array $filters = []): array
    {
        Auth::requireUser();

        $q = trim((string)($filters['q'] ?? ''));
        $stockStatus = strtolower(trim((string)($filters['stock_status'] ?? '')));
        $sort = strtolower(trim((string)($filters['sort'] ?? 'name')));
        $direction = strtoupper(trim((string)($filters['direction'] ?? 'ASC')));
        if (!in_array($direction, ['ASC','DESC'], true)) $direction = 'ASC';

        $order = safe_sort($sort, [
            'name'=>'p.name',
            'sku'=>'p.sku',
            'quantity'=>'i.quantity',
            'minimum'=>'p.minimum_stock',
            'category'=>'c.name',
        ], 'name');

        $sql = "SELECT p.id,p.sku,p.name,p.unit,p.minimum_stock,p.unit_cost,p.status,
                       c.id AS category_id,c.name AS category_name,
                       s.id AS supplier_id,s.name AS supplier_name,
                       i.quantity,i.updated_at,
                       CASE WHEN i.quantity<=p.minimum_stock THEN 1 ELSE 0 END AS is_low_stock
                FROM products p
                JOIN categories c ON c.id=p.category_id
                LEFT JOIN suppliers s ON s.id=p.supplier_id
                JOIN inventory i ON i.product_id=p.id
                WHERE p.status='ACTIVE'";
        $params = [];

        if ($q !== '') {
            $sql .= " AND (p.sku LIKE ? ESCAPE '\\\\' OR p.name LIKE ? ESCAPE '\\\\')";
            $term = like_term($q);
            $params[] = $term;
            $params[] = $term;
        }
        if ($stockStatus === 'low') $sql .= " AND i.quantity<=p.minimum_stock";
        if ($stockStatus === 'ok') $sql .= " AND i.quantity>p.minimum_stock";

        $sql .= " ORDER BY {$order} {$direction},p.id ASC";
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function transactions(array $filters = []): array
    {
        Auth::requireRole('ADMIN_MANAGER','WAREHOUSE');

        $type = strtoupper(trim((string)($filters['type'] ?? '')));
        $from = trim((string)($filters['from'] ?? ''));
        $to = trim((string)($filters['to'] ?? ''));
        $limit = min(500, max(1, (int)($filters['limit'] ?? 300)));

        $sql = "SELECT t.id,t.reference_code,t.type,t.note,t.created_at,t.request_id,
                       d.name AS department_name,s.name AS supplier_name,
                       u.full_name AS created_by_name,
                       ti.product_id,p.sku,p.name AS product_name,p.unit,ti.quantity,ti.unit_cost
                FROM inventory_transactions t
                JOIN users u ON u.id=t.created_by
                LEFT JOIN departments d ON d.id=t.department_id
                LEFT JOIN suppliers s ON s.id=t.supplier_id
                JOIN inventory_transaction_items ti ON ti.transaction_id=t.id
                JOIN products p ON p.id=ti.product_id
                WHERE 1=1";
        $params = [];

        if (in_array($type, ['IN','OUT','REQUEST_ISSUE'], true)) {
            $sql .= " AND t.type=?";
            $params[] = $type;
        }
        if ($from !== '' && self::validDate($from)) {
            $sql .= " AND DATE(t.created_at)>=?";
            $params[] = $from;
        }
        if ($to !== '' && self::validDate($to)) {
            $sql .= " AND DATE(t.created_at)<=?";
            $params[] = $to;
        }

        $sql .= " ORDER BY t.id DESC,ti.id ASC LIMIT {$limit}";
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function reportSummary(): array
    {
        Auth::requireRole('ADMIN_MANAGER');
        return Database::connection()->query(
            "SELECT type,COUNT(DISTINCT id) AS transaction_count
             FROM inventory_transactions GROUP BY type ORDER BY type"
        )->fetchAll();
    }

    private static function ensureActiveSupplier(int $id): void
    {
        $stmt = Database::connection()->prepare("SELECT id FROM suppliers WHERE id=? AND status='ACTIVE'");
        $stmt->execute([$id]);
        if (!$stmt->fetchColumn()) throw new DomainException('Supplier is invalid or inactive.');
    }

    private static function ensureActiveDepartment(int $id): void
    {
        $stmt = Database::connection()->prepare("SELECT id FROM departments WHERE id=? AND status='ACTIVE'");
        $stmt->execute([$id]);
        if (!$stmt->fetchColumn()) throw new DomainException('Department is invalid or inactive.');
    }

    private static function validDate(string $date): bool
    {
        $d = DateTimeImmutable::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}

<?php
declare(strict_types=1);

final class RequestService
{
    public static function create(string $reason, array $items): array
    {
        $user = Auth::requireRole('EMPLOYEE');
        $reason = trim($reason);
        if ($reason === '') throw new DomainException('Reason is required.');

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $code = next_code('REQ');
            $header = $pdo->prepare(
                "INSERT INTO stationery_requests(request_code,requester_id,reason,status)
                 VALUES(?,?,?,'PENDING')"
            );
            $header->execute([$code, (int)$user['id'], $reason]);
            $requestId = (int)$pdo->lastInsertId();

            $check = $pdo->prepare("SELECT id FROM products WHERE id=? AND status='ACTIVE'");
            $insert = $pdo->prepare(
                "INSERT INTO request_items(request_id,product_id,quantity) VALUES(?,?,?)"
            );
            foreach ($items as $item) {
                $pid = Validator::positiveInt($item['product_id'] ?? null, 'product_id');
                $qty = Validator::positiveInt($item['quantity'] ?? null, 'quantity');
                $check->execute([$pid]);
                if (!$check->fetchColumn()) throw new DomainException('A selected product is invalid or inactive.');
                $insert->execute([$requestId, $pid, $qty]);
            }

            $pdo->commit();
            Audit::log('CREATE_REQUEST', 'stationery_request', $requestId, ['code'=>$code]);
            return ['request_id'=>$requestId,'request_code'=>$code,'status'=>'PENDING'];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function cancelOwn(int $requestId): void
    {
        $user = Auth::requireRole('EMPLOYEE');
        $stmt = Database::connection()->prepare(
            "UPDATE stationery_requests SET status='CANCELLED'
             WHERE id=? AND requester_id=? AND status='PENDING'"
        );
        $stmt->execute([$requestId, (int)$user['id']]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Only your own PENDING request can be cancelled.');
        }
        Audit::log('CANCEL_REQUEST', 'stationery_request', $requestId);
    }

    public static function review(int $requestId, string $decision, ?string $note = null): void
    {
        $user = Auth::requireRole('ADMIN_MANAGER');
        $decision = strtolower(trim($decision));
        if (!in_array($decision, ['approve','reject'], true)) throw new DomainException('Decision is invalid.');

        $status = $decision === 'approve' ? 'APPROVED' : 'REJECTED';
        $note = trim((string)$note);
        if ($status === 'REJECTED' && $note === '') {
            throw new DomainException('A rejection reason is required.');
        }

        $stmt = Database::connection()->prepare(
            "UPDATE stationery_requests
             SET status=?,reviewed_by=?,reviewed_at=UTC_TIMESTAMP(),review_note=?
             WHERE id=? AND status='PENDING'"
        );
        $stmt->execute([$status, (int)$user['id'], $note ?: null, $requestId]);
        if ($stmt->rowCount() !== 1) throw new RuntimeException('The request is no longer PENDING.');

        Audit::log($status.'_REQUEST', 'stationery_request', $requestId);
    }

    public static function issue(int $requestId): array
    {
        $user = Auth::requireRole('WAREHOUSE');
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $header = $pdo->prepare(
                "SELECT sr.id,sr.request_code,sr.status,u.department_id
                 FROM stationery_requests sr
                 JOIN users u ON u.id=sr.requester_id
                 WHERE sr.id=? FOR UPDATE"
            );
            $header->execute([$requestId]);
            $request = $header->fetch();
            if (!$request || $request['status'] !== 'APPROVED') {
                throw new RuntimeException('Only APPROVED requests can be issued.');
            }
            $departmentId = (int)$request['department_id'];
            if ($departmentId <= 0) throw new RuntimeException('The requester has no department.');

            $itemsStmt = $pdo->prepare(
                "SELECT product_id,quantity FROM request_items WHERE request_id=? ORDER BY id"
            );
            $itemsStmt->execute([$requestId]);
            $items = $itemsStmt->fetchAll();
            if (!$items) throw new RuntimeException('The request has no items.');

            $reference = next_code('ISSUE');
            $tx = $pdo->prepare(
                "INSERT INTO inventory_transactions
                 (reference_code,type,supplier_id,department_id,request_id,note,created_by)
                 VALUES(?,'REQUEST_ISSUE',NULL,?,?,?,?)"
            );
            $tx->execute([
                $reference, $departmentId, $requestId,
                'Issued for '.$request['request_code'], (int)$user['id'],
            ]);
            $txId = (int)$pdo->lastInsertId();

            $lock = $pdo->prepare(
                "SELECT i.quantity,p.name,p.unit_cost,p.status
                 FROM inventory i JOIN products p ON p.id=i.product_id
                 WHERE i.product_id=? FOR UPDATE"
            );
            $insertItem = $pdo->prepare(
                "INSERT INTO inventory_transaction_items
                 (transaction_id,product_id,quantity,unit_cost) VALUES(?,?,?,?)"
            );
            $updateStock = $pdo->prepare(
                "UPDATE inventory SET quantity=?,updated_at=UTC_TIMESTAMP() WHERE product_id=?"
            );

            foreach ($items as $item) {
                $pid = (int)$item['product_id'];
                $qty = (int)$item['quantity'];
                $lock->execute([$pid]);
                $stock = $lock->fetch();
                if (!$stock || $stock['status'] !== 'ACTIVE') throw new RuntimeException('A request product is unavailable.');
                $current = (int)$stock['quantity'];
                if ($qty > $current) {
                    throw new RuntimeException(
                        sprintf('Insufficient stock for "%s". Available: %d, required: %d.',
                            $stock['name'], $current, $qty)
                    );
                }
                $insertItem->execute([$txId, $pid, $qty, $stock['unit_cost']]);
                $updateStock->execute([$current - $qty, $pid]);
            }

            $finish = $pdo->prepare(
                "UPDATE stationery_requests
                 SET status='ISSUED',issued_by=?,issued_at=UTC_TIMESTAMP()
                 WHERE id=? AND status='APPROVED'"
            );
            $finish->execute([(int)$user['id'], $requestId]);
            if ($finish->rowCount() !== 1) throw new RuntimeException('The request could not be finalized.');

            $pdo->commit();
            Audit::log('ISSUE_REQUEST', 'stationery_request', $requestId, [
                'transaction_id'=>$txId,'reference'=>$reference,
            ]);
            return ['transaction_id'=>$txId,'reference_code'=>$reference,'request_status'=>'ISSUED'];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function list(array $filters = []): array
    {
        $user = Auth::requireUser();
        $status = strtoupper(trim((string)($filters['status'] ?? '')));
        $allowed = ['PENDING','APPROVED','REJECTED','ISSUED','CANCELLED'];
        if ($status !== '' && !in_array($status, $allowed, true)) throw new DomainException('Invalid request status.');

        $conditions = ['1=1'];
        $params = [];

        if ($user['role_code'] === 'EMPLOYEE') {
            $conditions[] = 'sr.requester_id=?';
            $params[] = (int)$user['id'];
        } elseif ($user['role_code'] === 'WAREHOUSE') {
            if ($status === '') {
                $conditions[] = "sr.status IN ('APPROVED','ISSUED')";
            }
        } elseif ($user['role_code'] !== 'ADMIN_MANAGER') {
            throw new RuntimeException('Unsupported role.');
        }

        if ($status !== '') {
            $conditions[] = 'sr.status=?';
            $params[] = $status;
        }

        $sql = "SELECT sr.id,sr.request_code,sr.reason,sr.status,sr.review_note,
                       sr.created_at,sr.reviewed_at,sr.issued_at,
                       requester.full_name AS requester_name,
                       d.name AS department_name,
                       reviewer.full_name AS reviewer_name,
                       issuer.full_name AS issuer_name
                FROM stationery_requests sr
                JOIN users requester ON requester.id=sr.requester_id
                LEFT JOIN departments d ON d.id=requester.department_id
                LEFT JOIN users reviewer ON reviewer.id=sr.reviewed_by
                LEFT JOIN users issuer ON issuer.id=sr.issued_by
                WHERE ".implode(' AND ', $conditions)."
                ORDER BY sr.id DESC";
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        if (!$rows) return [];

        $ids = array_map(fn($r)=>(int)$r['id'], $rows);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $itemsStmt = Database::connection()->prepare(
            "SELECT ri.request_id,ri.product_id,ri.quantity,
                    p.sku,p.name,p.unit,i.quantity AS current_stock
             FROM request_items ri
             JOIN products p ON p.id=ri.product_id
             JOIN inventory i ON i.product_id=p.id
             WHERE ri.request_id IN ({$placeholders})
             ORDER BY ri.request_id DESC,ri.id ASC"
        );
        $itemsStmt->execute($ids);
        $grouped = [];
        foreach ($itemsStmt->fetchAll() as $item) {
            $grouped[(int)$item['request_id']][] = $item;
        }
        foreach ($rows as &$row) {
            $row['items'] = $grouped[(int)$row['id']] ?? [];
        }
        unset($row);
        return $rows;
    }
}

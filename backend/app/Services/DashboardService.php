<?php
declare(strict_types=1);

final class DashboardService
{
    public static function data(): array
    {
        $user = Auth::requireUser();
        $pdo = Database::connection();

        $common = [
            'total_products'=>(int)$pdo->query("SELECT COUNT(*) FROM products WHERE status='ACTIVE'")->fetchColumn(),
            'total_inventory'=>(int)$pdo->query("SELECT COALESCE(SUM(quantity),0) FROM inventory")->fetchColumn(),
            'low_stock'=>(int)$pdo->query(
                "SELECT COUNT(*) FROM inventory i JOIN products p ON p.id=i.product_id
                 WHERE p.status='ACTIVE' AND i.quantity<=p.minimum_stock"
            )->fetchColumn(),
        ];

        if ($user['role_code'] === 'EMPLOYEE') {
            $stmt=$pdo->prepare(
                "SELECT status,COUNT(*) AS total FROM stationery_requests
                 WHERE requester_id=? GROUP BY status"
            );
            $stmt->execute([(int)$user['id']]);
            $requestCounts=[];
            foreach($stmt->fetchAll() as $row) $requestCounts[$row['status']] = (int)$row['total'];
            return [
                'role'=>'EMPLOYEE',
                'metrics'=>$common + [
                    'my_pending'=>$requestCounts['PENDING']??0,
                    'my_approved'=>$requestCounts['APPROVED']??0,
                    'my_issued'=>$requestCounts['ISSUED']??0,
                ],
                'recent_requests'=>array_slice(RequestService::list([]),0,5),
            ];
        }

        if ($user['role_code'] === 'WAREHOUSE') {
            $approved=(int)$pdo->query("SELECT COUNT(*) FROM stationery_requests WHERE status='APPROVED'")->fetchColumn();
            return [
                'role'=>'WAREHOUSE',
                'metrics'=>$common + ['approved_requests'=>$approved],
                'recent_transactions'=>array_slice(InventoryService::transactions(['limit'=>6]),0,6),
                'approved_requests'=>array_slice(RequestService::list(['status'=>'APPROVED']),0,5),
            ];
        }

        Auth::requireRole('ADMIN_MANAGER');
        $pending=(int)$pdo->query("SELECT COUNT(*) FROM stationery_requests WHERE status='PENDING'")->fetchColumn();
        $approved=(int)$pdo->query("SELECT COUNT(*) FROM stationery_requests WHERE status='APPROVED'")->fetchColumn();
        $issued=(int)$pdo->query("SELECT COUNT(*) FROM stationery_requests WHERE status='ISSUED'")->fetchColumn();
        $recent = $pdo->query(
            "SELECT t.reference_code,t.type,t.created_at,u.full_name AS created_by_name
             FROM inventory_transactions t JOIN users u ON u.id=t.created_by
             ORDER BY t.id DESC LIMIT 6"
        )->fetchAll();

        return [
            'role'=>'ADMIN_MANAGER',
            'metrics'=>$common + [
                'pending_requests'=>$pending,
                'approved_requests'=>$approved,
                'issued_requests'=>$issued,
            ],
            'pending_requests'=>array_slice(RequestService::list(['status'=>'PENDING']),0,5),
            'recent_transactions'=>$recent,
        ];
    }
}

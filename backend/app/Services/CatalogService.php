<?php
declare(strict_types=1);

final class CatalogService
{
    public static function products(array $filters = []): array
    {
        Auth::requireUser();

        $q = trim((string)($filters['q'] ?? ''));
        $status = strtoupper(trim((string)($filters['status'] ?? 'ACTIVE')));
        $categoryId = (int)($filters['category_id'] ?? 0);
        $sort = strtolower(trim((string)($filters['sort'] ?? 'name')));
        $direction = strtoupper(trim((string)($filters['direction'] ?? 'ASC')));
        if (!in_array($direction, ['ASC','DESC'], true)) $direction = 'ASC';

        $order = safe_sort($sort, [
            'name'=>'p.name',
            'sku'=>'p.sku',
            'quantity'=>'i.quantity',
            'minimum'=>'p.minimum_stock',
            'created'=>'p.created_at',
        ], 'name');

        $sql = "SELECT p.id,p.sku,p.name,p.category_id,p.supplier_id,p.unit,
                       p.minimum_stock,p.unit_cost,p.status,p.created_at,p.updated_at,
                       c.name AS category_name,s.name AS supplier_name,i.quantity,
                       CASE WHEN i.quantity<=p.minimum_stock THEN 1 ELSE 0 END AS is_low_stock
                FROM products p
                JOIN categories c ON c.id=p.category_id
                LEFT JOIN suppliers s ON s.id=p.supplier_id
                JOIN inventory i ON i.product_id=p.id
                WHERE 1=1";
        $params = [];

        if ($status === 'ACTIVE' || $status === 'INACTIVE') {
            $sql .= " AND p.status=?";
            $params[] = $status;
        }
        if ($categoryId > 0) {
            $sql .= " AND p.category_id=?";
            $params[] = $categoryId;
        }
        if ($q !== '') {
            $sql .= " AND (p.sku LIKE ? ESCAPE '\\\\' OR p.name LIKE ? ESCAPE '\\\\')";
            $term = like_term($q);
            $params[] = $term;
            $params[] = $term;
        }
        $sql .= " ORDER BY {$order} {$direction},p.id ASC";

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function saveProduct(?int $id, array $data): array
    {
        Auth::requireRole('ADMIN_MANAGER');
        $sku = strtoupper(Validator::requiredString($data, 'sku', 40));
        $name = Validator::requiredString($data, 'name', 160);
        $categoryId = Validator::positiveInt($data['category_id'] ?? null, 'category_id');
        $supplierId = int_or_null($data['supplier_id'] ?? null);
        $unit = Validator::requiredString($data, 'unit', 40);
        $minimum = Validator::nonNegativeInt($data['minimum_stock'] ?? 0, 'minimum_stock');
        $unitCost = Validator::nonNegativeFloat($data['unit_cost'] ?? 0, 'unit_cost');

        self::ensureCategory($categoryId);
        if ($supplierId) self::ensureSupplier($supplierId);

        $pdo = Database::connection();
        if ($id && $id > 0) {
            $stmt = $pdo->prepare(
                "UPDATE products SET sku=?,name=?,category_id=?,supplier_id=?,unit=?,
                        minimum_stock=?,unit_cost=?,updated_at=UTC_TIMESTAMP()
                 WHERE id=?"
            );
            $stmt->execute([$sku,$name,$categoryId,$supplierId,$unit,$minimum,$unitCost,$id]);
            if ($stmt->rowCount() === 0) {
                $check = $pdo->prepare("SELECT id FROM products WHERE id=?");
                $check->execute([$id]);
                if (!$check->fetchColumn()) throw new RuntimeException('Product not found.');
            }
            Audit::log('UPDATE_PRODUCT','product',$id);
            return ['id'=>$id,'message'=>'Product updated.'];
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO products
                 (sku,name,category_id,supplier_id,unit,minimum_stock,unit_cost,status)
                 VALUES(?,?,?,?,?,?,?,'ACTIVE')"
            );
            $stmt->execute([$sku,$name,$categoryId,$supplierId,$unit,$minimum,$unitCost]);
            $newId = (int)$pdo->lastInsertId();
            $pdo->prepare("INSERT INTO inventory(product_id,quantity) VALUES(?,0)")->execute([$newId]);
            $pdo->commit();
            Audit::log('CREATE_PRODUCT','product',$newId);
            return ['id'=>$newId,'message'=>'Product created.'];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function deactivateProduct(int $id): void
    {
        Auth::requireRole('ADMIN_MANAGER');
        $stmt = Database::connection()->prepare("UPDATE products SET status='INACTIVE' WHERE id=? AND status='ACTIVE'");
        $stmt->execute([$id]);
        if ($stmt->rowCount() !== 1) throw new RuntimeException('Product not found or already inactive.');
        Audit::log('DEACTIVATE_PRODUCT','product',$id);
    }

    public static function restoreProduct(int $id): void
    {
        Auth::requireRole('ADMIN_MANAGER');
        $stmt = Database::connection()->prepare("UPDATE products SET status='ACTIVE' WHERE id=? AND status='INACTIVE'");
        $stmt->execute([$id]);
        if ($stmt->rowCount() !== 1) throw new RuntimeException('Product not found or already active.');
        Audit::log('RESTORE_PRODUCT','product',$id);
    }

    public static function suppliers(array $filters = []): array
    {
        Auth::requireUser();
        $q = trim((string)($filters['q'] ?? ''));
        $status = strtoupper(trim((string)($filters['status'] ?? '')));
        $sql = "SELECT id,name,phone,email,address,status,created_at FROM suppliers WHERE 1=1";
        $params = [];
        if (in_array($status,['ACTIVE','INACTIVE'],true)) {
            $sql .= " AND status=?";
            $params[] = $status;
        }
        if ($q !== '') {
            $term = like_term($q);
            $sql .= " AND (name LIKE ? ESCAPE '\\\\' OR email LIKE ? ESCAPE '\\\\' OR phone LIKE ? ESCAPE '\\\\')";
            array_push($params,$term,$term,$term);
        }
        $sql .= " ORDER BY name,id";
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function saveSupplier(?int $id, array $data): array
    {
        Auth::requireRole('ADMIN_MANAGER');
        $name = Validator::requiredString($data,'name',160);
        $phone = Validator::optionalString($data,'phone',30);
        $email = Validator::email($data,'email',false);
        $address = Validator::optionalString($data,'address',255);

        $pdo = Database::connection();
        if ($id && $id > 0) {
            $stmt = $pdo->prepare("UPDATE suppliers SET name=?,phone=?,email=?,address=? WHERE id=?");
            $stmt->execute([$name,$phone,$email,$address,$id]);
            if ($stmt->rowCount() === 0) {
                $check=$pdo->prepare("SELECT id FROM suppliers WHERE id=?");
                $check->execute([$id]);
                if(!$check->fetchColumn()) throw new RuntimeException('Supplier not found.');
            }
            Audit::log('UPDATE_SUPPLIER','supplier',$id);
            return ['id'=>$id,'message'=>'Supplier updated.'];
        }

        $stmt = $pdo->prepare(
            "INSERT INTO suppliers(name,phone,email,address,status) VALUES(?,?,?,?,'ACTIVE')"
        );
        $stmt->execute([$name,$phone,$email,$address]);
        $newId = (int)$pdo->lastInsertId();
        Audit::log('CREATE_SUPPLIER','supplier',$newId);
        return ['id'=>$newId,'message'=>'Supplier created.'];
    }

    public static function deactivateSupplier(int $id): void
    {
        Auth::requireRole('ADMIN_MANAGER');
        $stmt=Database::connection()->prepare("UPDATE suppliers SET status='INACTIVE' WHERE id=? AND status='ACTIVE'");
        $stmt->execute([$id]);
        if($stmt->rowCount()!==1) throw new RuntimeException('Supplier not found or already inactive.');
        Audit::log('DEACTIVATE_SUPPLIER','supplier',$id);
    }

    public static function restoreSupplier(int $id): void
    {
        Auth::requireRole('ADMIN_MANAGER');
        $stmt=Database::connection()->prepare("UPDATE suppliers SET status='ACTIVE' WHERE id=? AND status='INACTIVE'");
        $stmt->execute([$id]);
        if($stmt->rowCount()!==1) throw new RuntimeException('Supplier not found or already active.');
        Audit::log('RESTORE_SUPPLIER','supplier',$id);
    }

    public static function metadata(): array
    {
        Auth::requireUser();
        $pdo = Database::connection();
        return [
            'roles'=>$pdo->query("SELECT id,code,name FROM roles ORDER BY id")->fetchAll(),
            'departments'=>$pdo->query("SELECT id,name,status FROM departments ORDER BY name")->fetchAll(),
            'categories'=>$pdo->query("SELECT id,name,status FROM categories ORDER BY name")->fetchAll(),
            'suppliers'=>$pdo->query("SELECT id,name,status FROM suppliers ORDER BY name")->fetchAll(),
        ];
    }

    public static function users(): array
    {
        Auth::requireRole('ADMIN_MANAGER');
        return Database::connection()->query(
            "SELECT u.id,u.full_name,u.email,u.status,u.department_id,u.created_at,
                    r.id AS role_id,r.code AS role_code,r.name AS role_name,d.name AS department_name
             FROM users u
             JOIN roles r ON r.id=u.role_id
             LEFT JOIN departments d ON d.id=u.department_id
             ORDER BY u.id"
        )->fetchAll();
    }

    public static function createUser(array $data): array
    {
        Auth::requireRole('ADMIN_MANAGER');
        $name = Validator::requiredString($data,'full_name',120);
        $email = Validator::email($data,'email',true);
        $password = (string)($data['password'] ?? '');
        if (strlen($password) < 8) throw new DomainException('Password must contain at least 8 characters.');
        $roleId = Validator::positiveInt($data['role_id'] ?? null,'role_id');
        $departmentId = int_or_null($data['department_id'] ?? null);

        $pdo=Database::connection();
        $role=$pdo->prepare("SELECT code FROM roles WHERE id=?");
        $role->execute([$roleId]);
        $roleCode=$role->fetchColumn();
        if(!$roleCode) throw new DomainException('Role does not exist.');
        if($roleCode==='EMPLOYEE' && !$departmentId) throw new DomainException('Employee must be assigned to a department.');

        $stmt=$pdo->prepare(
            "INSERT INTO users(full_name,email,password_hash,role_id,department_id,status)
             VALUES(?,?,?,?,?,'ACTIVE')"
        );
        $stmt->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT),$roleId,$departmentId]);
        $id=(int)$pdo->lastInsertId();
        Audit::log('CREATE_USER','user',$id);
        return ['id'=>$id,'message'=>'User created.'];
    }

    public static function toggleUser(int $id): void
    {
        $current=Auth::requireRole('ADMIN_MANAGER');
        if((int)$current['id']===$id) throw new DomainException('You cannot lock your own active session.');
        $stmt=Database::connection()->prepare(
            "UPDATE users SET status=IF(status='ACTIVE','LOCKED','ACTIVE') WHERE id=?"
        );
        $stmt->execute([$id]);
        if($stmt->rowCount()!==1) throw new RuntimeException('User not found.');
        Database::connection()->prepare("DELETE FROM auth_sessions WHERE user_id=? AND EXISTS(SELECT 1 FROM users WHERE id=? AND status='LOCKED')")
            ->execute([$id,$id]);
        Audit::log('TOGGLE_USER','user',$id);
    }

    private static function ensureCategory(int $id): void
    {
        $stmt=Database::connection()->prepare("SELECT id FROM categories WHERE id=? AND status='ACTIVE'");
        $stmt->execute([$id]);
        if(!$stmt->fetchColumn()) throw new DomainException('Category is invalid or inactive.');
    }

    private static function ensureSupplier(int $id): void
    {
        $stmt=Database::connection()->prepare("SELECT id FROM suppliers WHERE id=? AND status='ACTIVE'");
        $stmt->execute([$id]);
        if(!$stmt->fetchColumn()) throw new DomainException('Supplier is invalid or inactive.');
    }
}

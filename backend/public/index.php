<?php
declare(strict_types=1);

// --- HỖ TRỢ CORS VÀ PREFLIGHT REQUEST (CHO VERCEL) ---
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
// ----------------------------------------------------

require __DIR__ . '/../app/bootstrap.php';

$method = Request::method();
$path = rtrim(Request::path(), '/');
if ($path === '') $path = '/';

try {
    if ($method === 'GET' && ($path === '/' || $path === '/health')) {
        try {
            $db = Database::ping();
            Response::json([
                'service'=>'OfficeStock PHP API',
                'status'=>'ok',
                'database'=>$db ? 'connected' : 'unavailable',
                'time_utc'=>gmdate(DATE_ATOM),
            ]);
        } catch (Throwable $e) {
            Response::error('Service is running but database is unavailable.', 503, 'DB_UNAVAILABLE', [
                'service'=>'OfficeStock PHP API',
                'exception_message' => $e->getMessage() // 💡 In trực tiếp chi tiết lỗi MySQL ra màn hình
            ]);
        }
    }

    if ($method === 'POST' && $path === '/api/auth/login') {
        $body = Request::json();
        $email = Validator::email($body, 'email', true);
        $password = (string)($body['password'] ?? '');
        if ($password === '') throw new DomainException('password is required.');
        Response::json(Auth::attempt((string)$email, $password));
    }

    if ($method === 'GET' && $path === '/api/auth/me') {
        Response::json(Auth::requireUser());
    }

    if ($method === 'POST' && $path === '/api/auth/logout') {
        Auth::requireUser();
        Auth::logout();
        Response::json(['message'=>'Logged out.']);
    }

    if ($method === 'GET' && $path === '/api/dashboard') {
        Response::json(DashboardService::data());
    }

    if ($method === 'GET' && $path === '/api/meta') {
        Response::json(CatalogService::metadata());
    }

    if ($method === 'GET' && $path === '/api/products') {
        Response::json(CatalogService::products($_GET));
    }

    if ($method === 'POST' && $path === '/api/products') {
        Response::json(CatalogService::saveProduct(null, Request::json()), 201);
    }

    if (preg_match('#^/api/products/(\d+)$#', $path, $m)) {
        $id = (int)$m[1];
        if ($method === 'PUT' || $method === 'PATCH') {
            Response::json(CatalogService::saveProduct($id, Request::json()));
        }
        if ($method === 'DELETE') {
            CatalogService::deactivateProduct($id);
            Response::json(['message'=>'Product deactivated.']);
        }
    }

    if ($method === 'POST' && preg_match('#^/api/products/(\d+)/restore$#', $path, $m)) {
        CatalogService::restoreProduct((int)$m[1]);
        Response::json(['message'=>'Product restored.']);
    }

    if ($method === 'GET' && $path === '/api/suppliers') {
        Response::json(CatalogService::suppliers($_GET));
    }

    if ($method === 'POST' && $path === '/api/suppliers') {
        Response::json(CatalogService::saveSupplier(null, Request::json()), 201);
    }

    if (preg_match('#^/api/suppliers/(\d+)$#', $path, $m)) {
        $id=(int)$m[1];
        if ($method === 'PUT' || $method === 'PATCH') {
            Response::json(CatalogService::saveSupplier($id, Request::json()));
        }
        if ($method === 'DELETE') {
            CatalogService::deactivateSupplier($id);
            Response::json(['message'=>'Supplier deactivated.']);
        }
    }

    if ($method === 'POST' && preg_match('#^/api/suppliers/(\d+)/restore$#', $path, $m)) {
        CatalogService::restoreSupplier((int)$m[1]);
        Response::json(['message'=>'Supplier restored.']);
    }

    if ($method === 'GET' && $path === '/api/inventory') {
        Response::json(InventoryService::inventory($_GET));
    }

    if ($method === 'POST' && $path === '/api/inventory/stock-in') {
        $body=Request::json();
        $items=Validator::items($body['items']??null);
        Response::json(InventoryService::move(
            'IN',
            $items,
            int_or_null($body['supplier_id']??null),
            null,
            Validator::optionalString($body,'note',255)
        ), 201);
    }

    if ($method === 'POST' && $path === '/api/inventory/stock-out') {
        $body=Request::json();
        $items=Validator::items($body['items']??null);
        Response::json(InventoryService::move(
            'OUT',
            $items,
            null,
            Validator::positiveInt($body['department_id']??null,'department_id'),
            Validator::optionalString($body,'note',255)
        ), 201);
    }

    if ($method === 'GET' && $path === '/api/transactions') {
        Response::json(InventoryService::transactions($_GET));
    }

    if ($method === 'GET' && $path === '/api/reports') {
        Auth::requireRole('ADMIN_MANAGER');
        Response::json([
            'summary'=>InventoryService::reportSummary(),
            'rows'=>InventoryService::transactions($_GET),
        ]);
    }

    if ($method === 'GET' && $path === '/api/requests') {
        Response::json(RequestService::list($_GET));
    }

    if ($method === 'POST' && $path === '/api/requests') {
        $body=Request::json();
        $items=Validator::items($body['items']??null);
        Response::json(RequestService::create(
            Validator::requiredString($body,'reason',255),
            $items
        ), 201);
    }

    if ($method === 'POST' && preg_match('#^/api/requests/(\d+)/cancel$#', $path, $m)) {
        RequestService::cancelOwn((int)$m[1]);
        Response::json(['message'=>'Request cancelled.']);
    }

    if ($method === 'POST' && preg_match('#^/api/requests/(\d+)/review$#', $path, $m)) {
        $body=Request::json();
        RequestService::review(
            (int)$m[1],
            (string)($body['decision']??''),
            Validator::optionalString($body,'review_note',255)
        );
        Response::json(['message'=>'Request reviewed.']);
    }

    if ($method === 'POST' && preg_match('#^/api/requests/(\d+)/issue$#', $path, $m)) {
        Response::json(RequestService::issue((int)$m[1]), 201);
    }

    if ($method === 'GET' && $path === '/api/users') {
        Response::json(CatalogService::users());
    }

    if ($method === 'POST' && $path === '/api/users') {
        Response::json(CatalogService::createUser(Request::json()), 201);
    }

    if ($method === 'PATCH' && preg_match('#^/api/users/(\d+)/status$#', $path, $m)) {
        CatalogService::toggleUser((int)$m[1]);
        Response::json(['message'=>'User status updated.']);
    }

    Response::error('Endpoint not found.', 404, 'NOT_FOUND');
} catch (DomainException $e) {
    Response::error($e->getMessage(), 422, 'VALIDATION_ERROR');
} catch (PDOException $e) {
    $msg = str_contains(strtolower($e->getMessage()), 'duplicate')
        ? 'A unique value already exists.'
        : 'Database operation failed.';
    Response::error($msg, 409, 'DATABASE_ERROR');
} catch (RuntimeException $e) {
    Response::error($e->getMessage(), 409, 'BUSINESS_RULE_ERROR');
} catch (Throwable $e) {
    $debug = Env::bool('APP_DEBUG', true); // 💡 Bật hiển thị lỗi chi tiết
    Response::error(
        'Unexpected server error.',
        500,
        'SERVER_ERROR',
        $debug ? ['exception'=>$e::class,'message'=>$e->getMessage()] : null
    );
}

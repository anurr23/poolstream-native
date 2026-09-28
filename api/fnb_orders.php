<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$database = new Database();
$db = $database->getConnection();
if (!$db) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$fnbModel = new FnbItem($db);
$txModel = new Transaction($db);

$action = $_GET['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            $activeSearch = trim($_GET['active_search'] ?? '');
            $historySearch = trim($_GET['history_search'] ?? '');
            $filterPreset = trim($_GET['filter'] ?? 'hari_ini');

            $activeOrders = $txModel->getActiveFnbOrders($activeSearch);
            $historyOrders = $txModel->getFnbHistory($filterPreset, $historySearch);
            $fnbItems = $fnbModel->getAll();

            // Ekstrak kategori unik
            $categories = [];
            foreach ($fnbItems as $it) {
                if (!empty($it['category']) && !in_array($it['category'], $categories)) {
                    $categories[] = $it['category'];
                }
            }
            sort($categories);

            echo json_encode([
                'success' => true,
                'data' => [
                    'activeOrders' => $activeOrders,
                    'historyOrders' => $historyOrders,
                    'fnbItems' => $fnbItems,
                    'categories' => $categories,
                    'filterPreset' => $filterPreset,
                    'server_time' => date('Y-m-d H:i:s')
                ]
            ]);
            break;

        case 'create':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception("Method not allowed");
            }
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $customerName = trim($input['customer_name'] ?? '');
            $items = $input['items'] ?? [];

            if (empty($customerName)) {
                throw new Exception("Nama pelanggan wajib diisi!");
            }
            if (empty($items) || !is_array($items)) {
                throw new Exception("Pesanan F&B tidak boleh kosong!");
            }

            $userId = $_SESSION['user_id'] ?? '019f684b-9232-7270-b76c-0808bde4bacd';
            $txId = $txModel->createFnbOrder($userId, $customerName, $items);
            $tx = $txModel->getFnbOrderDetails($txId);

            echo json_encode([
                'success' => true,
                'message' => 'Pesanan F&B berhasil dibuat!',
                'transaction_id' => $txId,
                'transaction' => $tx
            ]);
            break;

        case 'detail':
            $txId = (int)($_GET['id'] ?? 0);
            if ($txId <= 0) {
                throw new Exception("ID transaksi tidak valid.");
            }
            $tx = $txModel->getFnbOrderDetails($txId);
            if (!$tx) {
                throw new Exception("Pesanan tidak ditemukan.");
            }
            echo json_encode(['success' => true, 'transaction' => $tx]);
            break;

        case 'add_item':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception("Method not allowed");
            }
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $txId = (int)($input['transaction_id'] ?? 0);
            $fnbId = (int)($input['fnb_item_id'] ?? 0);
            $qty = max(1, (int)($input['quantity'] ?? 1));

            if ($txId <= 0 || $fnbId <= 0) {
                throw new Exception("Parameter tidak valid.");
            }

            $tx = $txModel->addFnbOrderItem($txId, $fnbId, $qty);
            echo json_encode([
                'success' => true,
                'message' => 'Menu berhasil ditambahkan!',
                'transaction' => $tx
            ]);
            break;

        case 'update_item_qty':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception("Method not allowed");
            }
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $itemId = (int)($input['item_id'] ?? 0);
            $change = (int)($input['change'] ?? 1);

            if ($itemId <= 0) {
                throw new Exception("Parameter tidak valid.");
            }

            $tx = $txModel->updateFnbItemQuantity($itemId, $change);
            echo json_encode([
                'success' => true,
                'message' => 'Jumlah berhasil diubah!',
                'transaction' => $tx
            ]);
            break;

        case 'delete_item':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception("Method not allowed");
            }
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $itemId = (int)($input['item_id'] ?? 0);

            if ($itemId <= 0) {
                throw new Exception("Parameter tidak valid.");
            }

            $tx = $txModel->removeFnbItem($itemId);
            echo json_encode([
                'success' => true,
                'message' => 'Item berhasil dihapus!',
                'transaction' => $tx
            ]);
            break;

        case 'checkout':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception("Method not allowed");
            }
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $txId = (int)($input['transaction_id'] ?? 0);

            if ($txId <= 0) {
                throw new Exception("Parameter tidak valid.");
            }

            $tx = $txModel->checkoutFnbOrder($txId);
            echo json_encode([
                'success' => true,
                'message' => 'Pesanan F&B berhasil di-checkout!',
                'transaction' => $tx
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Action tidak dikenali']);
            break;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

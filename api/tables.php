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

$tableModel = new Table($db);
$packageModel = new Package($db);
$fnbModel = new FnbItem($db);
$txModel = new Transaction($db);

$action = $_GET['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            // Auto checkout meja yang sudah lewat batas waktu (sesuai poolstream)
            $txModel->autoCheckoutExpired();

            $tables = $tableModel->getAllWithActiveAndRecentTransactions();
            $packages = $packageModel->getAll();
            $fnbItems = $fnbModel->getAll();

            echo json_encode([
                'success' => true,
                'data' => [
                    'tables' => $tables,
                    'packages' => $packages,
                    'fnbItems' => $fnbItems,
                    'server_time' => date('Y-m-d H:i:s')
                ]
            ]);
            break;

        case 'start':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception("Method not allowed");
            }
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $tableId = $input['table_id'] ?? null;
            $customerName = trim($input['customer_name'] ?? '');
            $packageId = $input['package_id'] ?? null;
            $durationHours = floatval($input['duration_hours'] ?? 1);
            $items = $input['items'] ?? [];

            if (empty($tableId) || empty($customerName) || empty($packageId) || $durationHours <= 0) {
                throw new Exception("Data form tidak lengkap.");
            }

            $userId = $_SESSION['user_id'] ?? '019f684b-9232-7270-b76c-0808bde4bacd';
            $txId = $txModel->startSession($tableId, $userId, $customerName, $packageId, $durationHours, $items);

            echo json_encode(['success' => true, 'message' => 'Sesi meja berhasil dimulai!', 'transaction_id' => $txId]);
            break;

        case 'update':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception("Method not allowed");
            }
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $tableId = $input['table_id'] ?? null;
            $txId = $input['transaction_id'] ?? null;
            $packageId = $input['package_id'] ?? null;
            $durationHours = floatval($input['duration_hours'] ?? 1);
            $items = $input['items'] ?? [];

            if (empty($tableId) || empty($txId) || empty($packageId)) {
                throw new Exception("Parameter tidak valid.");
            }

            $txModel->updateSession($tableId, $txId, $packageId, $durationHours, $items);
            echo json_encode(['success' => true, 'message' => 'Sesi pesanan berhasil diperbarui!']);
            break;

        case 'stop':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception("Method not allowed");
            }
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $tableId = $input['table_id'] ?? null;

            if (empty($tableId)) {
                throw new Exception("Table ID tidak ditemukan.");
            }

            $tx = $txModel->stopSession($tableId);
            echo json_encode(['success' => true, 'message' => 'Meja berhasil dihentikan / checkout.', 'transaction' => $tx]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Action tidak dikenal']);
            break;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

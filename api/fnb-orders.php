<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

api_handle(function (): void {
    require_login();
    require_role(['admin', 'kasir']);
    $action = (string) ($_GET['action'] ?? 'list');
    $input = request_input();
    $txModel = new Transaction();

    if ($action === 'list') {
        json_ok([
            'data' => [
                'activeOrders' => $txModel->getActiveFnbOrders(),
                'historyOrders' => $txModel->getFnbHistory(),
                'fnbItems' => (new FnbItem())->all(),
                'categories' => (new FnbItem())->categories(),
                'server_time' => now(),
            ],
        ]);
    }

    require_method('POST');
    verify_csrf();
    $user = current_user();

    if ($action === 'create') {
        $customerName = trim((string) ($input['customer_name'] ?? ''));
        $items = is_array($input['items'] ?? null) ? $input['items'] : [];
        if ($customerName === '') {
            json_error(['customer_name' => 'Nama pelanggan wajib diisi.']);
        }
        $validItems = array_filter($items, static function ($item): bool {
            return is_array($item)
                && (int) ($item['fnb_item_id'] ?? 0) > 0
                && (int) ($item['quantity'] ?? 0) > 0;
        });
        if (!$validItems) {
            json_error(['items' => 'Pesanan F&B minimal berisi 1 item.']);
        }
        $transaction = $txModel->createFnbOrder((string) $user['id'], $customerName, $items);
        flash('success', 'Pesanan F&B berhasil dibuat.');
        json_ok(['transaction' => $transaction], 'Pesanan F&B berhasil dibuat.', 'fnb-orders.php');
    }

    if ($action === 'checkout') {
        $transactionId = (int) ($_GET['id'] ?? $input['transaction_id'] ?? 0);
        if ($transactionId <= 0) {
            json_error(['transaction' => 'Pesanan tidak ditemukan.']);
        }
        $transaction = $txModel->checkoutFnbOrder($transactionId);
        flash('success', 'Pesanan F&B berhasil di-checkout.');
        json_ok(['transaction' => $transaction], 'Pesanan F&B berhasil di-checkout.', 'fnb-orders.php');
    }

    if ($action === 'detail') {
        $transactionId = (int) ($_GET['id'] ?? $input['transaction_id'] ?? 0);
        $transaction = $txModel->fnbOrderDetails($transactionId);
        if (!$transaction) {
            json_error(['transaction' => 'Pesanan tidak ditemukan.'], 404);
        }
        json_ok(['transaction' => $transaction]);
    }

    json_error('Aksi tidak dikenal.', 404);
});

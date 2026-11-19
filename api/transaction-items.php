<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

api_handle(function (): void {
    require_login();
    require_role(['admin', 'kasir']);
    require_method('POST');
    verify_csrf();

    $action = (string) ($_GET['action'] ?? 'store');
    $input = request_input();
    $txModel = new Transaction();

    $back = (string) ($_SERVER['HTTP_REFERER'] ?? url('dashboard.php'));
    if (!preg_match('#^https?://#i', $back)) {
        $back = url('dashboard.php');
    }

    if ($action === 'store') {
        $transactionId = (int) ($_GET['id'] ?? $input['transaction_id'] ?? 0);
        $fnbId = (int) ($input['fnb_item_id'] ?? 0);
        $quantity = max(1, (int) ($input['quantity'] ?? 1));
        if ($transactionId <= 0 || $fnbId <= 0) {
            json_error(['item' => 'Item atau transaksi tidak valid.']);
        }
        $transaction = $txModel->addItem($transactionId, $fnbId, $quantity);
        flash('success', 'Menu berhasil ditambahkan.');
        json_ok(['transaction' => $transaction], 'Menu berhasil ditambahkan.', $back);
    }

    if ($action === 'update') {
        $itemId = (int) ($_GET['id'] ?? $input['item_id'] ?? 0);
        $change = (int) ($input['change'] ?? 1);
        if ($itemId <= 0) {
            json_error(['item' => 'Item tidak valid.']);
        }
        $transaction = $txModel->updateItemQuantity($itemId, $change);
        flash('success', 'Jumlah berhasil diubah.');
        json_ok(['transaction' => $transaction], 'Jumlah berhasil diubah.', $back);
    }

    if ($action === 'destroy') {
        $itemId = (int) ($_GET['id'] ?? $input['item_id'] ?? 0);
        if ($itemId <= 0) {
            json_error(['item' => 'Item tidak valid.']);
        }
        $transaction = $txModel->removeItem($itemId);
        flash('success', 'Item berhasil dihapus.');
        json_ok(['transaction' => $transaction], 'Item berhasil dihapus.', $back);
    }

    json_error('Aksi tidak dikenal.', 404);
});

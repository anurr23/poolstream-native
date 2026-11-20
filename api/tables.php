<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

api_handle(function (): void {
    require_login();
    require_role(['admin', 'kasir']);

    $action = (string) ($_GET['action'] ?? 'list');
    $input = request_input();

    if ($action === 'list') {
        $tableModel = new Table();
        $txModel = new Transaction();
        $txModel->autoCheckoutExpired();
        json_ok([
            'data' => [
                'tables' => $tableModel->allWithActiveAndRecentTransactions(),
                'packages' => (new Package())->all(),
                'fnbItems' => (new FnbItem())->all(),
                'upcomingBookings' => $txModel->upcomingBookings(),
                'server_time' => now(),
            ],
        ]);
    }

    require_method('POST');
    verify_csrf();

    $user = current_user();
    $txModel = new Transaction();
    $tableModel = new Table();

    switch ($action) {
        case 'start':
            $tableId = (string) ($_GET['id'] ?? $input['table_id'] ?? '');
            $customerName = trim((string) ($input['customer_name'] ?? ''));
            $packageId = (int) ($input['package_id'] ?? 0);
            $duration = (float) ($input['duration_hours'] ?? 0);
            $items = is_array($input['items'] ?? null) ? $input['items'] : [];

            if ($tableId === '' || $customerName === '' || $packageId <= 0 || $duration < 0.5) {
                json_error(['customer_name' => 'Lengkapi nama, paket, dan durasi minimal 0.5 jam.']);
            }

            $tx = $txModel->startSession($tableId, (string) $user['id'], $customerName, $packageId, $duration, $items);
            flash('success', 'Sesi meja berhasil dimulai.');
            json_ok(['transaction' => $tx], 'Sesi meja berhasil dimulai.', 'dashboard.php');
            break;

        case 'update':
            $tableId = (string) ($_GET['id'] ?? $input['table_id'] ?? '');
            $transactionId = (int) ($_GET['transaction'] ?? $input['transaction_id'] ?? 0);
            $packageId = (int) ($input['package_id'] ?? 0);
            $duration = (float) ($input['duration_hours'] ?? 0);
            $items = is_array($input['items'] ?? null) ? $input['items'] : [];

            if ($tableId === '' || $transactionId <= 0 || $packageId <= 0 || $duration < 0.5) {
                json_error(['duration_hours' => 'Parameter sesi tidak valid. Durasi minimal 0.5 jam.']);
            }

            $txModel->updateSession($tableId, $transactionId, $packageId, $duration, $items);
            flash('success', 'Sesi berhasil diperbarui.');
            json_ok([], 'Sesi berhasil diperbarui.', 'dashboard.php');
            break;

        case 'stop':
            $tableId = (string) ($_GET['id'] ?? $input['table_id'] ?? '');
            if ($tableId === '') {
                json_error(['table_id' => 'Meja tidak ditemukan.']);
            }
            $tx = $txModel->stopSession($tableId);
            flash('success', 'Meja berhasil dihentikan / checkout.');
            json_ok(['transaction' => $tx], 'Meja berhasil dihentikan / checkout.', 'dashboard.php');
            break;

        case 'start-booking':
            $transactionId = (int) ($_GET['id'] ?? $input['transaction_id'] ?? 0);
            if ($transactionId <= 0) {
                json_error(['booking' => 'Booking tidak ditemukan.']);
            }
            $booking = $txModel->startBooking($transactionId);
            flash('success', 'Booking ' . $booking['customer_name'] . ' berhasil dimulai.');
            json_ok(['transaction' => $booking], null, 'dashboard.php');
            break;

        case 'toggle-light':
            $tableId = (string) ($_GET['id'] ?? $input['table_id'] ?? '');
            $raw = $tableModel->findRaw($tableId);
            if (!$raw) {
                json_error(['table_id' => 'Meja tidak ditemukan.']);
            }
            $isOn = RelayService::toggle($raw);
            flash('success', 'Lampu ' . $raw['name'] . ' berhasil ' . ($isOn ? 'dinyalakan' : 'dimatikan') . '.');
            json_ok(['is_light_on' => $isOn], null, 'dashboard.php');
            break;

        default:
            json_error('Aksi tidak dikenal.', 404);
    }
});

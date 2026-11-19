<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

api_handle(function (): void {
    require_login();
    require_customer_verified();
    $user = current_user();
    $action = (string) ($_GET['action'] ?? '');
    $input = request_input();

    $txModel = new Transaction();
    $txModel->autoCheckoutExpired();

    if ($action === 'tables') {
        $date = (string) ($_GET['date'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            json_response(['message' => 'Tanggal tidak valid.', 'errors' => ['date' => ['Tanggal tidak valid.']]], 422);
        }
        json_response([
            'date' => $date,
            'tables' => $txModel->tablesForDate($date),
        ]);
    }

    if ($action === 'table-bookings') {
        $tableId = (string) ($_GET['id'] ?? '');
        $date = (string) ($_GET['date'] ?? '');
        if ($tableId === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            json_response(['message' => 'Parameter tidak valid.'], 422);
        }
        $table = fetch_one('SELECT * FROM tables WHERE id = :id LIMIT 1', [':id' => $tableId]);
        if (!$table) {
            json_response(['message' => 'Meja tidak ditemukan.'], 404);
        }
        json_response([
            'table' => ['id' => $table['id'], 'name' => $table['name']],
            'bookings' => $txModel->tableBookings($tableId, $date, (string) $user['id']),
        ]);
    }

    if ($action === 'availability') {
        $date = (string) ($_GET['date'] ?? '');
        $startTime = (string) ($_GET['start_time'] ?? '');
        $duration = (float) ($_GET['duration_hours'] ?? 0);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $startTime) || $duration <= 0) {
            json_response(['tables' => [], 'message' => 'Parameter tidak valid.'], 422);
        }
        $start = $date . ' ' . $startTime . ':00';
        if (strtotime($start) < time()) {
            json_response(['tables' => [], 'message' => 'Waktu mulai sudah lewat. Pilih jam lain.']);
        }
        $end = date('Y-m-d H:i:s', strtotime($start . ' +' . (int) round($duration * 60) . ' minutes'));
        $busy = $txModel->busyTableIds($start, $end);
        $tables = array_map(static function (array $table) use ($busy): array {
            return [
                'id' => $table['id'],
                'name' => $table['name'],
                'relay_channel' => $table['relay_channel'],
                'available' => !in_array($table['id'], $busy, true),
            ];
        }, (new Table())->all());
        json_response(['tables' => $tables, 'start' => to_iso($start), 'end' => to_iso($end)]);
    }

    if ($action === 'store') {
        require_method('POST');
        verify_csrf();

        $date = (string) ($input['date'] ?? '');
        $startTime = (string) ($input['start_time'] ?? '');
        $duration = (float) ($input['duration_hours'] ?? 0);
        $packageId = (int) ($input['package_id'] ?? 0);
        $tableId = (string) ($input['table_id'] ?? '');

        $today = date('Y-m-d');
        $maxDate = date('Y-m-d', strtotime('+30 days'));
        $errors = [];
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date < $today || $date > $maxDate) {
            $errors['date'] = 'Tanggal tidak valid.';
        }
        if (!preg_match('/^\d{2}:\d{2}$/', $startTime)) {
            $errors['start_time'] = 'Jam mulai tidak valid.';
        }
        if ($duration < 0.5 || $duration > 12) {
            $errors['duration_hours'] = 'Durasi harus antara 0,5 dan 12 jam.';
        }
        if ($packageId <= 0) {
            $errors['package_id'] = 'Paket wajib dipilih.';
        }
        if ($tableId === '') {
            $errors['table_id'] = 'Meja wajib dipilih.';
        }
        if ($errors) {
            json_error($errors);
        }

        $start = $date . ' ' . $startTime . ':00';
        if (strtotime($start) < time()) {
            json_error(['start_time' => 'Waktu mulai sudah lewat. Pilih jam lain.']);
        }
        $end = date('Y-m-d H:i:s', strtotime($start . ' +' . (int) round($duration * 60) . ' minutes'));

        if (in_array($tableId, $txModel->busyTableIds($start, $end), true)) {
            json_error(['table_id' => 'Meja ini sudah dibooking pada jam tersebut. Silakan pilih meja lain.']);
        }

        $package = (new Package())->find($packageId);
        if (!$package) {
            json_error(['package_id' => 'Paket tidak ditemukan.']);
        }

        $txModel->createBooking($tableId, (string) $user['id'], (string) $user['name'], $packageId, $start, $end, (float) $package['price'] * $duration);
        flash('success', 'Booking berhasil dibuat. Silakan datang sesuai jadwal.');
        json_ok([], null, 'customer-history.php');
    }

    if ($action === 'cancel') {
        require_method('POST');
        verify_csrf();
        $transactionId = (int) ($_GET['id'] ?? $input['transaction_id'] ?? 0);
        if ($transactionId <= 0) {
            json_error(['booking' => 'Booking tidak ditemukan.']);
        }
        $txModel->cancelBooking($transactionId, (string) $user['id']);
        flash('success', 'Booking berhasil dibatalkan.');
        json_ok([], null, 'customer-history.php');
    }

    json_error('Aksi tidak dikenal.', 404);
});

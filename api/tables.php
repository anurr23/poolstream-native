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
                'customers' => (new User())->customers(),
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
            $customerUserId = trim((string) ($input['customer_user_id'] ?? ''));
            $startDate = trim((string) ($input['start_date'] ?? ''));
            $startTime = trim((string) ($input['start_time'] ?? ''));
            if ($startDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) && preg_match('/^\d{2}:\d{2}$/', $startTime)) {
                $startTime = $startDate . ' ' . $startTime . ':00';
            }
            $packageId = (int) ($input['package_id'] ?? 0);
            $duration = (float) ($input['duration_hours'] ?? 0);
            $items = is_array($input['items'] ?? null) ? $input['items'] : [];

            if ($tableId === '' || $customerName === '' || $packageId <= 0 || $duration < 0.5) {
                json_error(['customer_name' => 'Lengkapi nama, jam mulai, durasi minimal 0.5 jam, dan paket.']);
            }

            $targetUserId = (string) $user['id'];
            if ($customerUserId !== '') {
                $custRow = fetch_one("SELECT id, name FROM users WHERE id = :id AND role = 'customer' LIMIT 1", [':id' => $customerUserId]);
                if ($custRow) {
                    $targetUserId = (string) $custRow['id'];
                }
            }

            $tx = $txModel->startSession($tableId, $targetUserId, $customerName, $packageId, $duration, $items, $startTime);
            $msg = ($tx['status'] ?? 'active') === 'booked'
                ? 'Jadwal pesanan meja berhasil dibuat.'
                : 'Sesi meja berhasil dimulai.';
            flash('success', $msg);
            json_ok(['transaction' => $tx], $msg, 'dashboard.php');
            break;

        case 'update':
            $tableId = (string) ($_GET['id'] ?? $input['table_id'] ?? '');
            $transactionId = (int) ($_GET['transaction'] ?? $input['transaction_id'] ?? 0);
            $packageId = (int) ($input['package_id'] ?? 0);
            $duration = (float) ($input['duration_hours'] ?? 0);
            $items = is_array($input['items'] ?? null) ? $input['items'] : [];
            $customerName = isset($input['customer_name']) ? trim((string) $input['customer_name']) : null;
            $customerUserId = isset($input['customer_user_id']) ? trim((string) $input['customer_user_id']) : null;
            $startDate = trim((string) ($input['start_date'] ?? ''));
            $startTime = trim((string) ($input['start_time'] ?? ''));
            $startDateTime = null;
            if ($startDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) && preg_match('/^\d{2}:\d{2}$/', $startTime)) {
                $startDateTime = $startDate . ' ' . $startTime . ':00';
            } elseif ($startTime !== '') {
                $startDateTime = $startTime;
            }

            if ($tableId === '' || $transactionId <= 0 || $packageId <= 0 || $duration < 0.5) {
                json_error(['duration_hours' => 'Parameter pesanan tidak valid. Durasi minimal 0.5 jam.']);
            }

            $txModel->updateSession($tableId, $transactionId, $packageId, $duration, $items, $customerName, $customerUserId, $startDateTime);
            flash('success', 'Pesanan berhasil diperbarui.');
            json_ok([], 'Pesanan berhasil diperbarui.', 'dashboard.php');
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

        case 'cancel-booking':
            $transactionId = (int) ($_GET['id'] ?? $input['transaction_id'] ?? 0);
            if ($transactionId <= 0) {
                json_error(['booking' => 'Booking tidak ditemukan.']);
            }
            $booking = $txModel->cancelBookingByStaff($transactionId);
            flash('success', 'Booking ' . ($booking['customer_name'] ?? '') . ' berhasil dibatalkan.');
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

        case 'send-receipt':
            $transactionId = (int) ($_GET['id'] ?? $input['transaction_id'] ?? 0);
            if ($transactionId <= 0) {
                json_error(['receipt' => 'Transaksi tidak ditemukan.']);
            }
            $tx = $txModel->find($transactionId);
            if (!$tx) {
                json_error(['receipt' => 'Transaksi tidak ditemukan.'], 404);
            }
            if (empty($tx['receipt_no'])) {
                $tx['receipt_no'] = $txModel->ensureReceiptNumber($transactionId, (string) ($tx['end_time'] ?? $tx['start_time'] ?? $tx['created_at'] ?? 'now'));
            }
            $receiptNo = (string) ($tx['receipt_no'] ?: ('#' . $tx['id']));
            $customer = $tx['user'] ?? $txModel->userFor($tx['user_id'] ?? null, $tx['customer_name'] ?? null);
            $email = trim((string) ($customer['email'] ?? ''));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                json_error(['receipt' => 'Customer belum melengkapi alamat email.']);
            }

            $kasirName = e($user['name'] ?? 'Kasir');
            $custName = e($tx['customer_name'] ?? '-');
            $tableName = e($tx['table']['name'] ?? ($input['table_name'] ?? ''));
            $isBilliard = ($tx['type'] ?? 'billiard') === 'billiard';
            $billiardCost = (float) ($tx['billiard_cost'] ?? 0);
            $fnbCost = (float) ($tx['fnb_cost'] ?? 0);
            $totalCost = $billiardCost + $fnbCost;
            $fmt = static fn (float $v): string => 'Rp ' . number_format($v, 0, ',', '.');
            $dateStr = date('d/m/Y H:i', strtotime((string) ($tx['end_time'] ?? $tx['start_time'] ?? $tx['created_at'] ?? 'now')));

            $durationText = '-';
            $sTs = !empty($tx['start_time']) ? strtotime((string) $tx['start_time']) : false;
            $eTs = !empty($tx['end_time']) ? strtotime((string) $tx['end_time']) : (!empty($tx['expected_end_time']) ? strtotime((string) $tx['expected_end_time']) : false);
            if ($sTs && $eTs && $eTs > $sTs) {
                $mins = (int) round(($eTs - $sTs) / 60);
                $h = intdiv($mins, 60);
                $m = $mins % 60;
                $durationText = $h > 0 ? ($m > 0 ? "{$h} Jam {$m} Mnt" : "{$h} Jam") : "{$mins} Mnt";
            }

            $rowsHtml = '';
            if ($isBilliard) {
                $pkgName = e($tx['package']['name'] ?? 'Paket Biliar');
                $rowsHtml .= "<tr><td style='padding:6px 0;border-bottom:1px solid #e2e8f0;'><strong>Biliar:</strong> {$pkgName} ({$durationText})</td><td style='padding:6px 0;border-bottom:1px solid #e2e8f0;text-align:right;'>" . $fmt($billiardCost) . "</td></tr>";
            }
            foreach (($tx['items'] ?? []) as $item) {
                $itemName = e($item['fnb_item']['name'] ?? 'Item F&B');
                $qty = (int) ($item['quantity'] ?? 1);
                $sub = (float) ($item['subtotal'] ?? 0);
                $rowsHtml .= "<tr><td style='padding:6px 0;border-bottom:1px solid #e2e8f0;'>{$itemName} &times; {$qty}</td><td style='padding:6px 0;border-bottom:1px solid #e2e8f0;text-align:right;'>" . $fmt($sub) . "</td></tr>";
            }

            $body = "<div style='font-family:Arial,sans-serif;max-width:480px;margin:0 auto;padding:20px;border:1px solid #e2e8f0;border-radius:12px;color:#0f172a;'>"
                . "<div style='text-align:center;border-bottom:2px solid #0f172a;padding-bottom:12px;margin-bottom:14px;'>"
                . "<h2 style='margin:0;letter-spacing:1px;'>POOLSTREAM</h2>"
                . "<div style='font-size:12px;color:#64748b;'>Billiard &amp; Lounge — Struk Pembayaran</div>"
                . "</div>"
                . "<table style='width:100%;font-size:13px;margin-bottom:14px;'>"
                . "<tr><td style='color:#64748b;padding:2px 0;'>No. Struk</td><td style='text-align:right;font-weight:bold;'>" . e($receiptNo) . "</td></tr>"
                . "<tr><td style='color:#64748b;padding:2px 0;'>Tanggal</td><td style='text-align:right;'>{$dateStr}</td></tr>"
                . "<tr><td style='color:#64748b;padding:2px 0;'>Kasir</td><td style='text-align:right;'>{$kasirName}</td></tr>"
                . "<tr><td style='color:#64748b;padding:2px 0;'>Customer</td><td style='text-align:right;font-weight:bold;'>{$custName}</td></tr>"
                . ($isBilliard && $tableName !== '' ? "<tr><td style='color:#64748b;padding:2px 0;'>Meja</td><td style='text-align:right;'>{$tableName}</td></tr>" : '')
                . "</table>"
                . "<table style='width:100%;font-size:13px;border-collapse:collapse;margin-bottom:14px;'>{$rowsHtml}</table>"
                . "<div style='display:flex;justify-content:space-between;border-top:2px solid #0f172a;padding-top:10px;font-size:16px;font-weight:bold;'>"
                . "<span>TOTAL: " . $fmt($totalCost) . "</span>"
                . "</div>"
                . "<p style='text-align:center;font-size:12px;color:#64748b;margin-top:18px;margin-bottom:0;'>Terima kasih telah berkunjung ke PoolStream!</p>"
                . "</div>";

            try {
                MailService::send($email, "Struk Pembayaran {$receiptNo} - PoolStream", $body);
            } catch (Throwable $e) {
                error_log('[Receipt Mail] ' . $e->getMessage());
                json_error(['receipt' => 'Gagal mengirim email struk saat ini. Silakan coba lagi.']);
            }

            json_ok(['email' => $email], "Struk berhasil dikirim ke email {$email}.");
            break;

        case 'send-receipt-wa':
            $transactionId = (int) ($_GET['id'] ?? $input['transaction_id'] ?? 0);
            if ($transactionId <= 0) {
                json_error(['receipt' => 'Transaksi tidak ditemukan.']);
            }
            $tx = $txModel->find($transactionId);
            if (!$tx) {
                json_error(['receipt' => 'Transaksi tidak ditemukan.'], 404);
            }
            if (empty($tx['receipt_no'])) {
                $tx['receipt_no'] = $txModel->ensureReceiptNumber($transactionId, (string) ($tx['end_time'] ?? $tx['start_time'] ?? $tx['created_at'] ?? 'now'));
            }
            $receiptNo = (string) ($tx['receipt_no'] ?: ('#' . $tx['id']));

            $customer = $tx['user'] ?? $txModel->userFor($tx['user_id'] ?? null, $tx['customer_name'] ?? null);
            $waRaw = normalize_whatsapp($customer['whatsapp'] ?? '');
            if ($waRaw === '') {
                json_error(['receipt' => 'Customer belum melengkapi nomor WhatsApp.']);
            }
            $noDestination = str_starts_with($waRaw, '0') ? '62' . substr($waRaw, 1) : $waRaw;

            $filePath = upload_image('file', 'receipts', ['png', 'jpg', 'jpeg', 'webp'], 5242880);
            if ($filePath === null) {
                json_error(['file' => 'Gambar struk gagal dibuat atau diunggah.']);
            }

            $kasirName = trim((string) ($user['name'] ?? 'Kasir'));
            $custName = trim((string) ($tx['customer_name'] ?? '-'));
            $tableName = trim((string) ($tx['table']['name'] ?? ($input['table_name'] ?? '')));
            $isBilliard = ($tx['type'] ?? 'billiard') === 'billiard';
            $billiardCost = (float) ($tx['billiard_cost'] ?? 0);
            $fnbCost = (float) ($tx['fnb_cost'] ?? 0);
            $totalCost = $billiardCost + $fnbCost;
            $fmt = static fn (float $v): string => 'Rp ' . number_format($v, 0, ',', '.');
            $dateStr = date('d/m/Y H:i', strtotime((string) ($tx['end_time'] ?? $tx['start_time'] ?? $tx['created_at'] ?? 'now')));

            $durationText = '-';
            $sTs = !empty($tx['start_time']) ? strtotime((string) $tx['start_time']) : false;
            $eTs = !empty($tx['end_time']) ? strtotime((string) $tx['end_time']) : (!empty($tx['expected_end_time']) ? strtotime((string) $tx['expected_end_time']) : false);
            if ($sTs && $eTs && $eTs > $sTs) {
                $mins = (int) round(($eTs - $sTs) / 60);
                $h = intdiv($mins, 60);
                $m = $mins % 60;
                $durationText = $h > 0 ? ($m > 0 ? "{$h} Jam {$m} Mnt" : "{$h} Jam") : "{$mins} Mnt";
            }

            $lines = [
                '*STRUK PEMBAYARAN - POOLSTREAM*',
                '--------------------------------',
                "No. Struk : {$receiptNo}",
                "Tanggal   : {$dateStr}",
                "Kasir     : {$kasirName}",
                "Customer  : {$custName}",
            ];
            if ($isBilliard && $tableName !== '') {
                $lines[] = "Meja      : {$tableName}";
            }
            $lines[] = '--------------------------------';
            if ($isBilliard) {
                $pkgName = (string) ($tx['package']['name'] ?? 'Paket Biliar');
                $lines[] = '*BILIAR*';
                $lines[] = "- {$pkgName} x {$durationText} : " . $fmt($billiardCost);
            }
            if (!empty($tx['items'])) {
                $lines[] = '*F&B*';
                foreach ($tx['items'] as $item) {
                    $itemName = (string) ($item['fnb_item']['name'] ?? 'Item F&B');
                    $qty = (int) ($item['quantity'] ?? 1);
                    $sub = (float) ($item['subtotal'] ?? 0);
                    $lines[] = "- {$itemName} x{$qty} : " . $fmt($sub);
                }
            }
            $lines[] = '--------------------------------';
            $lines[] = '*TOTAL : ' . $fmt($totalCost) . '*';
            $lines[] = '--------------------------------';
            $lines[] = 'Terima kasih telah berkunjung ke PoolStream!';
            $message = implode("
", $lines);

            execute_sql(
                "INSERT INTO send_wa_api (transaction_id, no_destination, message, file, status, created_at, updated_at)
                 VALUES (:transaction_id, :no_destination, :message, :file, 'pending', NOW(), NOW())",
                [
                    ':transaction_id' => $transactionId,
                    ':no_destination' => $noDestination,
                    ':message' => $message,
                    ':file' => $filePath,
                ]
            );
            $queueId = (int) db()->lastInsertId();

            json_ok([
                'id' => $queueId,
                'no_destination' => $noDestination,
                'file' => $filePath,
                'file_url' => storage_url($filePath),
            ], "Struk {$receiptNo} berhasil ditambahkan ke antrean WhatsApp ({$noDestination}).");
            break;

        default:
            json_error('Aksi tidak dikenal.', 404);
    }
});

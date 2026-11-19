export const printReceipt = (transaction, kasirName = 'Kasir') => {
    const now = new Date();
    const dateStr = now.toLocaleDateString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric' });
    const timeStr = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });

    const fmtPrice = (val) => {
        const n = Number(val) || 0;
        return n.toLocaleString('id-ID');
    };

    const calculateDuration = (tx) => {
        const startTimeStr = tx.start_time || tx.created_at;
        const endTimeStr = tx.end_time || tx.expected_end_time;
        
        if (startTimeStr && endTimeStr) {
            const start = new Date(startTimeStr);
            const end = new Date(endTimeStr);
            const diffMs = end - start;
            if (!isNaN(diffMs) && diffMs > 0) {
                const diffMinutes = Math.round(diffMs / (1000 * 60));
                if (diffMinutes < 60) {
                    return `${diffMinutes} Mnt`;
                }
                const hours = Math.floor(diffMinutes / 60);
                const mins = diffMinutes % 60;
                if (mins === 0) return `${hours} Jam`;
                return `${hours} Jam ${mins} Mnt`;
            }
        }
        return '-';
    };

    let itemsHtml = '';
    if (transaction.items && transaction.items.length > 0) {
        transaction.items.forEach(item => {
            const name = item.fnb_item?.name || item.name || 'Item';
            itemsHtml += `
                <tr>
                    <td style="padding: 2px 0;">${name} x${item.quantity}</td>
                    <td style="padding: 2px 0; text-align: right;">${fmtPrice(item.subtotal)}</td>
                </tr>
            `;
        });
    }

    const isBilliard = transaction.type === 'billiard';
    const billiardCost = Number(transaction.billiard_cost) || 0;
    const fnbCost = Number(transaction.fnb_cost) || 0;
    const totalCost = billiardCost + fnbCost;

    const html = `
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Struk #${transaction.id || ''}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            width: 80mm;
            padding: 8px;
            color: #000;
        }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .divider {
            border-top: 1px dashed #000;
            margin: 6px 0;
        }
        .double-divider {
            border-top: 2px solid #000;
            margin: 6px 0;
        }
        .store-name { font-size: 18px; font-weight: bold; letter-spacing: 2px; }
        .total-row { font-size: 16px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; }
        .footer { margin-top: 10px; font-size: 11px; }
        @media print {
            body { width: 80mm; }
            @page { size: 80mm auto; margin: 0; }
        }
    </style>
</head>
<body>
    <div class="center">
        <div class="store-name">POOLSTREAM</div>
        <div style="font-size: 10px; margin-top: 2px;">Billiard & Lounge</div>
    </div>
    <div class="double-divider"></div>

    <table>
        <tr><td>No</td><td class="right">#${transaction.id || '-'}</td></tr>
        <tr><td>Tanggal</td><td class="right">${dateStr} ${timeStr}</td></tr>
        <tr><td>Kasir</td><td class="right">${kasirName}</td></tr>
        <tr><td>Customer</td><td class="right">${transaction.customer_name || '-'}</td></tr>
        ${isBilliard && transaction.table_name ? `<tr><td>Meja</td><td class="right">${transaction.table_name}</td></tr>` : ''}
    </table>

    ${isBilliard ? `
        <div class="divider"></div>
        <div class="bold" style="margin-bottom: 4px;">BILIAR</div>
        <table>
            <tr>
                <td>${transaction.package?.name || 'Paket'} x ${calculateDuration(transaction)}</td>
                <td class="right">${fmtPrice(billiardCost)}</td>
            </tr>
        </table>
    ` : ''}

    ${transaction.items && transaction.items.length > 0 ? `
        <div class="divider"></div>
        <div class="bold" style="margin-bottom: 4px;">F&B</div>
        <table>${itemsHtml}</table>
    ` : ''}

    <div class="divider"></div>
    <table>
        ${isBilliard ? `
            <tr><td>Subtotal Biliar</td><td class="right">${fmtPrice(billiardCost)}</td></tr>
            <tr><td>Subtotal F&B</td><td class="right">${fmtPrice(fnbCost)}</td></tr>
        ` : `
            <tr><td>Subtotal F&B</td><td class="right">${fmtPrice(fnbCost)}</td></tr>
        `}
    </table>
    <div class="double-divider"></div>
    <table>
        <tr class="total-row">
            <td>TOTAL</td>
            <td class="right">Rp ${fmtPrice(totalCost)}</td>
        </tr>
    </table>
    <div class="double-divider"></div>

    <div class="center footer">
        <div>Terima kasih!</div>
        <div>Selamat bermain &#127921;</div>
    </div>

    <script>
        window.onload = function() {
            window.print();
        };
    <\/script>
</body>
</html>`;

    const printWindow = window.open('', '_blank', 'width=400,height=600');
    if (printWindow) {
        printWindow.document.write(html);
        printWindow.document.close();
    }
};

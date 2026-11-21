export const getReceiptNumber = (transaction) => {
    if (transaction?.receipt_no) return String(transaction.receipt_no);
    return transaction?.id ? `#${transaction.id}` : '-';
};

export const printReceipt = (transaction, kasirName = '') => {
    const now = new Date();
    const dateStr = now.toLocaleDateString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric' });
    const timeStr = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    const receiptNo = getReceiptNumber(transaction);

    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    }[char]));
    const resolvedKasir = kasirName || window.$page?.props?.auth?.user?.name || 'Kasir';
    const safeKasir = esc(resolvedKasir);

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
        transaction.items.forEach((item) => {
            const name = item.fnb_item?.name || item.name || 'Item';
            itemsHtml += `
                <tr>
                    <td style="padding: 2px 0;">${esc(name)} x${esc(item.quantity)}</td>
                    <td style="padding: 2px 0; text-align: right;">${fmtPrice(item.subtotal)}</td>
                </tr>
            `;
        });
    }

    const isBilliard = transaction.type === 'billiard';
    const billiardCost = Number(transaction.billiard_cost) || 0;
    const fnbCost = Number(transaction.fnb_cost) || 0;
    const totalCost = billiardCost + fnbCost;

    const html = `<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Struk ${esc(receiptNo)}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            width: 80mm;
            padding: 8px;
            color: #000;
            background: #fff;
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
        <div style="font-size: 10px; margin-top: 2px;">Billiard &amp; Lounge</div>
    </div>
    <div class="double-divider"></div>

    <table>
        <tr><td>No</td><td class="right">${esc(receiptNo)}</td></tr>
        <tr><td>Tanggal</td><td class="right">${dateStr} ${timeStr}</td></tr>
        <tr><td>Kasir</td><td class="right">${safeKasir}</td></tr>
        <tr><td>Customer</td><td class="right">${esc(transaction.customer_name || '-')}</td></tr>
        ${isBilliard && transaction.table_name ? `<tr><td>Meja</td><td class="right">${esc(transaction.table_name)}</td></tr>` : ''}
    </table>

    ${isBilliard ? `
        <div class="divider"></div>
        <div class="bold" style="margin-bottom: 4px;">BILIAR</div>
        <table>
            <tr>
                <td>${esc(transaction.package?.name || 'Paket')} x ${calculateDuration(transaction)}</td>
                <td class="right">${fmtPrice(billiardCost)}</td>
            </tr>
        </table>
    ` : ''}

    ${transaction.items && transaction.items.length > 0 ? `
        <div class="divider"></div>
        <div class="bold" style="margin-bottom: 4px;">F&amp;B</div>
        <table>${itemsHtml}</table>
    ` : ''}

    <div class="divider"></div>
    <table>
        ${isBilliard ? `
            <tr><td>Subtotal Biliar</td><td class="right">${fmtPrice(billiardCost)}</td></tr>
            <tr><td>Subtotal F&amp;B</td><td class="right">${fmtPrice(fnbCost)}</td></tr>
        ` : `
            <tr><td>Subtotal F&amp;B</td><td class="right">${fmtPrice(fnbCost)}</td></tr>
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
</body>
</html>`;

    return new Promise((resolve) => {
        const prev = document.getElementById('bb-receipt-print-frame');
        if (prev) {
            prev.remove();
        }

        const iframe = document.createElement('iframe');
        iframe.id = 'bb-receipt-print-frame';
        iframe.setAttribute('aria-hidden', 'true');
        iframe.style.position = 'fixed';
        iframe.style.right = '0';
        iframe.style.bottom = '0';
        iframe.style.width = '0';
        iframe.style.height = '0';
        iframe.style.border = '0';
        iframe.style.opacity = '0';
        iframe.style.pointerEvents = 'none';
        document.body.appendChild(iframe);

        let finished = false;
        const done = () => {
            if (finished) return;
            finished = true;
            setTimeout(() => {
                if (iframe.parentNode) {
                    iframe.parentNode.removeChild(iframe);
                }
            }, 300);
            resolve();
        };

        const win = iframe.contentWindow;
        const doc = win?.document || iframe.contentDocument;
        if (!win || !doc) {
            done();
            return;
        }

        const triggerPrint = () => {
            try {
                win.addEventListener('afterprint', done, { once: true });
                win.focus();
                win.print();
                // Di Chrome/Edge/Firefox, win.print() bersifat blocking sampai dialog print ditutup.
                setTimeout(done, 500);
            } catch (e) {
                console.error(e);
                done();
            }
        };

        doc.open();
        doc.write(html);
        doc.close();

        if (doc.readyState === 'complete') {
            setTimeout(triggerPrint, 50);
        } else {
            iframe.onload = () => setTimeout(triggerPrint, 50);
        }
    });
};

export const getReceiptContact = (transaction, customers = []) => {
    if (!transaction) return { whatsapp: null, email: null };

    let user = transaction.user || null;
    if (user && user.role && user.role !== 'customer') {
        user = null;
    }

    if ((!user || (!user.whatsapp && !user.email)) && Array.isArray(customers) && customers.length > 0) {
        const custName = (transaction.customer_name || '').trim().toLowerCase();
        user = customers.find(
            (c) => (transaction.user_id && c.id === transaction.user_id) || (custName && (c.name || '').trim().toLowerCase() === custName)
        ) || user;
    }

    const whatsapp = user?.whatsapp ? String(user.whatsapp).trim() : '';
    const email = user?.email ? String(user.email).trim() : '';

    return {
        whatsapp: whatsapp !== '' ? whatsapp : null,
        email: email !== '' ? email : null,
    };
};

const calculateDurationLabel = (tx) => {
    const startTimeStr = tx.start_time || tx.created_at;
    const endTimeStr = tx.end_time || tx.expected_end_time;
    if (startTimeStr && endTimeStr) {
        const start = new Date(startTimeStr);
        const end = new Date(endTimeStr);
        const diffMs = end - start;
        if (!isNaN(diffMs) && diffMs > 0) {
            const diffMinutes = Math.round(diffMs / (1000 * 60));
            if (diffMinutes < 60) return `${diffMinutes} Mnt`;
            const hours = Math.floor(diffMinutes / 60);
            const mins = diffMinutes % 60;
            return mins === 0 ? `${hours} Jam` : `${hours} Jam ${mins} Mnt`;
        }
    }
    return '-';
};

export const generateReceiptImageBlob = (transaction, kasirName = '') => {
    const receiptNo = getReceiptNumber(transaction);
    const refDate = new Date(transaction.end_time || transaction.start_time || transaction.created_at || Date.now());
    const validDate = isNaN(refDate.getTime()) ? new Date() : refDate;
    const dateStr = validDate.toLocaleDateString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric' });
    const timeStr = validDate.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    const resolvedKasir = kasirName || window.$page?.props?.auth?.user?.name || 'Kasir';
    const fmtPrice = (val) => 'Rp ' + (Number(val) || 0).toLocaleString('id-ID');

    const isBilliard = transaction.type === 'billiard';
    const tableName = transaction.table_name || transaction.table?.name || '';
    const billiardCost = Number(transaction.billiard_cost) || 0;
    const fnbCost = Number(transaction.fnb_cost) || 0;
    const totalCost = billiardCost + fnbCost;
    const items = Array.isArray(transaction.items) ? transaction.items : [];

    const width = 440;
    const padX = 28;
    const lineH = 24;

    const metaRows = [
        ['No. Struk', receiptNo],
        ['Tanggal', `${dateStr} ${timeStr}`],
        ['Kasir', resolvedKasir],
        ['Customer', transaction.customer_name || '-'],
    ];
    if (isBilliard && tableName) {
        metaRows.push(['Meja', tableName]);
    }

    let totalHeight = 36 + 52 + 22 + metaRows.length * lineH + 22;
    if (isBilliard) totalHeight += 28 + lineH + 16;
    if (items.length > 0) totalHeight += 28 + items.length * lineH + 16;
    if (isBilliard) totalHeight += lineH * 2;
    else totalHeight += lineH;
    totalHeight += 22 + 34 + 22 + 64;

    const scale = 2;
    const canvas = document.createElement('canvas');
    canvas.width = width * scale;
    canvas.height = totalHeight * scale;
    const ctx = canvas.getContext('2d');
    ctx.scale(scale, scale);

    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, width, totalHeight);

    const mono = '"Courier New", Consolas, monospace';
    const sans = 'system-ui, -apple-system, "Segoe UI", sans-serif';

    const drawDash = (yPos) => {
        ctx.save();
        ctx.strokeStyle = '#94a3b8';
        ctx.lineWidth = 1;
        ctx.setLineDash([5, 4]);
        ctx.beginPath();
        ctx.moveTo(padX, yPos);
        ctx.lineTo(width - padX, yPos);
        ctx.stroke();
        ctx.restore();
    };

    const drawSolid = (yPos) => {
        ctx.save();
        ctx.strokeStyle = '#0f172a';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.moveTo(padX, yPos);
        ctx.lineTo(width - padX, yPos);
        ctx.stroke();
        ctx.restore();
    };

    const drawPair = (yPos, leftText, rightText, boldLeft = false, boldRight = false, color = '#0f172a', size = 13) => {
        ctx.fillStyle = color;
        ctx.font = `${boldLeft ? 'bold ' : ''}${size}px ${mono}`;
        ctx.textAlign = 'left';
        const maxLeftW = width - padX * 2 - 135;
        let truncated = String(leftText);
        while (ctx.measureText(truncated).width > maxLeftW && truncated.length > 3) {
            truncated = truncated.slice(0, -4) + '...';
        }
        ctx.fillText(truncated, padX, yPos);

        ctx.font = `${boldRight ? 'bold ' : ''}${size}px ${mono}`;
        ctx.textAlign = 'right';
        ctx.fillText(String(rightText), width - padX, yPos);
    };

    let y = 40;
    ctx.fillStyle = '#0f172a';
    ctx.font = `bold 24px ${sans}`;
    ctx.textAlign = 'center';
    ctx.fillText('POOLSTREAM', width / 2, y);
    y += 22;
    ctx.fillStyle = '#64748b';
    ctx.font = `12px ${sans}`;
    ctx.fillText('Billiard & Lounge', width / 2, y);
    y += 18;
    drawSolid(y);
    y += 24;

    metaRows.forEach(([label, val], idx) => {
        drawPair(y, label, val, false, idx === 0);
        y += lineH;
    });

    if (isBilliard) {
        y += 4;
        drawDash(y);
        y += 22;
        ctx.fillStyle = '#0f172a';
        ctx.font = `bold 13px ${mono}`;
        ctx.textAlign = 'left';
        ctx.fillText('BILIAR', padX, y);
        y += lineH;
        const pkgName = transaction.package?.name || 'Paket Biliar';
        drawPair(y, `${pkgName} x ${calculateDurationLabel(transaction)}`, fmtPrice(billiardCost));
        y += lineH;
    }

    if (items.length > 0) {
        y += 4;
        drawDash(y);
        y += 22;
        ctx.fillStyle = '#0f172a';
        ctx.font = `bold 13px ${mono}`;
        ctx.textAlign = 'left';
        ctx.fillText('F&B', padX, y);
        y += lineH;
        items.forEach((item) => {
            const name = item.fnb_item?.name || item.name || 'Item';
            drawPair(y, `${name} x${item.quantity}`, fmtPrice(item.subtotal));
            y += lineH;
        });
    }

    y += 4;
    drawDash(y);
    y += 22;
    if (isBilliard) {
        drawPair(y, 'Subtotal Biliar', fmtPrice(billiardCost));
        y += lineH;
        drawPair(y, 'Subtotal F&B', fmtPrice(fnbCost));
        y += lineH;
    } else {
        drawPair(y, 'Subtotal F&B', fmtPrice(fnbCost));
        y += lineH;
    }

    y += 2;
    drawSolid(y);
    y += 26;
    drawPair(y, 'TOTAL', fmtPrice(totalCost), true, true, '#059669', 18);
    y += 14;
    drawSolid(y);
    y += 28;

    ctx.fillStyle = '#475569';
    ctx.font = `12px ${sans}`;
    ctx.textAlign = 'center';
    ctx.fillText('Terima kasih telah berkunjung ke PoolStream!', width / 2, y);

    return new Promise((resolve, reject) => {
        canvas.toBlob((blob) => {
            if (blob) resolve(blob);
            else reject(new Error('Gagal membuat gambar struk.'));
        }, 'image/png');
    });
};

export const buildReceiptFormData = async (transaction, tableName = '', kasirName = '') => {
    const tx = {
        ...transaction,
        table_name: tableName || transaction.table_name || transaction.table?.name || '',
    };
    const blob = await generateReceiptImageBlob(tx, kasirName);
    const receiptNo = getReceiptNumber(tx).replace(/[^a-zA-Z0-9_-]/g, '');
    const file = new File([blob], `struk-${receiptNo || tx.id || 'receipt'}.png`, { type: 'image/png' });
    const formData = new FormData();
    formData.append('file', file);
    if (tx.table_name) {
        formData.append('table_name', tx.table_name);
    }
    return formData;
};

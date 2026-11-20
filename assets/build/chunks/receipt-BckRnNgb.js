var e=(e,t=`Kasir`)=>{let n=new Date,r=n.toLocaleDateString(`id-ID`,{day:`2-digit`,month:`2-digit`,year:`numeric`}),i=n.toLocaleTimeString(`id-ID`,{hour:`2-digit`,minute:`2-digit`}),a=e=>String(e??``).replace(/[&<>"']/g,e=>({"&":`&amp;`,"<":`&lt;`,">":`&gt;`,'"':`&quot;`,"'":`&#039;`})[e]),o=a(t),s=e=>(Number(e)||0).toLocaleString(`id-ID`),c=e=>{let t=e.start_time||e.created_at,n=e.end_time||e.expected_end_time;if(t&&n){let e=new Date(t),r=new Date(n)-e;if(!isNaN(r)&&r>0){let e=Math.round(r/6e4);if(e<60)return`${e} Mnt`;let t=Math.floor(e/60),n=e%60;return n===0?`${t} Jam`:`${t} Jam ${n} Mnt`}}return`-`},l=``;e.items&&e.items.length>0&&e.items.forEach(e=>{let t=e.fnb_item?.name||e.name||`Item`;l+=`
                <tr>
                    <td style="padding: 2px 0;">${a(t)} x${a(e.quantity)}</td>
                    <td style="padding: 2px 0; text-align: right;">${s(e.subtotal)}</td>
                </tr>
            `});let u=e.type===`billiard`,d=Number(e.billiard_cost)||0,f=Number(e.fnb_cost)||0,p=d+f,m=`
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Struk #${a(e.id)}</title>
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
        <tr><td>No</td><td class="right">#${a(e.id||`-`)}</td></tr>
        <tr><td>Tanggal</td><td class="right">${r} ${i}</td></tr>
        <tr><td>Kasir</td><td class="right">${o}</td></tr>
        <tr><td>Customer</td><td class="right">${a(e.customer_name||`-`)}</td></tr>
        ${u&&e.table_name?`<tr><td>Meja</td><td class="right">${a(e.table_name)}</td></tr>`:``}
    </table>

    ${u?`
        <div class="divider"></div>
        <div class="bold" style="margin-bottom: 4px;">BILIAR</div>
        <table>
            <tr>
                <td>${a(e.package?.name||`Paket`)} x ${c(e)}</td>
                <td class="right">${s(d)}</td>
            </tr>
        </table>
    `:``}

    ${e.items&&e.items.length>0?`
        <div class="divider"></div>
        <div class="bold" style="margin-bottom: 4px;">F&B</div>
        <table>${l}</table>
    `:``}

    <div class="divider"></div>
    <table>
        ${u?`
            <tr><td>Subtotal Biliar</td><td class="right">${s(d)}</td></tr>
            <tr><td>Subtotal F&B</td><td class="right">${s(f)}</td></tr>
        `:`
            <tr><td>Subtotal F&B</td><td class="right">${s(f)}</td></tr>
        `}
    </table>
    <div class="double-divider"></div>
    <table>
        <tr class="total-row">
            <td>TOTAL</td>
            <td class="right">Rp ${s(p)}</td>
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
</html>`,h=window.open(``,`_blank`,`width=400,height=600`);h&&(h.document.write(m),h.document.close())};export{e as t};
var e=e=>e?.receipt_no?String(e.receipt_no):e?.id?`#${e.id}`:`-`,t=(t,n=``)=>{let r=new Date,i=r.toLocaleDateString(`id-ID`,{day:`2-digit`,month:`2-digit`,year:`numeric`}),a=r.toLocaleTimeString(`id-ID`,{hour:`2-digit`,minute:`2-digit`}),o=e(t),s=e=>String(e??``).replace(/[&<>"']/g,e=>({"&":`&amp;`,"<":`&lt;`,">":`&gt;`,'"':`&quot;`,"'":`&#039;`})[e]),c=s(n||window.$page?.props?.auth?.user?.name||`Kasir`),l=e=>(Number(e)||0).toLocaleString(`id-ID`),u=e=>{let t=e.start_time||e.created_at,n=e.end_time||e.expected_end_time;if(t&&n){let e=new Date(t),r=new Date(n)-e;if(!isNaN(r)&&r>0){let e=Math.round(r/6e4);if(e<60)return`${e} Mnt`;let t=Math.floor(e/60),n=e%60;return n===0?`${t} Jam`:`${t} Jam ${n} Mnt`}}return`-`},d=``;t.items&&t.items.length>0&&t.items.forEach(e=>{let t=e.fnb_item?.name||e.name||`Item`;d+=`
                <tr>
                    <td style="padding: 2px 0;">${s(t)} x${s(e.quantity)}</td>
                    <td style="padding: 2px 0; text-align: right;">${l(e.subtotal)}</td>
                </tr>
            `});let f=t.type===`billiard`,p=Number(t.billiard_cost)||0,m=Number(t.fnb_cost)||0,h=p+m,g=`<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Struk ${s(o)}</title>
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
        <tr><td>No</td><td class="right">${s(o)}</td></tr>
        <tr><td>Tanggal</td><td class="right">${i} ${a}</td></tr>
        <tr><td>Kasir</td><td class="right">${c}</td></tr>
        <tr><td>Customer</td><td class="right">${s(t.customer_name||`-`)}</td></tr>
        ${f&&t.table_name?`<tr><td>Meja</td><td class="right">${s(t.table_name)}</td></tr>`:``}
    </table>

    ${f?`
        <div class="divider"></div>
        <div class="bold" style="margin-bottom: 4px;">BILIAR</div>
        <table>
            <tr>
                <td>${s(t.package?.name||`Paket`)} x ${u(t)}</td>
                <td class="right">${l(p)}</td>
            </tr>
        </table>
    `:``}

    ${t.items&&t.items.length>0?`
        <div class="divider"></div>
        <div class="bold" style="margin-bottom: 4px;">F&amp;B</div>
        <table>${d}</table>
    `:``}

    <div class="divider"></div>
    <table>
        ${f?`
            <tr><td>Subtotal Biliar</td><td class="right">${l(p)}</td></tr>
            <tr><td>Subtotal F&amp;B</td><td class="right">${l(m)}</td></tr>
        `:`
            <tr><td>Subtotal F&amp;B</td><td class="right">${l(m)}</td></tr>
        `}
    </table>
    <div class="double-divider"></div>
    <table>
        <tr class="total-row">
            <td>TOTAL</td>
            <td class="right">Rp ${l(h)}</td>
        </tr>
    </table>
    <div class="double-divider"></div>

    <div class="center footer">
        <div>Terima kasih!</div>
        <div>Selamat bermain &#127921;</div>
    </div>
</body>
</html>`;return new Promise(e=>{let t=document.getElementById(`bb-receipt-print-frame`);t&&t.remove();let n=document.createElement(`iframe`);n.id=`bb-receipt-print-frame`,n.setAttribute(`aria-hidden`,`true`),n.style.position=`fixed`,n.style.right=`0`,n.style.bottom=`0`,n.style.width=`0`,n.style.height=`0`,n.style.border=`0`,n.style.opacity=`0`,n.style.pointerEvents=`none`,document.body.appendChild(n);let r=!1,i=()=>{r||(r=!0,setTimeout(()=>{n.parentNode&&n.parentNode.removeChild(n)},300),e())},a=n.contentWindow,o=a?.document||n.contentDocument;if(!a||!o){i();return}let s=()=>{try{a.addEventListener(`afterprint`,i,{once:!0}),a.focus(),a.print(),setTimeout(i,500)}catch(e){console.error(e),i()}};o.open(),o.write(g),o.close(),o.readyState===`complete`?setTimeout(s,50):n.onload=()=>setTimeout(s,50)})},n=(e,t=[])=>{if(!e)return{whatsapp:null,email:null};let n=e.user||null;if(n&&n.role&&n.role!==`customer`&&(n=null),(!n||!n.whatsapp&&!n.email)&&Array.isArray(t)&&t.length>0){let r=(e.customer_name||``).trim().toLowerCase();n=t.find(t=>e.user_id&&t.id===e.user_id||r&&(t.name||``).trim().toLowerCase()===r)||n}let r=n?.whatsapp?String(n.whatsapp).trim():``,i=n?.email?String(n.email).trim():``;return{whatsapp:r===``?null:r,email:i===``?null:i}},r=e=>{let t=e.start_time||e.created_at,n=e.end_time||e.expected_end_time;if(t&&n){let e=new Date(t),r=new Date(n)-e;if(!isNaN(r)&&r>0){let e=Math.round(r/6e4);if(e<60)return`${e} Mnt`;let t=Math.floor(e/60),n=e%60;return n===0?`${t} Jam`:`${t} Jam ${n} Mnt`}}return`-`},i=(t,n=``)=>{let i=e(t),a=new Date(t.end_time||t.start_time||t.created_at||Date.now()),o=isNaN(a.getTime())?new Date:a,s=o.toLocaleDateString(`id-ID`,{day:`2-digit`,month:`2-digit`,year:`numeric`}),c=o.toLocaleTimeString(`id-ID`,{hour:`2-digit`,minute:`2-digit`}),l=n||window.$page?.props?.auth?.user?.name||`Kasir`,u=e=>`Rp `+(Number(e)||0).toLocaleString(`id-ID`),d=t.type===`billiard`,f=t.table_name||t.table?.name||``,p=Number(t.billiard_cost)||0,m=Number(t.fnb_cost)||0,h=p+m,g=Array.isArray(t.items)?t.items:[],_=[[`No. Struk`,i],[`Tanggal`,`${s} ${c}`],[`Kasir`,l],[`Customer`,t.customer_name||`-`]];d&&f&&_.push([`Meja`,f]);let v=110+_.length*24+22;d&&(v+=68),g.length>0&&(v+=28+g.length*24+16),v+=d?48:24,v+=142;let y=document.createElement(`canvas`);y.width=880,y.height=v*2;let b=y.getContext(`2d`);b.scale(2,2),b.fillStyle=`#ffffff`,b.fillRect(0,0,440,v);let x=`"Courier New", Consolas, monospace`,S=`system-ui, -apple-system, "Segoe UI", sans-serif`,C=e=>{b.save(),b.strokeStyle=`#94a3b8`,b.lineWidth=1,b.setLineDash([5,4]),b.beginPath(),b.moveTo(28,e),b.lineTo(412,e),b.stroke(),b.restore()},w=e=>{b.save(),b.strokeStyle=`#0f172a`,b.lineWidth=2,b.beginPath(),b.moveTo(28,e),b.lineTo(412,e),b.stroke(),b.restore()},T=(e,t,n,r=!1,i=!1,a=`#0f172a`,o=13)=>{b.fillStyle=a,b.font=`${r?`bold `:``}${o}px ${x}`,b.textAlign=`left`;let s=String(t);for(;b.measureText(s).width>249&&s.length>3;)s=s.slice(0,-4)+`...`;b.fillText(s,28,e),b.font=`${i?`bold `:``}${o}px ${x}`,b.textAlign=`right`,b.fillText(String(n),412,e)},E=40;if(b.fillStyle=`#0f172a`,b.font=`bold 24px ${S}`,b.textAlign=`center`,b.fillText(`POOLSTREAM`,220,E),E+=22,b.fillStyle=`#64748b`,b.font=`12px ${S}`,b.fillText(`Billiard & Lounge`,220,E),E+=18,w(E),E+=24,_.forEach(([e,t],n)=>{T(E,e,t,!1,n===0),E+=24}),d){E+=4,C(E),E+=22,b.fillStyle=`#0f172a`,b.font=`bold 13px ${x}`,b.textAlign=`left`,b.fillText(`BILIAR`,28,E),E+=24;let e=t.package?.name||`Paket Biliar`;T(E,`${e} x ${r(t)}`,u(p)),E+=24}return g.length>0&&(E+=4,C(E),E+=22,b.fillStyle=`#0f172a`,b.font=`bold 13px ${x}`,b.textAlign=`left`,b.fillText(`F&B`,28,E),E+=24,g.forEach(e=>{let t=e.fnb_item?.name||e.name||`Item`;T(E,`${t} x${e.quantity}`,u(e.subtotal)),E+=24})),E+=4,C(E),E+=22,d?(T(E,`Subtotal Biliar`,u(p)),E+=24,T(E,`Subtotal F&B`,u(m)),E+=24):(T(E,`Subtotal F&B`,u(m)),E+=24),E+=2,w(E),E+=26,T(E,`TOTAL`,u(h),!0,!0,`#059669`,18),E+=14,w(E),E+=28,b.fillStyle=`#475569`,b.font=`12px ${S}`,b.textAlign=`center`,b.fillText(`Terima kasih telah berkunjung ke PoolStream!`,220,E),new Promise((e,t)=>{y.toBlob(n=>{n?e(n):t(Error(`Gagal membuat gambar struk.`))},`image/png`)})},a=async(t,n=``,r=``)=>{let a={...t,table_name:n||t.table_name||t.table?.name||``},o=await i(a,r),s=e(a).replace(/[^a-zA-Z0-9_-]/g,``),c=new File([o],`struk-${s||a.id||`receipt`}.png`,{type:`image/png`}),l=new FormData;return l.append(`file`,c),a.table_name&&l.append(`table_name`,a.table_name),l};export{n,t as r,a as t};
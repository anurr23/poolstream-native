<script setup>
import KasirLayout from '@/Layouts/KasirLayout.vue';
import BbSelect from '@/Components/BbSelect.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { Head, Link, router, useForm, usePage } from '@/shims/inertia.js';
import { printReceipt, getReceiptContact, buildReceiptFormData } from '@/utils/receipt';
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import flatPickr from 'vue-flatpickr-component';
import { Indonesian } from 'flatpickr/dist/l10n/id.js';

const props = defineProps({
    tables: {
        type: Array,
        default: () => []
    },
    packages: {
        type: Array,
        default: () => []
    },
    fnbItems: {
        type: Array,
        default: () => []
    },
    upcomingBookings: {
        type: Array,
        default: () => []
    },
    customers: {
        type: Array,
        default: () => []
    }
});

// ─── Helpers ────────────────────────────────────────────
const formatRupiah = (val) => {
    const amount = Number(val) || 0;
    return 'Rp ' + amount.toLocaleString('id-ID');
};

const nowTick = ref(Date.now());

const bookingsByTable = computed(() => {
    const map = {};
    props.upcomingBookings.forEach((booking) => {
        if (!booking.table_id) return;
        if (!map[booking.table_id]) map[booking.table_id] = [];
        map[booking.table_id].push(booking);
    });
    return map;
});

const bookingByTable = computed(() => {
    const map = {};
    Object.entries(bookingsByTable.value).forEach(([tableId, list]) => {
        if (list.length > 0) map[tableId] = list[0];
    });
    return map;
});

const formatBookingShort = (value) => {
    if (!value) return '';
    const date = new Date(value);
    const now = new Date(nowTick.value);
    const sameDay = date.toDateString() === now.toDateString();
    return date.toLocaleString('id-ID', sameDay
        ? { hour: '2-digit', minute: '2-digit' }
        : { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
};

const formatBookingFull = (value) => {
    if (!value) return '';
    const date = new Date(value);
    const now = new Date(nowTick.value);
    const sameDay = date.toDateString() === now.toDateString();
    const timeStr = date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    return sameDay ? `Hari ini, ${timeStr}` : date.toLocaleString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
};

const formatBookingRange = (booking) => {
    if (!booking?.start_time) return '';
    const startText = formatBookingFull(booking.start_time);
    if (!booking.expected_end_time) return startText;
    const endTime = new Date(booking.expected_end_time).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    return `${startText} – ${endTime}`;
};

const formatBookingCountdown = (booking) => {
    if (!booking?.start_time) return '';
    const diffMs = new Date(booking.start_time).getTime() - nowTick.value;
    const diffMin = Math.ceil(diffMs / 60000);
    if (diffMin <= 0) return 'Sudah masuk jadwal';
    if (diffMin < 60) return `${diffMin} mnt lagi`;
    const hours = Math.floor(diffMin / 60);
    const mins = diffMin % 60;
    if (hours < 24) {
        return mins > 0 ? `${hours}j ${mins}m lagi` : `${hours} jam lagi`;
    }
    const days = Math.floor(hours / 24);
    return `${days} hari lagi`;
};

const canStartBooking = (booking) => {
    const now = new Date(nowTick.value);
    const start = new Date(booking.start_time);
    const end = new Date(booking.expected_end_time);

    return now >= new Date(start.getTime() - 30 * 60 * 1000) && now < end;
};

const startBooking = (booking) => {
    showBookingModal.value = false;
    showTableHistoryModal.value = false;
    router.post(route('tables.start-booking', booking.id), {}, { preserveScroll: true });
};

const confirmCancelBooking = ref({
    show: false,
    booking: null,
});

const promptCancelBooking = (booking) => {
    confirmCancelBooking.value = {
        show: true,
        booking,
    };
};

const executeCancelBooking = () => {
    const booking = confirmCancelBooking.value.booking;
    if (!booking) return;
    router.post(route('tables.cancel-booking', booking.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            confirmCancelBooking.value = { show: false, booking: null };
        },
    });
};

const getBookingWhatsapp = (booking) => {
    return booking?.user?.whatsapp || null;
};

const getWhatsappLink = (booking) => {
    const raw = getBookingWhatsapp(booking);
    if (!raw) return null;
    const digits = String(raw).replace(/\D/g, '');
    const intl = digits.startsWith('0') ? '62' + digits.slice(1) : digits;
    const tableName = booking.table?.name || 'Meja';
    const schedule = formatBookingRange(booking);
    const text = `Halo kak ${booking.customer_name}, konfirmasi booking ${tableName} di PoolStream untuk jadwal ${schedule}.`;
    return `https://wa.me/${intl}?text=${encodeURIComponent(text)}`;
};

const bookingSearch = ref('');
const bookingDrawerFilter = ref('all');

const readyBookingsCount = computed(() => {
    return props.upcomingBookings.filter((b) => canStartBooking(b)).length;
});

const filteredUpcomingBookings = computed(() => {
    const now = new Date(nowTick.value);
    return props.upcomingBookings.filter((b) => {
        if (bookingDrawerFilter.value === 'ready' && !canStartBooking(b)) return false;
        if (bookingDrawerFilter.value === 'today') {
            const d = new Date(b.start_time);
            if (d.toDateString() !== now.toDateString()) return false;
        }
        const q = bookingSearch.value.trim().toLowerCase();
        if (!q) return true;
        const cust = (b.customer_name || '').toLowerCase();
        const tbl = (b.table?.name || '').toLowerCase();
        const wa = (b.user?.whatsapp || '').toLowerCase();
        return cust.includes(q) || tbl.includes(q) || wa.includes(q);
    });
});

const packageOptions = computed(() => {
    return props.packages.map(pkg => ({
        label: `${pkg.name} — ${formatRupiah(pkg.price)}/jam`,
        value: pkg.id
    }));
});

// ─── Booking Modal State ───────────────────────────────────
const showBookingModal = ref(false);

// ─── Manual Light Control ──────────────────────────────────
const togglingLight = ref(false);

const toggleTableLight = (table) => {
    if (!table || togglingLight.value) return;
    togglingLight.value = true;
    router.post(route('tables.toggle-light', table.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            togglingLight.value = false;
        }
    });
};

watch(() => props.tables, (newTables) => {
    if (selectedTableForHistory.value) {
        const found = newTables.find(t => t.id === selectedTableForHistory.value.id);
        if (found) selectedTableForHistory.value = found;
    }
    if (selectedActiveTable.value) {
        const found = newTables.find(t => t.id === selectedActiveTable.value.id);
        if (found) selectedActiveTable.value = found;
    }
}, { deep: true });

// ─── Table History Modal State ─────────────────────────────
const showTableHistoryModal = ref(false);
const selectedTableForHistory = ref(null);
const historySearch = ref('');
const openSendReceiptId = ref(null);
const sendingReceiptEmailId = ref(null);

const toggleSendReceiptMenu = (txId) => {
    openSendReceiptId.value = openSendReceiptId.value === txId ? null : txId;
};

const txContact = (tx) => {
    return getReceiptContact(tx, props.customers);
};

const hasAnyTxContact = (tx) => {
    const c = txContact(tx);
    return !!(c.whatsapp || c.email);
};

const handleSendReceiptWa = async (tx, tableName = '') => {
    const c = txContact(tx);
    if (!c.whatsapp || sendingReceiptEmailId.value === tx.id) return;
    openSendReceiptId.value = null;
    sendingReceiptEmailId.value = tx.id;
    try {
        const formData = await buildReceiptFormData(tx, tableName || tx.table?.name || '');
        await router.post(route('transactions.send-receipt-wa', tx.id), formData, {
            preserveScroll: true,
            preserveState: true,
        });
    } catch (e) {
        console.error(e);
    } finally {
        sendingReceiptEmailId.value = null;
    }
};

const handleSendReceiptEmail = (tx, tableName = '') => {
    const c = txContact(tx);
    if (!c.email || sendingReceiptEmailId.value === tx.id) return;
    openSendReceiptId.value = null;
    sendingReceiptEmailId.value = tx.id;
    router.post(route('transactions.send-receipt', tx.id), {
        table_name: tableName || tx.table?.name || '',
    }, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            sendingReceiptEmailId.value = null;
        },
    });
};

const openTableHistoryModal = (table) => {
    selectedTableForHistory.value = table;
    historySearch.value = '';
    openSendReceiptId.value = null;
    showTableHistoryModal.value = true;
};

const closeTableHistoryModal = () => {
    showTableHistoryModal.value = false;
    selectedTableForHistory.value = null;
    historySearch.value = '';
    openSendReceiptId.value = null;
};

// ─── Table Order (Start Session) Modal State ──────────────
const showOrderModal = ref(false);
const selectedTable = ref(null);
const showCustomerDropdown = ref(false);

const pad2 = (n) => String(n).padStart(2, '0');
const toYMD = (d) => `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`;
const currentYMD = () => toYMD(new Date(nowTick.value));
const tomorrowYMD = () => {
    const d = new Date(nowTick.value);
    d.setDate(d.getDate() + 1);
    return toYMD(d);
};
const maxYMD = () => {
    const d = new Date(nowTick.value);
    d.setDate(d.getDate() + 30);
    return toYMD(d);
};
const currentHHMM = () => {
    const d = new Date(nowTick.value);
    return `${pad2(d.getHours())}:${pad2(d.getMinutes())}`;
};
const setOrderNow = () => {
    form.start_date = currentYMD();
    form.start_time = currentHHMM();
};

const orderDateConfig = computed(() => ({
    dateFormat: 'Y-m-d',
    altInput: true,
    altFormat: 'd M Y',
    altInputClass: 'bb-input w-100',
    minDate: currentYMD(),
    maxDate: maxYMD(),
    disableMobile: true,
    locale: Indonesian,
}));

const orderTimeConfig = {
    enableTime: true,
    noCalendar: true,
    dateFormat: 'H:i',
    time_24hr: true,
    minuteIncrement: 5,
    disableMobile: true,
};

const form = useForm({
    customer_name: '',
    customer_user_id: '',
    start_date: '',
    start_time: '',
    duration_hours: 1,
    package_id: null,
    items: [],
});

const selectedCustomerObj = computed(() => {
    if (!form.customer_user_id) return null;
    return props.customers.find((c) => c.id === form.customer_user_id) || null;
});

const filteredCustomers = computed(() => {
    const q = (form.customer_name || '').trim().toLowerCase();
    if (!q) return props.customers.slice(0, 25);
    return props.customers
        .filter((c) => {
            return (
                (c.name || '').toLowerCase().includes(q) ||
                (c.username || '').toLowerCase().includes(q) ||
                (c.whatsapp || '').toLowerCase().includes(q) ||
                (c.email || '').toLowerCase().includes(q)
            );
        })
        .slice(0, 25);
});

const selectCustomer = (cust) => {
    form.customer_name = cust.name;
    form.customer_user_id = cust.id;
    showCustomerDropdown.value = false;
};

const clearSelectedCustomer = () => {
    form.customer_user_id = '';
    form.customer_name = '';
};

const onCustomerNameInput = () => {
    form.customer_name = form.customer_name.replace(/\b\w/g, (l) => l.toUpperCase());
    if (
        selectedCustomerObj.value &&
        selectedCustomerObj.value.name.trim().toLowerCase() !== form.customer_name.trim().toLowerCase()
    ) {
        form.customer_user_id = '';
    }
    showCustomerDropdown.value = true;
};

const openOrderModal = (table) => {
    form.reset();
    form.clearErrors();
    form.customer_user_id = '';
    form.start_date = currentYMD();
    form.start_time = currentHHMM();
    showCustomerDropdown.value = false;
    selectedTable.value = table;
    showOrderModal.value = true;
};

const closeOrderModal = () => {
    showOrderModal.value = false;
    showCustomerDropdown.value = false;
    
    // Return to history modal if it was opened from there
    if (selectedTableForHistory.value && selectedTable.value) {
        const updatedTable = props.tables.find(t => t.id === selectedTable.value.id);
        if (updatedTable) {
            selectedTableForHistory.value = updatedTable;
        }
        showTableHistoryModal.value = true;
    }
    
    selectedTable.value = null;
    form.reset();
};

const submitOrder = () => {
    if (conflictingOrderBooking.value || isOrderStartInPast.value) return;
    form.post(route('tables.start', selectedTable.value.id), {
        onSuccess: () => closeOrderModal()
    });
};

const selectedPackage = computed(() => {
    return props.packages.find(p => p.id === form.package_id) || {};
});

const calculatedPrice = computed(() => {
    return (selectedPackage.value.price || 0) * form.duration_hours;
});

const addDraftToTableOrder = (item) => {
    const existing = form.items.find(i => i.fnb_item_id === item.id);
    if (existing) {
        existing.quantity++;
    } else {
        form.items.push({
            fnb_item_id: item.id,
            name: item.name,
            price: item.price,
            quantity: 1
        });
    }
};

const removeDraftTableOrder = (index) => {
    form.items.splice(index, 1);
};

const tableOrderFnbTotal = computed(() => {
    return form.items.reduce((sum, item) => sum + (item.price * item.quantity), 0);
});

// ─── Timer / Countdown ──────────────────────────────────
const formatCountdown = (endString) => {
    if (!endString) return '00:00:00';
    const end = new Date(endString);
    const now = new Date();
    let diff = Math.floor((end - now) / 1000);
    if (diff < 0) diff = 0;
    const h = String(Math.floor(diff / 3600)).padStart(2, '0');
    const m = String(Math.floor((diff % 3600) / 60)).padStart(2, '0');
    const s = String(diff % 60).padStart(2, '0');
    return `${h}:${m}:${s}`;
};

const isExpired = (endString) => {
    if (!endString) return false;
    return new Date(endString) <= new Date();
};

const timers = ref({});
const autoStoppingTables = ref(new Set());
const autoStartingBookings = ref(new Set());

const updateTimers = () => {
    const newTimers = {};
    const now = new Date();

    props.tables.forEach(table => {
        if (table.status === 'active' && table.transactions && table.transactions.length > 0 && table.transactions[0].expected_end_time) {
            newTimers[table.id] = formatCountdown(table.transactions[0].expected_end_time);

            if (isExpired(table.transactions[0].expected_end_time)) {
                if (!autoStoppingTables.value.has(table.id)) {
                    autoStoppingTables.value.add(table.id);
                    
                    // Secara otomatis trigger stop meja dari frontend agar langsung instan
                    // tanpa perlu menunggu cron job server yang berjalan tiap 1 menit
                    router.post(route('tables.stop', table.id), {}, {
                        preserveScroll: true,
                        preserveState: true,
                    });
                }
            }
        }
        
        // Reset state jika meja sudah tidak aktif
        if (table.status !== 'active' && autoStoppingTables.value.has(table.id)) {
            autoStoppingTables.value.delete(table.id);
        }
    });

    // Auto-start booking saat waktu mulai (start_time) sudah tiba dan meja sedang kosong
    props.upcomingBookings.forEach((booking) => {
        if (!booking || booking.status !== 'booked' || !booking.start_time) return;
        const start = new Date(booking.start_time);
        const end = booking.expected_end_time ? new Date(booking.expected_end_time) : null;
        if (start <= now && (!end || end > now)) {
            const table = props.tables.find((t) => t.id === booking.table_id);
            if (table && table.status !== 'active' && !autoStartingBookings.value.has(booking.id)) {
                autoStartingBookings.value.add(booking.id);
                router.post(route('tables.start-booking', booking.id), {}, {
                    preserveScroll: true,
                    preserveState: true,
                    onFinish: () => {
                        autoStartingBookings.value.delete(booking.id);
                    },
                });
            }
        }
    });

    timers.value = newTimers;
};

// ─── Clock State ────────────────────────────────────────
const currentTime = ref('');
const currentDate = ref('');

const updateClock = () => {
    const now = new Date();
    nowTick.value = now.getTime();
    currentTime.value = now.toLocaleTimeString('id-ID', { hour12: false });
    currentDate.value = now.toLocaleDateString('id-ID', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
};

// ─── Filter State ───────────────────────────────────────
const currentFilter = ref('all');
const filteredTables = computed(() => {
    if (currentFilter.value === 'active') return props.tables.filter(t => t.status === 'active');
    if (currentFilter.value === 'ready') return props.tables.filter(t => t.status !== 'active');
    if (currentFilter.value === 'booked') return props.tables.filter(t => (bookingsByTable.value[t.id]?.length || 0) > 0);
    return props.tables;
});
const activeCount = computed(() => props.tables.filter(t => t.status === 'active').length);
const readyCount = computed(() => props.tables.filter(t => t.status !== 'active').length);
const bookedTableCount = computed(() => props.tables.filter(t => (bookingsByTable.value[t.id]?.length || 0) > 0).length);

const orderModalBookings = computed(() => {
    if (!selectedTable.value) return [];
    return bookingsByTable.value[selectedTable.value.id] || [];
});

const orderStartMs = computed(() => {
    const rawDate = (form.start_date || '').trim();
    const rawTime = (form.start_time || '').trim();
    if (!/^\d{4}-\d{2}-\d{2}$/.test(rawDate) || !/^\d{2}:\d{2}$/.test(rawTime)) {
        return nowTick.value;
    }
    const [yy, mo, dd] = rawDate.split('-').map(Number);
    const [hh, mm] = rawTime.split(':').map(Number);
    const d = new Date(yy, mo - 1, dd, hh, mm, 0, 0);
    if (Math.abs(d.getTime() - nowTick.value) <= 60 * 1000) {
        return nowTick.value;
    }
    return d.getTime();
});

const orderEndMs = computed(() => {
    const durationHours = Math.max(0.5, Number(form.duration_hours) || 0);
    return orderStartMs.value + durationHours * 3600 * 1000;
});

const isOrderStartInPast = computed(() => {
    return orderStartMs.value < nowTick.value - 60 * 1000;
});

const isOrderImmediate = computed(() => {
    return orderStartMs.value <= nowTick.value + 60 * 1000;
});

const orderTimeRangeLabel = computed(() => {
    const s = new Date(orderStartMs.value);
    const e = new Date(orderEndMs.value);
    const sStr = `${pad2(s.getHours())}:${pad2(s.getMinutes())}`;
    const eStr = `${pad2(e.getHours())}:${pad2(e.getMinutes())}`;
    const isToday = (form.start_date || '') === currentYMD();
    const datePrefix = isToday
        ? ''
        : s.toLocaleDateString('id-ID', { day: '2-digit', month: 'short' }) + ', ';
    return `${datePrefix}${sStr} – ${eStr}`;
});

const conflictingOrderBooking = computed(() => {
    if (!selectedTable.value) return null;
    const sMs = orderStartMs.value;
    const eMs = orderEndMs.value;

    // Cek sesi aktif di meja tersebut jika ada
    const activeTx = selectedTable.value.transactions?.[0];
    if (activeTx && activeTx.status === 'active') {
        const aStart = new Date(activeTx.start_time).getTime();
        const aEnd = activeTx.expected_end_time ? new Date(activeTx.expected_end_time).getTime() : Infinity;
        if (sMs < aEnd && eMs > aStart) {
            return activeTx;
        }
    }

    return orderModalBookings.value.find((b) => {
        const bStart = new Date(b.start_time).getTime();
        const bEnd = new Date(b.expected_end_time).getTime();
        return sMs < bEnd && eMs > bStart;
    }) || null;
});

let interval;
onMounted(() => {
    updateTimers();
    updateClock();
    interval = setInterval(() => {
        updateTimers();
        updateClock();
    }, 1000);
});
onUnmounted(() => {
    clearInterval(interval);
});

// ─── Session Detail Modal (Active Session & Scheduled Booking Edit) ──
const showSessionModal = ref(false);
const selectedActiveTable = ref(null);
const editingTransactionTarget = ref(null);
const showEditCustomerDropdown = ref(false);
const sessionTab = ref('edit'); // 'checkout' | 'edit'
const fnbSearch = ref('');
const fnbCategoryFilter = ref('all');

const editSessionForm = useForm({
    customer_name: '',
    customer_user_id: '',
    start_date: '',
    start_time: '',
    package_id: '',
    duration_hours: 1,
    items: [],
});

const activeTransaction = computed(() => {
    if (editingTransactionTarget.value) return editingTransactionTarget.value;
    if (!selectedActiveTable.value) return null;
    return selectedActiveTable.value.transactions?.[0] || null;
});

const isEditingBooked = computed(() => activeTransaction.value?.status === 'booked');

const selectedEditCustomerObj = computed(() => {
    if (!editSessionForm.customer_user_id) return null;
    return props.customers.find((c) => c.id === editSessionForm.customer_user_id) || null;
});

const filteredEditCustomers = computed(() => {
    const q = (editSessionForm.customer_name || '').trim().toLowerCase();
    if (!q) return props.customers.slice(0, 25);
    return props.customers
        .filter((c) => {
            return (
                (c.name || '').toLowerCase().includes(q) ||
                (c.username || '').toLowerCase().includes(q) ||
                (c.whatsapp || '').toLowerCase().includes(q) ||
                (c.email || '').toLowerCase().includes(q)
            );
        })
        .slice(0, 25);
});

const selectEditCustomer = (cust) => {
    editSessionForm.customer_name = cust.name;
    editSessionForm.customer_user_id = cust.id;
    showEditCustomerDropdown.value = false;
};

const clearEditSelectedCustomer = () => {
    editSessionForm.customer_user_id = '';
    editSessionForm.customer_name = '';
};

const onEditCustomerNameInput = () => {
    editSessionForm.customer_name = editSessionForm.customer_name.replace(/\b\w/g, (l) => l.toUpperCase());
    if (
        selectedEditCustomerObj.value &&
        selectedEditCustomerObj.value.name.trim().toLowerCase() !== editSessionForm.customer_name.trim().toLowerCase()
    ) {
        editSessionForm.customer_user_id = '';
    }
    showEditCustomerDropdown.value = true;
};

const minimumEditDuration = computed(() => {
    const tx = activeTransaction.value;
    if (!tx || !tx.start_time || tx.status === 'booked') return 0.5;
    
    const start = new Date(tx.start_time);
    const now = new Date(nowTick.value);
    const diffHours = (now - start) / (1000 * 60 * 60);
    
    const minHours = Math.ceil(diffHours * 2) / 2;
    return Math.max(0.5, minHours);
});

const editStartMs = computed(() => {
    const tx = activeTransaction.value;
    if (!tx) return nowTick.value;
    if (tx.status !== 'booked') {
        return new Date(tx.start_time).getTime();
    }
    const rawDate = (editSessionForm.start_date || '').trim();
    const rawTime = (editSessionForm.start_time || '').trim();
    if (!/^\d{4}-\d{2}-\d{2}$/.test(rawDate) || !/^\d{2}:\d{2}$/.test(rawTime)) {
        return new Date(tx.start_time).getTime();
    }
    const [yy, mo, dd] = rawDate.split('-').map(Number);
    const [hh, mm] = rawTime.split(':').map(Number);
    return new Date(yy, mo - 1, dd, hh, mm, 0, 0).getTime();
});

const editEndMs = computed(() => {
    const durationHours = Math.max(0.5, Number(editSessionForm.duration_hours) || 0);
    return editStartMs.value + durationHours * 3600 * 1000;
});

const isEditStartInPast = computed(() => {
    if (!isEditingBooked.value) return false;
    const origMs = activeTransaction.value?.start_time ? new Date(activeTransaction.value.start_time).getTime() : 0;
    if (Math.abs(editStartMs.value - origMs) < 60 * 1000) return false;
    return editStartMs.value < nowTick.value - 60 * 1000;
});

const editTimeRangeLabel = computed(() => {
    const s = new Date(editStartMs.value);
    const e = new Date(editEndMs.value);
    const sStr = `${pad2(s.getHours())}:${pad2(s.getMinutes())}`;
    const eStr = `${pad2(e.getHours())}:${pad2(e.getMinutes())}`;
    const sYmd = toYMD(s);
    const datePrefix = sYmd === currentYMD()
        ? ''
        : s.toLocaleDateString('id-ID', { day: '2-digit', month: 'short' }) + ', ';
    return `${datePrefix}${sStr} – ${eStr}`;
});

const conflictingEditBooking = computed(() => {
    const table = selectedActiveTable.value;
    const currentTx = activeTransaction.value;
    if (!table || !currentTx) return null;

    const sMs = editStartMs.value;
    const eMs = editEndMs.value;

    const activeTx = table.transactions?.[0];
    if (activeTx && activeTx.status === 'active' && activeTx.id !== currentTx.id) {
        const aStart = new Date(activeTx.start_time).getTime();
        const aEnd = activeTx.expected_end_time ? new Date(activeTx.expected_end_time).getTime() : Infinity;
        if (sMs < aEnd && eMs > aStart) {
            return activeTx;
        }
    }

    const tableBookings = bookingsByTable.value[table.id] || [];
    return tableBookings.find((b) => {
        if (b.id === currentTx.id) return false;
        const bStart = new Date(b.start_time).getTime();
        const bEnd = new Date(b.expected_end_time).getTime();
        return sMs < bEnd && eMs > bStart;
    }) || null;
});

const editSelectedPackage = computed(() => {
    return props.packages.find((p) => p.id === editSessionForm.package_id) || {};
});

const editCalculatedPrice = computed(() => {
    return (Number(editSelectedPackage.value.price) || 0) * (Number(editSessionForm.duration_hours) || 0);
});

const editFnbTotal = computed(() => {
    return (editSessionForm.items || []).reduce((sum, item) => sum + (Number(item.price) || 0) * (Number(item.quantity) || 0), 0);
});

const openSessionModal = (table, bookingTx = null) => {
    selectedActiveTable.value = table;
    editingTransactionTarget.value = bookingTx;
    showEditCustomerDropdown.value = false;
    sessionTab.value = 'edit';
    fnbSearch.value = '';
    fnbCategoryFilter.value = 'all';
    showSessionModal.value = true;
    
    const tx = bookingTx || table?.transactions?.[0] || null;
    if (tx) {
        editSessionForm.customer_name = tx.customer_name || '';
        const matchedCustomer = props.customers.find(
            (c) => c.id === tx.user_id || c.name.trim().toLowerCase() === (tx.customer_name || '').trim().toLowerCase()
        );
        editSessionForm.customer_user_id = matchedCustomer ? matchedCustomer.id : '';
        const start = new Date(tx.start_time);
        const end = new Date(tx.expected_end_time);
        editSessionForm.start_date = toYMD(start);
        editSessionForm.start_time = `${pad2(start.getHours())}:${pad2(start.getMinutes())}`;
        editSessionForm.package_id = tx.package_id;
        const diffHours = (end - start) / (1000 * 60 * 60);
        
        editSessionForm.duration_hours = Math.max(minimumEditDuration.value, Math.round(diffHours * 2) / 2);
        
        editSessionForm.items = (tx.items || []).map(i => ({
            id: i.id,
            fnb_item_id: i.fnb_item_id,
            name: i.fnb_item?.name || i.name,
            price: i.price,
            quantity: i.quantity,
            is_new: false
        }));
    }
};

const openBookingEdit = (booking) => {
    const table = props.tables.find((t) => t.id === booking.table_id) || booking.table;
    if (!table) return;
    showBookingModal.value = false;
    showTableHistoryModal.value = false;
    openSessionModal(table, booking);
};

const closeSessionModal = () => {
    showSessionModal.value = false;
    showEditCustomerDropdown.value = false;
    editingTransactionTarget.value = null;
    
    if (selectedTableForHistory.value && selectedActiveTable.value) {
        const updatedTable = props.tables.find(t => t.id === selectedActiveTable.value.id);
        if (updatedTable) {
            selectedTableForHistory.value = updatedTable;
        }
        showTableHistoryModal.value = true;
    }
    
    selectedActiveTable.value = null;
    editSessionForm.reset();
};

const submitEditSession = () => {
    if (!selectedActiveTable.value || !activeTransaction.value) return;
    if (conflictingEditBooking.value || isEditStartInPast.value) return;
    editSessionForm.put(route('tables.updateSession', [selectedActiveTable.value.id, activeTransaction.value.id]), {
        preserveScroll: true,
        onSuccess: () => {
            closeSessionModal();
        }
    });
};



// F&B categories from items
const fnbCategories = computed(() => {
    const cats = [...new Set(props.fnbItems.map(item => item.category).filter(Boolean))];
    return cats.sort();
});

// Filtered F&B items
const filteredFnbItems = computed(() => {
    let items = props.fnbItems;
    if (fnbCategoryFilter.value !== 'all') {
        items = items.filter(item => item.category === fnbCategoryFilter.value);
    }
    if (fnbSearch.value.trim()) {
        const q = fnbSearch.value.toLowerCase();
        items = items.filter(item => item.name.toLowerCase().includes(q));
    }
    return items;
});

// Add F&B item to edit form state
const addFnbItemToEditForm = (item) => {
    const existing = editSessionForm.items.find(i => i.fnb_item_id === item.id);
    if (existing) {
        existing.quantity++;
    } else {
        editSessionForm.items.push({
            fnb_item_id: item.id,
            name: item.name,
            price: item.price,
            quantity: 1,
            is_new: true
        });
    }
};

const incrementEditItem = (index) => {
    editSessionForm.items[index].quantity++;
};

const decrementEditItem = (index) => {
    if (editSessionForm.items[index].quantity > 1) {
        editSessionForm.items[index].quantity--;
    } else if (editSessionForm.items[index].is_new) {
        editSessionForm.items.splice(index, 1);
    }
};

const incrementItem = (itemId) => {
// obsolete functions removed
};

const decrementItem = (itemId) => {
// obsolete functions removed
};

const updateItemQuantity = (itemId, newQty, currentQty) => {
// obsolete functions removed
};

// Get item count for badge
const getItemCount = (transaction) => {
    if (!transaction?.items) return 0;
    return transaction.items.reduce((sum, item) => sum + item.quantity, 0);
};

// ─── Checkout (Billiard Session) ────────────────────────
const executeCheckout = () => {
    if (!selectedActiveTable.value) return;
    const tx = activeTransaction.value;
    const tableName = selectedActiveTable.value.name;
    router.post(route('tables.stop', selectedActiveTable.value.id), {}, {
        onSuccess: async (res) => {
            closeSessionModal();
            const finalTx = res?.transaction || tx;
            if (finalTx) {
                await printReceipt({
                    ...finalTx,
                    type: 'billiard',
                    table_name: finalTx.table?.name || tableName,
                    end_time: finalTx.end_time || new Date().toISOString(),
                    status: 'completed',
                    total_cost: Number(finalTx.billiard_cost) + Number(finalTx.fnb_cost),
                });
            }
        }
    });
};

// ─── Table Click Handler ────────────────────────────────
const handleTableClick = (table) => {
    openTableHistoryModal(table);
};

// ─── Formatting Helpers ─────────────────────────────────
const formatDate = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const calculateDuration = (tx) => {
    // 1. Calculate duration from start_time and end_time / expected_end_time if available
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

    // 2. Fallback: estimate from billiard_cost and package price
    if (tx.package && Number(tx.package.price) > 0 && Number(tx.billiard_cost) > 0) {
        const rawHours = Number(tx.billiard_cost) / Number(tx.package.price);
        const totalMinutes = Math.round(rawHours * 60);
        if (totalMinutes < 60) {
            return `${totalMinutes} Mnt`;
        }
        const hours = Math.floor(totalMinutes / 60);
        const mins = totalMinutes % 60;
        if (mins === 0) return `${hours} Jam`;
        return `${hours} Jam ${mins} Mnt`;
    }

    return '-';
};

const getFilteredHistory = (transactions) => {
    if (!transactions) return [];
    
    let result = transactions
        .filter((tx) => tx.status === 'active' || tx.status === 'completed')
        .sort((a, b) => {
            return new Date(b.created_at || b.start_time) - new Date(a.created_at || a.start_time);
        });

    if (!historySearch.value.trim()) return result;
    const query = historySearch.value.toLowerCase();
    return result.filter(tx => tx.customer_name && tx.customer_name.toLowerCase().includes(query));
};
</script>

<template>
    <Head title="Dashboard" />

    <KasirLayout>
        <template #header>
            <div class="d-flex justify-content-between align-items-center w-100 min-w-0">
                <div class="d-flex align-items-center gap-2 min-w-0">
                    <h1 class="bb-header-title mb-0 fs-6 fs-sm-5 text-truncate">
                        <i class="bi bi-grid-1x2-fill me-2" style="color: #10b981;"></i>
                        <span class="d-none d-md-inline">Live </span>Monitoring Meja
                    </h1>
                </div>
                <div class="text-end d-none d-sm-block flex-shrink-0">
                    <div class="fw-bold fs-4 text-gradient" style="line-height: 1.2;">{{ currentTime }}</div>
                    <div class="small text-secondary" style="letter-spacing: 0.5px;">{{ currentDate }}</div>
                </div>
            </div>
        </template>

        <div class="bb-meja-screen">
        <!-- Toolbar: filter + tombol drawer booking + jam mobile -->
        <div class="bb-meja-toolbar mb-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="bb-seg" role="group" aria-label="Filter meja">
                <button type="button" @click="currentFilter = 'all'" :class="['bb-seg__btn', { 'is-on': currentFilter === 'all' }]">
                    <i class="bi bi-grid-3x3-gap-fill"></i>
                    <span>Semua</span>
                    <span class="bb-seg__count">{{ tables.length }}</span>
                </button>
                <button type="button" @click="currentFilter = 'active'" :class="['bb-seg__btn', { 'is-on': currentFilter === 'active' }]">
                    <i class="bi bi-fire"></i>
                    <span><span class="d-none d-sm-inline">Sedang </span>Jalan</span>
                    <span class="bb-seg__count">{{ activeCount }}</span>
                </button>
                <button type="button" @click="currentFilter = 'ready'" :class="['bb-seg__btn', { 'is-on': currentFilter === 'ready' }]">
                    <i class="bi bi-record-circle"></i>
                    <span>Kosong</span>
                    <span class="bb-seg__count">{{ readyCount }}</span>
                </button>
                <button type="button" @click="currentFilter = 'booked'" :class="['bb-seg__btn', { 'is-on': currentFilter === 'booked' }]">
                    <i class="bi bi-calendar2-check-fill"></i>
                    <span>Booking</span>
                    <span class="bb-seg__count">{{ bookedTableCount }}</span>
                </button>
            </div>

            <div class="d-flex align-items-center gap-2 ms-auto">
                <button
                    type="button"
                    class="bb-btn bb-btn--ghost d-flex align-items-center gap-2 py-2 px-3 border flex-shrink-0"
                    :style="upcomingBookings.length > 0
                        ? 'border-color: rgba(6, 182, 212, 0.45) !important; background: rgba(6, 182, 212, 0.08); color: #06b6d4; border-radius: 50rem; font-size: 0.8rem; text-transform: none;'
                        : 'border-radius: 50rem; font-size: 0.8rem; text-transform: none;'"
                    @click="showBookingModal = true"
                >
                    <i class="bi bi-calendar2-week-fill"></i>
                    <span class="fw-semibold">Daftar Booking</span>
                    <span
                        class="badge rounded-pill px-2 py-1"
                        :class="readyBookingsCount > 0 ? 'bg-success text-white' : (upcomingBookings.length > 0 ? 'bg-info text-dark' : 'bg-secondary bg-opacity-25 text-secondary')"
                        style="font-size: 0.72rem;"
                    >
                        {{ upcomingBookings.length }}
                    </span>
                </button>

                <div class="d-sm-none text-end">
                    <div class="fw-bold text-gradient fs-5 font-monospace" style="line-height: 1;">{{ currentTime }}</div>
                </div>
            </div>
        </div>

        <!-- Grid meja: kartu selalu center, meja sedikit tidak menempel kiri -->
        <div class="bb-meja-grid">
            <div
                v-for="table in filteredTables"
                :key="table.id"
                class="bb-meja-card"
                :class="{ 'bb-meja-card--active': table.status === 'active' }"
                role="button"
                tabindex="0"
                @click="handleTableClick(table)"
                @keyup.enter="handleTableClick(table)"
            >
                <div class="bb-meja-card__top">
                    <div class="bb-meja-card__name">
                        {{ table.name }}
                        <i class="bi"
                           :class="table.is_light_on ? 'bi-lightbulb-fill text-warning' : (table.status === 'active' ? 'bi-lightbulb-fill' : 'bi-lightbulb')"
                           :title="table.is_light_on ? 'Lampu Menyala' : 'Lampu Mati'"
                        ></i>
                    </div>
                    <span class="bb-status-badge flex-shrink-0" :class="table.status === 'active' ? 'bb-status-badge--active' : 'bb-status-badge--ready'">
                        <i :class="table.status === 'active' ? 'bi bi-circle-fill' : 'bi bi-circle'" style="font-size: 0.45rem;"></i>
                        {{ table.status === 'active' ? 'In Use' : 'Ready' }}
                    </span>
                </div>

                <!-- Panel felt meja -->
                <div class="bb-meja-felt">
                    <span class="bb-meja-pocket bb-meja-pocket--tl"></span>
                    <span class="bb-meja-pocket bb-meja-pocket--tr"></span>
                    <span class="bb-meja-pocket bb-meja-pocket--bl"></span>
                    <span class="bb-meja-pocket bb-meja-pocket--br"></span>

                    <div class="bb-meja-felt__body">
                        <template v-if="table.status === 'active'">
                            <span class="bb-meja-felt__label">Sisa Waktu</span>
                            <span class="bb-timer bb-meja-felt__clock" :class="{ 'bb-timer--expired': timers[table.id] === '00:00:00' }">
                                {{ timers[table.id] || '00:00:00' }}
                            </span>
                            <span v-if="getItemCount(table.transactions?.[0])" class="bb-meja-fnb">
                                <i class="bi bi-cup-hot-fill"></i>{{ getItemCount(table.transactions[0]) }} item F&amp;B
                            </span>
                            <span
                                v-if="bookingByTable[table.id]"
                                class="bb-meja-booking-tag mt-1"
                                role="button"
                                title="Lihat jadwal booking meja ini"
                                @click.stop="openTableHistoryModal(table)"
                            >
                                <i class="bi bi-calendar2-check"></i>
                                Next {{ formatBookingShort(bookingByTable[table.id].start_time) }} ({{ formatBookingCountdown(bookingByTable[table.id]) }})
                            </span>
                        </template>
                        <template v-else>
                            <i class="bi bi-hourglass-split bb-meja-felt__icon"></i>
                            <span class="bb-meja-felt__label bb-meja-felt__label--idle">Tersedia</span>
                            <span
                                v-if="bookingByTable[table.id]"
                                class="bb-meja-booking-tag"
                                :style="canStartBooking(bookingByTable[table.id]) ? 'background: rgba(16, 185, 129, 0.18); border-color: rgba(16, 185, 129, 0.45); color: #10b981;' : ''"
                                role="button"
                                title="Lihat detail booking"
                                @click.stop="openTableHistoryModal(table)"
                            >
                                <i class="bi bi-calendar2-check-fill"></i>
                                {{ formatBookingShort(bookingByTable[table.id].start_time) }} · {{ formatBookingCountdown(bookingByTable[table.id]) }}
                                <strong v-if="(bookingsByTable[table.id]?.length || 0) > 1" class="ms-1">+{{ bookingsByTable[table.id].length - 1 }}</strong>
                            </span>
                        </template>
                    </div>
                </div>

                <!-- Info sesi -->
                <div class="bb-meja-card__foot">
                    <div class="bb-meja-card__meta">
                        <template v-if="table.status === 'active'">
                            <span v-if="table.transactions?.[0]?.customer_name" class="bb-meja-meta">
                                <i class="bi bi-person-fill"></i>{{ table.transactions[0].customer_name }}
                            </span>
                            <span v-if="table.transactions?.[0]?.package?.name" class="bb-meja-meta">
                                <i class="bi bi-trophy-fill"></i>{{ table.transactions[0].package.name }}
                            </span>
                            <span v-else-if="table.transactions?.[0]" class="bb-meja-meta">
                                <i class="bi bi-clock"></i>{{ calculateDuration(table.transactions[0]) }}
                            </span>
                        </template>
                        <template v-else-if="bookingByTable[table.id]">
                            <span class="bb-meja-meta" style="color: #06b6d4;">
                                <i class="bi bi-person-badge-fill" style="color: #06b6d4;"></i>{{ bookingByTable[table.id].customer_name }}
                            </span>
                            <span class="bb-meja-meta">
                                <i class="bi bi-clock" style="color: #06b6d4;"></i>{{ calculateDuration(bookingByTable[table.id]) }}
                            </span>
                        </template>
                        <template v-else>
                            <span class="bb-meja-meta bb-meja-meta--idle"><i class="bi bi-arrow-right-circle"></i>Sesi baru</span>
                        </template>
                    </div>
                    <span class="bb-meja-go"><i class="bi bi-arrow-right"></i></span>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="filteredTables.length === 0" class="bb-meja-empty">
                <div class="bb-card text-center py-5">
                    <div class="bb-card-body">
                        <i class="bi bi-inbox" style="font-size: 3rem; opacity: 0.2;"></i>
                        <h5 class="mt-3 fw-bold" style="opacity: 0.5;">
                            {{ tables.length === 0 ? 'Belum ada meja' : 'Tidak ada meja di kategori ini' }}
                        </h5>
                        <p v-if="tables.length === 0" class="text-secondary small mb-3">Tambahkan meja baru melalui menu Kelola Meja</p>
                        <Link v-if="tables.length === 0" :href="route('tables.index')" class="bb-btn bb-btn--primary">
                            <i class="bi bi-plus-lg"></i> Tambah Meja
                        </Link>
                    </div>
                </div>
            </div>
        </div>
        </div>



        <!-- ═══════════════════════════════════════════════════ -->
        <!-- Upcoming Bookings Right Side Drawer                 -->
        <!-- ═══════════════════════════════════════════════════ -->
        <div v-if="showBookingModal" class="bb-drawer-backdrop" @click.self="showBookingModal = false">
            <aside class="bb-drawer" role="dialog" aria-modal="true" aria-label="Daftar Booking Akan Datang">
                <div class="p-3 p-sm-4 border-bottom" style="border-color: rgba(148, 163, 184, 0.14) !important;">
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                        <div class="d-flex align-items-center gap-2 min-w-0">
                            <div class="d-flex align-items-center justify-content-center rounded-3 p-2 flex-shrink-0" style="background: rgba(6, 182, 212, 0.15); color: #06b6d4;">
                                <i class="bi bi-calendar2-week-fill fs-5"></i>
                            </div>
                            <div class="min-w-0">
                                <h5 class="fw-bold mb-0 text-truncate">Daftar Booking</h5>
                                <div class="small text-secondary" style="font-size: 0.75rem;">
                                    {{ upcomingBookings.length }} jadwal mendatang · {{ readyBookingsCount }} siap dimulai
                                </div>
                            </div>
                        </div>
                        <button @click="showBookingModal = false" class="bb-theme-toggle flex-shrink-0" style="width: 34px; height: 34px; font-size: 0.85rem;" title="Tutup">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>

                    <!-- Search & quick filter inside drawer -->
                    <div class="position-relative mb-2">
                        <i class="bi bi-search position-absolute top-50 translate-middle-y ms-3 text-secondary" style="font-size: 0.8rem;"></i>
                        <input
                            type="text"
                            v-model="bookingSearch"
                            class="bb-input py-2 ps-5 pe-3"
                            style="font-size: 0.82rem;"
                            placeholder="Cari nama customer, meja, atau No. WA..."
                        />
                    </div>
                    <div class="d-flex gap-1">
                        <button
                            type="button"
                            class="bb-btn bb-btn--sm flex-grow-1"
                            :class="bookingDrawerFilter === 'all' ? 'bb-btn--primary' : 'bb-btn--ghost'"
                            style="text-transform: none;"
                            @click="bookingDrawerFilter = 'all'"
                        >
                            Semua ({{ upcomingBookings.length }})
                        </button>
                        <button
                            type="button"
                            class="bb-btn bb-btn--sm flex-grow-1"
                            :class="bookingDrawerFilter === 'ready' ? 'bb-btn--success' : 'bb-btn--ghost'"
                            style="text-transform: none;"
                            @click="bookingDrawerFilter = 'ready'"
                        >
                            Siap Mulai ({{ readyBookingsCount }})
                        </button>
                        <button
                            type="button"
                            class="bb-btn bb-btn--sm flex-grow-1"
                            :class="bookingDrawerFilter === 'today' ? 'bb-btn--primary' : 'bb-btn--ghost'"
                            style="text-transform: none;"
                            @click="bookingDrawerFilter = 'today'"
                        >
                            Hari Ini
                        </button>
                    </div>
                </div>

                <div class="p-3 p-sm-4 flex-grow-1" style="overflow-y: auto;">
                    <div v-if="filteredUpcomingBookings.length === 0" class="text-center py-5">
                        <div class="d-inline-flex align-items-center justify-content-center p-3 rounded-circle mb-3" style="background: rgba(6, 182, 212, 0.08); color: #06b6d4;">
                            <i class="bi bi-calendar-check fs-2"></i>
                        </div>
                        <h6 class="fw-bold mb-1">Tidak Ada Booking Ditemukan</h6>
                        <p class="text-secondary small mb-0">
                            {{ upcomingBookings.length === 0 ? 'Belum ada reservasi meja yang akan datang.' : 'Tidak ada jadwal yang cocok dengan filter pencarian.' }}
                        </p>
                    </div>

                    <div v-else class="d-flex flex-column gap-3">
                        <div
                            v-for="booking in filteredUpcomingBookings"
                            :key="booking.id"
                            class="bb-booking-item p-3 rounded-4"
                            :class="{ 'bb-booking-item--ready': canStartBooking(booking) }"
                        >
                            <!-- Row 1: Meja, Countdown, Jadwal -->
                            <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                                <span class="badge rounded-pill px-2 py-1" style="background: rgba(6, 182, 212, 0.16); color: #06b6d4; font-weight: 700; font-size: 0.78rem;">
                                    <i class="bi bi-grid-fill me-1" style="font-size: 0.65rem;"></i>{{ booking.table?.name || 'Meja' }}
                                </span>
                                <span
                                    class="badge rounded-pill px-2 py-1 fw-semibold"
                                    :style="canStartBooking(booking)
                                        ? 'background: rgba(16, 185, 129, 0.16); color: #10b981; font-size: 0.73rem;'
                                        : 'background: rgba(245, 158, 11, 0.14); color: #f59e0b; font-size: 0.73rem;'"
                                >
                                    <i class="bi bi-hourglass-split me-1"></i>{{ formatBookingCountdown(booking) }}
                                </span>
                            </div>

                            <!-- Row 2: Customer + WhatsApp -->
                            <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                                <div class="min-w-0">
                                    <div class="fw-bold text-truncate">{{ booking.customer_name }}</div>
                                    <div class="small text-secondary d-flex align-items-center gap-2 flex-wrap">
                                        <span><i class="bi bi-clock me-1 text-info"></i>{{ formatBookingRange(booking) }}</span>
                                    </div>
                                </div>
                                <a
                                    v-if="getWhatsappLink(booking)"
                                    :href="getWhatsappLink(booking)"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="bb-btn bb-btn--sm d-inline-flex align-items-center gap-1 py-1 px-2 flex-shrink-0 text-decoration-none"
                                    style="background: rgba(16, 185, 129, 0.14); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); text-transform: none; font-size: 0.75rem;"
                                    :title="`Chat WA: ${getBookingWhatsapp(booking)}`"
                                >
                                    <i class="bi bi-whatsapp"></i>
                                    <span>{{ getBookingWhatsapp(booking) }}</span>
                                </a>
                            </div>

                            <!-- Row 3: Paket, Durasi & Total Cost -->
                            <div class="d-flex justify-content-between align-items-center p-2 rounded-2 mb-3 bb-booking-subbar">
                                <div class="small">
                                    <span class="text-secondary">Paket:</span>
                                    <span class="fw-semibold ms-1">{{ booking.package?.name || 'Reguler' }}</span>
                                    <span class="text-secondary mx-1">·</span>
                                    <span class="text-info fw-semibold">{{ calculateDuration(booking) }}</span>
                                </div>
                                <div class="text-end font-monospace fw-bold text-success" style="font-size: 0.88rem;">
                                    {{ formatRupiah(booking.total_cost) }}
                                </div>
                            </div>

                            <!-- Row 4: Actions -->
                            <div class="d-flex gap-2">
                                <button
                                    v-if="canStartBooking(booking)"
                                    type="button"
                                    class="bb-btn bb-btn--success flex-grow-1 py-2 d-flex align-items-center justify-content-center gap-1 fw-bold"
                                    style="border-radius: 0.65rem; font-size: 0.82rem; text-transform: none;"
                                    @click="startBooking(booking)"
                                >
                                    <i class="bi bi-play-fill fs-6"></i> Mulai Sesi Sekarang
                                </button>
                                <div
                                    v-else
                                    class="d-flex align-items-center justify-content-between small px-3 py-2 rounded-2 text-secondary bb-booking-waiting flex-grow-1"
                                >
                                    <span class="d-flex align-items-center gap-1">
                                        <i class="bi bi-lock-fill text-warning"></i>
                                        <span>Bisa mulai 30 mnt sebelum jadwal</span>
                                    </span>
                                </div>
                                <button
                                    type="button"
                                    class="bb-btn bb-btn--ghost py-2 px-3 text-primary border flex-shrink-0"
                                    style="border-radius: 0.65rem; border-color: rgba(99, 102, 241, 0.28) !important; font-size: 0.8rem; text-transform: none;"
                                    title="Edit Pesanan Booking"
                                    @click="openBookingEdit(booking)"
                                >
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <button
                                    type="button"
                                    class="bb-btn bb-btn--ghost py-2 px-3 text-danger border flex-shrink-0"
                                    style="border-radius: 0.65rem; border-color: rgba(239, 68, 68, 0.28) !important; font-size: 0.8rem; text-transform: none;"
                                    title="Batalkan Booking"
                                    @click="promptCancelBooking(booking)"
                                >
                                    <i class="bi bi-x-circle"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>
        </div>

        <!-- ═══════════════════════════════════════════════════ -->
        <!-- Table History Modal (NEW)                          -->
        <!-- ═══════════════════════════════════════════════════ -->
        <div v-if="showTableHistoryModal && selectedTableForHistory" class="bb-modal-backdrop">
            <div class="bb-modal" style="max-width: 900px; width: 95%;">
                <!-- Header -->
                <div class="bb-modal-header">
                    <div>
                        <h5 class="fw-bold mb-0 d-flex align-items-center gap-2">
                            <i class="bi bi-clock-history" style="color: #6366f1;"></i>
                            Riwayat Meja: {{ selectedTableForHistory.name }}
                        </h5>
                    </div>
                    <button @click="closeTableHistoryModal" class="bb-theme-toggle" style="width: 32px; height: 32px; font-size: 0.9rem;">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>

                <div class="bb-modal-body p-4" style="max-height: 65vh; overflow-y: auto;">
                    <!-- Active Session / Add New Session -->
                    <div class="mb-4 p-3 rounded-4 d-flex justify-content-between align-items-center bb-card flex-wrap gap-2" 
                         :style="{
                             background: selectedTableForHistory.status === 'active' ? 'rgba(16,185,129,0.05)' : '',
                             border: selectedTableForHistory.status === 'active' ? '1px solid rgba(16,185,129,0.2)' : ''
                         }">
                        <div class="d-flex align-items-center gap-2 gap-sm-3 flex-wrap">
                            <div class="fw-bold opacity-75">Status Meja:</div>
                            
                            <!-- Active Badge -->
                            <div v-if="selectedTableForHistory.status === 'active'" 
                                 class="d-flex align-items-center gap-2 px-3 py-1 rounded-pill" 
                                 style="background: #10b981; color: white; box-shadow: 0 4px 12px rgba(16,185,129,0.3);">
                                <span class="spinner-grow spinner-grow-sm" style="width: 10px; height: 10px;" role="status"></span>
                                <span class="fw-bold" style="font-size: 0.85rem; letter-spacing: 0.5px; padding-top: 1px;">SEDANG JALAN</span>
                            </div>
                            
                            <!-- Empty Badge -->
                            <div v-else 
                                 class="d-flex align-items-center gap-2 px-3 py-1 rounded-pill" 
                                 style="background: rgba(128,128,128,0.08); border: 1px solid rgba(128,128,128,0.2);">
                                <i class="bi bi-dash-circle-fill opacity-50"></i>
                                <span class="fw-bold opacity-75" style="font-size: 0.85rem; letter-spacing: 0.5px; padding-top: 1px;">KOSONG</span>
                            </div>
                        </div>
                        
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <!-- Toggle Saklar Lampu Meja (Manual / Test) -->
                            <button
                                type="button"
                                class="bb-btn d-flex align-items-center gap-2 py-1 px-3 rounded-pill"
                                :class="selectedTableForHistory.is_light_on ? 'bb-btn--warning text-white fw-bold' : 'bb-btn--ghost border text-secondary'"
                                :disabled="togglingLight"
                                @click="toggleTableLight(selectedTableForHistory)"
                                :title="selectedTableForHistory.is_light_on ? 'Matikan Lampu Meja' : 'Nyalakan Lampu Meja'"
                                style="font-size: 0.82rem;"
                            >
                                <span v-if="togglingLight" class="spinner-border spinner-border-sm" role="status"></span>
                                <i v-else class="bi" :class="selectedTableForHistory.is_light_on ? 'bi-lightbulb-fill' : 'bi-lightbulb'"></i>
                                <span>{{ selectedTableForHistory.is_light_on ? 'Lampu: ON' : 'Lampu: OFF' }}</span>
                            </button>

                            <div v-if="selectedTableForHistory.status !== 'active'">
                                <button @click="showTableHistoryModal = false; openOrderModal(selectedTableForHistory)" class="bb-btn bb-btn--primary bb-btn--sm px-3 px-sm-4 rounded-pill fw-bold" style="box-shadow: 0 4px 12px rgba(99,102,241,0.25); font-size: 0.82rem;">
                                    <i class="bi bi-play-fill me-1"></i> Mulai Sesi Baru
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Upcoming Bookings on This Table -->
                    <div v-if="(bookingsByTable[selectedTableForHistory.id]?.length || 0) > 0" class="mb-4">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h6 class="fw-bold mb-0 d-flex align-items-center gap-2" style="color: #06b6d4;">
                                <i class="bi bi-calendar2-check-fill"></i>
                                Jadwal Booking Meja Ini ({{ bookingsByTable[selectedTableForHistory.id].length }})
                            </h6>
                        </div>
                        <div class="row g-2">
                            <div
                                v-for="booking in bookingsByTable[selectedTableForHistory.id]"
                                :key="booking.id"
                                class="col-md-6"
                            >
                                <div class="bb-booking-item p-3 rounded-4 h-100 d-flex flex-column justify-content-between" :class="{ 'bb-booking-item--ready': canStartBooking(booking) }">
                                    <div>
                                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                            <div>
                                                <div class="fw-bold">{{ booking.customer_name }}</div>
                                                <div class="small text-secondary">
                                                    <i class="bi bi-clock me-1 text-info"></i>{{ formatBookingRange(booking) }}
                                                </div>
                                            </div>
                                            <span
                                                class="badge rounded-pill px-2 py-1"
                                                :style="canStartBooking(booking)
                                                    ? 'background: rgba(16, 185, 129, 0.16); color: #10b981; font-size: 0.72rem;'
                                                    : 'background: rgba(245, 158, 11, 0.14); color: #f59e0b; font-size: 0.72rem;'"
                                            >
                                                {{ formatBookingCountdown(booking) }}
                                            </span>
                                        </div>

                                        <div class="d-flex justify-content-between align-items-center small p-2 rounded-2 mb-2 bb-booking-subbar">
                                            <span>{{ booking.package?.name || 'Reguler' }} · <strong class="text-info">{{ calculateDuration(booking) }}</strong></span>
                                            <span class="fw-bold text-success font-monospace">{{ formatRupiah(booking.total_cost) }}</span>
                                        </div>
                                    </div>

                                    <div class="d-flex align-items-center gap-2 mt-1 flex-wrap">
                                        <a
                                            v-if="getWhatsappLink(booking)"
                                            :href="getWhatsappLink(booking)"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="bb-btn bb-btn--sm d-inline-flex align-items-center gap-1 py-1 px-2 text-decoration-none"
                                            style="background: rgba(16, 185, 129, 0.14); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); text-transform: none; font-size: 0.76rem;"
                                        >
                                            <i class="bi bi-whatsapp"></i>
                                            <span>{{ getBookingWhatsapp(booking) }}</span>
                                        </a>
                                        <button
                                            v-if="canStartBooking(booking)"
                                            type="button"
                                            class="bb-btn bb-btn--success bb-btn--sm py-1 px-3 ms-auto"
                                            style="text-transform: none; font-size: 0.78rem;"
                                            @click="startBooking(booking)"
                                        >
                                            <i class="bi bi-play-fill"></i> Mulai Sesi
                                        </button>
                                        <button
                                            type="button"
                                            class="bb-btn bb-btn--ghost bb-btn--sm py-1 px-2 text-primary border"
                                            :class="{ 'ms-auto': !canStartBooking(booking) }"
                                            style="border-color: rgba(99, 102, 241, 0.28) !important; text-transform: none; font-size: 0.76rem;"
                                            @click="openBookingEdit(booking)"
                                        >
                                            <i class="bi bi-pencil-square me-1"></i>Edit
                                        </button>
                                        <button
                                            type="button"
                                            class="bb-btn bb-btn--ghost bb-btn--sm py-1 px-2 text-danger border"
                                            style="border-color: rgba(239, 68, 68, 0.28) !important; text-transform: none; font-size: 0.76rem;"
                                            @click="promptCancelBooking(booking)"
                                        >
                                            <i class="bi bi-x-circle me-1"></i>Batal
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0 d-flex align-items-center gap-2">
                            <i class="bi bi-list-ul opacity-75"></i> Riwayat Transaksi
                        </h6>
                        <div class="position-relative" style="width: 220px;">
                            <input type="text" v-model="historySearch" class="bb-input py-1 px-3" placeholder="Cari nama pelanggan..." style="font-size: 0.8rem; padding-left: 2rem !important;" />
                            <i class="bi bi-search position-absolute top-50 translate-middle-y opacity-50" style="left: 0.6rem; font-size: 0.75rem;"></i>
                        </div>
                    </div>

                    <div v-if="getFilteredHistory(selectedTableForHistory.recent_transactions)?.length > 0" class="row g-3">
                        <div v-for="tx in getFilteredHistory(selectedTableForHistory.recent_transactions)" :key="tx.id" class="col-md-6">
                            <div class="bb-card rounded-4 position-relative overflow-hidden d-flex flex-column h-100" 
                                 :style="{
                                    border: tx.status === 'active' 
                                        ? '1px solid rgba(16,185,129,0.5)' 
                                        : (tx.status === 'cancelled' ? '1px solid rgba(239,68,68,0.3)' : ''),
                                    boxShadow: tx.status === 'active' 
                                        ? '0 0 15px rgba(16,185,129,0.15)' 
                                        : ''
                                 }">
                             
                            <!-- Left Accent Line -->
                            <div class="position-absolute top-0 bottom-0 start-0" 
                                 :style="{
                                    width: '4px',
                                    background: tx.status === 'active' ? '#10b981' : (tx.status === 'cancelled' ? '#ef4444' : '#6c757d'),
                                    zIndex: 1
                                 }"></div>
                            
                            <!-- Card Body -->
                            <div class="p-3 ps-4">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <div class="fw-bold fs-6 mb-1 d-flex align-items-center gap-2 flex-wrap">
                                            <span>{{ tx.customer_name }}</span>
                                            <span v-if="tx.receipt_no" class="badge bg-secondary-subtle text-secondary font-monospace fw-semibold" style="font-size: 0.68rem;">{{ tx.receipt_no }}</span>
                                        </div>
                                        <div class="small d-flex align-items-center gap-2 flex-wrap">
                                            <span class="badge fw-bold rounded-pill" :style="{
                                                backgroundColor: tx.status === 'active' ? 'rgba(16,185,129,0.15)' : (tx.status === 'cancelled' ? 'rgba(239,68,68,0.15)' : 'rgba(108,117,125,0.15)'),
                                                color: tx.status === 'active' ? '#10b981' : (tx.status === 'cancelled' ? '#ef4444' : '#6c757d'),
                                                border: tx.status === 'active' ? '1px solid rgba(16,185,129,0.3)' : (tx.status === 'cancelled' ? '1px solid rgba(239,68,68,0.3)' : '1px solid rgba(108,117,125,0.3)'),
                                                fontSize: '0.65rem',
                                                padding: '0.35em 0.75em',
                                                letterSpacing: '0.5px'
                                            }">{{ tx.status === 'active' ? '● AKTIF' : (tx.status === 'completed' ? 'SELESAI' : 'BATAL') }}</span>
                                            <span class="opacity-75" style="font-size: 0.78rem;"><i class="bi bi-calendar3 me-1"></i>{{ formatDate(tx.start_time) }}</span>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-1" style="z-index: 5; position: relative;">
                                        <button v-if="tx.status === 'active'" @click.stop="showTableHistoryModal = false; openSessionModal(selectedTableForHistory);" class="bb-btn bb-btn--ghost bb-btn--sm text-primary" title="Edit Sesi">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button @click.stop="printReceipt({...tx, table_name: selectedTableForHistory.name})" class="bb-btn bb-btn--ghost bb-btn--sm" title="Cetak Struk">
                                            <i class="bi bi-printer"></i>
                                        </button>

                                        <!-- Dropdown Kirim Struk (WA / Email) -->
                                        <div class="position-relative">
                                            <button
                                                type="button"
                                                class="bb-btn bb-btn--ghost bb-btn--sm d-inline-flex align-items-center gap-1"
                                                :class="hasAnyTxContact(tx) ? 'text-info' : 'text-secondary'"
                                                :disabled="!hasAnyTxContact(tx) || sendingReceiptEmailId === tx.id"
                                                :title="hasAnyTxContact(tx) ? 'Kirim Struk (WA / Email)' : 'Kontak WA & Email customer belum tersedia'"
                                                style="text-transform: none;"
                                                @click.stop="toggleSendReceiptMenu(tx.id)"
                                            >
                                                <span v-if="sendingReceiptEmailId === tx.id" class="spinner-border spinner-border-sm" style="width: 12px; height: 12px;"></span>
                                                <i v-else class="bi bi-send"></i>
                                                <i class="bi bi-chevron-down" style="font-size: 0.6rem;"></i>
                                            </button>

                                            <div
                                                v-if="openSendReceiptId === tx.id"
                                                class="position-absolute end-0 mt-1 rounded-3 shadow-lg border p-1"
                                                style="z-index: 40; min-width: 215px; background: var(--bs-body-bg); border-color: rgba(148, 163, 184, 0.22) !important;"
                                                @click.stop
                                            >
                                                <div class="px-2 py-1 small text-secondary fw-semibold border-bottom mb-1" style="font-size: 0.7rem; border-color: rgba(148, 163, 184, 0.12) !important;">
                                                    Kirim Struk Ke Customer
                                                </div>
                                                <button
                                                    type="button"
                                                    class="w-100 text-start px-2 py-2 rounded-2 border-0 bg-transparent d-flex align-items-center justify-content-between small"
                                                    :disabled="!txContact(tx).whatsapp"
                                                    :style="txContact(tx).whatsapp
                                                        ? 'color: var(--bs-body-color); cursor: pointer;'
                                                        : 'color: var(--bs-secondary-color); opacity: 0.45; cursor: not-allowed;'"
                                                    @click="handleSendReceiptWa(tx, selectedTableForHistory.name)"
                                                >
                                                    <span class="d-flex align-items-center gap-2">
                                                        <i class="bi bi-whatsapp text-success"></i>
                                                        <span>WhatsApp</span>
                                                    </span>
                                                    <span class="text-secondary" style="font-size: 0.68rem;">
                                                        {{ txContact(tx).whatsapp || 'Tidak tersedia' }}
                                                    </span>
                                                </button>
                                                <button
                                                    type="button"
                                                    class="w-100 text-start px-2 py-2 rounded-2 border-0 bg-transparent d-flex align-items-center justify-content-between small"
                                                    :disabled="!txContact(tx).email || sendingReceiptEmailId === tx.id"
                                                    :style="txContact(tx).email
                                                        ? 'color: var(--bs-body-color); cursor: pointer;'
                                                        : 'color: var(--bs-secondary-color); opacity: 0.45; cursor: not-allowed;'"
                                                    @click="handleSendReceiptEmail(tx, selectedTableForHistory.name)"
                                                >
                                                    <span class="d-flex align-items-center gap-2">
                                                        <i class="bi bi-envelope-fill text-info"></i>
                                                        <span>Email</span>
                                                    </span>
                                                    <span class="text-secondary text-truncate ms-2" style="font-size: 0.68rem; max-width: 110px;">
                                                        {{ txContact(tx).email || 'Tidak tersedia' }}
                                                    </span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                    <!-- Billiard details -->
                                    <div class="d-flex justify-content-between mb-2" style="font-size: 0.85rem;">
                                        <span class="opacity-75"><i class="bi bi-play-circle me-2" style="color: #10b981;"></i>Paket Biliar ({{ tx.package?.name }})</span>
                                        <span class="fw-semibold">{{ formatRupiah(tx.billiard_cost) }}</span>
                                    </div>
                                
                                <!-- F&B details -->
                                <div v-if="tx.items && tx.items.length > 0">
                                    <div class="d-flex justify-content-between mb-1" style="font-size: 0.85rem;">
                                        <span class="opacity-75"><i class="bi bi-cup-hot me-2 text-warning"></i>Pesanan F&B ({{ tx.items.length }} item)</span>
                                        <span class="fw-semibold">{{ formatRupiah(tx.fnb_cost) }}</span>
                                    </div>
                                    <!-- F&B Items Breakdown -->
                                    <div class="ms-4 ps-2 mt-2 mb-2" style="border-left: 2px solid rgba(16,185,129,0.15);">
                                        <div v-for="item in tx.items" :key="item.id" class="d-flex justify-content-between opacity-75" style="font-size: 0.75rem; margin-bottom: 2px;">
                                            <span>{{ item.fnb_item?.name || 'Item' }} <span class="opacity-50">x{{ item.quantity }}</span></span>
                                            <span>{{ formatRupiah(item.subtotal) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                                <!-- Card Footer (Total Cost) -->
                                <div class="p-3 ps-4 mt-auto d-flex justify-content-between align-items-center" 
                                     style="background: rgba(16,185,129,0.06); border-top: 1px solid rgba(16,185,129,0.12);">
                                    <span class="fw-bold opacity-75" style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Total Pembayaran</span>
                                    <span class="fw-bold fs-5" style="color: #10b981;">{{ formatRupiah(Number(tx.billiard_cost) + Number(tx.fnb_cost)) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-4 text-secondary opacity-50 small">
                        Belum ada riwayat transaksi 48 jam terakhir.
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════ -->
        <!-- Order Modal (Start Session — unchanged)            -->
        <!-- ═══════════════════════════════════════════════════ -->
        <div v-if="showOrderModal" class="bb-modal-backdrop">
            <div class="bb-modal" style="max-width: 1240px; width: 95%;">
                <div class="bb-modal-header">
                    <div>
                        <h5 class="fw-bold mb-0">Order Paket Meja</h5>
                        <p class="small text-secondary mb-0 mt-1">
                            Mulai sesi untuk <strong class="text-gradient">{{ selectedTable?.name }}</strong>
                        </p>
                    </div>
                    <button type="button" @click="closeOrderModal" class="bb-theme-toggle" style="width: 32px; height: 32px; font-size: 0.9rem;">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="bb-modal-body p-4">
                    <form @submit.prevent="submitOrder">
                        <div class="row g-4">
                            <!-- Left: F&B Menu -->
                            <div class="col-md-6 border-end" style="border-color: rgba(255,255,255,0.1) !important;">
                                <h6 class="fw-bold mb-3"><i class="bi bi-cup-hot-fill text-warning me-2"></i>Pesan F&B (Opsional)</h6>
                                <div class="mb-3 d-flex gap-2">
                                    <div class="position-relative flex-grow-1">
                                        <i class="bi bi-search position-absolute top-50 translate-middle-y ms-3 text-secondary"></i>
                                        <input type="text" v-model="fnbSearch" class="bb-input ps-5" placeholder="Cari menu F&B..." />
                                    </div>
                                </div>
                                <div class="d-flex gap-2 overflow-auto pb-2 mb-3" style="scrollbar-width: none;">
                                    <button type="button" @click="fnbCategoryFilter = 'all'"
                                            :class="['bb-btn bb-btn--sm flex-shrink-0', fnbCategoryFilter === 'all' ? 'bb-btn--warning' : 'bb-btn--ghost']">
                                        Semua
                                    </button>
                                    <button type="button" v-for="cat in fnbCategories" :key="cat"
                                            @click="fnbCategoryFilter = cat"
                                            :class="['bb-btn bb-btn--sm flex-shrink-0', fnbCategoryFilter === cat ? 'bb-btn--warning' : 'bb-btn--ghost']">
                                        {{ cat }}
                                    </button>
                                </div>
                                <div v-if="filteredFnbItems.length > 0" class="d-grid gap-2" style="grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); max-height: 380px; overflow-y: auto; padding-right: 5px;">
                                    <div v-for="item in filteredFnbItems" :key="item.id" 
                                         class="bb-table-card p-0 overflow-hidden d-flex flex-column" style="border-radius: 0.75rem; cursor: pointer;"
                                         @click="addDraftToTableOrder(item)">
                                        <div v-if="item.image_url" style="height: 80px; overflow: hidden;">
                                            <img :src="item.image_url" :alt="item.name" class="w-100 h-100" style="object-fit: cover;" />
                                        </div>
                                        <div v-else class="d-flex align-items-center justify-content-center" style="height: 80px; background: rgba(255,255,255,0.03);">
                                            <i class="bi bi-cup-hot" style="font-size: 1.5rem; opacity: 0.15;"></i>
                                        </div>
                                        <div class="p-2 d-flex flex-column flex-grow-1">
                                            <div class="fw-bold small text-truncate mb-1" style="font-size: 0.75rem;">{{ item.name }}</div>
                                            <div class="d-flex justify-content-between align-items-center mt-auto pt-1">
                                                <span class="fw-bold small" style="color: #f59e0b; font-size: 0.7rem;">{{ formatRupiah(item.price) }}</span>
                                                <div class="bb-btn bb-btn--warning bb-btn--sm px-1 py-0" style="font-size: 0.6rem;">
                                                    <i class="bi bi-plus-lg"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="text-center py-5 text-secondary opacity-50">
                                    <i class="bi bi-search fs-2 d-block mb-2"></i>
                                    <div class="small">{{ fnbSearch ? 'Menu tidak ditemukan' : 'Belum ada menu F&B' }}</div>
                                </div>
                            </div>
                            
                            <!-- Right Column: Order Form -->
                            <div class="col-md-6">
                                <h6 class="fw-bold mb-3"><i class="bi bi-card-list text-success me-2"></i>Detail Pemesanan</h6>

                                <!-- Peringatan Bentrok Jadwal Booking -->
                                <div
                                    v-if="isOrderStartInPast"
                                    class="alert alert-danger border-0 rounded-3 small mb-3 d-flex align-items-start gap-2"
                                >
                                    <i class="bi bi-exclamation-octagon-fill mt-1 flex-shrink-0"></i>
                                    <div>
                                        <strong>Jam Mulai Tidak Valid!</strong><br />
                                        Jam mulai tidak boleh berada di waktu yang sudah lewat. Klik tombol <strong>Sekarang</strong> atau pilih jam setelahnya.
                                    </div>
                                </div>
                                <div
                                    v-else-if="conflictingOrderBooking"
                                    class="alert alert-danger border-0 rounded-3 small mb-3 d-flex align-items-start gap-2"
                                >
                                    <i class="bi bi-exclamation-triangle-fill mt-1 flex-shrink-0"></i>
                                    <div>
                                        <strong>Pesanan Tidak Dapat Dibuat — Bentrok Jadwal!</strong><br />
                                        Jadwal <strong>{{ orderTimeRangeLabel }}</strong> ({{ form.duration_hours }} jam) bentrok dengan pesanan
                                        <strong>{{ conflictingOrderBooking.customer_name }}</strong>
                                        ({{ formatBookingRange(conflictingOrderBooking) }}). Ubah jam mulai atau kurangi durasi.
                                    </div>
                                </div>
                                <div
                                    v-else-if="orderModalBookings.length > 0"
                                    class="alert alert-info border-0 rounded-3 small mb-3 d-flex align-items-start gap-2"
                                >
                                    <i class="bi bi-calendar2-check-fill mt-1 flex-shrink-0"></i>
                                    <div>
                                        Meja ini memiliki booking berikutnya atas nama
                                        <strong>{{ orderModalBookings[0].customer_name }}</strong>
                                        ({{ formatBookingRange(orderModalBookings[0]) }} · {{ formatBookingCountdown(orderModalBookings[0]) }}).
                                    </div>
                                </div>

                                <div class="row g-3 mb-3">
                                    <!-- 1. Nama Pelanggan (Pilih Customer Terdaftar atau Ketik Sendiri) -->
                                    <div class="col-12">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="bb-label mb-0">Nama Pelanggan <span class="text-danger">*</span></label>
                                            <span v-if="selectedCustomerObj" class="badge rounded-pill px-2 py-1" style="background: rgba(6, 182, 212, 0.15); color: #06b6d4; font-size: 0.7rem;">
                                                <i class="bi bi-person-check-fill me-1"></i>Customer Terdaftar
                                                <span v-if="selectedCustomerObj.whatsapp">· {{ selectedCustomerObj.whatsapp }}</span>
                                            </span>
                                            <span v-else class="small text-secondary" style="font-size: 0.72rem;">
                                                Pilih customer atau ketik manual
                                            </span>
                                        </div>

                                        <div class="position-relative">
                                            <div class="d-flex gap-1">
                                                <div class="position-relative flex-grow-1">
                                                    <input
                                                        type="text"
                                                        v-model="form.customer_name"
                                                        @input="onCustomerNameInput"
                                                        @focus="showCustomerDropdown = true"
                                                        required
                                                        class="bb-input pe-4"
                                                        placeholder="Ketik nama atau cari customer terdaftar..."
                                                        autocomplete="off"
                                                    />
                                                    <button
                                                        v-if="form.customer_name"
                                                        type="button"
                                                        class="btn btn-sm position-absolute top-50 end-0 translate-middle-y text-secondary border-0 pe-2"
                                                        title="Bersihkan"
                                                        @click="clearSelectedCustomer"
                                                    >
                                                        <i class="bi bi-x-lg" style="font-size: 0.75rem;"></i>
                                                    </button>
                                                </div>
                                                <button
                                                    v-if="customers.length > 0"
                                                    type="button"
                                                    class="bb-btn bb-btn--ghost px-3 flex-shrink-0 border"
                                                    title="Pilih dari daftar customer"
                                                    @click="showCustomerDropdown = !showCustomerDropdown"
                                                >
                                                    <i class="bi" :class="showCustomerDropdown ? 'bi-chevron-up' : 'bi-people-fill'"></i>
                                                </button>
                                            </div>

                                            <!-- Dropdown Daftar Customer -->
                                            <div
                                                v-if="showCustomerDropdown && customers.length > 0"
                                                class="position-absolute start-0 end-0 mt-1 rounded-3 shadow-lg border overflow-auto"
                                                style="z-index: 30; max-height: 210px; background: var(--bs-body-bg); border-color: rgba(148, 163, 184, 0.22) !important;"
                                            >
                                                <div class="px-3 py-1 small text-secondary border-bottom d-flex justify-content-between align-items-center" style="font-size: 0.72rem; border-color: rgba(148, 163, 184, 0.12) !important;">
                                                    <span>Customer Terdaftar ({{ filteredCustomers.length }})</span>
                                                    <button type="button" class="btn btn-sm p-0 text-secondary border-0" @click="showCustomerDropdown = false">
                                                        Tutup
                                                    </button>
                                                </div>
                                                <button
                                                    v-for="cust in filteredCustomers"
                                                    :key="cust.id"
                                                    type="button"
                                                    class="w-100 text-start px-3 py-2 border-0 bg-transparent d-flex justify-content-between align-items-center"
                                                    style="color: var(--bs-body-color); border-bottom: 1px solid rgba(148, 163, 184, 0.08) !important;"
                                                    @click="selectCustomer(cust)"
                                                >
                                                    <div class="min-w-0">
                                                        <div class="fw-semibold small text-truncate">{{ cust.name }}</div>
                                                        <div class="text-secondary" style="font-size: 0.72rem;">
                                                            @{{ cust.username }}
                                                            <span v-if="cust.email">· {{ cust.email }}</span>
                                                        </div>
                                                    </div>
                                                    <span v-if="cust.whatsapp" class="badge rounded-pill ms-2 flex-shrink-0" style="background: rgba(16, 185, 129, 0.14); color: #10b981; font-size: 0.7rem;">
                                                        <i class="bi bi-whatsapp me-1"></i>{{ cust.whatsapp }}
                                                    </span>
                                                </button>
                                                <div v-if="filteredCustomers.length === 0" class="px-3 py-2 small text-secondary">
                                                    Tidak ditemukan di daftar customer — menggunakan nama manual <strong>"{{ form.customer_name }}"</strong>.
                                                </div>
                                            </div>
                                        </div>
                                        <div v-if="form.errors.customer_name" class="small text-danger mt-1">{{ form.errors.customer_name }}</div>
                                    </div>

                                    <!-- 2. Tanggal Mulai (Default Hari Berjalan) -->
                                    <div class="col-12 col-sm-5">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="bb-label mb-0">Tanggal <span class="text-danger">*</span></label>
                                            <div class="d-flex gap-2">
                                                <button
                                                    type="button"
                                                    class="btn btn-link btn-sm p-0 text-decoration-none fw-semibold"
                                                    style="font-size: 0.72rem;"
                                                    :style="form.start_date === currentYMD() ? 'color: #10b981;' : 'color: var(--bs-secondary-color);'"
                                                    @click="form.start_date = currentYMD()"
                                                >
                                                    Hari Ini
                                                </button>
                                                <button
                                                    type="button"
                                                    class="btn btn-link btn-sm p-0 text-decoration-none fw-semibold"
                                                    style="font-size: 0.72rem;"
                                                    :style="form.start_date === tomorrowYMD() ? 'color: #06b6d4;' : 'color: var(--bs-secondary-color);'"
                                                    @click="form.start_date = tomorrowYMD()"
                                                >
                                                    Besok
                                                </button>
                                            </div>
                                        </div>
                                        <flatPickr
                                            v-model="form.start_date"
                                            :config="orderDateConfig"
                                            class="bb-input w-100"
                                            :class="{ 'border-danger': isOrderStartInPast || !!conflictingOrderBooking }"
                                            placeholder="Pilih tanggal"
                                        />
                                    </div>

                                    <!-- 3. Jam Mulai (Dipilih sebelum Durasi) -->
                                    <div class="col-6 col-sm-4">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="bb-label mb-0">Jam Mulai <span class="text-danger">*</span></label>
                                            <button
                                                type="button"
                                                class="btn btn-link btn-sm p-0 text-decoration-none fw-semibold"
                                                style="font-size: 0.72rem; color: #10b981;"
                                                @click="setOrderNow"
                                            >
                                                <i class="bi bi-clock-fill me-1"></i>Sekarang
                                            </button>
                                        </div>
                                        <flatPickr
                                            v-model="form.start_time"
                                            :config="orderTimeConfig"
                                            class="bb-input w-100"
                                            :class="{ 'border-danger': isOrderStartInPast || !!conflictingOrderBooking }"
                                            placeholder="Pilih jam mulai"
                                        />
                                    </div>

                                    <!-- 4. Durasi (Jam) -->
                                    <div class="col-6 col-sm-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="bb-label mb-0">Durasi <span class="text-danger">*</span></label>
                                            <span class="small text-secondary" style="font-size: 0.7rem;">Jam</span>
                                        </div>
                                        <input
                                            type="number"
                                            step="0.5"
                                            min="0.5"
                                            v-model="form.duration_hours"
                                            required
                                            class="bb-input"
                                            :class="{ 'border-danger': !!conflictingOrderBooking }"
                                        />
                                        <div v-if="form.errors.duration_hours" class="small text-danger mt-1">{{ form.errors.duration_hours }}</div>
                                    </div>

                                    <div class="col-12 mt-1">
                                        <div class="small text-secondary d-flex align-items-center justify-content-between px-2 py-1 rounded-2" style="background: rgba(6, 182, 212, 0.06); border: 1px dashed rgba(6, 182, 212, 0.25);">
                                            <span><i class="bi bi-calendar2-range me-1 text-info"></i>Estimasi Jadwal Bermain:</span>
                                            <strong class="text-info font-monospace">{{ orderTimeRangeLabel }}</strong>
                                        </div>
                                    </div>

                                    <!-- 4. Pilih Paket -->
                                    <div class="col-12">
                                        <label class="bb-label">Pilih Paket <span class="text-danger">*</span></label>
                                        <BbSelect 
                                            v-model="form.package_id" 
                                            :options="packageOptions" 
                                            placeholder="Pilih Paket..."
                                            :error="!!form.errors.package_id"
                                            required
                                        />
                                        <div v-if="form.errors.package_id" class="small text-danger mt-1">{{ form.errors.package_id }}</div>
                                    </div>
                                </div>

                                <div v-if="form.items.length > 0" class="mb-3">
                                    <h6 class="fw-bold mb-2 small d-flex justify-content-between align-items-center">
                                        Keranjang F&B 
                                        <span class="badge rounded-pill px-2 py-1" style="background: rgba(245,158,11,0.15); color: #f59e0b; font-size: 0.7rem;">{{ form.items.length }}</span>
                                    </h6>
                                    <div class="d-flex flex-column gap-2" style="max-height: 150px; overflow-y: auto;">
                                        <div v-for="(item, index) in form.items" :key="item.fnb_item_id" class="p-2 rounded" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05);">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="fw-bold small">{{ item.name }}</span>
                                                <button type="button" @click="removeDraftTableOrder(index)" class="btn btn-sm text-danger p-0" title="Hapus">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="text-secondary small">{{ formatRupiah(item.price) }}</span>
                                                <div class="d-flex align-items-center gap-1">
                                                    <button type="button" @click="item.quantity > 1 ? item.quantity-- : null" class="bb-btn bb-btn--ghost px-2 py-0 text-secondary" style="font-size: 0.75rem;">
                                                        <i class="bi bi-dash-lg"></i>
                                                    </button>
                                                    <input type="number" v-model.number="item.quantity" min="1" class="bb-input py-0 px-1 text-center" style="width: 50px; height: 26px; font-size: 0.75rem; border: none; background: rgba(255,255,255,0.05);" />
                                                    <button type="button" @click="item.quantity++" class="bb-btn bb-btn--ghost px-2 py-0 text-warning" style="font-size: 0.75rem;">
                                                        <i class="bi bi-plus-lg"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Price Summary -->
                                <div class="p-3 rounded-4 mb-4" style="background: rgba(16,185,129,0.06); border: 1px solid rgba(16,185,129,0.12);">
                                    <div class="d-flex justify-content-between mb-2 small">
                                        <span class="text-secondary">Paket ({{ form.duration_hours }} Jam)</span>
                                        <span class="fw-semibold">{{ formatRupiah(calculatedPrice) }}</span>
                                    </div>
                                    <div v-if="form.items.length > 0" class="d-flex justify-content-between mb-2 small">
                                        <span class="text-secondary">F&B ({{ form.items.length }} item)</span>
                                        <span class="fw-semibold" style="color: #f59e0b;">{{ formatRupiah(tableOrderFnbTotal) }}</span>
                                    </div>
                                    <hr class="my-2" style="opacity: 0.1;">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-bold">Total Pembayaran</span>
                                        <span class="fw-bold fs-4 text-gradient">{{ formatRupiah(calculatedPrice + tableOrderFnbTotal) }}</span>
                                    </div>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="button" @click="closeOrderModal" class="bb-btn bb-btn--ghost flex-grow-1 py-3">
                                        Batal
                                    </button>
                                    <button
                                        type="submit"
                                        :disabled="form.processing || !!conflictingOrderBooking || isOrderStartInPast"
                                        class="bb-btn bb-btn--success flex-grow-1 py-3"
                                    >
                                        <i class="bi bi-check2-circle me-1"></i>
                                        {{ isOrderImmediate ? 'Mulai Sesi' : 'Simpan Jadwal Pesanan' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════ -->
        <!-- Session Detail Modal (Active Table — with F&B)     -->
        <!-- ═══════════════════════════════════════════════════ -->
        <div v-if="showSessionModal && selectedActiveTable" class="bb-modal-backdrop">
            <div class="bb-modal" style="max-width: 1240px; width: 95%;">
                <!-- Header: Table info + countdown -->
                <div class="bb-modal-header">
                    <div class="d-flex align-items-center gap-3 flex-grow-1 min-w-0">
                        <div class="min-w-0">
                            <h5 class="fw-bold mb-0 d-flex align-items-center gap-2 text-truncate">
                                <i class="bi bi-circle-fill" style="font-size: 0.5rem;" :style="{ color: isEditingBooked ? '#06b6d4' : '#10b981' }"></i>
                                {{ isEditingBooked ? `Edit Jadwal Booking — ${selectedActiveTable.name}` : selectedActiveTable.name }}
                            </h5>
                            <div class="d-flex gap-3 mt-1 small text-secondary flex-wrap">
                                <span><i class="bi bi-person-fill me-1"></i>{{ activeTransaction?.customer_name }}</span>
                                <span><i class="bi bi-box me-1"></i>{{ activeTransaction?.package?.name || '-' }}</span>
                                <span v-if="!isEditingBooked" class="fw-bold" style="color: #10b981;">
                                    <i class="bi bi-clock me-1"></i>{{ timers[selectedActiveTable.id] || '00:00:00' }}
                                </span>
                                <span v-else class="fw-bold" style="color: #06b6d4;">
                                    <i class="bi bi-calendar2-check me-1"></i>{{ formatBookingRange(activeTransaction) }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-shrink-0">
                        <!-- Toggle Saklar Lampu Meja (Manual / Test) -->
                        <button
                            v-if="!isEditingBooked"
                            type="button"
                            class="bb-btn d-flex align-items-center gap-2 py-1 px-3 rounded-pill"
                            :class="selectedActiveTable.is_light_on ? 'bb-btn--warning text-white fw-bold' : 'bb-btn--ghost border text-secondary'"
                            :disabled="togglingLight"
                            @click="toggleTableLight(selectedActiveTable)"
                            :title="selectedActiveTable.is_light_on ? 'Matikan Lampu Meja' : 'Nyalakan Lampu Meja'"
                            style="font-size: 0.82rem;"
                        >
                            <span v-if="togglingLight" class="spinner-border spinner-border-sm" role="status"></span>
                            <i v-else class="bi" :class="selectedActiveTable.is_light_on ? 'bi-lightbulb-fill' : 'bi-lightbulb'"></i>
                            <span>{{ selectedActiveTable.is_light_on ? 'Lampu: ON' : 'Lampu: OFF' }}</span>
                        </button>
                        <button @click="closeSessionModal" class="bb-theme-toggle" style="width: 32px; height: 32px; font-size: 0.9rem;">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>

                <div class="d-flex gap-1 px-4 pt-3" style="border-bottom: 1px solid rgba(255,255,255,0.06);">
                    <button @click="sessionTab = 'edit'" 
                            :class="['bb-btn bb-btn--sm rounded-bottom-0 px-3 py-2', sessionTab === 'edit' ? 'bb-btn--primary' : 'bb-btn--ghost']">
                        <i class="bi bi-pencil-square me-1"></i> {{ isEditingBooked ? 'Edit Jadwal & Pesanan' : 'Edit Sesi & Pesanan' }}
                    </button>
                    <button v-if="!isEditingBooked" @click="sessionTab = 'checkout'" 
                            :class="['bb-btn bb-btn--sm rounded-bottom-0 px-3 py-2', sessionTab === 'checkout' ? 'bb-btn--danger' : 'bb-btn--ghost']">
                        <i class="bi bi-cash-stack me-1"></i> Checkout
                    </button>
                </div>

                <div class="bb-modal-body p-4">
                    
                    <!-- TAB: Checkout (Moved outside of tab wrapper so we can just use v-if without breaking layout) -->
                    <div v-if="sessionTab === 'checkout'">
                        <div class="text-center mb-4">
                            <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width: 64px; height: 64px; background: rgba(239,68,68,0.1);">
                                <i class="bi bi-cash-stack" style="font-size: 1.8rem; color: #ef4444;"></i>
                            </div>
                            <h5 class="fw-bold mb-1">Checkout & Selesaikan Sesi</h5>
                            <p class="text-secondary small mb-0">{{ selectedActiveTable?.name }} — {{ activeTransaction?.customer_name }}</p>
                        </div>

                        <!-- Cost Breakdown -->
                        <div class="p-4 rounded-4 mb-4" style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06);">
                            <!-- Billiard Cost -->
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-secondary"><i class="bi bi-circle-fill me-2" style="font-size: 0.4rem; color: #10b981;"></i>Biaya Biliar</span>
                                <span class="fw-semibold">{{ formatRupiah(activeTransaction?.billiard_cost) }}</span>
                            </div>
                            <div class="small text-secondary mb-3 ms-3" style="font-size: 0.72rem;">
                                {{ activeTransaction?.package?.name || '-' }} × {{ calculateDuration(activeTransaction || {}) }}
                            </div>

                            <!-- F&B Cost -->
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="text-secondary"><i class="bi bi-circle-fill me-2" style="font-size: 0.4rem; color: #f59e0b;"></i>Biaya F&B</span>
                                <span class="fw-semibold">{{ formatRupiah(activeTransaction?.fnb_cost) }}</span>
                            </div>
                            <div v-if="activeTransaction?.items?.length > 0" class="ms-3 mb-3">
                                <div v-for="item in activeTransaction.items" :key="item.id" class="d-flex justify-content-between text-secondary" style="font-size: 0.72rem;">
                                    <span>{{ item.fnb_item?.name }} ×{{ item.quantity }}</span>
                                    <span>{{ formatRupiah(item.subtotal) }}</span>
                                </div>
                            </div>

                            <hr style="opacity: 0.1;">

                            <!-- Total -->
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold fs-5">TOTAL</span>
                                <span class="fw-bold fs-4 text-gradient">{{ formatRupiah(Number(activeTransaction?.billiard_cost || 0) + Number(activeTransaction?.fnb_cost || 0)) }}</span>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="button" @click="closeSessionModal" class="bb-btn bb-btn--ghost flex-grow-1 py-3">
                                Batal
                            </button>
                            <button type="button" @click="executeCheckout" class="bb-btn bb-btn--danger flex-grow-1 py-3">
                                <i class="bi bi-receipt me-1"></i> Checkout & Cetak Struk
                            </button>
                        </div>
                    </div>

                    <!-- TAB: Edit Sesi -->
                    <div v-if="sessionTab === 'edit'">
                        <form @submit.prevent="submitEditSession">
                            <div class="row g-4">
                                <!-- Left: F&B Menu -->
                                <div class="col-md-6 border-end" style="border-color: rgba(255,255,255,0.1) !important;">
                                    <h6 class="fw-bold mb-3"><i class="bi bi-cup-hot-fill text-warning me-2"></i>Pesan F&B (Sesi Aktif)</h6>
                                    
                                    <!-- Search + Category Filter -->
                                    <div class="mb-3 d-flex gap-2">
                                        <div class="position-relative flex-grow-1">
                                            <i class="bi bi-search position-absolute top-50 translate-middle-y ms-3 text-secondary"></i>
                                            <input type="text" v-model="fnbSearch" class="bb-input ps-5" placeholder="Cari menu F&B..." />
                                        </div>
                                    </div>
                                    <div class="d-flex gap-2 overflow-auto pb-2 mb-3" style="scrollbar-width: none;">
                                        <button type="button" @click="fnbCategoryFilter = 'all'" :class="['bb-btn bb-btn--sm flex-shrink-0', fnbCategoryFilter === 'all' ? 'bb-btn--warning' : 'bb-btn--ghost']">
                                            Semua
                                        </button>
                                        <button type="button" v-for="cat in fnbCategories" :key="cat" @click="fnbCategoryFilter = cat" :class="['bb-btn bb-btn--sm flex-shrink-0', fnbCategoryFilter === cat ? 'bb-btn--warning' : 'bb-btn--ghost']">
                                            {{ cat }}
                                        </button>
                                    </div>
            
                                    <!-- Menu Grid -->
                                    <div v-if="filteredFnbItems.length > 0" class="d-grid gap-2" style="grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); max-height: 380px; overflow-y: auto; padding-right: 5px;">
                                        <div v-for="item in filteredFnbItems" :key="item.id" class="bb-table-card p-0 overflow-hidden d-flex flex-column" style="border-radius: 0.75rem; cursor: pointer;" @click="addFnbItemToEditForm(item)">
                                            <!-- Image -->
                                            <div v-if="item.image_url" style="height: 80px; overflow: hidden;">
                                                <img :src="item.image_url" :alt="item.name" class="w-100 h-100" style="object-fit: cover;" />
                                            </div>
                                            <div v-else class="d-flex align-items-center justify-content-center" style="height: 80px; background: rgba(255,255,255,0.03);">
                                                <i class="bi bi-cup-hot" style="font-size: 1.5rem; opacity: 0.15;"></i>
                                            </div>
                                            <!-- Info -->
                                            <div class="p-2 d-flex flex-column flex-grow-1">
                                                <div class="fw-bold small text-truncate mb-1" style="font-size: 0.75rem;">{{ item.name }}</div>
                                                <div class="d-flex justify-content-between align-items-center mt-auto pt-1">
                                                    <span class="fw-bold small" style="color: #f59e0b; font-size: 0.7rem;">{{ formatRupiah(item.price) }}</span>
                                                    <div class="bb-btn bb-btn--warning bb-btn--sm px-1 py-0" style="font-size: 0.6rem;">
                                                        <i class="bi bi-plus-lg"></i>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-else class="text-center py-5 text-secondary opacity-50">
                                        <i class="bi bi-search fs-2 d-block mb-2"></i>
                                        <div class="small">{{ fnbSearch ? 'Menu tidak ditemukan' : 'Belum ada menu F&B' }}</div>
                                    </div>
                                </div>
                                
                                <!-- Right Column: Order Form -->
                                <div class="col-md-6">
                                    <h6 class="fw-bold mb-3">
                                        <i class="bi bi-card-list text-primary me-2"></i>
                                        {{ isEditingBooked ? 'Detail Pesanan (Booking)' : 'Detail Pemesanan (Aktif)' }}
                                    </h6>

                                    <!-- Peringatan Bentrok / Jam Lewat saat Edit -->
                                    <div
                                        v-if="isEditStartInPast"
                                        class="alert alert-danger border-0 rounded-3 small mb-3 d-flex align-items-start gap-2"
                                    >
                                        <i class="bi bi-exclamation-octagon-fill mt-1 flex-shrink-0"></i>
                                        <div>
                                            <strong>Jam Mulai Tidak Valid!</strong><br />
                                            Jam mulai booking tidak boleh diubah ke waktu yang sudah lewat.
                                        </div>
                                    </div>
                                    <div
                                        v-else-if="conflictingEditBooking"
                                        class="alert alert-danger border-0 rounded-3 small mb-3 d-flex align-items-start gap-2"
                                    >
                                        <i class="bi bi-exclamation-triangle-fill mt-1 flex-shrink-0"></i>
                                        <div>
                                            <strong>Perubahan Tidak Dapat Disimpan — Bentrok Jadwal!</strong><br />
                                            Jadwal <strong>{{ editTimeRangeLabel }}</strong> ({{ editSessionForm.duration_hours }} jam) bentrok dengan pesanan
                                            <strong>{{ conflictingEditBooking.customer_name }}</strong>
                                            ({{ formatBookingRange(conflictingEditBooking) }}).
                                        </div>
                                    </div>
                                    
                                    <div class="row g-3 mb-3">
                                        <!-- 1. Nama Pelanggan (Pilih Customer Terdaftar atau Ketik Sendiri) -->
                                        <div class="col-12">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <label class="bb-label mb-0">Nama Pelanggan <span class="text-danger">*</span></label>
                                                <span v-if="selectedEditCustomerObj" class="badge rounded-pill px-2 py-1" style="background: rgba(6, 182, 212, 0.15); color: #06b6d4; font-size: 0.7rem;">
                                                    <i class="bi bi-person-check-fill me-1"></i>Customer Terdaftar
                                                    <span v-if="selectedEditCustomerObj.whatsapp">· {{ selectedEditCustomerObj.whatsapp }}</span>
                                                </span>
                                                <span v-else class="small text-secondary" style="font-size: 0.72rem;">
                                                    Pilih customer atau ketik manual
                                                </span>
                                            </div>

                                            <div class="position-relative">
                                                <div class="d-flex gap-1">
                                                    <div class="position-relative flex-grow-1">
                                                        <input
                                                            type="text"
                                                            v-model="editSessionForm.customer_name"
                                                            @input="onEditCustomerNameInput"
                                                            @focus="showEditCustomerDropdown = true"
                                                            required
                                                            class="bb-input pe-4"
                                                            placeholder="Ketik nama atau cari customer terdaftar..."
                                                            autocomplete="off"
                                                        />
                                                        <button
                                                            v-if="editSessionForm.customer_name"
                                                            type="button"
                                                            class="btn btn-sm position-absolute top-50 end-0 translate-middle-y text-secondary border-0 pe-2"
                                                            title="Bersihkan"
                                                            @click="clearEditSelectedCustomer"
                                                        >
                                                            <i class="bi bi-x-lg" style="font-size: 0.75rem;"></i>
                                                        </button>
                                                    </div>
                                                    <button
                                                        v-if="customers.length > 0"
                                                        type="button"
                                                        class="bb-btn bb-btn--ghost px-3 flex-shrink-0 border"
                                                        title="Pilih dari daftar customer"
                                                        @click="showEditCustomerDropdown = !showEditCustomerDropdown"
                                                    >
                                                        <i class="bi" :class="showEditCustomerDropdown ? 'bi-chevron-up' : 'bi-people-fill'"></i>
                                                    </button>
                                                </div>

                                                <div
                                                    v-if="showEditCustomerDropdown && customers.length > 0"
                                                    class="position-absolute start-0 end-0 mt-1 rounded-3 shadow-lg border overflow-auto"
                                                    style="z-index: 30; max-height: 210px; background: var(--bs-body-bg); border-color: rgba(148, 163, 184, 0.22) !important;"
                                                >
                                                    <div class="px-3 py-1 small text-secondary border-bottom d-flex justify-content-between align-items-center" style="font-size: 0.72rem; border-color: rgba(148, 163, 184, 0.12) !important;">
                                                        <span>Customer Terdaftar ({{ filteredEditCustomers.length }})</span>
                                                        <button type="button" class="btn btn-sm p-0 text-secondary border-0" @click="showEditCustomerDropdown = false">
                                                            Tutup
                                                        </button>
                                                    </div>
                                                    <button
                                                        v-for="cust in filteredEditCustomers"
                                                        :key="cust.id"
                                                        type="button"
                                                        class="w-100 text-start px-3 py-2 border-0 bg-transparent d-flex justify-content-between align-items-center"
                                                        style="color: var(--bs-body-color); border-bottom: 1px solid rgba(148, 163, 184, 0.08) !important;"
                                                        @click="selectEditCustomer(cust)"
                                                    >
                                                        <div class="min-w-0">
                                                            <div class="fw-semibold small text-truncate">{{ cust.name }}</div>
                                                            <div class="text-secondary" style="font-size: 0.72rem;">
                                                                @{{ cust.username }}
                                                                <span v-if="cust.email">· {{ cust.email }}</span>
                                                            </div>
                                                        </div>
                                                        <span v-if="cust.whatsapp" class="badge rounded-pill ms-2 flex-shrink-0" style="background: rgba(16, 185, 129, 0.14); color: #10b981; font-size: 0.7rem;">
                                                            <i class="bi bi-whatsapp me-1"></i>{{ cust.whatsapp }}
                                                        </span>
                                                    </button>
                                                    <div v-if="filteredEditCustomers.length === 0" class="px-3 py-2 small text-secondary">
                                                        Tidak ditemukan di daftar customer — menggunakan nama manual <strong>"{{ editSessionForm.customer_name }}"</strong>.
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- 2. Tanggal & Jam Mulai (Bisa diubah saat status = booked) -->
                                        <template v-if="isEditingBooked">
                                            <div class="col-12 col-sm-5">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <label class="bb-label mb-0">Tanggal <span class="text-danger">*</span></label>
                                                    <div class="d-flex gap-2">
                                                        <button
                                                            type="button"
                                                            class="btn btn-link btn-sm p-0 text-decoration-none fw-semibold"
                                                            style="font-size: 0.72rem;"
                                                            :style="editSessionForm.start_date === currentYMD() ? 'color: #10b981;' : 'color: var(--bs-secondary-color);'"
                                                            @click="editSessionForm.start_date = currentYMD()"
                                                        >
                                                            Hari Ini
                                                        </button>
                                                        <button
                                                            type="button"
                                                            class="btn btn-link btn-sm p-0 text-decoration-none fw-semibold"
                                                            style="font-size: 0.72rem;"
                                                            :style="editSessionForm.start_date === tomorrowYMD() ? 'color: #06b6d4;' : 'color: var(--bs-secondary-color);'"
                                                            @click="editSessionForm.start_date = tomorrowYMD()"
                                                        >
                                                            Besok
                                                        </button>
                                                    </div>
                                                </div>
                                                <flatPickr
                                                    v-model="editSessionForm.start_date"
                                                    :config="orderDateConfig"
                                                    class="bb-input w-100"
                                                    :class="{ 'border-danger': isEditStartInPast || !!conflictingEditBooking }"
                                                    placeholder="Pilih tanggal"
                                                />
                                            </div>

                                            <div class="col-6 col-sm-4">
                                                <label class="bb-label mb-1">Jam Mulai <span class="text-danger">*</span></label>
                                                <flatPickr
                                                    v-model="editSessionForm.start_time"
                                                    :config="orderTimeConfig"
                                                    class="bb-input w-100"
                                                    :class="{ 'border-danger': isEditStartInPast || !!conflictingEditBooking }"
                                                    placeholder="Pilih jam mulai"
                                                />
                                            </div>
                                        </template>

                                        <!-- 3. Durasi -->
                                        <div :class="isEditingBooked ? 'col-6 col-sm-3' : 'col-4 col-sm-3'">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <label class="bb-label mb-0">Durasi <span class="text-danger">*</span></label>
                                                <span class="small text-secondary" style="font-size: 0.7rem;">Jam</span>
                                            </div>
                                            <input
                                                type="number"
                                                step="0.5"
                                                :min="minimumEditDuration"
                                                v-model="editSessionForm.duration_hours"
                                                required
                                                class="bb-input"
                                                :class="{ 'border-danger': !!conflictingEditBooking }"
                                            />
                                            <div v-if="editSessionForm.errors.duration_hours" class="small text-danger mt-1">{{ editSessionForm.errors.duration_hours }}</div>
                                        </div>

                                        <!-- 4. Paket (menggunakan BbSelect, bukan native select) -->
                                        <div :class="isEditingBooked ? 'col-12' : 'col-8 col-sm-9'">
                                            <label class="bb-label mb-1">Pilih Paket <span class="text-danger">*</span></label>
                                            <BbSelect
                                                v-model="editSessionForm.package_id"
                                                :options="packageOptions"
                                                placeholder="Pilih Paket..."
                                                :error="!!editSessionForm.errors.package_id"
                                                required
                                            />
                                        </div>

                                        <div class="col-12 mt-1">
                                            <div class="small text-secondary d-flex align-items-center justify-content-between px-2 py-1 rounded-2" style="background: rgba(6, 182, 212, 0.06); border: 1px dashed rgba(6, 182, 212, 0.25);">
                                                <span><i class="bi bi-calendar2-range me-1 text-info"></i>Estimasi Jadwal Bermain:</span>
                                                <strong class="text-info font-monospace">{{ editTimeRangeLabel }}</strong>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="editSessionForm.items?.length > 0" class="mb-3">
                                        <h6 class="fw-bold mb-2 small d-flex justify-content-between align-items-center">
                                            Keranjang F&B 
                                            <span class="badge rounded-pill px-2 py-1" style="background: rgba(16,185,129,0.15); color: #10b981; font-size: 0.7rem;">{{ editSessionForm.items.length }}</span>
                                        </h6>
                                        <div class="d-flex flex-column gap-2" style="max-height: 150px; overflow-y: auto;">
                                            <div v-for="(item, index) in editSessionForm.items" :key="index" class="p-2 rounded" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05);">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <span class="fw-bold small">{{ item.name }}</span>
                                                    <span class="fw-bold small" style="color: #10b981;">{{ formatRupiah(item.price * item.quantity) }}</span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="text-secondary small">{{ formatRupiah(item.price) }}</span>
                                                    <div class="d-flex align-items-center gap-1">
                                                        <button type="button" @click="decrementEditItem(index)" class="bb-btn bb-btn--ghost px-2 py-0 text-secondary" style="font-size: 0.75rem;">
                                                            <i class="bi bi-dash-lg"></i>
                                                        </button>
                                                        <input type="number" 
                                                            v-model="item.quantity" 
                                                            min="0" 
                                                            class="bb-input py-0 px-1 text-center" 
                                                            style="width: 50px; height: 26px; font-size: 0.75rem; border: none; background: rgba(255,255,255,0.05);" 
                                                        />
                                                        <button type="button" @click="incrementEditItem(index)" class="bb-btn bb-btn--ghost px-2 py-0 text-warning" style="font-size: 0.75rem;">
                                                            <i class="bi bi-plus-lg"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Price Summary -->
                                    <div class="p-3 rounded-4 mb-3" style="background: rgba(16,185,129,0.06); border: 1px solid rgba(16,185,129,0.12);">
                                        <div class="d-flex justify-content-between mb-2 small">
                                            <span class="text-secondary">Paket ({{ editSessionForm.duration_hours }} Jam)</span>
                                            <span class="fw-semibold">{{ formatRupiah(editCalculatedPrice) }}</span>
                                        </div>
                                        <div v-if="editSessionForm.items?.length > 0" class="d-flex justify-content-between mb-2 small">
                                            <span class="text-secondary">F&B ({{ editSessionForm.items.length }} item)</span>
                                            <span class="fw-semibold" style="color: #f59e0b;">{{ formatRupiah(editFnbTotal) }}</span>
                                        </div>
                                        <hr class="my-2" style="opacity: 0.1;">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="fw-bold">Total Pembayaran</span>
                                            <span class="fw-bold fs-4 text-gradient">{{ formatRupiah(editCalculatedPrice + editFnbTotal) }}</span>
                                        </div>
                                    </div>

                                    <div class="d-flex gap-2 mt-3">
                                        <button type="button" @click="closeSessionModal" class="bb-btn bb-btn--ghost flex-grow-1 py-3">
                                            Batal
                                        </button>
                                        <button
                                            type="submit"
                                            :disabled="editSessionForm.processing || !editSessionForm.package_id || !!conflictingEditBooking || isEditStartInPast"
                                            class="bb-btn bb-btn--primary flex-grow-1 py-3"
                                        >
                                            <i class="bi bi-check-lg me-1"></i> Simpan Perubahan
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                </div>
            </div>
        </div>

        <ConfirmModal
            :show="confirmCancelBooking.show"
            title="Batalkan Booking"
            :message="confirmCancelBooking.booking
                ? `Batalkan booking ${confirmCancelBooking.booking.table?.name || 'Meja'} atas nama ${confirmCancelBooking.booking.customer_name} (${formatBookingRange(confirmCancelBooking.booking)})?`
                : 'Apakah Anda yakin ingin membatalkan booking ini?'"
            confirmText="Ya, Batalkan Booking"
            cancelText="Kembali"
            :danger="true"
            @confirm="executeCancelBooking"
            @cancel="confirmCancelBooking = { show: false, booking: null }"
        />

    </KasirLayout>
</template>

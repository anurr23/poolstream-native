import { defineComponent, h, reactive, onMounted, watch } from 'vue';

const routeMap = {
    landing: 'index.php',
    menu: 'menu.php',
    login: 'login.php',
    'api.login': 'api/auth.php?action=login',
    'api.register': 'api/auth.php?action=register',
    'api.password.confirm': 'api/auth.php?action=confirm-password',
    register: 'register.php',
    logout: 'api/auth.php?action=logout',
    'password.confirm': 'confirm-password.php',
    'password.update': 'api/profile.php?action=password',
    'customer.verification.notice': 'verify-otp.php',
    'customer.verification.verify': 'api/auth.php?action=verify',
    'customer.verification.resend': 'api/auth.php?action=resend',
    'customer.verification.restart': 'api/auth.php?action=restart-registration',
    'customer.verification.cancel': 'api/auth.php?action=cancel-verification',
    'customer.home': 'customer-home.php',
    'customer.booking.index': 'customer-booking.php',
    'customer.history': 'customer-history.php',
    'customer.tables': 'api/customer.php?action=tables',
    'customer.table-bookings': 'api/customer.php?action=table-bookings&id={0}',
    'customer.booking.availability': 'api/customer.php?action=availability',
    'customer.booking.store': 'api/customer.php?action=store',
    'customer.booking.cancel': 'api/customer.php?action=cancel&id={0}',
    dashboard: 'dashboard.php',
    'fnb-orders.index': 'fnb-orders.php',
    'fnb-orders.store': 'api/fnb-orders.php?action=create',
    'fnb-orders.checkout': 'api/fnb-orders.php?action=checkout&id={0}',
    'tables.list': 'api/tables.php?action=list',
    'tables.start': 'api/tables.php?action=start&id={0}',
    'tables.stop': 'api/tables.php?action=stop&id={0}',
    'tables.toggle-light': 'api/tables.php?action=toggle-light&id={0}',
    'tables.start-booking': 'api/tables.php?action=start-booking&id={0}',
    'tables.cancel-booking': 'api/tables.php?action=cancel-booking&id={0}',
    'transactions.send-receipt': 'api/tables.php?action=send-receipt&id={0}',
    'transactions.send-receipt-wa': 'api/tables.php?action=send-receipt-wa&id={0}',
    'tables.updateSession': 'api/tables.php?action=update&id={0}&transaction={1}',
    'transaction-items.store': 'api/transaction-items.php?action=store&id={0}',
    'transaction-items.add': 'api/transaction-items.php?action=update&id={0}',
    'transaction-items.update-quantity': 'api/transaction-items.php?action=update&id={0}',
    'transaction-items.destroy': 'api/transaction-items.php?action=destroy&id={0}',
    'admin.index': 'master.php',
    'tables.index': 'master-tables.php',
    'tables.store': 'api/master.php?resource=tables&action=store',
    'tables.update': 'api/master.php?resource=tables&action=update&id={0}',
    'tables.destroy': 'api/master.php?resource=tables&action=destroy&id={0}',
    'packages.index': 'master-packages.php',
    'packages.store': 'api/master.php?resource=packages&action=store',
    'packages.update': 'api/master.php?resource=packages&action=update&id={0}',
    'packages.destroy': 'api/master.php?resource=packages&action=destroy&id={0}',
    'fnb_items.index': 'master-fnb-items.php',
    'fnb_items.store': 'api/master.php?resource=fnb&action=store',
    'fnb_items.update': 'api/master.php?resource=fnb&action=update&id={0}',
    'fnb_items.destroy': 'api/master.php?resource=fnb&action=destroy&id={0}',
    'users.index': 'master-users.php',
    'users.store': 'api/master.php?resource=users&action=store',
    'users.update': 'api/master.php?resource=users&action=update&id={0}',
    'users.destroy': 'api/master.php?resource=users&action=destroy&id={0}',
    'reports.fnb-sales': 'report-fnb-sales.php',
    'reports.table-transactions': 'report-table-transactions.php',
    'reports.revenue': 'report-revenue.php',
    'reports.analytics': 'report-analytics.php',
    'ai-evaluations.index': 'ai-evaluations.php',
    'ai-evaluations.store': 'api/ai-evaluations.php?action=store',
    'ai-evaluations.show': 'ai-evaluation.php?id={0}',
    'ai-evaluations.pdf': 'ai-evaluation-pdf.php?id={0}',
    'ai-evaluations.destroy': 'api/ai-evaluations.php?action=delete&id={0}',
    'api.ai-evaluation.show': 'api/ai-evaluations.php?action=show',
    'profile.edit': 'profile.php',
    'profile.update': 'api/profile.php?action=update',
    'profile.whatsapp': 'api/profile.php?action=whatsapp',
    'auth.google.redirect': 'google-auth.php?action=redirect',
    'auth.google.callback': 'google-auth.php?action=callback',
};

const pageRouteMap = {
    Landing: 'landing',
    Menu: 'menu',
    Dashboard: 'dashboard',
    'Fnb/Index': 'fnb-orders.index',
    'Admin/Index': 'admin.index',
    'Auth/Login': 'login',
    'Auth/Register': 'register',
    'Auth/VerifyOtp': 'customer.verification.notice',
    'Auth/ConfirmPassword': 'password.confirm',
    'Customer/Home': 'customer.home',
    'Customer/Booking': 'customer.booking.index',
    'Customer/History': 'customer.history',
    'Master/Tables': 'tables.index',
    'Master/Packages': 'packages.index',
    'Master/FnbItems': 'fnb_items.index',
    'Master/Users': 'users.index',
    'Master/Reports/FnbSales': 'reports.fnb-sales',
    'Master/Reports/TableTransactions': 'reports.table-transactions',
    'Master/Reports/Revenue': 'reports.revenue',
    'Master/Reports/Analytics': 'reports.analytics',
    'Master/AiEvaluation/Index': 'ai-evaluations.index',
    'Master/AiEvaluation/Show': 'ai-evaluations.show',
    'Profile/Edit': 'profile.edit',
};

export const page = reactive({
    component: window.__POOLSTREAM__?.component || '',
    props: window.__POOLSTREAM__?.props || {},
    url: window.location.pathname,
    version: null,
});

export function usePage() {
    return page;
}

export function route(name, params) {
    if (name === undefined) {
        return {
            has: (key) => route.has(key),
            current: (key) => route.current(key),
        };
    }

    let target = routeMap[name] || name;

    if (params !== undefined && params !== null) {
        if (Array.isArray(params)) {
            params.forEach((value, index) => {
                target = target.replaceAll(`{${index}}`, encodeURIComponent(String(value)));
            });
        } else if (typeof params === 'object') {
            const query = new URLSearchParams();
            Object.entries(params).forEach(([key, value]) => {
                if (value !== undefined && value !== null && value !== '') query.append(key, value);
            });
            const separator = target.includes('?') ? '&' : '?';
            if (query.toString()) target += separator + query.toString();
        } else {
            target = target.replace('{0}', encodeURIComponent(String(params)));
        }
    }

    return target;
}

route.current = (name) => pageRouteMap[page.component] === name;
route.has = (name) => Object.prototype.hasOwnProperty.call(routeMap, name);

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function blockUi(show) {
    const el = document.getElementById('bb-block-ui');
    if (!el) return;
    el.classList.toggle('is-active', !!show);
    el.setAttribute('aria-hidden', show ? 'false' : 'true');
}

function setFlash(type, message) {
    if (!page.props.flash) page.props.flash = { success: null, error: null };
    page.props.flash[type] = null;
    queueMicrotask(() => {
        page.props.flash[type] = message;
    });
}

function mergePageProps(props) {
    if (!props || typeof props !== 'object') return;
    Object.entries(props).forEach(([key, value]) => {
        page.props[key] = value;
    });
}

function setErrors(errors) {
    page.props.errors = errors || {};
}

function appendValue(formData, key, value) {
    if (value === undefined || value === null) return;
    if (value instanceof File || value instanceof Blob) {
        formData.append(key, value);
        return;
    }
    if (Array.isArray(value)) {
        value.forEach((item, index) => appendValue(formData, `${key}[${index}]`, item));
        return;
    }
    if (typeof value === 'object') {
        Object.entries(value).forEach(([childKey, childValue]) => appendValue(formData, `${key}[${childKey}]`, childValue));
        return;
    }
    formData.append(key, String(value));
}

async function request(method, url, data = {}, options = {}) {
    const actualMethod = String(method || 'get').toUpperCase();
    const isSilent = !!options.silent;
    if (!isSilent) blockUi(true);

    try {
        let response;
        if (actualMethod === 'GET') {
            const query = new URLSearchParams();
            Object.entries(data || {}).forEach(([key, value]) => {
                if (value !== undefined && value !== null && value !== '') query.append(key, value);
            });
            const target = url + (query.toString() ? (url.includes('?') ? '&' : '?') + query.toString() : '');
            window.location.href = target;
            return;
        }

        let formData;
        if (data instanceof FormData) {
            // Halaman yang sudah menyusun FormData sendiri (mis. upload foto user)
            // harus dikirim apa adanya, bukan diubah menjadi objek kosong.
            formData = data;
            if (!formData.has('_method')) formData.append('_method', actualMethod);
            if (!formData.has('_token')) formData.append('_token', csrfToken());
        } else {
            formData = new FormData();
            Object.entries(data || {}).forEach(([key, value]) => appendValue(formData, key, value));
            formData.append('_method', actualMethod);
            formData.append('_token', csrfToken());
        }

        response = await fetch(url, {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
            credentials: 'same-origin',
        });

        const contentType = response.headers.get('content-type') || '';
        let json = null;
        if (contentType.includes('application/json')) {
            json = await response.json();
        }

        if (!json) {
            if (response.redirected) {
                window.location.href = response.url;
            } else {
                window.location.reload();
            }
            return;
        }

        if (!response.ok || json.ok === false) {
            const errors = json?.errors || {};
            setErrors(errors);
            if (json?.message) setFlash('error', json.message);
            if (typeof options.onError === 'function') options.onError(errors);
            return;
        }

        if (json?.errors) setErrors(json.errors);
        else setErrors({});
        if (json?.props) mergePageProps(json.props);
        if (json?.message && !options.suppressFlash) setFlash('success', json.message);
        if (!isSilent) blockUi(false);
        if (typeof options.onSuccess === 'function') await options.onSuccess(json || {});

        if (json?.redirect) {
            window.location.href = json.redirect;
            return;
        }
    } catch (error) {
        console.error(error);
        if (!isSilent) setFlash('error', 'Terjadi kesalahan jaringan.');
        if (typeof options.onError === 'function') options.onError({ _general: 'Terjadi kesalahan jaringan.' });
    } finally {
        if (typeof options.onFinish === 'function') options.onFinish();
        if (!isSilent) blockUi(false);
    }
}

export const router = {
    visit(url, options = {}) {
        const method = options.method || 'get';
        if (String(method).toLowerCase() === 'get') {
            window.location.href = url;
            return;
        }
        return request(method, url, options.data || {}, options);
    },
    get(url, data = {}, options = {}) {
        const query = new URLSearchParams();
        Object.entries(data || {}).forEach(([key, value]) => {
            if (value !== undefined && value !== null && value !== '') query.append(key, value);
        });
        window.location.href = url + (query.toString() ? (url.includes('?') ? '&' : '?') + query.toString() : '');
    },
    post(url, data = {}, options = {}) {
        return request('post', url, data, options);
    },
    put(url, data = {}, options = {}) {
        return request('put', url, data, options);
    },
    patch(url, data = {}, options = {}) {
        return request('patch', url, data, options);
    },
    delete(url, options = {}) {
        return request('delete', url, options.data || {}, options);
    },
    reload() {
        window.location.reload();
    },
};

export function useForm(initialValues = {}) {
    const form = reactive({ ...initialValues });
    form.errors = reactive({});
    form.processing = false;
    form.recentlySuccessful = false;
    form.progress = null;
    form._transform = null;
    form._defaults = JSON.parse(JSON.stringify(initialValues, (key, value) => (value instanceof File ? null : value)));

    form.transform = (callback) => {
        form._transform = callback;
        return form;
    };

    form.clearErrors = () => {
        Object.keys(form.errors).forEach((key) => delete form.errors[key]);
        setErrors({});
    };

    form.reset = (...fields) => {
        const keys = fields.length ? fields : Object.keys(form._defaults);
        keys.forEach((key) => {
            if (Object.prototype.hasOwnProperty.call(form._defaults, key)) {
                form[key] = form._defaults[key];
            }
        });
    };

    const submit = async (method, url, options = {}) => {
        form.processing = true;
        form.recentlySuccessful = false;
        form.clearErrors();

        let payload = {};
        Object.entries(form).forEach(([key, value]) => {
            if (['errors', 'processing', 'recentlySuccessful', 'progress', '_transform', '_defaults', 'transform', 'clearErrors', 'reset', 'post', 'put', 'patch', 'delete', 'get', '_submit'].includes(key)) return;
            payload[key] = value;
        });
        if (typeof form._transform === 'function') {
            payload = form._transform(payload) || payload;
        }

        await request(method, url, payload, {
            ...options,
            onSuccess: (json) => {
                form.recentlySuccessful = true;
                setTimeout(() => { form.recentlySuccessful = false; }, 2000);
                if (typeof options.onSuccess === 'function') options.onSuccess(json);
            },
            onError: (errors) => {
                Object.entries(errors || {}).forEach(([key, value]) => {
                    form.errors[key] = Array.isArray(value) ? value[0] : value;
                });
                if (typeof options.onError === 'function') options.onError(errors);
            },
            onFinish: () => {
                form.processing = false;
                if (typeof options.onFinish === 'function') options.onFinish();
            },
        });
    };

    form.post = (url, options = {}) => submit('post', url, options);
    form.put = (url, options = {}) => submit('put', url, options);
    form.patch = (url, options = {}) => submit('patch', url, options);
    form.delete = (url, options = {}) => submit('delete', url, options);
    form.get = (url, options = {}) => submit('get', url, options);

    return form;
}

export const Head = defineComponent({
    name: 'Head',
    props: { title: { type: String, default: '' } },
    setup(props) {
        const apply = () => {
            document.title = props.title ? `${props.title} - PoolStream` : 'PoolStream';
        };
        onMounted(apply);
        watch(() => props.title, apply);
        return () => null;
    },
});

export const Link = defineComponent({
    name: 'Link',
    inheritAttrs: false,
    props: {
        href: { type: String, required: true },
        method: { type: String, default: 'get' },
        as: { type: String, default: 'a' },
        data: { type: Object, default: () => ({}) },
        preserveScroll: { type: Boolean, default: false },
        preserveState: { type: Boolean, default: false },
    },
    setup(props, { slots, attrs }) {
        const handleClick = (event) => {
            if (String(props.method).toLowerCase() === 'get') return;
            event.preventDefault();
            router.visit(props.href, { method: props.method, data: props.data });
        };

        return () => {
            const tag = props.as === 'button' ? 'button' : (props.as || 'a');
            const finalAttrs = { ...attrs, onClick: handleClick };
            if (tag === 'a') {
                finalAttrs.href = props.href;
            } else {
                finalAttrs.type = 'button';
            }
            return h(tag, finalAttrs, slots.default ? slots.default() : []);
        };
    },
});


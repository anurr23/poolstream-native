import '../vue/scss/app.scss';
import 'bootstrap';
import 'bootstrap-icons/font/bootstrap-icons.css';
import { createApp, defineComponent, h } from 'vue';
import { Head, Link, page, route, router, useForm, usePage } from './shims/inertia.js';

const pages = import.meta.glob('./Pages/**/*.vue');
const component = page.component || 'Landing';
const loader = pages[`./Pages/${component}.vue`];
const showRuntimeError = new URLSearchParams(location.search).has('debug');

function closeDropdowns(except = null) {
    document.querySelectorAll('.dropdown-menu.show').forEach((menu) => {
        if (except && menu === except) return;
        menu.classList.remove('show');
        const toggle = menu.parentElement?.querySelector('[data-bs-toggle="dropdown"]');
        toggle?.setAttribute('aria-expanded', 'false');
    });
}

/**
 * Bootstrap data-api dropdown tidak selalu aktif saat module dievaluasi lewat
 * Vite ESM. Fallback kecil ini memastikan dropdown sidebar/header tetap bekerja.
 */
function initDropdowns() {
    document.querySelectorAll('.dropdown').forEach((root) => {
        if (root.dataset.poolstreamDropdown === '1') return;
        const toggle = root.querySelector('[data-bs-toggle="dropdown"]');
        const menu = root.querySelector('.dropdown-menu');
        if (!toggle || !menu) return;
        root.dataset.poolstreamDropdown = '1';

        toggle.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            const willOpen = !menu.classList.contains('show');
            closeDropdowns(menu);
            if (willOpen) {
                menu.classList.add('show');
                toggle.setAttribute('aria-expanded', 'true');
            } else {
                menu.classList.remove('show');
                toggle.setAttribute('aria-expanded', 'false');
            }
        }, true);
    });

    if (!window.__poolstreamDropdownOutside) {
        window.__poolstreamDropdownOutside = true;
        document.addEventListener('click', () => closeDropdowns());
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') closeDropdowns();
        });
    }
}

function hideBlockUi() {
    const el = document.getElementById('bb-block-ui');
    if (el) {
        el.classList.remove('is-active');
        el.setAttribute('aria-hidden', 'true');
    }
}

if (!loader) {
    document.getElementById('app').innerHTML = `<div style="padding:2rem;font-family:sans-serif;color:#ef4444">
        Halaman <strong>${component}</strong> tidak ditemukan.
    </div>`;
    hideBlockUi();
} else {
    loader()
        .then((module) => {
            const PageComponent = module.default || module;
            const Root = defineComponent({
                name: 'PoolStreamRoot',
                setup() {
                    return () => h(PageComponent, page.props);
                },
            });
            const app = createApp(Root);
            app.config.globalProperties.$page = page;
            app.config.globalProperties.$route = route;
            app.config.globalProperties.route = route;
            app.config.globalProperties.$inertia = router;
            app.provide('page', page);
            window.route = route;
            window.$page = page;
            window.$inertia = router;
            app.component('Head', Head);
            app.component('Link', Link);
            app.config.errorHandler = (error, instance, info) => {
                console.error(error, info);
                const target = document.getElementById('app');
                if (showRuntimeError && target) {
                    target.innerHTML = '<pre style="padding:1.5rem;white-space:pre-wrap;color:#ef4444;font-size:12px">'
                        + String(error && error.stack ? error.stack : error) + '\n' + info + '</pre>';
                }
            };
            app.mount('#app');
            requestAnimationFrame(() => {
                hideBlockUi();
                initDropdowns();
            });
        })
        .catch((error) => {
            console.error(error);
            const target = document.getElementById('app');
            if (showRuntimeError && target) {
                target.innerHTML = '<pre style="padding:1.5rem;white-space:pre-wrap;color:#ef4444;font-size:12px">'
                    + String(error && error.stack ? error.stack : error) + '</pre>';
            }
            hideBlockUi();
        });
}

// Ekspor agar tooling/debug mudah; bukan bagian dari API publik.
export { useForm, usePage };

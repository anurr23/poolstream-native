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
    document.querySelectorAll('.dropdown-menu').forEach((menu) => {
        if (except && menu === except) return;
        menu.classList.remove('show');
        // Layout me-render ulang setiap detik (jam), dan patch Vue dapat
        // menghapus class .show. Inline style tidak ikut di-patch sehingga
        // dropdown tetap bisa ditutup dengan andal.
        if (menu.style.display === 'block') menu.style.display = 'none';
        const toggle = menu.parentElement?.querySelector('[data-bs-toggle="dropdown"]');
        if (toggle) toggle.setAttribute('aria-expanded', 'false');
    });
}

/**
 * Bootstrap data-api dropdown tidak selalu aktif saat module dievaluasi lewat
 * Vite ESM. Delegasi global ini memastikan dropdown sidebar/header selalu
 * terbuka tepat satu kali, tanpa konflik dengan handler Bootstrap.
 */
function initDropdowns() {
    if (window.__poolstreamDropdownInit) return;
    window.__poolstreamDropdownInit = true;

    // Lepas atribut Bootstrap agar data-api tidak ikut men-toggle menu yang
    // sama (konflik handler menyebabkan menu langsung tertutup lagi).
    document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach((toggle) => {
        toggle.setAttribute('data-poolstream-toggle', '1');
        toggle.removeAttribute('data-bs-toggle');
    });

    document.addEventListener('click', (event) => {
        const toggle = event.target.closest?.('[data-poolstream-toggle="1"]');
        if (toggle) {
            event.preventDefault();
            event.stopImmediatePropagation();

            const root = toggle.closest('.dropdown') || toggle.parentElement;
            const menu = root?.querySelector('.dropdown-menu');
            if (!menu) return;

            const isOpen = menu.classList.contains('show') || menu.style.display === 'block';
            const willOpen = !isOpen;
            closeDropdowns(menu);
            menu.classList.toggle('show', willOpen);
            menu.style.display = willOpen ? 'block' : 'none';
            toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            return;
        }

        if (!event.target.closest?.('.dropdown')) closeDropdowns();
    }, true);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeDropdowns();
    });
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

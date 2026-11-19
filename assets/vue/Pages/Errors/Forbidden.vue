<script setup>
import { Head, Link } from '@/shims/inertia.js';
</script>

<template>
    <Head title="Akses Ditolak" />
    <div class="bb-auth-wrapper" style="min-height:100vh;">
        <div class="bb-auth-card text-center">
            <div class="mb-3" style="font-size:3rem;color:#ef4444;"><i class="bi bi-shield-lock-fill"></i></div>
            <h4 class="fw-bold mb-2">Akses Ditolak</h4>
            <p class="small text-secondary mb-4">Anda tidak memiliki izin untuk membuka halaman ini.</p>
            <Link href="index.php" class="bb-btn bb-btn--success w-100 py-3">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Menu Utama
            </Link>
        </div>
    </div>
</template>

<script setup>
import { onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    isDark: { type: Boolean, default: true },
    scrollProgress: { type: Number, default: 0 },
});

const canvasRef = ref(null);
const active = ref(false);

let cleanup = null;
let applyThemeFn = null;
let scrollTarget = 0;

watch(
    () => props.isDark,
    (dark) => {
        if (typeof applyThemeFn === 'function') applyThemeFn(dark);
    }
);

watch(
    () => props.scrollProgress,
    (val) => {
        scrollTarget = Number(val) || 0;
    },
    { immediate: true }
);

const AMBIENT_BALLS = [
    { num: 8, color: '#0f172a', stripe: false, x: -7.6, y: 2.8, z: -3.2, r: 1.05, depth: 1.15, speed: 0.42 },
    { num: 9, color: '#f59e0b', stripe: true, x: 7.9, y: 3.1, z: -4.6, r: 0.92, depth: 0.85, speed: 0.36 },
    { num: 6, color: '#10b981', stripe: false, x: -8.8, y: -2.4, z: -5.4, r: 0.88, depth: 0.75, speed: 0.32 },
    { num: 2, color: '#2563eb', stripe: false, x: 8.6, y: -2.9, z: -3.8, r: 0.96, depth: 1.05, speed: 0.38 },
    { num: 0, color: '#f8fafc', stripe: false, x: -4.8, y: -4.2, z: -6.8, r: 0.72, depth: 0.55, speed: 0.28 },
    { num: 4, color: '#7c3aed', stripe: false, x: 4.9, y: -4.5, z: -7.2, r: 0.76, depth: 0.5, speed: 0.31 },
    { num: 3, color: '#e11d48', stripe: false, x: 0.6, y: 4.9, z: -7.8, r: 0.68, depth: 0.45, speed: 0.26 },
];

const createAmbientBallTexture = (THREE, { num, color, stripe }) => {
    const canvas = document.createElement('canvas');
    canvas.width = 256;
    canvas.height = 128;
    const ctx = canvas.getContext('2d');

    if (num === 0) {
        ctx.fillStyle = '#f8fafc';
        ctx.fillRect(0, 0, 256, 128);
        ctx.fillStyle = '#ef4444';
        [
            [64, 64],
            [192, 64],
            [128, 28],
            [128, 100],
        ].forEach(([x, y]) => {
            ctx.beginPath();
            ctx.arc(x, y, 4.5, 0, Math.PI * 2);
            ctx.fill();
        });
    } else {
        if (stripe) {
            ctx.fillStyle = '#f8fafc';
            ctx.fillRect(0, 0, 256, 128);
            ctx.fillStyle = color;
            ctx.fillRect(0, 34, 256, 60);
        } else {
            ctx.fillStyle = color;
            ctx.fillRect(0, 0, 256, 128);
        }

        [64, 192].forEach((cx) => {
            ctx.fillStyle = '#fffbeb';
            ctx.beginPath();
            ctx.arc(cx, 64, 22, 0, Math.PI * 2);
            ctx.fill();

            ctx.fillStyle = '#0f172a';
            ctx.font = '800 22px system-ui, -apple-system, sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(String(num), cx, 65);
        });
    }

    const tex = new THREE.CanvasTexture(canvas);
    tex.colorSpace = THREE.SRGBColorSpace;
    return tex;
};

const createDustSprite = (THREE) => {
    const canvas = document.createElement('canvas');
    canvas.width = 64;
    canvas.height = 64;
    const ctx = canvas.getContext('2d');
    const grad = ctx.createRadialGradient(32, 32, 0, 32, 32, 32);
    grad.addColorStop(0, 'rgba(255,255,255,1)');
    grad.addColorStop(0.35, 'rgba(167,243,208,0.65)');
    grad.addColorStop(1, 'rgba(16,185,129,0)');
    ctx.fillStyle = grad;
    ctx.fillRect(0, 0, 64, 64);

    const tex = new THREE.CanvasTexture(canvas);
    tex.colorSpace = THREE.SRGBColorSpace;
    return tex;
};

onMounted(async () => {
    const reduceMotion =
        typeof window !== 'undefined' &&
        window.matchMedia &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (reduceMotion) return;

    const canvas = canvasRef.value;
    if (!canvas) return;

    try {
        const THREE = await import('three');

        const renderer = new THREE.WebGLRenderer({
            canvas,
            alpha: true,
            antialias: true,
            powerPreference: 'low-power',
        });
        renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.5));
        renderer.toneMapping = THREE.ACESFilmicToneMapping;
        renderer.toneMappingExposure = 1.1;

        const scene = new THREE.Scene();
        const camera = new THREE.PerspectiveCamera(44, window.innerWidth / Math.max(1, window.innerHeight), 0.1, 40);
        camera.position.set(0, 0, 10.5);

        const disposables = [];
        const track = (item) => {
            disposables.push(item);
            return item;
        };

        // ─── Lights ─────────────────────────────────────
        const hemi = new THREE.HemisphereLight(0xe2e8f0, 0x0f172a, 0.9);
        scene.add(hemi);

        const keyLight = new THREE.DirectionalLight(0xffffff, 1.8);
        keyLight.position.set(4, 7, 8);
        scene.add(keyLight);

        const rimEmerald = new THREE.PointLight(0x10b981, 22, 26, 1.4);
        rimEmerald.position.set(-9, 4, 2);
        scene.add(rimEmerald);

        const rimCyan = new THREE.PointLight(0x22d3ee, 18, 26, 1.4);
        rimCyan.position.set(9, -3, 2);
        scene.add(rimCyan);

        const rimIndigo = new THREE.PointLight(0x6366f1, 16, 26, 1.4);
        rimIndigo.position.set(2, 6, -4);
        scene.add(rimIndigo);

        // ─── Floating 3D Billiard Balls ─────────────────
        const sphereGeo = track(new THREE.SphereGeometry(1, 32, 24));
        const ballGroup = new THREE.Group();
        scene.add(ballGroup);

        const ballEntries = AMBIENT_BALLS.map((spec, idx) => {
            const tex = track(createAmbientBallTexture(THREE, spec));
            const mat = track(
                new THREE.MeshPhysicalMaterial({
                    map: tex,
                    roughness: 0.16,
                    metalness: 0.08,
                    clearcoat: 1.0,
                    clearcoatRoughness: 0.08,
                })
            );
            const mesh = new THREE.Mesh(sphereGeo, mat);
            mesh.scale.setScalar(spec.r);
            mesh.position.set(spec.x, spec.y, spec.z);
            mesh.rotation.set(idx * 0.8, idx * 1.1, idx * 0.4);
            ballGroup.add(mesh);

            return {
                mesh,
                spec,
                phase: idx * 1.17,
                rotX: (idx % 2 === 0 ? 1 : -1) * (0.14 + idx * 0.025),
                rotY: (idx % 3 === 0 ? -1 : 1) * (0.2 + idx * 0.02),
            };
        });

        // ─── Ambient Chalk Dust Particles ───────────────
        const dustCount = 85;
        const dustPositions = new Float32Array(dustCount * 3);
        const dustSpeeds = new Float32Array(dustCount);
        for (let i = 0; i < dustCount; i++) {
            dustPositions[i * 3] = (Math.random() - 0.5) * 24;
            dustPositions[i * 3 + 1] = (Math.random() - 0.5) * 14;
            dustPositions[i * 3 + 2] = -1.5 - Math.random() * 9;
            dustSpeeds[i] = 0.12 + Math.random() * 0.24;
        }
        const dustGeo = track(new THREE.BufferGeometry());
        dustGeo.setAttribute('position', new THREE.BufferAttribute(dustPositions, 3));
        const dustTex = track(createDustSprite(THREE));
        const dustMat = track(
            new THREE.PointsMaterial({
                map: dustTex,
                size: 0.16,
                transparent: true,
                opacity: 0.55,
                depthWrite: false,
                blending: THREE.AdditiveBlending,
            })
        );
        const dustPoints = new THREE.Points(dustGeo, dustMat);
        scene.add(dustPoints);

        applyThemeFn = (dark) => {
            hemi.intensity = dark ? 0.9 : 1.55;
            keyLight.intensity = dark ? 1.8 : 2.1;
            rimEmerald.intensity = dark ? 22 : 10;
            rimCyan.intensity = dark ? 18 : 8;
            rimIndigo.intensity = dark ? 16 : 7;
            dustMat.opacity = dark ? 0.55 : 0.24;
            dustMat.blending = dark ? THREE.AdditiveBlending : THREE.NormalBlending;
            dustMat.needsUpdate = true;
        };
        applyThemeFn(props.isDark);

        // ─── Responsive & Pointer Listeners ─────────────
        let aspectScale = 1;
        const onResize = () => {
            const w = window.innerWidth;
            const h = Math.max(1, window.innerHeight);
            camera.aspect = w / h;
            camera.updateProjectionMatrix();
            renderer.setSize(w, h, false);
            // Spread balls slightly inward on narrow mobile screens so edge balls still peek in cleanly
            aspectScale = Math.max(0.56, Math.min(1.12, w / 1280));
        };
        onResize();
        window.addEventListener('resize', onResize, { passive: true });

        let pointerX = 0;
        let pointerY = 0;
        let smoothX = 0;
        let smoothY = 0;
        let smoothScroll = scrollTarget;

        const onPointerMove = (e) => {
            pointerX = (e.clientX / Math.max(1, window.innerWidth) - 0.5) * 2;
            pointerY = (e.clientY / Math.max(1, window.innerHeight) - 0.5) * 2;
        };
        window.addEventListener('pointermove', onPointerMove, { passive: true });

        // ─── Render Loop ────────────────────────────────
        let rafId = 0;
        let lastTime = performance.now();
        let elapsed = 0;

        const tick = (now) => {
            rafId = requestAnimationFrame(tick);
            if (document.hidden) {
                lastTime = now;
                return;
            }

            const dt = Math.min(0.05, Math.max(0.001, (now - lastTime) / 1000));
            lastTime = now;
            elapsed += dt;

            const lerp = Math.min(1, dt * 4.5);
            smoothX += (pointerX - smoothX) * lerp;
            smoothY += (pointerY - smoothY) * lerp;
            smoothScroll += (scrollTarget - smoothScroll) * Math.min(1, dt * 6);

            // Subtle camera shift on pointer
            camera.position.x = smoothX * 0.55;
            camera.position.y = -smoothY * 0.35;
            camera.lookAt(0, 0, -3);

            // Animate floating balls
            for (let i = 0; i < ballEntries.length; i++) {
                const entry = ballEntries[i];
                const { mesh, spec, phase, rotX, rotY } = entry;
                const bobY = Math.sin(elapsed * spec.speed + phase) * 0.48;
                const driftX = Math.cos(elapsed * (spec.speed * 0.7) + phase) * 0.3;
                const scrollShiftY = (smoothScroll - 0.5) * 5.2 * spec.depth;

                mesh.position.x = spec.x * aspectScale + driftX + smoothX * 0.45 * spec.depth;
                mesh.position.y = spec.y + bobY + scrollShiftY - smoothY * 0.3 * spec.depth;
                mesh.position.z = spec.z;

                mesh.rotation.x += rotX * dt;
                mesh.rotation.y += rotY * dt;
            }

            // Animate chalk dust upward drift
            const posAttr = dustGeo.getAttribute('position');
            for (let i = 0; i < dustCount; i++) {
                let y = posAttr.getY(i) + dustSpeeds[i] * dt;
                if (y > 7.2) y = -7.2;
                posAttr.setY(i, y);
            }
            posAttr.needsUpdate = true;
            dustPoints.position.x = -smoothX * 0.3;
            dustPoints.position.y = (smoothScroll - 0.5) * 1.8;

            renderer.render(scene, camera);
        };

        active.value = true;
        rafId = requestAnimationFrame(tick);

        cleanup = () => {
            cancelAnimationFrame(rafId);
            window.removeEventListener('resize', onResize);
            window.removeEventListener('pointermove', onPointerMove);
            disposables.forEach((d) => d?.dispose?.());
            renderer.dispose();
        };
    } catch (error) {
        console.warn('Ambient 3D dinonaktifkan:', error);
    }
});

onUnmounted(() => {
    cleanup?.();
    cleanup = null;
    applyThemeFn = null;
});
</script>

<template>
    <canvas
        ref="canvasRef"
        class="bbp-ambient-3d"
        :class="{ 'is-active': active, 'is-light': !isDark }"
        aria-hidden="true"
    ></canvas>
</template>

<style scoped>
.bbp-ambient-3d {
    position: fixed;
    inset: 0;
    width: 100vw;
    height: 100vh;
    z-index: 0;
    pointer-events: none;
    opacity: 0;
    transition: opacity 0.8s ease;
}

.bbp-ambient-3d.is-active {
    opacity: 0.72;
}

.bbp-ambient-3d.is-active.is-light {
    opacity: 0.4;
}
</style>

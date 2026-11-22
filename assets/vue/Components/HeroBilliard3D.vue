<script setup>
import { onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    isDark: { type: Boolean, default: true },
    scrollProgress: { type: Number, default: 0 },
    compact: { type: Boolean, default: false },
    showLamp: { type: Boolean, default: false },
});

const hostRef = ref(null);
const canvasRef = ref(null);
const ready = ref(false);
const fallback = ref(false);
const isDragging = ref(false);
const phaseLabel = ref('Siap Break');

let cleanup = null;
let triggerBreakFn = null;
let applyThemeFn = null;
let scrollTarget = 0;

watch(
    () => props.scrollProgress,
    (val) => {
        scrollTarget = Number(val) || 0;
    },
    { immediate: true }
);

const BALL_SPECS = [
    { num: 1, color: '#f59e0b', stripe: false },
    { num: 2, color: '#2563eb', stripe: false },
    { num: 3, color: '#dc2626', stripe: false },
    { num: 4, color: '#7c3aed', stripe: false },
    { num: 8, color: '#111827', stripe: false },
    { num: 5, color: '#ea580c', stripe: false },
    { num: 6, color: '#16a34a', stripe: false },
    { num: 7, color: '#991b1b', stripe: false },
    { num: 9, color: '#eab308', stripe: true },
    { num: 10, color: '#0284c7', stripe: true },
];

const createFeltTexture = (THREE) => {
    const canvas = document.createElement('canvas');
    canvas.width = 512;
    canvas.height = 256;
    const ctx = canvas.getContext('2d');

    const grad = ctx.createRadialGradient(256, 128, 24, 256, 128, 290);
    grad.addColorStop(0, '#16a06f');
    grad.addColorStop(0.55, '#0f7d57');
    grad.addColorStop(1, '#094f37');
    ctx.fillStyle = grad;
    ctx.fillRect(0, 0, 512, 256);

    // Subtle felt grain
    ctx.fillStyle = 'rgba(255,255,255,0.03)';
    for (let i = 0; i < 2400; i++) {
        const x = Math.random() * 512;
        const y = Math.random() * 256;
        ctx.fillRect(x, y, 1.4, 1.4);
    }

    // Baulk line & head/foot spots
    ctx.strokeStyle = 'rgba(226, 248, 236, 0.22)';
    ctx.lineWidth = 1.6;
    ctx.beginPath();
    ctx.moveTo(132, 16);
    ctx.lineTo(132, 240);
    ctx.stroke();

    ctx.fillStyle = 'rgba(226, 248, 236, 0.32)';
    ctx.beginPath();
    ctx.arc(132, 128, 3.5, 0, Math.PI * 2);
    ctx.arc(368, 128, 3.5, 0, Math.PI * 2);
    ctx.fill();

    const tex = new THREE.CanvasTexture(canvas);
    tex.colorSpace = THREE.SRGBColorSpace;
    tex.anisotropy = 4;
    return tex;
};

const createBallTexture = (THREE, { num, color, stripe }) => {
    const canvas = document.createElement('canvas');
    canvas.width = 256;
    canvas.height = 128;
    const ctx = canvas.getContext('2d');

    if (stripe) {
        ctx.fillStyle = '#f8fafc';
        ctx.fillRect(0, 0, 256, 128);
        ctx.fillStyle = color;
        ctx.fillRect(0, 34, 256, 60);
    } else {
        ctx.fillStyle = color;
        ctx.fillRect(0, 0, 256, 128);
    }

    // Two opposing number medallions so numbers are frequently visible while rolling
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

    const tex = new THREE.CanvasTexture(canvas);
    tex.colorSpace = THREE.SRGBColorSpace;
    return tex;
};

const createCueBallTexture = (THREE) => {
    const canvas = document.createElement('canvas');
    canvas.width = 256;
    canvas.height = 128;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#f8fafc';
    ctx.fillRect(0, 0, 256, 128);

    // Pro-cup subtle red dots so rolling spin is visible
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

    const tex = new THREE.CanvasTexture(canvas);
    tex.colorSpace = THREE.SRGBColorSpace;
    return tex;
};

const createChalkSprite = (THREE, innerColor = 'rgba(226,248,236,1)', midColor = 'rgba(110,231,183,0.55)') => {
    const canvas = document.createElement('canvas');
    canvas.width = 64;
    canvas.height = 64;
    const ctx = canvas.getContext('2d');
    const grad = ctx.createRadialGradient(32, 32, 0, 32, 32, 32);
    grad.addColorStop(0, innerColor);
    grad.addColorStop(0.4, midColor);
    grad.addColorStop(1, 'rgba(16,185,129,0)');
    ctx.fillStyle = grad;
    ctx.fillRect(0, 0, 64, 64);

    const tex = new THREE.CanvasTexture(canvas);
    tex.colorSpace = THREE.SRGBColorSpace;
    return tex;
};

const triggerBreak = () => {
    if (typeof triggerBreakFn === 'function') {
        triggerBreakFn();
    }
};

watch(
    () => props.isDark,
    (dark) => {
        if (typeof applyThemeFn === 'function') {
            applyThemeFn(dark);
        }
    }
);

onMounted(async () => {
    const reduceMotion =
        typeof window !== 'undefined' &&
        window.matchMedia &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (reduceMotion) {
        fallback.value = true;
        return;
    }

    const host = hostRef.value;
    const canvas = canvasRef.value;
    if (!host || !canvas) {
        fallback.value = true;
        return;
    }

    try {
        const THREE = await import('three');

        const renderer = new THREE.WebGLRenderer({
            canvas,
            alpha: true,
            antialias: true,
            powerPreference: 'high-performance',
        });
        renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
        renderer.shadowMap.enabled = true;
        renderer.shadowMap.type = THREE.PCFSoftShadowMap;
        renderer.toneMapping = THREE.ACESFilmicToneMapping;
        renderer.toneMappingExposure = 1.15;

        const scene = new THREE.Scene();

        const camera = new THREE.PerspectiveCamera(34, 560 / 440, 0.1, 60);
        const baseCam = new THREE.Vector3(0, 5.9, 6.2);
        camera.position.copy(baseCam);
        camera.lookAt(0, 0.15, 0.05);

        // ─── Lights ─────────────────────────────────────
        const hemiLight = new THREE.HemisphereLight(0xd1fae5, 0x0f172a, 0.85);
        scene.add(hemiLight);

        const spotLight = new THREE.SpotLight(0xfff7ed, 42, 22, Math.PI / 3.4, 0.48, 1.35);
        spotLight.position.set(0.3, 6.8, 1.2);
        spotLight.castShadow = true;
        spotLight.shadow.mapSize.set(1024, 1024);
        spotLight.shadow.bias = -0.0005;
        spotLight.shadow.radius = 4;
        scene.add(spotLight);
        scene.add(spotLight.target);

        const rimEmerald = new THREE.PointLight(0x10b981, 7.5, 14, 1.6);
        rimEmerald.position.set(-4.2, 2.5, -2.6);
        scene.add(rimEmerald);

        const rimCyan = new THREE.PointLight(0x22d3ee, 6.0, 14, 1.6);
        rimCyan.position.set(4.2, 2.2, 2.6);
        scene.add(rimCyan);

        // ─── Table Geometry ─────────────────────────────
        const disposables = [];
        const track = (item) => {
            disposables.push(item);
            return item;
        };

        const tableGroup = new THREE.Group();
        scene.add(tableGroup);

        // ─── Overhead Spotlight Beam + Hanging Billiard Lamp + Chalk Dust ─
        const beamMat = track(
            new THREE.MeshBasicMaterial({
                color: 0xa7f3d0,
                transparent: true,
                opacity: props.showLamp ? 0.085 : 0.045,
                depthWrite: false,
                side: THREE.DoubleSide,
                blending: THREE.AdditiveBlending,
            })
        );
        const beamCone = new THREE.Mesh(
            track(new THREE.ConeGeometry(props.showLamp ? 3.05 : 2.75, props.showLamp ? 2.45 : 3.6, 32, 1, true)),
            beamMat
        );
        beamCone.position.set(0, props.showLamp ? 1.25 : 1.85, 0);
        tableGroup.add(beamCone);

        let lampGroup = null;
        let lampBulbMat = null;
        let lampShadeGlowMat = null;
        if (props.showLamp) {
            lampGroup = new THREE.Group();
            lampGroup.position.set(0, 2.38, 0);

            const metalMat = track(
                new THREE.MeshStandardMaterial({
                    color: 0x1e293b,
                    roughness: 0.28,
                    metalness: 0.85,
                })
            );
            const brassMat = track(
                new THREE.MeshStandardMaterial({
                    color: 0xf59e0b,
                    roughness: 0.25,
                    metalness: 0.82,
                })
            );
            lampShadeGlowMat = track(
                new THREE.MeshPhysicalMaterial({
                    color: 0x059669,
                    emissive: 0x10b981,
                    emissiveIntensity: 0.35,
                    roughness: 0.2,
                    metalness: 0.15,
                    clearcoat: 0.9,
                    side: THREE.DoubleSide,
                })
            );
            lampBulbMat = track(
                new THREE.MeshBasicMaterial({
                    color: 0xfffbeb,
                })
            );

            // Horizontal bar
            const bar = new THREE.Mesh(track(new THREE.CylinderGeometry(0.032, 0.032, 3.25, 16)), metalMat);
            bar.rotation.z = Math.PI / 2;
            bar.position.y = 0.24;
            lampGroup.add(bar);

            // Two ceiling suspension rods
            const rodGeo = track(new THREE.CylinderGeometry(0.016, 0.016, 1.4, 12));
            [-0.95, 0.95].forEach((rx) => {
                const rod = new THREE.Mesh(rodGeo, metalMat);
                rod.position.set(rx, 0.94, 0);
                lampGroup.add(rod);
            });

            // 3 iconic emerald billiard lamp shades + warm glowing bulbs
            const shadeGeo = track(new THREE.ConeGeometry(0.42, 0.28, 28, 1, true));
            const capGeo = track(new THREE.CylinderGeometry(0.08, 0.11, 0.08, 18));
            const rimGeo = track(new THREE.TorusGeometry(0.42, 0.015, 10, 28));
            const bulbGeo = track(new THREE.SphereGeometry(0.095, 16, 12));

            [-1.32, 0, 1.32].forEach((sx) => {
                const cap = new THREE.Mesh(capGeo, brassMat);
                cap.position.set(sx, 0.17, 0);
                lampGroup.add(cap);

                const shade = new THREE.Mesh(shadeGeo, lampShadeGlowMat);
                shade.position.set(sx, 0.02, 0);
                lampGroup.add(shade);

                const rim = new THREE.Mesh(rimGeo, brassMat);
                rim.rotation.x = Math.PI / 2;
                rim.position.set(sx, -0.12, 0);
                lampGroup.add(rim);

                const bulb = new THREE.Mesh(bulbGeo, lampBulbMat);
                bulb.position.set(sx, -0.05, 0);
                lampGroup.add(bulb);
            });

            tableGroup.add(lampGroup);
        }

        const tableDustCount = 58;
        const tableDustPos = new Float32Array(tableDustCount * 3);
        const tableDustMeta = [];
        for (let i = 0; i < tableDustCount; i++) {
            const x = (Math.random() - 0.5) * 5.0;
            const y = 0.22 + Math.random() * 2.15;
            const z = (Math.random() - 0.5) * 2.5;
            tableDustPos[i * 3] = x;
            tableDustPos[i * 3 + 1] = y;
            tableDustPos[i * 3 + 2] = z;
            tableDustMeta.push({
                baseX: x,
                baseZ: z,
                speedY: 0.06 + Math.random() * 0.11,
                sway: 0.08 + Math.random() * 0.14,
                phase: Math.random() * Math.PI * 2,
            });
        }
        const tableDustGeo = track(new THREE.BufferGeometry());
        tableDustGeo.setAttribute('position', new THREE.BufferAttribute(tableDustPos, 3));
        const tableDustTex = track(createChalkSprite(THREE));
        const tableDustMat = track(
            new THREE.PointsMaterial({
                map: tableDustTex,
                size: 0.13,
                transparent: true,
                opacity: 0.65,
                depthWrite: false,
                blending: THREE.AdditiveBlending,
            })
        );
        const tableDustPoints = new THREE.Points(tableDustGeo, tableDustMat);
        tableGroup.add(tableDustPoints);

        // ─── Chalk Puff Burst on Strike & Rack Break Impact ─────────
        const puffCount = 26;
        const puffPos = new Float32Array(puffCount * 3);
        const puffVel = new Float32Array(puffCount * 3);
        let puffLife = 0;
        let rackBreakSparked = false;

        const puffGeo = track(new THREE.BufferGeometry());
        puffGeo.setAttribute('position', new THREE.BufferAttribute(puffPos, 3));
        const puffTex = track(createChalkSprite(THREE, 'rgba(240,249,255,1)', 'rgba(56,189,248,0.65)'));
        const puffMat = track(
            new THREE.PointsMaterial({
                map: puffTex,
                size: 0.19,
                transparent: true,
                opacity: 0,
                depthWrite: false,
                blending: THREE.AdditiveBlending,
            })
        );
        const puffPoints = new THREE.Points(puffGeo, puffMat);
        tableGroup.add(puffPoints);

        const spawnChalkPuff = (ox, oy, oz, spread = 1.0) => {
            const attr = puffGeo.getAttribute('position');
            for (let i = 0; i < puffCount; i++) {
                attr.setXYZ(i, ox, oy, oz);
                const angle = Math.random() * Math.PI * 2;
                const spd = (0.45 + Math.random() * 1.45) * spread;
                puffVel[i * 3] = Math.cos(angle) * spd;
                puffVel[i * 3 + 1] = (0.35 + Math.random() * 1.1) * spread;
                puffVel[i * 3 + 2] = Math.sin(angle) * spd;
            }
            attr.needsUpdate = true;
            puffLife = 1.0;
        };

        applyThemeFn = (dark) => {
            hemiLight.intensity = dark ? 0.85 : 1.35;
            spotLight.intensity = dark ? (props.showLamp ? 52 : 42) : (props.showLamp ? 56 : 48);
            rimEmerald.intensity = dark ? 7.5 : 4.2;
            rimCyan.intensity = dark ? 6.0 : 3.5;
            renderer.toneMappingExposure = dark ? 1.15 : 1.24;
            beamMat.opacity = dark ? (props.showLamp ? 0.085 : 0.045) : (props.showLamp ? 0.032 : 0.018);
            if (lampShadeGlowMat) {
                lampShadeGlowMat.emissiveIntensity = dark ? 0.42 : 0.18;
            }
            tableDustMat.opacity = dark ? 0.65 : 0.28;
            tableDustMat.blending = dark ? THREE.AdditiveBlending : THREE.NormalBlending;
            tableDustMat.needsUpdate = true;
            puffMat.blending = dark ? THREE.AdditiveBlending : THREE.NormalBlending;
            puffMat.needsUpdate = true;
        };
        applyThemeFn(props.isDark);

        const playW = 5.2;
        const playD = 2.6;
        const halfW = playW / 2;
        const halfD = playD / 2;
        const ballR = 0.125;

        // Soft floor shadow catcher
        const shadowPlane = new THREE.Mesh(
            track(new THREE.PlaneGeometry(8.5, 5.2)),
            track(new THREE.ShadowMaterial({ opacity: 0.34 }))
        );
        shadowPlane.rotation.x = -Math.PI / 2;
        shadowPlane.position.y = -0.58;
        shadowPlane.receiveShadow = true;
        tableGroup.add(shadowPlane);

        // Under-body cabinet
        const cabinetMat = track(
            new THREE.MeshStandardMaterial({
                color: 0x0f172a,
                roughness: 0.55,
                metalness: 0.35,
            })
        );
        const cabinet = new THREE.Mesh(track(new THREE.BoxGeometry(playW + 0.52, 0.36, playD + 0.52)), cabinetMat);
        cabinet.position.y = -0.22;
        cabinet.castShadow = true;
        cabinet.receiveShadow = true;
        tableGroup.add(cabinet);

        // Sleek tapered legs
        const legGeo = track(new THREE.CylinderGeometry(0.11, 0.08, 0.42, 16));
        [
            [-2.25, -0.45, -1.05],
            [2.25, -0.45, -1.05],
            [-2.25, -0.45, 1.05],
            [2.25, -0.45, 1.05],
        ].forEach(([lx, ly, lz]) => {
            const leg = new THREE.Mesh(legGeo, cabinetMat);
            leg.position.set(lx, ly, lz);
            leg.castShadow = true;
            tableGroup.add(leg);
        });

        // Playing felt
        const feltTex = track(createFeltTexture(THREE));
        const feltMat = track(
            new THREE.MeshStandardMaterial({
                map: feltTex,
                roughness: 0.88,
                metalness: 0.02,
            })
        );
        const felt = new THREE.Mesh(track(new THREE.BoxGeometry(playW, 0.08, playD)), feltMat);
        felt.position.y = -0.04;
        felt.receiveShadow = true;
        tableGroup.add(felt);

        // Outer slate rails
        const railMat = track(
            new THREE.MeshPhysicalMaterial({
                color: 0x1e293b,
                roughness: 0.32,
                metalness: 0.42,
                clearcoat: 0.65,
                clearcoatRoughness: 0.22,
            })
        );
        const cushionMat = track(
            new THREE.MeshStandardMaterial({
                color: 0x0a5d40,
                roughness: 0.82,
                metalness: 0.04,
            })
        );
        const railH = 0.22;
        const railT = 0.3;

        // Top & bottom outer rails
        const longRailGeo = track(new THREE.BoxGeometry(playW + railT * 2, railH, railT));
        [-1, 1].forEach((side) => {
            const rail = new THREE.Mesh(longRailGeo, railMat);
            rail.position.set(0, railH / 2 - 0.04, side * (halfD + railT / 2));
            rail.castShadow = true;
            rail.receiveShadow = true;
            tableGroup.add(rail);
        });

        // Left & right outer rails
        const shortRailGeo = track(new THREE.BoxGeometry(railT, railH, playD));
        [-1, 1].forEach((side) => {
            const rail = new THREE.Mesh(shortRailGeo, railMat);
            rail.position.set(side * (halfW + railT / 2), railH / 2 - 0.04, 0);
            rail.castShadow = true;
            rail.receiveShadow = true;
            tableGroup.add(rail);
        });

        // Inner green cushions (split for pockets)
        const cushLongGeo = track(new THREE.BoxGeometry(2.14, 0.14, 0.1));
        [-1, 1].forEach((side) => {
            [-1.24, 1.24].forEach((cx) => {
                const c = new THREE.Mesh(cushLongGeo, cushionMat);
                c.position.set(cx, 0.06, side * (halfD - 0.01));
                c.castShadow = true;
                c.receiveShadow = true;
                tableGroup.add(c);
            });
        });
        const cushShortGeo = track(new THREE.BoxGeometry(0.1, 0.14, 2.08));
        [-1, 1].forEach((side) => {
            const c = new THREE.Mesh(cushShortGeo, cushionMat);
            c.position.set(side * (halfW - 0.01), 0.06, 0);
            c.castShadow = true;
            c.receiveShadow = true;
            tableGroup.add(c);
        });

        // Subtle chrome trim & diamond sights on rails
        const sightMat = track(
            new THREE.MeshStandardMaterial({
                color: 0xa7f3d0,
                emissive: 0x10b981,
                emissiveIntensity: 0.4,
                roughness: 0.25,
                metalness: 0.7,
            })
        );
        const sightGeo = track(new THREE.BoxGeometry(0.055, 0.012, 0.055));
        [-1.95, -1.3, -0.65, 0.65, 1.3, 1.95].forEach((sx) => {
            [-1, 1].forEach((side) => {
                const s = new THREE.Mesh(sightGeo, sightMat);
                s.rotation.y = Math.PI / 4;
                s.position.set(sx, railH - 0.032, side * (halfD + railT * 0.52));
                tableGroup.add(s);
            });
        });
        [-0.65, 0, 0.65].forEach((sz) => {
            [-1, 1].forEach((side) => {
                const s = new THREE.Mesh(sightGeo, sightMat);
                s.rotation.y = Math.PI / 4;
                s.position.set(side * (halfW + railT * 0.52), railH - 0.032, sz);
                tableGroup.add(s);
            });
        });

        // 6 Pockets (dark hole + metallic rim)
        const pocketCoords = [
            [-halfW + 0.04, -halfD + 0.04],
            [0, -halfD - 0.02],
            [halfW - 0.04, -halfD + 0.04],
            [-halfW + 0.04, halfD - 0.04],
            [0, halfD + 0.02],
            [halfW - 0.04, halfD - 0.04],
        ];
        const pocketHoleGeo = track(new THREE.CylinderGeometry(0.185, 0.17, 0.14, 24));
        const pocketHoleMat = track(new THREE.MeshBasicMaterial({ color: 0x020617 }));
        const pocketRimGeo = track(new THREE.TorusGeometry(0.195, 0.022, 12, 28));
        const pocketRimMat = track(
            new THREE.MeshStandardMaterial({
                color: 0x475569,
                roughness: 0.3,
                metalness: 0.85,
            })
        );

        pocketCoords.forEach(([px, pz]) => {
            const hole = new THREE.Mesh(pocketHoleGeo, pocketHoleMat);
            hole.position.set(px, 0.01, pz);
            tableGroup.add(hole);

            const rim = new THREE.Mesh(pocketRimGeo, pocketRimMat);
            rim.rotation.x = Math.PI / 2;
            rim.position.set(px, 0.09, pz);
            tableGroup.add(rim);
        });

        // Subtle emerald glow ring around cue ball while aiming
        const aimRing = new THREE.Mesh(
            track(new THREE.RingGeometry(0.19, 0.225, 40)),
            track(
                new THREE.MeshBasicMaterial({
                    color: 0x6ee7b7,
                    transparent: true,
                    opacity: 0.55,
                    side: THREE.DoubleSide,
                })
            )
        );
        aimRing.rotation.x = -Math.PI / 2;
        aimRing.position.set(-1.35, 0.008, 0);
        tableGroup.add(aimRing);

        // Aiming guide line on felt
        const aimLineGeo = track(new THREE.PlaneGeometry(2.1, 0.022));
        const aimLineMat = track(
            new THREE.MeshBasicMaterial({
                color: 0xa7f3d0,
                transparent: true,
                opacity: 0.35,
            })
        );
        const aimLine = new THREE.Mesh(aimLineGeo, aimLineMat);
        aimLine.rotation.x = -Math.PI / 2;
        aimLine.position.set(-0.2, 0.007, 0);
        tableGroup.add(aimLine);

        // ─── Cue Stick ──────────────────────────────────
        const cueGroup = new THREE.Group();
        const shaftMat = track(
            new THREE.MeshStandardMaterial({
                color: 0xe6caa0,
                roughness: 0.35,
                metalness: 0.08,
            })
        );
        const buttMat = track(
            new THREE.MeshPhysicalMaterial({
                color: 0x0f172a,
                roughness: 0.25,
                metalness: 0.3,
                clearcoat: 0.7,
            })
        );
        const tipMat = track(new THREE.MeshStandardMaterial({ color: 0x38bdf8, roughness: 0.7 }));
        const ferruleMat = track(new THREE.MeshStandardMaterial({ color: 0xf8fafc, roughness: 0.3 }));

        const shaft = new THREE.Mesh(track(new THREE.CylinderGeometry(0.017, 0.027, 1.5, 16)), shaftMat);
        shaft.rotation.z = Math.PI / 2;
        shaft.position.x = -0.82;
        shaft.castShadow = true;
        cueGroup.add(shaft);

        const butt = new THREE.Mesh(track(new THREE.CylinderGeometry(0.027, 0.036, 1.0, 16)), buttMat);
        butt.rotation.z = Math.PI / 2;
        butt.position.x = -2.07;
        butt.castShadow = true;
        cueGroup.add(butt);

        const ferrule = new THREE.Mesh(track(new THREE.CylinderGeometry(0.0165, 0.017, 0.05, 14)), ferruleMat);
        ferrule.rotation.z = Math.PI / 2;
        ferrule.position.x = -0.045;
        cueGroup.add(ferrule);

        const tip = new THREE.Mesh(track(new THREE.CylinderGeometry(0.0155, 0.0165, 0.028, 14)), tipMat);
        tip.rotation.z = Math.PI / 2;
        tip.position.x = -0.008;
        cueGroup.add(tip);

        cueGroup.rotation.z = -0.095;
        cueGroup.position.set(-1.55, ballR + 0.04, 0);
        tableGroup.add(cueGroup);

        // ─── Balls ──────────────────────────────────────
        const ballGeo = track(new THREE.SphereGeometry(ballR, 32, 24));
        const balls = [];

        const makeBall = (tex, homeX, homeZ, isCue = false) => {
            const mat = track(
                new THREE.MeshPhysicalMaterial({
                    map: tex,
                    roughness: 0.14,
                    metalness: 0.04,
                    clearcoat: 1.0,
                    clearcoatRoughness: 0.06,
                })
            );
            const mesh = new THREE.Mesh(ballGeo, mat);
            mesh.castShadow = true;
            mesh.receiveShadow = true;
            mesh.position.set(homeX, ballR, homeZ);
            tableGroup.add(mesh);

            return {
                mesh,
                isCue,
                homeX,
                homeZ,
                x: homeX,
                z: homeZ,
                vx: 0,
                vz: 0,
                pocketed: false,
                pocketDrop: 0,
                fromX: homeX,
                fromZ: homeZ,
            };
        };

        // Cue ball
        const cueBallTex = track(createCueBallTexture(THREE));
        const cueBall = makeBall(cueBallTex, -1.35, 0, true);
        balls.push(cueBall);

        // 10-ball triangle rack (4 rows: 1, 2, 3, 4)
        const rackStartX = 0.88;
        const rowDx = ballR * 2.02 * Math.cos(Math.PI / 6);
        const colDz = ballR * 2.04;
        let specIdx = 0;
        for (let row = 0; row < 4; row++) {
            for (let col = 0; col <= row; col++) {
                const spec = BALL_SPECS[specIdx++];
                if (!spec) continue;
                const tex = track(createBallTexture(THREE, spec));
                const bx = rackStartX + row * rowDx;
                const bz = (col - row / 2) * colDz;
                balls.push(makeBall(tex, bx, bz, false));
            }
        }

        // ─── Physics & Animation State Machine ──────────
        const boundX = halfW - 0.06 - ballR;
        const boundZ = halfD - 0.06 - ballR;
        const pocketRadiusSq = 0.21 * 0.21;
        const upAxis = new THREE.Vector3(0, 1, 0);
        const tmpAxis = new THREE.Vector3();
        const tmpQuat = new THREE.Quaternion();

        let phase = 'aim'; // 'aim' | 'strike' | 'play' | 'reset'
        let phaseTime = 0;
        let breakAngle = 0;

        const resetPositionsImmediate = () => {
            balls.forEach((b) => {
                b.x = b.homeX;
                b.z = b.homeZ;
                b.vx = 0;
                b.vz = 0;
                b.pocketed = false;
                b.pocketDrop = 0;
                b.mesh.visible = true;
                b.mesh.scale.setScalar(1);
                b.mesh.position.set(b.homeX, ballR, b.homeZ);
            });
            breakAngle = (Math.random() - 0.5) * 0.055;
            phase = 'aim';
            phaseTime = 0;
            phaseLabel.value = 'Siap Break';
        };

        triggerBreakFn = () => {
            if (phase === 'play' || phase === 'reset') {
                resetPositionsImmediate();
            }
            phase = 'strike';
            phaseTime = 0;
            phaseLabel.value = 'Break Shot!';
        };

        const stepPhysics = (dt) => {
            const substeps = 3;
            const subDt = dt / substeps;
            const friction = Math.pow(0.46, subDt);

            for (let s = 0; s < substeps; s++) {
                // Integrate positions & wall bounces
                for (let i = 0; i < balls.length; i++) {
                    const b = balls[i];
                    if (b.pocketed) continue;

                    b.x += b.vx * subDt;
                    b.z += b.vz * subDt;
                    b.vx *= friction;
                    b.vz *= friction;

                    if (Math.hypot(b.vx, b.vz) < 0.03) {
                        b.vx = 0;
                        b.vz = 0;
                    }

                    // Pocket detection (don't pocket cue ball so break looks clean)
                    if (!b.isCue) {
                        let fell = false;
                        for (let p = 0; p < pocketCoords.length; p++) {
                            const dx = b.x - pocketCoords[p][0];
                            const dz = b.z - pocketCoords[p][1];
                            if (dx * dx + dz * dz < pocketRadiusSq) {
                                b.pocketed = true;
                                b.vx = 0;
                                b.vz = 0;
                                fell = true;
                                break;
                            }
                        }
                        if (fell) continue;
                    }

                    // Cushion bounce
                    if (b.x > boundX) {
                        b.x = boundX;
                        b.vx = -Math.abs(b.vx) * 0.84;
                    } else if (b.x < -boundX) {
                        b.x = -boundX;
                        b.vx = Math.abs(b.vx) * 0.84;
                    }
                    if (b.z > boundZ) {
                        b.z = boundZ;
                        b.vz = -Math.abs(b.vz) * 0.84;
                    } else if (b.z < -boundZ) {
                        b.z = -boundZ;
                        b.vz = Math.abs(b.vz) * 0.84;
                    }
                }

                // Pairwise elastic ball collisions
                const minDist = ballR * 2;
                const minDistSq = minDist * minDist;
                for (let i = 0; i < balls.length; i++) {
                    const a = balls[i];
                    if (a.pocketed) continue;
                    for (let j = i + 1; j < balls.length; j++) {
                        const b = balls[j];
                        if (b.pocketed) continue;
                        const dx = b.x - a.x;
                        const dz = b.z - a.z;
                        const d2 = dx * dx + dz * dz;
                        if (d2 > 0 && d2 < minDistSq) {
                            const d = Math.sqrt(d2);
                            const nx = dx / d;
                            const nz = dz / d;
                            const overlap = (minDist - d) * 0.502;
                            a.x -= nx * overlap;
                            a.z -= nz * overlap;
                            b.x += nx * overlap;
                            b.z += nz * overlap;

                            const rvx = b.vx - a.vx;
                            const rvz = b.vz - a.vz;
                            const velAlongNormal = rvx * nx + rvz * nz;
                            if (velAlongNormal < 0) {
                                const restitution = 0.94;
                                const imp = (-(1 + restitution) * velAlongNormal) / 2;
                                a.vx -= imp * nx;
                                a.vz -= imp * nz;
                                b.vx += imp * nx;
                                b.vz += imp * nz;

                                if (!rackBreakSparked && (a.isCue || b.isCue) && Math.abs(velAlongNormal) > 3.5) {
                                    rackBreakSparked = true;
                                    spawnChalkPuff((a.x + b.x) * 0.5, ballR + 0.05, (a.z + b.z) * 0.5, 1.35);
                                }
                            }
                        }
                    }
                }
            }

            // Apply transforms & rolling spin
            let anyMoving = false;
            for (let i = 0; i < balls.length; i++) {
                const b = balls[i];
                if (b.pocketed) {
                    b.pocketDrop = Math.min(1, b.pocketDrop + dt * 4.5);
                    b.mesh.position.set(b.x, ballR - b.pocketDrop * 0.24, b.z);
                    const sc = Math.max(0.01, 1 - b.pocketDrop * 0.85);
                    b.mesh.scale.setScalar(sc);
                    if (b.pocketDrop >= 1) b.mesh.visible = false;
                    continue;
                }
                const speed = Math.hypot(b.vx, b.vz);
                if (speed > 0.04) {
                    anyMoving = true;
                    tmpAxis.set(b.vz, 0, -b.vx).normalize();
                    const angle = (speed * dt) / ballR;
                    tmpQuat.setFromAxisAngle(tmpAxis, angle);
                    b.mesh.quaternion.premultiply(tmpQuat);
                }
                b.mesh.position.set(b.x, ballR, b.z);
            }
            return anyMoving;
        };

        // ─── Pointer Parallax + Drag Orbit ──────────────
        let targetYaw = props.showLamp ? 0.24 : 0.22;
        let targetPitch = props.showLamp ? 0.44 : 0.68;
        let currentYaw = targetYaw;
        let currentPitch = targetPitch;
        let pointerX = 0;
        let pointerY = 0;
        let dragging = false;
        let draggedDist = 0;
        let lastClientX = 0;
        let lastClientY = 0;

        const onPointerDown = (e) => {
            dragging = true;
            isDragging.value = true;
            draggedDist = 0;
            lastClientX = e.clientX;
            lastClientY = e.clientY;
            canvas.setPointerCapture?.(e.pointerId);
        };

        const onPointerMove = (e) => {
            const rect = host.getBoundingClientRect();
            pointerX = ((e.clientX - rect.left) / Math.max(1, rect.width) - 0.5) * 2;
            pointerY = ((e.clientY - rect.top) / Math.max(1, rect.height) - 0.5) * 2;

            if (dragging) {
                const dx = e.clientX - lastClientX;
                const dy = e.clientY - lastClientY;
                draggedDist += Math.hypot(dx, dy);
                lastClientX = e.clientX;
                lastClientY = e.clientY;
                targetYaw -= dx * 0.0075;
                targetPitch = Math.max(0.42, Math.min(1.18, targetPitch + dy * 0.005));
            }
        };

        const onPointerUp = (e) => {
            if (!dragging) return;
            dragging = false;
            isDragging.value = false;
            canvas.releasePointerCapture?.(e.pointerId);
            if (draggedDist < 7) {
                triggerBreak();
            }
        };

        const onPointerLeave = () => {
            pointerX = 0;
            pointerY = 0;
            if (!dragging) return;
            dragging = false;
            isDragging.value = false;
        };

        canvas.addEventListener('pointerdown', onPointerDown);
        host.addEventListener('pointermove', onPointerMove);
        window.addEventListener('pointerup', onPointerUp);
        host.addEventListener('pointerleave', onPointerLeave);

        // ─── Sizing ─────────────────────────────────────
        const resize = () => {
            const w = host.clientWidth || 560;
            const h = host.clientHeight || 420;
            camera.aspect = w / h;
            camera.updateProjectionMatrix();
            renderer.setSize(w, h, false);
        };
        resize();
        const resizeObserver = new ResizeObserver(resize);
        resizeObserver.observe(host);

        // ─── Viewport / Tab Pause Guard ─────────────────
        let inViewport = true;
        const io = new IntersectionObserver(
            (entries) => {
                inViewport = entries[0]?.isIntersecting ?? true;
            },
            { threshold: 0.05 }
        );
        io.observe(host);

        // ─── Render Loop ────────────────────────────────
        let rafId = 0;
        let lastTime = performance.now();
        let elapsed = 0;
        let smoothScroll = scrollTarget;

        const tick = (now) => {
            rafId = requestAnimationFrame(tick);
            if (!inViewport || document.hidden) {
                lastTime = now;
                return;
            }

            const dt = Math.min(0.05, Math.max(0.001, (now - lastTime) / 1000));
            lastTime = now;
            elapsed += dt;
            phaseTime += dt;
            smoothScroll += (scrollTarget - smoothScroll) * Math.min(1, dt * 6);

            // Gentle idle camera sway + pointer & scroll parallax
            const desiredYaw = targetYaw + (dragging ? 0 : pointerX * 0.14 + Math.sin(elapsed * 0.45) * 0.045 + smoothScroll * 0.22);
            const desiredPitch = targetPitch + (dragging ? 0 : pointerY * 0.08 + smoothScroll * 0.16);
            currentYaw += (desiredYaw - currentYaw) * Math.min(1, dt * 6);
            currentPitch += (desiredPitch - currentPitch) * Math.min(1, dt * 6);

            const camDist = props.showLamp ? 7.35 : 7.75;
            const lookY = props.showLamp ? 0.58 : 0.05;
            camera.position.set(
                Math.sin(currentYaw) * Math.cos(currentPitch) * camDist,
                Math.sin(currentPitch) * camDist + (props.showLamp ? 0.85 : 0.55),
                Math.cos(currentYaw) * Math.cos(currentPitch) * camDist
            );
            camera.lookAt(0, lookY, 0.04);

            // Subtle table hover float & gentle lamp sway
            tableGroup.position.y = Math.sin(elapsed * 1.1) * 0.03;
            if (lampGroup) {
                lampGroup.rotation.z = Math.sin(elapsed * 0.9) * 0.018;
                lampGroup.rotation.x = Math.cos(elapsed * 0.7) * 0.012;
            }

            // Animate 3D chalk dust motes floating in spotlight beam
            const dustAttr = tableDustGeo.getAttribute('position');
            for (let i = 0; i < tableDustCount; i++) {
                const m = tableDustMeta[i];
                let y = dustAttr.getY(i) + m.speedY * dt;
                if (y > 2.45) y = 0.2;
                const x = m.baseX + Math.sin(elapsed * 0.7 + m.phase) * m.sway;
                const z = m.baseZ + Math.cos(elapsed * 0.6 + m.phase) * m.sway;
                dustAttr.setXYZ(i, x, y, z);
            }
            dustAttr.needsUpdate = true;

            // Animate chalk puff burst
            if (puffLife > 0) {
                puffLife = Math.max(0, puffLife - dt * 1.25);
                const pAttr = puffGeo.getAttribute('position');
                const drag = Math.pow(0.18, dt);
                for (let i = 0; i < puffCount; i++) {
                    puffVel[i * 3] *= drag;
                    puffVel[i * 3 + 1] = puffVel[i * 3 + 1] * drag + 0.08 * dt;
                    puffVel[i * 3 + 2] *= drag;
                    pAttr.setXYZ(
                        i,
                        pAttr.getX(i) + puffVel[i * 3] * dt,
                        pAttr.getY(i) + puffVel[i * 3 + 1] * dt,
                        pAttr.getZ(i) + puffVel[i * 3 + 2] * dt
                    );
                }
                pAttr.needsUpdate = true;
                puffMat.opacity = puffLife * (props.isDark ? 0.8 : 0.48);
            } else if (puffMat.opacity !== 0) {
                puffMat.opacity = 0;
            }

            // Phase transitions
            if (phase === 'aim') {
                cueGroup.visible = true;
                aimRing.visible = true;
                aimLine.visible = true;
                const pullback = 0.16 + Math.sin(phaseTime * 3.4) * 0.11;
                cueGroup.position.set(cueBall.x - ballR - pullback, ballR + 0.045, cueBall.z);
                cueGroup.rotation.y = -breakAngle;
                aimRing.position.set(cueBall.x, 0.008, cueBall.z);
                aimRing.rotation.z = elapsed * 0.7;
                aimRing.material.opacity = 0.4 + Math.sin(elapsed * 4) * 0.18;
                aimLine.rotation.z = -breakAngle;
                aimLine.material.opacity = 0.25 + Math.sin(elapsed * 4) * 0.1;

                if (phaseTime >= 1.85) {
                    phase = 'strike';
                    phaseTime = 0;
                    phaseLabel.value = 'Break Shot!';
                }
            } else if (phase === 'strike') {
                cueGroup.visible = true;
                aimRing.visible = false;
                aimLine.visible = false;
                const t = Math.min(1, phaseTime / 0.14);
                const ease = t * t * t;
                const offset = 0.34 * (1 - ease) + 0.015;
                cueGroup.position.set(cueBall.x - ballR - offset, ballR + 0.04, cueBall.z);

                if (t >= 1) {
                    const power = 10.6 + Math.random() * 1.6;
                    cueBall.vx = Math.cos(breakAngle) * power;
                    cueBall.vz = Math.sin(breakAngle) * power;
                    rackBreakSparked = false;
                    spawnChalkPuff(cueBall.x - ballR * 0.8, ballR + 0.02, cueBall.z, 0.85);
                    phase = 'play';
                    phaseTime = 0;
                }
            } else if (phase === 'play') {
                // Fade/retract cue stick away after strike
                if (phaseTime < 0.45) {
                    cueGroup.visible = true;
                    cueGroup.position.x -= dt * 3.4;
                } else {
                    cueGroup.visible = false;
                }
                aimRing.visible = false;
                aimLine.visible = false;

                const stillMoving = stepPhysics(dt);
                if ((!stillMoving && phaseTime > 1.4) || phaseTime > 4.6) {
                    balls.forEach((b) => {
                        b.fromX = b.x;
                        b.fromZ = b.z;
                        b.vx = 0;
                        b.vz = 0;
                        b.mesh.visible = true;
                    });
                    phase = 'reset';
                    phaseTime = 0;
                    phaseLabel.value = 'Re-rack';
                }
            } else if (phase === 'reset') {
                cueGroup.visible = false;
                aimRing.visible = false;
                aimLine.visible = false;
                const t = Math.min(1, phaseTime / 1.15);
                const ease = 1 - Math.pow(1 - t, 3);
                balls.forEach((b) => {
                    b.x = b.fromX + (b.homeX - b.fromX) * ease;
                    b.z = b.fromZ + (b.homeZ - b.fromZ) * ease;
                    const lift = b.pocketed ? Math.sin(t * Math.PI) * 0.25 : 0;
                    const sc = b.pocketed ? Math.max(0.15, ease) : 1;
                    b.mesh.scale.setScalar(sc);
                    b.mesh.position.set(b.x, ballR + lift, b.z);
                });

                if (t >= 1) {
                    resetPositionsImmediate();
                }
            }

            renderer.render(scene, camera);
        };

        resetPositionsImmediate();
        ready.value = true;
        rafId = requestAnimationFrame(tick);

        cleanup = () => {
            cancelAnimationFrame(rafId);
            resizeObserver.disconnect();
            io.disconnect();
            canvas.removeEventListener('pointerdown', onPointerDown);
            host.removeEventListener('pointermove', onPointerMove);
            window.removeEventListener('pointerup', onPointerUp);
            host.removeEventListener('pointerleave', onPointerLeave);
            disposables.forEach((d) => d?.dispose?.());
            renderer.dispose();
        };
    } catch (error) {
        console.warn('Three.js Hero fallback ke SVG:', error);
        fallback.value = true;
    }
});

onUnmounted(() => {
    cleanup?.();
    cleanup = null;
    triggerBreakFn = null;
    applyThemeFn = null;
});
</script>

<template>
    <div class="bbp-3d-wrap" :class="{ 'is-ready': ready, 'is-dragging': isDragging, 'is-compact': compact }">
        <slot v-if="fallback" name="fallback" />
        <template v-else>
            <div ref="hostRef" class="bbp-3d-stage" role="img" aria-label="Meja biliar 3D interaktif PoolStream">
                <canvas ref="canvasRef" class="bbp-3d-canvas"></canvas>

                <button
                    v-if="ready"
                    type="button"
                    class="bbp-3d-hint"
                    title="Klik untuk animasi break shot atau drag untuk putar meja 3D"
                    @click.stop="triggerBreak"
                >
                    <i class="bi bi-arrows-move"></i>
                    <span>Drag 3D &middot; Klik Break</span>
                </button>
            </div>
        </template>
    </div>
</template>

<style scoped>
.bbp-3d-wrap {
    position: relative;
    z-index: 1;
    width: 100%;
}

.bbp-3d-stage {
    position: relative;
    width: 100%;
    aspect-ratio: 560 / 430;
    cursor: grab;
    touch-action: pan-y;
    user-select: none;
    -webkit-user-select: none;
}

.bbp-3d-wrap.is-compact .bbp-3d-stage {
    aspect-ratio: 560 / 335;
}

.bbp-3d-wrap.is-dragging .bbp-3d-stage {
    cursor: grabbing;
}

.bbp-3d-canvas {
    width: 100%;
    height: 100%;
    display: block;
    outline: none;
    filter: drop-shadow(0 28px 46px rgba(2, 6, 23, 0.36));
    opacity: 0;
    transition: opacity 0.45s ease;
}

.bbp-3d-wrap.is-ready .bbp-3d-canvas {
    opacity: 1;
}

:global([data-bs-theme='light']) .bbp-3d-canvas {
    filter: drop-shadow(0 22px 36px rgba(15, 23, 42, 0.18));
}

.bbp-3d-hint {
    position: absolute;
    left: 50%;
    bottom: 6px;
    transform: translateX(-50%);
    z-index: 3;
    display: inline-flex;
    align-items: center;
    gap: 0.38rem;
    padding: 0.32rem 0.72rem;
    border-radius: 50rem;
    border: 1px solid rgba(148, 163, 184, 0.24);
    background: rgba(10, 17, 31, 0.68);
    color: rgba(226, 232, 240, 0.85);
    font-size: 0.68rem;
    font-weight: 600;
    letter-spacing: 0.02em;
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    cursor: pointer;
    transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease, transform 0.2s ease;
}

.bbp-3d-hint:hover {
    background: rgba(16, 185, 129, 0.22);
    border-color: rgba(52, 211, 153, 0.5);
    color: #ecfdf5;
    transform: translateX(-50%) translateY(-1px);
}

:global([data-bs-theme='light']) .bbp-3d-hint {
    background: rgba(255, 255, 255, 0.82);
    border-color: rgba(15, 23, 42, 0.14);
    color: #334155;
    box-shadow: 0 8px 20px -12px rgba(15, 23, 42, 0.28);
}

:global([data-bs-theme='light']) .bbp-3d-hint:hover {
    background: rgba(16, 185, 129, 0.14);
    border-color: rgba(5, 150, 105, 0.4);
    color: #065f46;
}
</style>

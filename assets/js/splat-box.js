/**
 * Glass box: a Gaussian splat rendered inside the cube, turning with it.
 *
 * A WebGL canvas lies over the scene (pointer-events: none). Its camera copies the
 * scene's CSS perspective, and every frame the splat takes the cube's computed
 * transform (mid-turn too), so it sits in the box and turns with it. It shows while
 * the face in front is a pure glass face and fades with the turn otherwise.
 *
 * Config: <script type="application/json" id="gcube-splat-box">
 *   {"url": "...", "spin": 24, "flip": true, "fill": 0.6, "credit": "...", "creditUrl": "..."}
 */

const configEl = document.getElementById('gcube-splat-box');
const sceneEl = document.getElementById('scene');
const cubeEl = document.getElementById('cube');

if (configEl && sceneEl && cubeEl) {
    let config = {};
    try { config = JSON.parse(configEl.textContent || '{}'); } catch (e) { config = {}; }
    if (config.url) {
        const start = () => ('requestIdleCallback' in window)
            ? requestIdleCallback(() => boot(config), { timeout: 3000 })
            : setTimeout(() => boot(config), 800);
        if (document.readyState === 'complete') start();
        else window.addEventListener('load', start, { once: true });
    }
}

async function boot(config) {
    const probe = document.createElement('canvas');
    if (!probe.getContext('webgl2')) return;

    const THREE = await import('three');
    const { SparkRenderer, SplatMesh } = await import('@sparkjsdev/spark');

    const canvas = document.createElement('canvas');
    canvas.className = 'gcube-splat-box';
    canvas.setAttribute('aria-hidden', 'true');
    sceneEl.after(canvas);

    let credit = null;
    if (config.credit) {
        credit = document.createElement(config.creditUrl ? 'a' : 'span');
        credit.className = 'gcube-splat-credit';
        credit.textContent = config.credit;
        if (config.creditUrl) {
            credit.href = config.creditUrl;
            credit.rel = 'noopener';
            credit.target = '_blank';
        }
        canvas.after(credit);
    }

    const renderer = new THREE.WebGLRenderer({ canvas, alpha: true, antialias: false, premultipliedAlpha: true });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    renderer.setClearColor(0x000000, 0);

    const world = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(30, 1, 1, 100000);
    world.add(new SparkRenderer({ renderer }));

    // box: follows the cube's transform; turntable: the slow product turn inside it
    const box = new THREE.Group();
    box.matrixAutoUpdate = false;
    const turntable = new THREE.Group();
    box.add(turntable);
    world.add(box);

    const splat = new SplatMesh({ url: config.url });
    turntable.add(splat);
    await splat.initialized;

    if (config.flip !== false) splat.quaternion.set(1, 0, 0, 0);
    const fit = () => {
        const half = boxHalf();
        splat.scale.setScalar(1);
        splat.position.set(0, 0, 0);
        splat.updateMatrixWorld(true);
        const bounds = splat.getBoundingBox(true).applyMatrix4(splat.matrix);
        const size = bounds.getSize(new THREE.Vector3());
        const centre = bounds.getCenter(new THREE.Vector3());
        const scale = (2 * half * (config.fill || 0.6)) / Math.max(size.x, size.y, size.z, 1e-6);
        splat.scale.setScalar(scale);
        splat.position.copy(centre).multiplyScalar(-scale);
    };

    const flipY = new THREE.Matrix4().makeScale(1, -1, 1);
    const cssMatrix = new THREE.Matrix4();
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const spinPerMs = config.spin > 0 ? (2 * Math.PI) / (config.spin * 1000) : 0;

    function boxHalf() {
        const front = cubeEl.querySelector('.face.two') || cubeEl.querySelector('.face');
        const m = new DOMMatrixReadOnly(getComputedStyle(front).transform);
        return Math.abs(m.m43) || Math.min(window.innerWidth, window.innerHeight) * 0.4;
    }

    function resize() {
        const w = window.innerWidth, h = window.innerHeight;
        renderer.setSize(w, h, false);
        const p = parseFloat(getComputedStyle(sceneEl).perspective) || 1000;
        camera.aspect = w / h;
        camera.fov = 2 * Math.atan((h / 2) / p) * 180 / Math.PI;
        camera.position.set(0, 0, p);
        camera.near = p / 50;
        camera.far = p * 10;
        camera.updateProjectionMatrix();
        fit();
    }

    // CSS (y down) to three (y up): S * M * S with S = diag(1, -1, 1)
    function syncToCube() {
        const t = getComputedStyle(cubeEl).transform;
        const m = new DOMMatrixReadOnly(t === 'none' ? undefined : t);
        cssMatrix.fromArray(m.toFloat32Array());
        box.matrix.copy(flipY).multiply(cssMatrix).multiply(flipY);
        box.matrixWorldNeedsUpdate = true;
    }

    function glassInFront() {
        if (document.body.classList.contains('face-expanded-mode')) return false;
        const face = cubeEl.querySelector('.face.face-visible');
        return !!face && face.dataset.glass === 'true' && !face.dataset.glassContent;
    }

    let last = performance.now();
    let running = false;

    function updateShown() {
        const shown = glassInFront();
        canvas.classList.toggle('is-shown', shown);
        if (credit) credit.classList.toggle('is-shown', shown);
        return shown;
    }

    function frame(now) {
        const shown = updateShown();
        if (spinPerMs && !reduceMotion.matches) turntable.rotation.y += spinPerMs * (now - last);
        last = now;
        syncToCube();
        renderer.render(world, camera);
        // keep drawing while shown and through the fade-out
        if (shown || getComputedStyle(canvas).opacity !== '0') {
            requestAnimationFrame(frame);
        } else {
            running = false;
        }
    }

    function wake() {
        updateShown();
        if (!running) {
            running = true;
            last = performance.now();
            requestAnimationFrame(frame);
        }
    }

    resize();
    window.addEventListener('resize', () => { resize(); wake(); });
    new MutationObserver(wake).observe(cubeEl, { subtree: true, attributes: true, attributeFilter: ['class', 'style'] });
    new MutationObserver(wake).observe(document.body, { attributes: true, attributeFilter: ['class'] });
    wake();
}

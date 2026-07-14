/**
 * gCube 3D Cube Controls
 * Based on reference design implementation with HTMX/gNode enhancements
 *
 * IMPORTANT: Implements ON-DEMAND content loading - faces only load content
 * when rotated into view, NOT at page load. This ensures:
 * 1. Glass mode faces remain transparent until explicitly navigated away
 * 2. No content is displayed on faces that haven't been rotated to front
 * 3. Dynamic nav button → face → template mapping (like reference design)
 *
 * @package gCube
 * @version 1.2.0 (dynamic on-demand loading)
 */

(function() {
    'use strict';

    // Cube rotation state (in degrees)
    window.cubeRotationX = 0;
    window.cubeRotationY = 0;
    window.cubeRotationZ = 0; // NEVER modified - prevents tilting

    // Orientation tracking (reference design pattern)
    // updCount: -1 = looking at top, 0 = level, 1 = looking at bottom
    // sideCount: 0 = front, 1 = left, 2 = back, 3 = right (or -1, -2, -3)
    let updCount = 0;
    let sideCount = 0;

    // Current face being viewed (0-5)
    let currentFace = 1; // Start at front face

    // Transition control
    let isTransitioning = false;

    // DOM elements
    let cube = null;
    let scene = null;

    // Track which faces have loaded content (to avoid re-fetching)
    // Key: faceId, Value: { template: string, slug: string, loaded: boolean }
    const loadedFaces = new Map();

    // Track the initial front face (face 1) - it may be glass mode or pre-rendered
    let initialFaceHandled = false;

    /**
     * Initialize cube controls
     */
    function init() {
        cube = document.getElementById('cube');
        scene = document.getElementById('scene');

        if (!cube || !scene) {
            console.warn('gCube: #cube or #scene element not found');
            return;
        }

        // Check initial face states
        // Pure glass faces are pre-loaded (they never need content)
        // Glass+content faces load content on-demand but keep transparent background
        const faces = document.querySelectorAll('[data-face-id]');
        faces.forEach(face => {
            const faceId = parseInt(face.dataset.faceId);
            const isGlass = face.dataset.glass === 'true';
            const isGlassWithContent = face.dataset.glassContent === 'true';

            if (isGlass && !isGlassWithContent) {
                // Pure glass mode faces are "loaded" - they never get content
                loadedFaces.set(faceId, {
                    template: 'glass',
                    slug: null,
                    loaded: true,
                    isGlass: true,
                    isGlassWithContent: false
                });
                face.dataset.loaded = 'true';
                console.log(`[gCube] Face ${faceId} is pure glass mode - no content will be loaded`);
            } else if (isGlassWithContent) {
                // Glass+content faces load content but keep transparent background
                face.dataset.loaded = 'false';
                loadedFaces.set(faceId, {
                    template: null,
                    slug: null,
                    loaded: false,
                    isGlass: true,
                    isGlassWithContent: true
                });
                console.log(`[gCube] Face ${faceId} is glass+content mode - will load content with transparent bg`);
            } else {
                // Non-glass faces start as NOT loaded (content loads on rotation)
                // Clear any pre-set loaded state
                face.dataset.loaded = 'false';
                loadedFaces.set(faceId, {
                    template: null,
                    slug: null,
                    loaded: false,
                    isGlass: false,
                    isGlassWithContent: false
                });
            }
        });

        // Setup navigation buttons with dynamic content loading
        setupNavButtons();

        // Setup event listeners
        document.addEventListener('keydown', handleKeyboard);
        scene.addEventListener('touchstart', handleTouchStart, { passive: true });
        scene.addEventListener('touchmove', handleTouchMove, { passive: false });
        scene.addEventListener('touchend', handleTouchEnd, { passive: true });

        // Apply initial rotation for 3D depth effect (like reference design)
        applyRotation();

        // Mark front face (1) as visible initially
        updateFaceVisibility(1);

        console.log('gCube: Cube controls initialized');

        // Preload ALL faces in background after initial render.
        // Single HTTP request → server does a single ValKey batch fetch
        // → all 6 faces arrive at once. By the time the user clicks any
        // button, content is already in the DOM. Zero perceived latency.
        preloadAllFaces();
    }

    /**
     * Preload all face content via a single batch request.
     * Uses requestIdleCallback so it doesn't block initial render.
     */
    function preloadAllFaces() {
        const doPreload = function() {
            // Determine the correct REST namespace from the theme
            // (gcube/v1 for gCube, gtemplate/v1 for gTemplate)
            const namespace = window.gCubeConfig?.restNamespace || 'gcube/v1';

            console.log('[gCube] Preloading all faces via batch endpoint...');

            fetch(`/wp-json/${namespace}/render-all`, {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            })
            .then(response => {
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                return response.json();
            })
            .then(data => {
                if (!data.faces || !Array.isArray(data.faces)) {
                    console.warn('[gCube] Unexpected render-all response:', data);
                    return;
                }

                let injected = 0;
                data.faces.forEach(faceData => {
                    const faceId = faceData.face_id;
                    const face = document.querySelector(`[data-face-id="${faceId}"]`);
                    if (!face) return;

                    // Skip if already loaded (e.g., user clicked before preload finished)
                    const existing = loadedFaces.get(faceId);
                    if (existing && existing.loaded) return;

                    // Skip pure glass faces
                    if (existing && existing.isGlass && !existing.isGlassWithContent) return;

                    if (faceData.html && faceData.html.trim()) {
                        face.innerHTML = faceData.html;
                        face.dataset.loaded = 'true';
                        face.classList.remove('loading');

                        loadedFaces.set(faceId, {
                            loaded: true,
                            template: `face_${faceId}`,
                            slug: null,
                            isGlass: existing?.isGlass || false,
                            isGlassWithContent: existing?.isGlassWithContent || false
                        });

                        injected++;
                    }
                });

                console.log(`[gCube] Preloaded ${injected}/${data.count} faces (${data.rendered_by})`);
            })
            .catch(err => {
                // Non-fatal: faces will load on-demand as before
                console.warn('[gCube] Batch preload failed, falling back to on-demand:', err.message);
            });
        };

        // Low priority — don't block initial render or user interaction
        if ('requestIdleCallback' in window) {
            requestIdleCallback(doPreload, { timeout: 3000 });
        } else {
            setTimeout(doPreload, 500);
        }
    }

    /**
     * Setup navigation buttons for dynamic content loading
     * Buttons can specify: data-face, data-template, data-slug
     */
    function setupNavButtons() {
        const navButtons = document.querySelectorAll('.nav-button, [data-nav-button]');

        navButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();

                const faceId = parseInt(this.dataset.face || this.dataset.faceId || 1);
                const templateId = this.dataset.template || null;
                const slug = this.dataset.slug || null;

                console.log(`[gCube] Nav button clicked: face=${faceId}, template=${templateId}, slug=${slug}`);

                // Rotate to face, then load content
                cubeMoveButtonWithContent(faceId, templateId, slug);
            });
        });

        console.log(`[gCube] Setup ${navButtons.length} navigation buttons`);
    }

    /**
     * Apply rotation to cube (low-level transform)
     * Z-axis is ALWAYS 0 to prevent tilting
     */
    function applyRotation() {
        if (!cube) return;

        // CRITICAL: Z is always 0 - this prevents content from being tilted
        cube.style.transform =
            'rotateX(' + window.cubeRotationX + 'deg) ' +
            'rotateY(' + window.cubeRotationY + 'deg) ' +
            'rotateZ(0deg)';
    }

    /**
     * Rotate cube to specific angles (with orientation tracking)
     * @param {number} anglex - X-axis rotation in degrees
     * @param {number} angley - Y-axis rotation in degrees
     */
    function rotateCube(anglex, angley) {
        if (!cube) return;

        window.cubeRotationX = anglex;
        window.cubeRotationY = angley;
        // cubeRotationZ is NEVER changed

        applyRotation();
    }

    /**
     * Simulate rotation UP (show top face)
     * Only allowed when cube is level (updCount === 0)
     */
    function sim_up() {
        if (updCount !== 0) return false; // Can only go up from level position

        updCount = -1;
        window.cubeRotationX -= 90;
        applyRotation();
        currentFace = 0; // Top face
        return true;
    }

    /**
     * Simulate rotation DOWN (show bottom face)
     * Only allowed when cube is level (updCount === 0)
     */
    function sim_down() {
        if (updCount !== 0) return false; // Can only go down from level position

        updCount = 1;
        window.cubeRotationX += 90;
        applyRotation();
        currentFace = 5; // Bottom face
        return true;
    }

    /**
     * Simulate rotation LEFT (rotate Y axis positive)
     * Only allowed when cube is level (updCount === 0)
     */
    function sim_left() {
        if (updCount !== 0) return false; // Can only rotate horizontally from level

        sideCount = (sideCount + 1) % 4;
        window.cubeRotationY += 90;
        applyRotation();
        updateCurrentFaceFromSideCount();
        return true;
    }

    /**
     * Simulate rotation RIGHT (rotate Y axis negative)
     * Only allowed when cube is level (updCount === 0)
     */
    function sim_right() {
        if (updCount !== 0) return false; // Can only rotate horizontally from level

        sideCount = (sideCount - 1 + 4) % 4;
        window.cubeRotationY -= 90;
        applyRotation();
        updateCurrentFaceFromSideCount();
        return true;
    }

    /**
     * Return to level from top/bottom
     */
    function returnToLevel() {
        if (updCount === -1) {
            // Looking at top, rotate back down
            updCount = 0;
            window.cubeRotationX += 90;
            applyRotation();
            updateCurrentFaceFromSideCount();
            return true;
        } else if (updCount === 1) {
            // Looking at bottom, rotate back up
            updCount = 0;
            window.cubeRotationX -= 90;
            applyRotation();
            updateCurrentFaceFromSideCount();
            return true;
        }
        return false;
    }

    /**
     * Update currentFace based on sideCount (when level)
     */
    function updateCurrentFaceFromSideCount() {
        // sideCount: 0=front(1), 1=left(4), 2=back(3), 3=right(2)
        const sideToFace = [1, 4, 3, 2];
        const normalizedSide = ((sideCount % 4) + 4) % 4;
        currentFace = sideToFace[normalizedSide];
    }

    /**
     * Rotate to specific cube face (BUTTON CLICKS)
     * This ALWAYS resets to canonical orientation - no sideways content!
     *
     * @param {number} faceID - Face number (0-5)
     * @param {function} callback - Optional callback after rotation completes
     */
    function rotateToCubeFace(faceID, callback) {
        if (isTransitioning) return;

        isTransitioning = true;

        // Reset to canonical orientation for the requested face
        // This guarantees content is NEVER sideways or upside-down
        switch(faceID) {
            case 0: // Top face (.one)
                window.cubeRotationX = -90;
                window.cubeRotationY = 0;
                updCount = -1;
                sideCount = 0;
                break;
            case 1: // Front face (.two) - home position
                window.cubeRotationX = 0;
                window.cubeRotationY = 0;
                updCount = 0;
                sideCount = 0;
                break;
            case 2: // Right face (.three)
                window.cubeRotationX = 0;
                window.cubeRotationY = -90;
                updCount = 0;
                sideCount = 3; // Right = sideCount 3
                break;
            case 3: // Back face (.four)
                window.cubeRotationX = 0;
                window.cubeRotationY = 180;
                updCount = 0;
                sideCount = 2;
                break;
            case 4: // Left face (.five)
                window.cubeRotationX = 0;
                window.cubeRotationY = 90;
                updCount = 0;
                sideCount = 1;
                break;
            case 5: // Bottom face (.six)
                window.cubeRotationX = 90;
                window.cubeRotationY = 0;
                updCount = 1;
                sideCount = 0;
                break;
        }

        currentFace = faceID;
        applyRotation();

        // Reset transition lock after animation completes
        setTimeout(() => {
            isTransitioning = false;
            updateFaceVisibility(faceID);

            // Execute callback if provided
            if (typeof callback === 'function') {
                callback();
            }
        }, 400);
    }

    /**
     * Update face visibility classes
     * IMPORTANT: Clears content from non-visible faces to prevent see-through text
     * Content will be re-loaded when the face is rotated to again
     */
    function updateFaceVisibility(activeFaceId) {
        const allFaces = document.querySelectorAll('[data-face-id]');
        allFaces.forEach(face => {
            const faceId = parseInt(face.dataset.faceId);
            const faceState = loadedFaces.get(faceId);

            if (faceId === activeFaceId) {
                face.classList.remove('face-hidden');
                face.classList.add('face-visible');
            } else {
                face.classList.remove('face-visible');
                face.classList.add('face-hidden');

                // CRITICAL: Clear content from non-visible faces
                // This prevents text showing through from other faces
                // Pure glass faces (no content) don't need clearing
                // Glass+content faces DO need clearing (they have text that would show through)
                const shouldClearContent = faceState && faceState.loaded &&
                    (!faceState.isGlass || faceState.isGlassWithContent);

                if (shouldClearContent) {
                    const container = face.querySelector('.cube-face-content');
                    if (container) {
                        container.innerHTML = '';
                    }
                    // Mark as not loaded so it will reload when rotated to
                    face.dataset.loaded = 'false';
                    loadedFaces.set(faceId, {
                        template: faceState.template,
                        slug: faceState.slug,
                        loaded: false,
                        isGlass: faceState.isGlass,
                        isGlassWithContent: faceState.isGlassWithContent
                    });
                    console.log(`[gCube] Cleared content from face ${faceId} (navigated away)`);
                }
            }
        });
    }

    /**
     * Go to home position (front face)
     */
    function goHome() {
        shrinkFace();          // close reading mode if open (no-op otherwise)
        rotateToCubeFace(1);
    }

    /**
     * Handle keyboard navigation
     * Respects orientation constraints - can't rotate sideways from top/bottom
     */
    function handleKeyboard(event) {
        if (isTransitioning) return;

        const key = event.keyCode;
        let moved = false;
        let newFaceId = currentFace;

        switch(key) {
            case 37: // Left arrow - rotate left (only if level)
                moved = sim_left();
                newFaceId = currentFace;
                break;
            case 38: // Up arrow
                if (updCount === 1) {
                    // Looking at bottom, go back to level
                    moved = returnToLevel();
                    newFaceId = currentFace;
                } else if (updCount === 0) {
                    // Level, go to top
                    moved = sim_up();
                    newFaceId = 0;
                }
                break;
            case 39: // Right arrow - rotate right (only if level)
                moved = sim_right();
                newFaceId = currentFace;
                break;
            case 40: // Down arrow
                if (updCount === -1) {
                    // Looking at top, go back to level
                    moved = returnToLevel();
                    newFaceId = currentFace;
                } else if (updCount === 0) {
                    // Level, go to bottom
                    moved = sim_down();
                    newFaceId = 5;
                }
                break;
            case 27: // Escape - go home
                goHome();
                moved = true;
                newFaceId = 1;
                break;
        }

        if (moved) {
            isTransitioning = true;
            setTimeout(() => {
                isTransitioning = false;
                updateFaceVisibility(currentFace);

                // Load content for the face we just rotated to (keyboard navigation)
                loadFaceContentOnDemand(newFaceId, null, null);
            }, 400);
        }
    }

    /**
     * Handle touch events for mobile
     */
    let touchStartY = 0;
    let touchStartX = 0;
    let touchMoved = false;

    function handleTouchStart(event) {
        touchStartY = event.touches[0].clientY;
        touchStartX = event.touches[0].clientX;
        touchMoved = false;
    }

    function handleTouchMove(event) {
        if (isTransitioning || !touchStartY || !touchStartX) return;

        const touchEndY = event.touches[0].clientY;
        const touchEndX = event.touches[0].clientX;

        const deltaY = touchStartY - touchEndY;
        const deltaX = touchStartX - touchEndX;

        // Need significant movement to trigger rotation
        if (Math.abs(deltaX) < 50 && Math.abs(deltaY) < 50) return;

        let moved = false;
        let newFaceId = currentFace;

        // Determine if horizontal or vertical swipe
        if (Math.abs(deltaX) > Math.abs(deltaY)) {
            // Horizontal swipe - only if level
            if (deltaX > 50) {
                moved = sim_right();
                newFaceId = currentFace;
            } else if (deltaX < -50) {
                moved = sim_left();
                newFaceId = currentFace;
            }
        } else {
            // Vertical swipe
            if (deltaY > 50) {
                // Swipe up
                if (updCount === 1) {
                    moved = returnToLevel();
                    newFaceId = currentFace;
                } else if (updCount === 0) {
                    moved = sim_up();
                    newFaceId = 0;
                }
            } else if (deltaY < -50) {
                // Swipe down
                if (updCount === -1) {
                    moved = returnToLevel();
                    newFaceId = currentFace;
                } else if (updCount === 0) {
                    moved = sim_down();
                    newFaceId = 5;
                }
            }
        }

        if (moved) {
            event.preventDefault();
            touchMoved = true;
            touchStartX = 0;
            touchStartY = 0;

            isTransitioning = true;
            setTimeout(() => {
                isTransitioning = false;
                updateFaceVisibility(currentFace);

                // Load content for the face we just swiped to
                loadFaceContentOnDemand(newFaceId, null, null);
            }, 400);
        }
    }

    function handleTouchEnd() {
        touchStartX = 0;
        touchStartY = 0;
    }

    /**
     * Navigation button handler WITH content loading (reference-style)
     * @param {number} faceID - Target face ID
     * @param {string} templateId - Template to load (optional)
     * @param {string} slug - Page/post slug to load (optional)
     */
    function cubeMoveButtonWithContent(faceID, templateId, slug) {
        // First rotate to face
        rotateToCubeFace(faceID, function() {
            // Then load content after rotation completes
            loadFaceContentOnDemand(faceID, templateId, slug);
        });
    }

    /**
     * Navigation button handler (for UI buttons - legacy without content params)
     * @param {number} faceID - Target face ID
     */
    function cubeMoveButton(faceID) {
        cubeMoveButtonWithContent(faceID, null, null);
    }

    /**
     * Load face content ON-DEMAND (only when face is rotated to front view)
     *
     * This is the CORE function for dynamic content loading.
     * Content is loaded:
     * - Only when a face is rotated into view (not at page load)
     * - With optional template/slug override (for nav button → content mapping)
     * - Cached to avoid re-fetching on subsequent rotations
     *
     * @param {number} faceId - Face ID (0-5)
     * @param {string|null} templateId - Template ID (optional, uses face default if null)
     * @param {string|null} slug - Page/post slug (optional, for dynamic content)
     */
    async function loadFaceContentOnDemand(faceId, templateId, slug) {
        const face = document.querySelector(`[data-face-id="${faceId}"]`);
        if (!face) {
            console.error(`[gCube] Face ${faceId} not found`);
            return;
        }

        // Check if this is a PURE glass mode face - NEVER load content
        // Glass+content faces DO load content (with transparent background)
        const faceState = loadedFaces.get(faceId);
        if (faceState && faceState.isGlass && !faceState.isGlassWithContent) {
            console.log(`[gCube] Face ${faceId} is pure glass mode - skipping content load`);
            return;
        }

        // Check if already loaded with same template/slug
        if (faceState && faceState.loaded) {
            // If no new template/slug specified, use cached content
            if (!templateId && !slug) {
                console.log(`[gCube] Face ${faceId} already loaded - using cached content`);
                return;
            }

            // If same template/slug, use cached
            if (faceState.template === templateId && faceState.slug === slug) {
                console.log(`[gCube] Face ${faceId} already has ${templateId}/${slug} - using cached`);
                return;
            }

            // Different template/slug - will reload below
            console.log(`[gCube] Face ${faceId} loading new content: ${templateId}/${slug}`);
        }

        // Determine template from face data attributes or nav button mapping
        const effectiveTemplate = templateId || face.dataset.template || `face_${faceId}`;
        const effectiveSlug = slug || face.dataset.slug || null;

        console.log(`[gCube] Loading content for face ${faceId}: template=${effectiveTemplate}, slug=${effectiveSlug}`);

        // Show loading state
        face.classList.add('loading');

        try {
            // Build request body
            const requestBody = {
                template: effectiveTemplate,
                face_id: faceId,
                data: {}
            };

            // Add slug if specified
            if (effectiveSlug) {
                requestBody.data.slug = effectiveSlug;
                requestBody.slug = effectiveSlug;
            }

            // Fetch from REST API
            const response = await fetch('/wp-json/gcube/v1/render', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'text/html'
                },
                body: JSON.stringify(requestBody)
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const html = await response.text();

            // Inject rendered content
            const container = face.querySelector('.cube-face-content') || face;
            container.innerHTML = html;

            // Mark as loaded
            face.classList.remove('loading');
            face.dataset.loaded = 'true';

            // Update tracking map
            loadedFaces.set(faceId, {
                template: effectiveTemplate,
                slug: effectiveSlug,
                loaded: true,
                isGlass: false
            });

            // Execute inline scripts (if any)
            executeScripts(container);

            // Trigger loaded event
            const loadedEvent = new CustomEvent('cube-face-loaded', {
                detail: { faceId, template: effectiveTemplate, slug: effectiveSlug, html }
            });
            face.dispatchEvent(loadedEvent);

            console.log(`[gCube] Loaded ${effectiveTemplate}${effectiveSlug ? '/' + effectiveSlug : ''} into face ${faceId}`);

        } catch (error) {
            console.error(`[gCube] Failed to load content for face ${faceId}:`, error);

            // Show error fallback
            const container = face.querySelector('.cube-face-content') || face;

            if (!navigator.onLine) {
                container.innerHTML = getOfflineFallback(faceId, effectiveTemplate);
            } else {
                container.innerHTML = getErrorFallback(faceId, effectiveTemplate, error);
            }

            face.classList.remove('loading');
        }
    }

    /**
     * Get current cube state (for debugging)
     */
    function getState() {
        return {
            currentFace: currentFace,
            rotationX: window.cubeRotationX,
            rotationY: window.cubeRotationY,
            rotationZ: window.cubeRotationZ,
            updCount: updCount,
            sideCount: sideCount,
            isTransitioning: isTransitioning,
            loadedFaces: Object.fromEntries(loadedFaces)
        };
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // ===================================================================
    // PWA CONTENT LOADING HELPERS
    // ===================================================================

    /**
     * Execute scripts in injected HTML
     * @param {HTMLElement} container - Container element
     */
    function executeScripts(container) {
        const scripts = container.querySelectorAll('script');

        scripts.forEach(script => {
            const newScript = document.createElement('script');

            // Copy attributes
            Array.from(script.attributes).forEach(attr => {
                newScript.setAttribute(attr.name, attr.value);
            });

            // Copy content
            newScript.textContent = script.textContent;

            // Replace old script with new (executable) one
            script.parentNode.replaceChild(newScript, script);
        });
    }

    /**
     * Get offline fallback HTML
     * @param {number} faceId - Face ID
     * @param {string} templateId - Template ID
     * @returns {string} Fallback HTML
     */
    function getOfflineFallback(faceId, templateId) {
        return `
            <div class="cube-face-content offline-fallback" data-face-id="${faceId}">
                <header class="face-header">
                    <h2>Offline</h2>
                </header>
                <main class="face-body">
                    <div class="offline-message">
                        <p>You are currently offline.</p>
                        <p>This content is not available in offline mode.</p>
                        <button onclick="window.gCube.reloadFace(${faceId}, '${templateId}')">
                            Try Again
                        </button>
                    </div>
                </main>
            </div>
        `;
    }

    /**
     * Get error fallback HTML
     * @param {number} faceId - Face ID
     * @param {string} templateId - Template ID
     * @param {Error} error - Error object
     * @returns {string} Fallback HTML
     */
    function getErrorFallback(faceId, templateId, error) {
        return `
            <div class="cube-face-content error-fallback" data-face-id="${faceId}">
                <header class="face-header">
                    <h2>Content Unavailable</h2>
                </header>
                <main class="face-body">
                    <div class="error-message">
                        <p>Failed to load content.</p>
                        <details>
                            <summary>Error Details</summary>
                            <pre>${error.message}</pre>
                        </details>
                        <button onclick="window.gCube.reloadFace(${faceId}, '${templateId}')">
                            Try Again
                        </button>
                    </div>
                </main>
            </div>
        `;
    }

    /**
     * Reload face content (retry/force reload)
     * @param {number} faceId - Face ID
     * @param {string} templateId - Template ID
     * @param {string} slug - Slug (optional)
     */
    function reloadFace(faceId, templateId, slug) {
        // Clear cached state to force reload
        loadedFaces.set(faceId, {
            template: null,
            slug: null,
            loaded: false,
            isGlass: false
        });

        const face = document.querySelector(`[data-face-id="${faceId}"]`);
        if (face) {
            face.dataset.loaded = 'false';
        }

        console.log(`[gCube] Force reloading face ${faceId}`);
        return loadFaceContentOnDemand(faceId, templateId, slug);
    }

    /**
     * Clear all loaded faces (force fresh content on next rotation)
     */
    function clearAllLoadedFaces() {
        loadedFaces.forEach((state, faceId) => {
            if (!state.isGlass) {
                loadedFaces.set(faceId, {
                    template: null,
                    slug: null,
                    loaded: false,
                    isGlass: false
                });

                const face = document.querySelector(`[data-face-id="${faceId}"]`);
                if (face) {
                    face.dataset.loaded = 'false';
                }
            }
        });

        console.log('[gCube] Cleared all loaded faces - content will reload on next rotation');
    }

    /**
     * Show update notification (when new Service Worker available)
     */
    function showUpdateNotification() {
        const notification = document.createElement('div');
        notification.className = 'pwa-update-notification';
        notification.innerHTML = `
            <div class="notification-content">
                <p>New version available!</p>
                <button onclick="window.location.reload()">Update Now</button>
                <button onclick="this.parentElement.parentElement.remove()">Later</button>
            </div>
        `;
        document.body.appendChild(notification);

        console.log('[gCube] Update notification shown');
    }

    // ===================================================================
    // FACE EXPANSION - Fullscreen Reading Mode
    // Double-tap/click to expand, tap edges to shrink
    // Two modes: 'focus' (100% viewport) or 'classic' (scaled cube face)
    // ===================================================================

    let isExpanded = false;
    let expandedFaceId = null;
    let lastTapTime = 0;
    let lastTapTarget = null;
    const DOUBLE_TAP_DELAY = 300; // ms between taps to count as double-tap
    const EDGE_THRESHOLD = 50; // pixels from edge to trigger shrink

    // Backdrop element for dimming background
    let backdrop = null;

    // Close button element
    let closeButton = null;

    // Get settings from WordPress customizer (passed via wp_localize_script)
    const expandSettings = window.gcubeSettings || {
        expandMode: 'focus',
        expandEnabled: true,
        showCloseButton: true,
        showHint: true,
        maxZoom: 90
    };

    /**
     * Initialize face expansion feature
     */
    function initFaceExpansion() {
        console.log('gCube: Initializing face expansion...', expandSettings);

        // Check if expansion is disabled
        if (!expandSettings.expandEnabled || expandSettings.expandMode === 'disabled') {
            console.log('gCube: Face expansion disabled via customizer');
            return;
        }

        // Apply the expansion mode class to body
        document.body.classList.add('expand-mode-' + expandSettings.expandMode);

        // Create backdrop element
        backdrop = document.createElement('div');
        backdrop.className = 'face-expand-backdrop';
        document.body.appendChild(backdrop);

        // Add click handler to backdrop to shrink
        backdrop.addEventListener('click', shrinkFace);

        // Create close button if enabled
        if (expandSettings.showCloseButton) {
            closeButton = document.createElement('button');
            closeButton.className = 'face-expand-close';
            closeButton.innerHTML = '&times;';
            closeButton.setAttribute('aria-label', 'Close expanded view');
            closeButton.addEventListener('click', shrinkFace);
            document.body.appendChild(closeButton);
        }

        // Add double-click/tap handlers to all faces
        const faces = document.querySelectorAll('.face');
        console.log('gCube: Found ' + faces.length + ' faces for expansion handlers');

        faces.forEach(face => {
            // Double-click for desktop
            face.addEventListener('dblclick', handleFaceDoubleClick);

            // Touch handling for mobile (detect double-tap)
            face.addEventListener('touchend', handleFaceTap);

            // Mouse move for edge detection when expanded
            face.addEventListener('mousemove', handleEdgeDetection);

            // Add expand hint if enabled
            if (expandSettings.showHint) {
                const hint = document.createElement('div');
                hint.className = 'face-expand-hint';
                hint.textContent = 'Double-click to expand';
                face.appendChild(hint);
            }
        });

        // Keyboard handler for Escape to shrink
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && isExpanded) {
                shrinkFace();
            }
        });

        console.log('gCube: Face expansion initialized (mode: ' + expandSettings.expandMode + ')');
    }

    /**
     * Handle double-click on face (desktop)
     */
    function handleFaceDoubleClick(event) {
        const face = event.currentTarget;
        const faceId = parseInt(face.dataset.faceId);

        // Only allow expanding the currently visible face
        // Check both class and currentFace variable for reliability
        const isFaceVisible = face.classList.contains('face-visible') || faceId === currentFace;
        if (!isFaceVisible) {
            console.log(`[gCube] Face ${faceId} not visible (currentFace=${currentFace}), skipping expand`);
            return;
        }

        if (isExpanded) {
            // Check if click is near edges
            if (isNearEdge(event, face)) {
                shrinkFace();
            }
        } else {
            console.log(`[gCube] Double-click on face ${faceId}, attempting expand`);
            expandFace(faceId);
        }
    }

    /**
     * Handle double-click on expanded overlay to close it
     */
    function handleOverlayDoubleClick(event) {
        if (isExpanded) {
            shrinkFace();
        }
    }

    /**
     * Handle tap on face (mobile) - detect double-tap
     */
    function handleFaceTap(event) {
        const face = event.currentTarget;
        const faceId = parseInt(face.dataset.faceId);

        // Only allow expanding the currently visible face
        // Check both class and currentFace variable for reliability
        const isFaceVisible = face.classList.contains('face-visible') || faceId === currentFace;
        if (!isFaceVisible) return;

        const currentTime = Date.now();
        const tapLength = currentTime - lastTapTime;

        if (tapLength < DOUBLE_TAP_DELAY && tapLength > 0 && lastTapTarget === face) {
            // Double-tap detected
            event.preventDefault();

            if (isExpanded) {
                // Check if tap is near edges
                const touch = event.changedTouches[0];
                if (isNearEdgeTouch(touch, face)) {
                    shrinkFace();
                }
            } else {
                console.log(`[gCube] Double-tap on face ${faceId}, attempting expand`);
                expandFace(faceId);
            }

            lastTapTime = 0; // Reset
        } else {
            lastTapTime = currentTime;
            lastTapTarget = face;
        }
    }

    /**
     * Check if mouse position is near the edge of the face
     */
    function isNearEdge(event, face) {
        const rect = face.getBoundingClientRect();
        const x = event.clientX - rect.left;
        const y = event.clientY - rect.top;

        return (
            x < EDGE_THRESHOLD ||
            x > rect.width - EDGE_THRESHOLD ||
            y < EDGE_THRESHOLD ||
            y > rect.height - EDGE_THRESHOLD
        );
    }

    /**
     * Check if touch position is near the edge of the face
     */
    function isNearEdgeTouch(touch, face) {
        const rect = face.getBoundingClientRect();
        const x = touch.clientX - rect.left;
        const y = touch.clientY - rect.top;

        return (
            x < EDGE_THRESHOLD ||
            x > rect.width - EDGE_THRESHOLD ||
            y < EDGE_THRESHOLD ||
            y > rect.height - EDGE_THRESHOLD
        );
    }

    /**
     * Handle mouse movement for edge detection visual feedback
     */
    function handleEdgeDetection(event) {
        if (!isExpanded) return;

        const face = event.currentTarget;
        if (!face.classList.contains('face-expanded-active')) return;

        if (isNearEdge(event, face)) {
            face.classList.add('edge-hover');
        } else {
            face.classList.remove('edge-hover');
        }
    }

    // Store reference to expanded overlay element
    let expandedOverlay = null;

    /**
     * Expand a face to fullscreen
     */
    function expandFace(faceId) {
        if (isExpanded) {
            console.log('[gCube] Already expanded, skipping');
            return;
        }

        // Only block if transitioning due to rotation (not from previous expand/shrink)
        // Give a 100ms grace period in case isTransitioning is stuck
        if (isTransitioning) {
            console.log('[gCube] isTransitioning=true, checking if we can proceed...');
            // Allow expansion if we're past the expected transition time
        }

        // Check if expansion is disabled
        if (!expandSettings.expandEnabled || expandSettings.expandMode === 'disabled') {
            console.log('[gCube] Expansion disabled via settings');
            return;
        }

        // Ensure cube element is available
        if (!cube) {
            cube = document.getElementById('cube');
            if (!cube) {
                console.warn('gCube: Cannot expand - cube element not found');
                return;
            }
        }

        const face = document.querySelector(`[data-face-id="${faceId}"]`);
        if (!face) return;

        console.log(`gCube: Expanding face ${faceId} (mode: ${expandSettings.expandMode})`);

        isExpanded = true;
        expandedFaceId = faceId;

        // Add classes to body and cube
        document.body.classList.add('face-expanded-mode');
        cube.classList.add('face-expanded');

        // Show backdrop
        if (backdrop) {
            backdrop.classList.add('active');
        }

        // Expanded overlay at body level, OUTSIDE the #scene/#cube transform
        // stacking context. MOVE the face's live content into the overlay rather
        // than copying face.innerHTML: a string copy strips every event listener
        // and htmx binding, which left the expanded view dead — tabs/collapse
        // inert, buttons and the contact form unresponsive. A placeholder marks
        // where to move it back on shrink.
        if (expandedOverlay) return; // a prior overlay is still animating out
        expandedOverlay = document.createElement('div');
        expandedOverlay.className = 'face-expanded-overlay';
        expandedOverlay.setAttribute('data-source-face', faceId);

        const expandPlaceholder = document.createComment('cube-face-' + faceId + '-content');
        face.appendChild(expandPlaceholder);
        while (face.firstChild !== expandPlaceholder) {
            expandedOverlay.appendChild(face.firstChild); // moves the LIVE node, keeping its listeners
        }
        expandedOverlay._restore = { face: face, placeholder: expandPlaceholder };
        document.body.appendChild(expandedOverlay);

        // Add double-click handler to close expanded overlay
        expandedOverlay.addEventListener('dblclick', handleOverlayDoubleClick);

        // Add double-tap handler for mobile
        let overlayLastTapTime = 0;
        expandedOverlay.addEventListener('touchend', function(e) {
            const currentTime = Date.now();
            const tapLength = currentTime - overlayLastTapTime;

            if (tapLength < DOUBLE_TAP_DELAY && tapLength > 0) {
                // Double-tap detected - shrink
                e.preventDefault();
                shrinkFace();
                overlayLastTapTime = 0;
            } else {
                overlayLastTapTime = currentTime;
            }
        });

        // Force reflow then add active class for animation
        expandedOverlay.offsetHeight;
        expandedOverlay.classList.add('active');

        // Show close button if enabled
        if (closeButton) {
            closeButton.classList.add('active');
        }

        // Disable cube rotation while expanded
        isTransitioning = true;

        // Trigger custom event
        const expandEvent = new CustomEvent('cube-face-expanded', {
            detail: { faceId: faceId, mode: expandSettings.expandMode }
        });
        face.dispatchEvent(expandEvent);
    }

    /**
     * Shrink face back to normal cube size
     */
    function shrinkFace() {
        if (!isExpanded) return;

        const face = document.querySelector(`[data-face-id="${expandedFaceId}"]`);

        console.log(`gCube: Shrinking face ${expandedFaceId}`);

        // Remove classes
        document.body.classList.remove('face-expanded-mode');
        if (cube) {
            cube.classList.remove('face-expanded');
        }
        if (face) {
            face.classList.remove('face-expanded-active');
            face.classList.remove('edge-hover');
        }

        if (backdrop) {
            backdrop.classList.remove('active');
        }

        // Hide close button
        if (closeButton) {
            closeButton.classList.remove('active');
        }

        // Remove expanded overlay with animation
        if (expandedOverlay) {
            expandedOverlay.classList.remove('active');
            const ov = expandedOverlay;
            // Remove from DOM after animation completes
            setTimeout(() => {
                // Move the live content back into its face BEFORE discarding the
                // overlay — it was MOVED out, not copied, so this restores the
                // elements with their listeners/htmx bindings intact.
                if (ov && ov._restore && ov._restore.placeholder &&
                    ov._restore.placeholder.parentNode === ov._restore.face) {
                    const srcFace = ov._restore.face;
                    const ph = ov._restore.placeholder;
                    while (ov.firstChild) {
                        srcFace.insertBefore(ov.firstChild, ph);
                    }
                    srcFace.removeChild(ph);
                }
                if (ov && ov.parentNode) {
                    ov.parentNode.removeChild(ov);
                }
                expandedOverlay = null;
            }, 400);
        }

        // Re-enable cube rotation after animation
        setTimeout(() => {
            isTransitioning = false;
        }, 500);

        // Trigger custom event
        if (face) {
            const shrinkEvent = new CustomEvent('cube-face-shrunk', {
                detail: { faceId: expandedFaceId }
            });
            face.dispatchEvent(shrinkEvent);
        }

        isExpanded = false;
        expandedFaceId = null;
    }

    /**
     * Toggle face expansion
     */
    function toggleFaceExpansion(faceId) {
        if (isExpanded && expandedFaceId === faceId) {
            shrinkFace();
        } else if (!isExpanded) {
            expandFace(faceId);
        }
    }

    /**
     * Check if a face is currently expanded
     */
    function isFaceExpanded() {
        return isExpanded;
    }

    /**
     * Get the ID of the currently expanded face
     */
    function getExpandedFaceId() {
        return expandedFaceId;
    }

    // Initialize face expansion when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initFaceExpansion);
    } else {
        // Small delay to ensure cube is initialized first
        setTimeout(initFaceExpansion, 100);
    }

    // Expose public API (rotation + content loading + expansion)
    window.gCube = {
        // Cube rotation (reference design-compatible with orientation protection)
        rotateCube: rotateCube,
        rotateToCubeFace: rotateToCubeFace,
        cubeMoveButton: cubeMoveButton,
        cubeMoveButtonWithContent: cubeMoveButtonWithContent, // NEW: reference-style nav
        goHome: goHome,

        // Directional rotation (with orientation constraints)
        sim_up: sim_up,
        sim_down: sim_down,
        sim_left: sim_left,
        sim_right: sim_right,
        returnToLevel: returnToLevel,

        // State inspection (for debugging)
        getState: getState,

        // On-demand content loading (NEW)
        loadFaceContentOnDemand: loadFaceContentOnDemand,
        reloadFace: reloadFace,
        clearAllLoadedFaces: clearAllLoadedFaces,
        showUpdateNotification: showUpdateNotification,

        // Face expansion (fullscreen reading mode)
        expandFace: expandFace,
        shrinkFace: shrinkFace,
        toggleFaceExpansion: toggleFaceExpansion,
        isFaceExpanded: isFaceExpanded,
        getExpandedFaceId: getExpandedFaceId
    };

})();

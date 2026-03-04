/**
 * OpsMan – GPS Tracking JS
 * Captures geolocation and sends it to the backend.
 */

let gpsWatchId = null;
let lastPosition = null;

/**
 * Get the current position once (promise-based).
 * @returns {Promise<{lat, lng, accuracy}|null>}
 */
function getCurrentPosition() {
    return new Promise((resolve) => {
        if (!navigator.geolocation) {
            showToast('Geolocation is not supported by your browser', 'error');
            resolve(null);
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (pos) => {
                const result = {
                    lat:      pos.coords.latitude,
                    lng:      pos.coords.longitude,
                    accuracy: pos.coords.accuracy,
                };
                lastPosition = result;
                resolve(result);
            },
            (err) => {
                const msgs = {
                    1: 'Location access denied. Please allow location permissions.',
                    2: 'Location unavailable. Check GPS signal.',
                    3: 'Location request timed out. Please try again.',
                };
                showToast(msgs[err.code] || 'Failed to get location', 'error');
                resolve(null);
            },
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 30000 }
        );
    });
}

/**
 * Start periodic GPS tracking (sends to backend every 60 s).
 */
function startGPS() {
    if (!navigator.geolocation) {
        showToast('Geolocation not supported', 'error');
        return;
    }
    if (gpsWatchId !== null) {
        showToast('GPS already active', 'info');
        return;
    }

    updateGpsStatus(true);
    showToast('GPS tracking started', 'success');

    gpsWatchId = navigator.geolocation.watchPosition(
        async (pos) => {
            lastPosition = {
                lat:      pos.coords.latitude,
                lng:      pos.coords.longitude,
                accuracy: pos.coords.accuracy,
            };
            updateGpsStatus(true, lastPosition);
            await sendGpsLog(lastPosition);
        },
        (err) => {
            console.warn('GPS watch error:', err);
            updateGpsStatus(false);
        },
        { enableHighAccuracy: true, maximumAge: 15000, timeout: 20000 }
    );
}

function stopGPS() {
    if (gpsWatchId !== null) {
        navigator.geolocation.clearWatch(gpsWatchId);
        gpsWatchId = null;
        updateGpsStatus(false);
        showToast('GPS tracking stopped', 'info');
    }
}

async function sendGpsLog(pos, taskId = null) {
    try {
        await api('/api/gps.php', 'POST', {
            latitude:  pos.lat,
            longitude: pos.lng,
            accuracy:  pos.accuracy,
            task_id:   taskId,
        });
    } catch (e) {
        console.warn('GPS log failed:', e.message);
    }
}

function updateGpsStatus(active, pos = null) {
    const statusEl = document.getElementById('gpsStatus');
    if (!statusEl) return;

    const dot  = statusEl.querySelector('.gps-dot');
    const text = statusEl.querySelector('span');
    if (dot) {
        dot.className = `gps-dot ${active ? 'gps-active' : 'gps-inactive'}`;
    }
    if (text) {
        if (active && pos) {
            text.textContent = `Active · ${pos.lat.toFixed(5)}, ${pos.lng.toFixed(5)}`;
        } else {
            text.textContent = active ? 'GPS active' : 'GPS inactive';
        }
    }

    // Also update location display if present
    if (active && pos) {
        const locEl = document.getElementById('locationDisplay');
        if (locEl) {
            locEl.innerHTML = `📍 <strong>${pos.lat.toFixed(5)}, ${pos.lng.toFixed(5)}</strong> (±${Math.round(pos.accuracy)}m)`;
        }
    }
}

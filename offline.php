<?php
/**
 * TESDA-BCAT GMS — Offline Fallback Page
 * Shown when the user is offline and requests an uncached page.
 */
$base = '/TesdaBCAT-1.02/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>You're Offline — TESDA-BCAT GMS</title>
    <link rel="icon" href="<?php echo $base; ?>BCAT logo 2024.png" type="image/png">
    <link rel="manifest" href="<?php echo $base; ?>manifest.json">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --navy: #002366;
            --blue: #0038A8;
            --soft: #f8fafc;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--soft);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            overflow: hidden;
        }

        /* Animated background blobs */
        body::before, body::after {
            content: '';
            position: fixed;
            border-radius: 50%;
            pointer-events: none;
            z-index: 0;
            opacity: 0.06;
        }
        body::before {
            width: 500px; height: 500px;
            background: var(--blue);
            top: -150px; left: -150px;
        }
        body::after {
            width: 400px; height: 400px;
            background: var(--navy);
            bottom: -100px; right: -100px;
        }

        .card {
            background: #fff;
            border-radius: 2rem;
            padding: 3rem 2rem 2.5rem;
            max-width: 420px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,35,102,0.1);
            position: relative;
            z-index: 1;
            animation: popIn 0.5s cubic-bezier(0.34,1.56,0.64,1) forwards;
        }

        @keyframes popIn {
            from { opacity: 0; transform: scale(0.85) translateY(20px); }
            to   { opacity: 1; transform: scale(1) translateY(0); }
        }

        .logo-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            margin-bottom: 2rem;
        }

        .logo-row img {
            height: 44px;
            width: auto;
            object-fit: contain;
        }

        .wifi-icon {
            width: 90px;
            height: 90px;
            background: rgba(0,56,168,0.08);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(0,56,168,0.15); }
            50% { transform: scale(1.05); box-shadow: 0 0 0 12px rgba(0,56,168,0); }
        }

        .wifi-icon svg { width: 42px; height: 42px; }

        h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--navy);
            margin-bottom: 0.5rem;
            letter-spacing: -0.03em;
        }

        .subtitle {
            color: #64748b;
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        /* Sync status indicator */
        .status-bar {
            background: rgba(0,56,168,0.05);
            border: 1px solid rgba(0,56,168,0.1);
            border-radius: 1rem;
            padding: 0.85rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            text-align: left;
        }

        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #ef4444;
            flex-shrink: 0;
            transition: background 0.3s;
        }

        .status-dot.online { background: #22c55e; animation: pulse-dot 1.5s infinite; }

        @keyframes pulse-dot {
            0%, 100% { box-shadow: 0 0 0 0 rgba(34,197,94,0.4); }
            50% { box-shadow: 0 0 0 6px rgba(34,197,94,0); }
        }

        .status-text {
            font-size: 0.85rem;
            color: #334155;
            font-weight: 500;
        }

        .status-text strong { color: var(--navy); }

        /* Cached pages list */
        .cached-section {
            text-align: left;
            background: #f8fafc;
            border-radius: 1rem;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
        }

        .cached-section h3 {
            font-family: 'Outfit', sans-serif;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #94a3b8;
            margin-bottom: 0.75rem;
        }

        .cached-link {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.5rem 0;
            text-decoration: none;
            color: var(--blue);
            font-size: 0.9rem;
            font-weight: 500;
            border-bottom: 1px solid rgba(0,0,0,0.04);
            transition: gap 0.2s;
        }

        .cached-link:last-child { border-bottom: none; }
        .cached-link:hover { gap: 0.9rem; }

        .cached-link svg { width: 16px; height: 16px; flex-shrink: 0; opacity: 0.7; }

        .btn-retry {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: linear-gradient(135deg, var(--blue), var(--navy));
            color: #fff;
            border: none;
            border-radius: 50px;
            padding: 0.85rem 2rem;
            font-family: 'Outfit', sans-serif;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            justify-content: center;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 8px 20px rgba(0,56,168,0.25);
        }

        .btn-retry:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(0,56,168,0.35);
        }

        .btn-retry svg { width: 18px; height: 18px; }

        .version-tag {
            margin-top: 1.5rem;
            color: #cbd5e1;
            font-size: 0.75rem;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo-row">
            <img src="tesda_logo.png" alt="TESDA">
            <img src="BCAT logo 2024.png" alt="BCAT">
        </div>

        <div class="wifi-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="#0038A8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 12.55a11 11 0 0 1 14.08 0"/>
                <path d="M1.42 9a16 16 0 0 1 21.16 0"/>
                <path d="M8.53 16.11a6 6 0 0 1 6.95 0"/>
                <line x1="12" y1="20" x2="12.01" y2="20" stroke-width="2.5"/>
            </svg>
        </div>

        <h1>You're Offline</h1>
        <p class="subtitle">
            No internet connection detected.<br>
            You can still view <strong>cached pages</strong> below while offline.
        </p>

        <!-- Connection Status -->
        <div class="status-bar">
            <div class="status-dot" id="statusDot"></div>
            <div class="status-text" id="statusText">
                <strong>Checking connection...</strong><br>
                <span id="statusSub">The app will reconnect automatically.</span>
            </div>
        </div>

        <!-- Cached Pages -->
        <div class="cached-section" id="cachedSection">
            <h3>Available Offline</h3>
            <div id="cachedList">
                <p style="color:#94a3b8; font-size:0.85rem;">Loading cached pages...</p>
            </div>
        </div>

        <button class="btn-retry" onclick="window.location.reload()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="23 4 23 10 17 10"/>
                <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
            </svg>
            Try Reconnecting
        </button>

        <div class="version-tag">TESDA-BCAT GMS &bull; Version 1.0.2</div>
    </div>

    <script>
        const dot = document.getElementById('statusDot');
        const text = document.getElementById('statusText');
        const sub = document.getElementById('statusSub');

        function updateStatus() {
            if (navigator.onLine) {
                dot.className = 'status-dot online';
                text.innerHTML = '<strong>Connection restored!</strong>';
                sub.textContent = 'Redirecting you back...';
                setTimeout(() => window.history.back(), 1500);
            } else {
                dot.className = 'status-dot';
                text.innerHTML = '<strong>No Connection</strong>';
                sub.textContent = 'The app will reconnect automatically when online.';
            }
        }

        window.addEventListener('online', updateStatus);
        window.addEventListener('offline', updateStatus);
        updateStatus();

        // List cached pages from the service worker cache
        (async () => {
            if (!('caches' in window)) return;
            const cachedList = document.getElementById('cachedList');
            const pageLabels = {
                'index.php': { icon: '🔑', label: 'Login Page' },
                'offline.php': { icon: '📶', label: 'Offline Page' },
                'admin/dashboard.php': { icon: '🏠', label: 'Admin Dashboard' },
                'admin/students.php': { icon: '🎓', label: 'Students Registry' },
                'admin/instructors.php': { icon: '👨‍🏫', label: 'Instructors' },
                'registrar/students.php': { icon: '🎓', label: 'Registrar — Students' },
                'registrar/instructors.php': { icon: '👨‍🏫', label: 'Registrar — Instructors' },
                'dept_head/dashboard.php': { icon: '🏠', label: 'Dept Head Dashboard' },
                'dept_head/students.php': { icon: '🎓', label: 'Dept — Students' },
                'instructor/dashboard.php': { icon: '🏠', label: 'Instructor Dashboard' },
                'student/dashboard.php': { icon: '🏠', label: 'Student Dashboard' },
                'student/my_grades.php': { icon: '📊', label: 'My Grades' },
            };

            const keys = await caches.keys();
            let found = [];

            for (const key of keys) {
                const cache = await caches.open(key);
                const reqs = await cache.keys();
                reqs.forEach(req => {
                    const url = new URL(req.url);
                    const path = url.pathname.replace(/.*TesdaBCAT-1\.02\//, '');
                    const meta = pageLabels[path];
                    if (meta && !found.find(f => f.path === path)) {
                        found.push({ path, url: req.url, ...meta });
                    }
                });
            }

            if (found.length === 0) {
                cachedList.innerHTML = '<p style="color:#94a3b8; font-size:0.85rem;">No pages cached yet. Visit pages while online to cache them.</p>';
                return;
            }

            cachedList.innerHTML = found.map(f => `
                <a href="${f.url}" class="cached-link">
                    <span>${f.icon}</span>
                    <span>${f.label}</span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </a>
            `).join('');
        })();
    </script>
</body>
</html>

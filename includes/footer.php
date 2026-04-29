        </div><!-- /.content-area -->

        <!-- Footer -->
        <footer style="
            margin-top: auto;
            margin-left: 0;
            background: #fff;
            border-top: 1px solid rgba(0,0,0,0.06);
            padding: 0.75rem 1.75rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.5rem;
            font-size: 0.8rem;
            color: #64748b;
        ">
            <div style="display:flex; align-items:center; gap: 0.6rem;">
                <img src="../tesda_logo.png" alt="TESDA" style="height:22px; width:auto; opacity:0.85;">
                <img src="../BCAT logo 2024.png" alt="BCAT" style="height:22px; width:auto; opacity:0.85;">
                <span style="font-weight:600; color:#0038A8;">TESDA-BCAT</span>
                <span>Grade Management System</span>
            </div>
            <div style="display:flex; align-items:center; gap: 1.25rem;">
                <span>Version <?php echo defined('APP_VERSION') ? APP_VERSION : '1.0'; ?></span>
                <span style="color:#cbd5e1;">|</span>
                <span>&copy; <?php echo date('Y'); ?> TESDA-BCAT. All rights reserved.</span>
            </div>
        </footer>

    </div><!-- /.main-content -->

    

    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <!-- DataTables Responsive Extension -->
    <script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.4.1/js/responsive.bootstrap5.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <script>
        // Initialize DataTables with Responsive extension
        $(document).ready(function() {
            $('.data-table').each(function() {
                if (!$.fn.DataTable.isDataTable(this)) {
                    $(this).DataTable({
                        responsive: true,
                        pageLength: <?php echo ITEMS_PER_PAGE; ?>,
                        language: {
                            search: "_INPUT_",
                            searchPlaceholder: "Search..."
                        }
                    });
                }
            });
        });
        
        // Confirm delete
        function confirmDelete(message = 'Are you sure you want to delete this?') {
            return confirm(message);
        }
        
        // Sidebar Toggle Logic
        function toggleSidebar() {
            $('.sidebar, .sidebar-overlay').toggleClass('active');
            
            // Toggle icon
            const icon = $('#sidebarCollapse i');
            if ($('.sidebar').hasClass('active')) {
                icon.removeClass('fa-bars').addClass('fa-times');
                $('body').css('overflow', 'hidden'); // Prevent scroll when menu is open
            } else {
                icon.removeClass('fa-times').addClass('fa-bars');
                $('body').css('overflow', '');
            }
        }

        $('#sidebarCollapse').on('click', function(e) {
            e.stopPropagation();
            toggleSidebar();
        });

        // Close when clicking overlay
        $('#sidebarOverlay').on('click', function() {
            toggleSidebar();
        });

        // Close sidebar when clicking menu item on mobile
        $('.sidebar-menu-item').on('click', function() {
            if (window.innerWidth <= 1024 && $('.sidebar').hasClass('active')) {
                toggleSidebar();
            }
        });

        // Handle window resize
        $(window).on('resize', function() {
            if (window.innerWidth > 1024) {
                $('.sidebar, .sidebar-overlay').removeClass('active');
                $('#sidebarCollapse i').removeClass('fa-times').addClass('fa-bars');
                $('body').css('overflow', '');
            }
        });

        // Auto-hide session alerts after 5 seconds (only dismissible ones)
        setTimeout(function() {
            $('.alert-dismissible').fadeOut('slow');
        }, 5000);

        // Emergency Backdrop Cleanup Utility
        $(document).on('click', function(e) {
            // Ignore if we are clicking a button meant to toggle a modal
            if ($(e.target).closest('[data-bs-toggle="modal"]').length) {
                return;
            }

            if ($('.modal-backdrop').length > 0 && !$('.modal.show').length) {
                $('.modal-backdrop').remove();
                $('body').removeClass('modal-open').css('overflow', '');
            }
        });

        /**
         * ──── 10-MINUTE INACTIVITY TIMEOUT HANDLER ────
         */
        <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
        (function() {
            const TIMEOUT_SECONDS = 600; // 10 minutes
            const WARNING_SECONDS = 540; // 9 minutes
            let idleTime = 0;
            let countdown = 60;
            let countdownInterval;
            let idleInterval;
            
            const modalElement = document.getElementById('inactivityModal');
            let inactivityModal;
            if (modalElement) {
                inactivityModal = new bootstrap.Modal(modalElement, {
                    backdrop: 'static',
                    keyboard: false
                });
            }

            // Reset idle timer on activity
            function resetIdleTimer() {
                idleTime = 0;
            }

            window.onload = resetIdleTimer;
            window.onmousemove = resetIdleTimer;
            window.onmousedown = resetIdleTimer;
            window.ontouchstart = resetIdleTimer;
            window.onclick = resetIdleTimer;
            window.onkeydown = resetIdleTimer;
            window.addEventListener('scroll', resetIdleTimer, true);

            // Increment idle timer every second
            idleInterval = setInterval(timerIncrement, 1000);

            function timerIncrement() {
                idleTime++;
                if (idleTime >= WARNING_SECONDS && !$('#inactivityModal').hasClass('show')) {
                    showWarning();
                }
            }

            function showWarning() {
                clearInterval(idleInterval);
                inactivityModal.show();
                countdown = TIMEOUT_SECONDS - idleTime;
                updateCountdownDisplay();
                
                countdownInterval = setInterval(function() {
                    countdown--;
                    updateCountdownDisplay();
                    if (countdown <= 0) {
                        logoutUser();
                    }
                }, 1000);
            }

            function updateCountdownDisplay() {
                $('#inactivityCountdown').text(countdown);
            }

            function logoutUser() {
                window.location.href = '<?php echo BASE_URL; ?>logout.php?error=timeout';
            }

            // Reset via AJAX
            $('#stayLoggedInBtn').on('click', function() {
                const btn = $(this);
                btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Refreshing...');
                
                $.ajax({
                    url: '<?php echo BASE_URL; ?>includes/ajax/keep_alive.php',
                    method: 'POST',
                    dataType: 'json',
                    success: function(data) {
                        if (data.status === 'ok') {
                            clearInterval(countdownInterval);
                            inactivityModal.hide();
                            idleTime = 0;
                            idleInterval = setInterval(timerIncrement, 1000);
                        } else {
                            logoutUser();
                        }
                    },
                    error: function() {
                        logoutUser();
                    },
                    complete: function() {
                        btn.prop('disabled', false).text('Stay Logged In');
                    }
                });
            });
        })();
        <?php endif; ?>
    </script>
    
    <!-- Inactivity Warning Modal -->
    <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
    <div class="modal fade" id="inactivityModal" tabindex="-1" aria-hidden="true" style="z-index: 9999;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 1.5rem; overflow: hidden;">
                <div class="modal-header bg-warning text-dark border-0 p-4">
                    <h5 class="modal-title fw-800" style="font-family: 'Outfit', sans-serif;">
                        <i class="fas fa-exclamation-triangle me-2"></i> Session Timing Out
                    </h5>
                </div>
                <div class="modal-body p-4 text-center">
                    <div class="mb-4">
                        <i class="fas fa-clock fa-4x text-warning opacity-25"></i>
                    </div>
                    <p class="fs-5 fw-bold mb-2">Are you still there?</p>
                    <p class="text-muted">You have been inactive for a while. For your security, you will be logged out in:</p>
                    <div class="display-4 fw-800 text-primary my-3" id="inactivityCountdown" style="font-family: 'Outfit', sans-serif;">60</div>
                    <p class="text-muted small">Seconds</p>
                </div>
                <div class="modal-footer border-0 p-4 gap-2">
                    <a href="<?php echo BASE_URL; ?>logout.php" class="btn btn-light rounded-pill px-4 fw-600">Logout Now</a>
                    <button type="button" class="btn btn-primary rounded-pill px-4 fw-700 shadow-sm" id="stayLoggedInBtn">Stay Logged In</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    
    <?php if (isset($additionalJS))
    echo $additionalJS; ?>

    <!-- PWA Sync Status Pill -->
    <div id="pwa-sync-pill">
        <div class="pwa-sync-dot" id="pwa-sync-dot"></div>
        <span id="pwa-sync-text">Online</span>
    </div>

    <!-- PWA Service Worker Registration -->
    <script>
    (function() {
        // ─── Service Worker Registration ───────────────────────────────
        if ('serviceWorker' in navigator) {
            const swUrl = '<?php echo BASE_URL; ?>sw.js';
            const swScope = '<?php echo BASE_URL; ?>';

            navigator.serviceWorker.register(swUrl, { scope: swScope })
                .then(reg => {
                    console.log('[PWA] Service Worker registered. Scope:', reg.scope);

                    // Detect updates
                    reg.addEventListener('updatefound', () => {
                        const newSW = reg.installing;
                        newSW.addEventListener('statechange', () => {
                            if (newSW.state === 'installed' && navigator.serviceWorker.controller) {
                                showSyncPill('update', '✨ Update available — refresh to apply');
                            }
                        });
                    });
                })
                .catch(err => console.warn('[PWA] SW registration failed:', err));

            // Listen for messages from Service Worker
            navigator.serviceWorker.addEventListener('message', event => {
                const { type, version, url, label } = event.data || {};
                if (type === 'SW_UPDATED') {
                    console.log('[PWA] Updated to', version);
                }
                if (type === 'SYNC_SUCCESS') {
                    showSyncPill('online', '✅ ' + (label || 'Data synced successfully'));
                }
            });
        }

        // ─── Install Prompt ("Add to Home Screen") ────────────────────
        let deferredInstallPrompt = null;
        const installBtn = document.getElementById('pwa-install-btn');

        window.addEventListener('beforeinstallprompt', e => {
            e.preventDefault();
            deferredInstallPrompt = e;
            if (installBtn) installBtn.classList.add('visible');
        });

        if (installBtn) {
            installBtn.addEventListener('click', async () => {
                if (!deferredInstallPrompt) return;
                deferredInstallPrompt.prompt();
                const { outcome } = await deferredInstallPrompt.userChoice;
                if (outcome === 'accepted') {
                    installBtn.classList.remove('visible');
                    showSyncPill('online', '📱 App installed successfully!');
                }
                deferredInstallPrompt = null;
            });
        }

        // Hide install button after app is installed
        window.addEventListener('appinstalled', () => {
            if (installBtn) installBtn.classList.remove('visible');
            deferredInstallPrompt = null;
        });

        // ─── Online / Offline Status Indicator ───────────────────────
        let syncPillTimer = null;
        let wasOffline = !navigator.onLine;

        function showSyncPill(state, message) {
            const pill = document.getElementById('pwa-sync-pill');
            const dot  = document.getElementById('pwa-sync-dot');
            const text = document.getElementById('pwa-sync-text');
            if (!pill) return;

            // Remove all state classes
            pill.className = '';
            dot.className = 'pwa-sync-dot';

            text.textContent = message;

            if (state === 'offline') {
                pill.classList.add('show', 'offline');
                dot.classList.add('pulse');
                clearTimeout(syncPillTimer);
                // Stay visible while offline
            } else if (state === 'syncing') {
                pill.classList.add('show', 'syncing');
                dot.classList.add('pulse');
                clearTimeout(syncPillTimer);
            } else if (state === 'online' || state === 'update') {
                pill.classList.add('show', 'online');
                clearTimeout(syncPillTimer);
                syncPillTimer = setTimeout(() => {
                    pill.classList.remove('show');
                }, 3500);
            }
        }

        function handleOnline() {
            if (wasOffline) {
                showSyncPill('syncing', '⟳ Back online — syncing data...');
                setTimeout(() => showSyncPill('online', '✅ Connected & synced'), 2200);
                wasOffline = false;
            }
        }

        function handleOffline() {
            wasOffline = true;
            showSyncPill('offline', '📶 No connection — offline mode active');
        }

        window.addEventListener('online',  handleOnline);
        window.addEventListener('offline', handleOffline);

        // Show initial status if already offline on load
        if (!navigator.onLine) handleOffline();
    })();
    </script>
</body>
</html>

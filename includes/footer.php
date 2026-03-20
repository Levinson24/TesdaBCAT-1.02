        </div><!-- /.content-area -->

        <!-- Footer -->
        <footer style="
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

    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <script>
        // Initialize DataTables
        $(document).ready(function() {
            $('.data-table').DataTable({
                responsive: true,
                pageLength: <?php echo ITEMS_PER_PAGE; ?>,
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search..."
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
            if (window.innerWidth <= 992 && $('.sidebar').hasClass('active')) {
                toggleSidebar();
            }
        });

        // Handle window resize
        $(window).on('resize', function() {
            if (window.innerWidth > 992) {
                $('.sidebar, .sidebar-overlay').removeClass('active');
                $('#sidebarCollapse i').removeClass('fa-times').addClass('fa-bars');
                $('body').css('overflow', '');
            }
        });

        // Auto-hide session alerts after 5 seconds (only dismissible ones)
        setTimeout(function() {
            $('.alert-dismissible').fadeOut('slow');
        }, 5000);
    </script>
    
    <?php if (isset($additionalJS))
    echo $additionalJS; ?>
</body>
</html>

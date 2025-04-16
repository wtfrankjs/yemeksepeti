<footer class="mt-auto py-3 bg-light">
        <div class="container">
            <span class="text-muted">© <?php echo date('Y'); ?> Yemeksepeti Admin Paneli</span>
        </div>
    </footer>

    <!-- Bootstrap JS ve dependencyleri -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Custom Admin JS -->
    <script>
        // Bootstrap tooltip'leri aktifleştir
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })
        
        // Aktif menü öğesini vurgula
        $(document).ready(function() {
            const path = window.location.pathname;
            const page = path.split("/").pop();
            
            $(".nav-link").each(function() {
                const href = $(this).attr('href');
                if (href === page) {
                    $(this).addClass('active');
                }
            });
            
            // Alert mesajlarını otomatik kapat
            $(".alert-dismissible").fadeTo(2000, 500).slideUp(500, function(){
                $(".alert-dismissible").alert('close');
            });
        });
    </script>
</body>
</html>
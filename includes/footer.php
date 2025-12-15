<?php
// includes/footer.php
?>
        </div> <!-- End container -->
    </div> <!-- End main-content -->
    
    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>© 2024 Age of Donnation. جميع الحقوق محفوظة.</p>
            <p>منصة التبرعات الأولى في المغرب | مبني على العمل التطوعي</p>
            <p style="margin-top: 20px; font-size: 14px;">
                <a href="#" style="color: #74b9ff; margin: 0 10px;">سياسة الخصوصية</a>
                <a href="#" style="color: #74b9ff; margin: 0 10px;">شروط الاستخدام</a>
                <a href="#" style="color: #74b9ff; margin: 0 10px;">اتصل بنا</a>
            </p>
        </div>
    </footer>
    
    <script>
    // Mobile Menu Toggle
    function toggleMenu() {
        const navLinks = document.getElementById('navLinks');
        navLinks.classList.toggle('active');
    }
    
    // Close menu when clicking outside
    document.addEventListener('click', function(event) {
        const navLinks = document.getElementById('navLinks');
        const menuToggle = document.querySelector('.menu-toggle');
        
        if (!navLinks.contains(event.target) && !menuToggle.contains(event.target)) {
            navLinks.classList.remove('active');
        }
    });
    
    // Auto-close alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);
    </script>
</body>
</html>
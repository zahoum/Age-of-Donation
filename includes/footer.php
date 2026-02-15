<?php // includes/footer.php ?>
        </div> <!-- End container -->
    </div> <!-- End main-content -->

    <!-- Footer -->
    <footer class="footer">
        <div class="container footer-container">
            
            <div class="footer-content">
                <p class="copyright">
                    © 2024 Age of Donnation. جميع الحقوق محفوظة.
                </p>

                <p class="footer-description">
                    منصة التبرعات الأولى في المغرب | مبني على العمل التطوعي
                </p>

                <div class="footer-links">
                    <a href="../includes/privacy-policy.php">سياسة الخصوصية</a>
                    <a href="../includes/terms-of-service.php">شروط الاستخدام</a>
                    <a href="../includes/contact-us.php">اتصل بنا</a>
                </div>
            </div>

        </div>
    </footer>

    <style>
        .footer {
            background-color: #1e272e;
            color: #ffffff;
            padding: 30px 0;
            text-align: center;
        }

        .footer-container {
            max-width: 1100px;
            margin: auto;
        }

        .footer-description {
            margin-top: 8px;
            color: #d2dae2;
        }

        .footer-links {
            margin-top: 20px;
        }

        .footer-links a {
            color: #74b9ff;
            text-decoration: none;
            margin: 0 12px;
            font-size: 14px;
            transition: 0.3s;
        }

        .footer-links a:hover {
            color: #ffffff;
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .footer-links a {
                display: block;
                margin: 8px 0;
            }
        }
    </style>

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

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4 mb-lg-0">
                    <div class="footer-brand">
                        <i class="bi bi-trophy-fill me-2"></i>VoteHubGH
                    </div>
                    <p class="mb-3">Your trusted platform for transparent and secure online voting. Making every vote count since 2024.</p>
                    <div class="mb-4">
                        <span class="badge bg-primary px-3 py-2 rounded-pill fs-6">
                            <i class="bi bi-phone me-1"></i> USSD: *920*137#
                        </span>
                    </div>
                    <div class="social-links">
                        <a href="<?php echo WHATSAPP_LINK; ?>" target="_blank" aria-label="WhatsApp"><i class="bi bi-whatsapp"></i></a>
                        <a href="#" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
                        <a href="#" aria-label="Twitter"><i class="bi bi-twitter-x"></i></a>
                        <a href="#" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
                        <a href="#" aria-label="LinkedIn"><i class="bi bi-linkedin"></i></a>
                        <a href="#" aria-label="YouTube"><i class="bi bi-youtube"></i></a>
                    </div>
                </div>
                <div class="col-6 col-lg-2">
                    <h5>Quick Links</h5>
                    <ul>
                        <li><a href="<?php echo SITE_URL; ?>">Home</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/events.php">Events</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/results.php">Results</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/how-it-works.php">How It Works</a></li>
                    </ul>
                </div>
                <div class="col-6 col-lg-2">
                    <h5>Support</h5>
                    <ul>
                        <li><a href="<?php echo SITE_URL; ?>/faq.php">FAQ</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/contact.php">Contact Us</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/privacy.php">Privacy Policy</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/terms.php">Terms of Service</a></li>
                    </ul>
                </div>
                <div class="col-lg-4">
                    <h5>Newsletter</h5>
                    <p class="mb-3">Stay updated with the latest events and voting opportunities.</p>
                    <form class="newsletter-form" id="newsletterForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <div class="input-group">
                            <input type="email" name="email" class="form-control" placeholder="Enter your email" required>
                            <button class="btn btn-primary" type="submit" id="subscribeBtn">Subscribe</button>
                        </div>
                        <div id="newsletterMessage" class="mt-2 small" style="display: none;"></div>
                    </form>
                </div>
            </div>
            <div class="footer-bottom">
                <div class="row align-items-center">
                    <div class="col-md-6 text-center text-md-start mb-2 mb-md-0">
                        <p class="mb-0">&copy; <?php echo date('Y'); ?> VoteHubGH. All rights reserved.</p>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Back to Top Button -->
    <a href="#" class="btn btn-primary btn-back-to-top" id="backToTop" style="position: fixed; bottom: 30px; right: 30px; width: 50px; height: 50px; border-radius: 50%; display: none; align-items: center; justify-content: center; z-index: 999;">
        <i class="bi bi-arrow-up"></i>
    </a>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script src="<?php echo SITE_URL; ?>/assets/js/main.js"></script>
    
    <!-- Notifications JS -->
    <?php if (isLoggedIn()): ?>
    <script src="<?php echo SITE_URL; ?>/assets/js/notifications.js"></script>
    <?php endif; ?>
    
    <?php if (isset($extra_js)): ?>
        <?php echo $extra_js; ?>
    <?php endif; ?>

    <script>
    document.getElementById('newsletterForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const form = this;
        const btn = document.getElementById('subscribeBtn');
        const msgDiv = document.getElementById('newsletterMessage');
        const email = form.querySelector('input[name="email"]').value;
        const csrf = form.querySelector('input[name="csrf_token"]').value;
        
        // Reset message
        msgDiv.style.display = 'none';
        msgDiv.className = 'mt-2 small';
        
        // Disable button
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
        
        const formData = new FormData();
        formData.append('email', email);
        formData.append('csrf_token', csrf);
        
        fetch('<?php echo SITE_URL; ?>/subscribe.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            msgDiv.textContent = data.message;
            msgDiv.style.display = 'block';
            
            if (data.status === 'success') {
                msgDiv.classList.add('text-success');
                form.reset();
            } else if (data.status === 'info') {
                msgDiv.classList.add('text-info');
            } else {
                msgDiv.classList.add('text-danger');
            }
        })
        .catch(error => {
            msgDiv.textContent = 'An error occurred. Please try again.';
            msgDiv.className = 'mt-2 small text-danger';
            msgDiv.style.display = 'block';
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = 'Subscribe';
        });
    });
    </script>
</body>
</html>

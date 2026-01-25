/**
 * VoteHub - Main JavaScript File
 */

document.addEventListener('DOMContentLoaded', function () {
    // Initialize all components
    initNavbarScroll();
    initBackToTop();
    initAnimations();
    initVotingForms();
    initVoteModal();
    initShortcodeVoting();
    initCounters();
});

/**
 * Navbar Scroll Effect
 */
function initNavbarScroll() {
    const navbar = document.getElementById('mainNav');
    if (!navbar) return;

    const handleScroll = () => {
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    };

    window.addEventListener('scroll', handleScroll);
    handleScroll(); // Initial check
}

/**
 * Back to Top Button
 */
function initBackToTop() {
    const backToTop = document.getElementById('backToTop');
    if (!backToTop) return;

    window.addEventListener('scroll', () => {
        if (window.scrollY > 300) {
            backToTop.style.display = 'flex';
        } else {
            backToTop.style.display = 'none';
        }
    });

    backToTop.addEventListener('click', (e) => {
        e.preventDefault();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
}

/**
 * Scroll Animations using Intersection Observer
 */
function initAnimations() {
    const animatedElements = document.querySelectorAll('.animate-on-scroll');

    if (animatedElements.length === 0) return;

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animated');
                observer.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    });

    animatedElements.forEach(el => observer.observe(el));
}

/**
 * Initialize Vote Quantity Modal
 */
function initVoteModal() {
    const modal = document.getElementById('voteQuantityModal');
    if (!modal) return;

    const bsModal = new bootstrap.Modal(modal);
    const form = document.getElementById('modalVoteForm');
    const quantityInput = document.getElementById('voteQuantity');
    const btnMinus = document.getElementById('btnMinus');
    const btnPlus = document.getElementById('btnPlus');
    const totalAmountSpan = document.getElementById('modalTotalAmount');
    const unitPriceSpan = document.getElementById('modalUnitPrice');
    const nomineeNameSpan = document.getElementById('modalNomineeName');
    const nomineePhotoImg = document.getElementById('modalNomineePhoto');

    // Hidden inputs for shortcode-initiated votes
    const modalNomineeId = document.getElementById('modalNomineeId');
    const modalCategoryId = document.getElementById('modalCategoryId');
    const modalEventId = document.getElementById('modalEventId');

    let currentPrice = 0;
    let currentForm = null;
    let nomineeData = null;

    // Open modal on "Vote" button click (from cards)
    document.querySelectorAll('.btn-buy-votes').forEach(btn => {
        btn.addEventListener('click', function () {
            currentForm = this.closest('form');
            nomineeData = null;
            const nomineeName = this.dataset.nomineeName;
            currentPrice = parseFloat(this.dataset.price);

            // Find nominee photo from the card
            const card = this.closest('.card') || this.closest('.nominee-card');
            const photo = card.querySelector('.nominee-photo').src;

            nomineeNameSpan.textContent = nomineeName;
            nomineePhotoImg.src = photo;
            unitPriceSpan.textContent = currentPrice.toFixed(2);
            quantityInput.value = 1;
            updateTotal();

            bsModal.show();
        });
    });

    // Function to open modal from shortcode search
    window.openVoteModal = function (data) {
        currentForm = null;
        nomineeData = data;
        currentPrice = parseFloat(data.vote_price);

        nomineeNameSpan.textContent = data.name;
        nomineePhotoImg.src = data.photo;
        unitPriceSpan.textContent = currentPrice.toFixed(2);

        if (modalNomineeId) modalNomineeId.value = data.id;
        if (modalCategoryId) modalCategoryId.value = data.category_id;
        if (modalEventId) modalEventId.value = data.event_id;

        quantityInput.value = 1;
        updateTotal();

        bsModal.show();
    };

    function updateTotal() {
        const qty = parseInt(quantityInput.value) || 1;
        const total = qty * currentPrice;
        totalAmountSpan.textContent = total.toFixed(2);
    }

    btnMinus.addEventListener('click', () => {
        if (quantityInput.value > 1) {
            quantityInput.value--;
            updateTotal();
        }
    });

    btnPlus.addEventListener('click', () => {
        quantityInput.value++;
        updateTotal();
    });

    quantityInput.addEventListener('input', updateTotal);

    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        const submitBtn = form.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="loading"></span> Processing...';

        // Get phone number from modal
        const phoneInput = document.getElementById('voterPhone');
        const phoneValue = phoneInput ? phoneInput.value.replace(/\D/g, '') : '';

        if (currentForm) {
            // Card-initiated vote
            let qtyInput = currentForm.querySelector('input[name="quantity"]');
            if (!qtyInput) {
                qtyInput = document.createElement('input');
                qtyInput.type = 'hidden';
                qtyInput.name = 'quantity';
                currentForm.appendChild(qtyInput);
            }
            qtyInput.value = quantityInput.value;

            // Add phone number to form if provided
            let phoneHiddenInput = currentForm.querySelector('input[name="phone"]');
            if (!phoneHiddenInput) {
                phoneHiddenInput = document.createElement('input');
                phoneHiddenInput.type = 'hidden';
                phoneHiddenInput.name = 'phone';
                currentForm.appendChild(phoneHiddenInput);
            }
            phoneHiddenInput.value = phoneValue;

            const submitEvent = new Event('submit', { cancelable: true, bubbles: true });
            currentForm.dispatchEvent(submitEvent);

            setTimeout(() => {
                bsModal.hide();
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
                // Clear phone input for next use
                if (phoneInput) phoneInput.value = '';
            }, 500);
        } else if (nomineeData) {
            // Shortcode-initiated vote
            try {
                const formData = new FormData();
                formData.append('nominee_id', nomineeData.id);
                formData.append('category_id', nomineeData.category_id);
                formData.append('event_id', nomineeData.event_id);
                formData.append('quantity', quantityInput.value);
                formData.append('phone', phoneValue); // Add phone for SMS confirmation

                // Get CSRF token from a hidden input if available, or fetch it
                const csrfToken = document.querySelector('input[name="csrf_token"]')?.value;
                if (csrfToken) formData.append('csrf_token', csrfToken);

                const response = await fetch(`${window.SITE_URL}/vote.php`, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const result = await response.json();

                if (result.success) {
                    bsModal.hide();
                    // Handle payment or success
                    if (result.payment_required) {
                        handlePayment(result.payment_data, null);
                    } else {
                        showToast('success', result.message);
                        setTimeout(() => window.location.reload(), 2000);
                    }
                } else {
                    showToast('danger', result.message);
                }
            } catch (error) {
                console.error('Shortcode vote error:', error);
                showToast('danger', 'An error occurred. Please try again.');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            }
        }
    });
}

/**
 * Initialize Voting Forms with AJAX and Paystack Integration
 */
function initVotingForms() {
    const voteForms = document.querySelectorAll('.vote-form');

    voteForms.forEach(form => {
        form.addEventListener('submit', async function (e) {
            e.preventDefault();

            const btn = this.querySelector('button[type="submit"]') || this.querySelector('.btn-buy-votes');
            const originalText = btn ? btn.innerHTML : '';

            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="loading"></span> Processing...';
            }

            try {
                const formData = new FormData(this);
                const response = await fetch(this.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const result = await response.json();

                if (result.success) {
                    if (result.payment_required) {
                        handlePayment(result.payment_data, btn, originalText);
                    } else {
                        showToast('success', result.message);
                        if (btn) {
                            btn.innerHTML = '<i class="bi bi-check-circle"></i> Voted';
                            btn.classList.add('btn-success');
                            btn.classList.remove('btn-primary');
                            btn.disabled = true;
                        }
                        setTimeout(() => window.location.reload(), 2000);
                    }
                } else {
                    showToast('danger', result.message);
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                    }
                }
            } catch (error) {
                console.error('Voting error:', error);
                showToast('danger', 'An error occurred. Please try again.');
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
            }
        });
    });
}

/**
 * Handle Paystack Payment
 */
function handlePayment(paymentData, btn, originalText) {
    let metadata = paymentData.metadata;
    if (typeof metadata === 'string') {
        try {
            metadata = JSON.parse(metadata);
        } catch (e) {
            metadata = {};
        }
    }

    const handler = PaystackPop.setup({
        key: paymentData.key,
        email: paymentData.email,
        amount: paymentData.amount,
        currency: paymentData.currency,
        ref: paymentData.ref,
        metadata: metadata,
        callback: function (response) {
            if (btn) btn.innerHTML = '<span class="loading"></span> Verifying...';

            (async () => {
                try {
                    const verifyResponse = await fetch(`${window.SITE_URL}/verify-payment.php`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: `reference=${response.reference}`
                    });
                    const verifyResult = await verifyResponse.json();

                    if (verifyResult.success) {
                        showToast('success', verifyResult.message);
                        if (btn) {
                            btn.innerHTML = '<i class="bi bi-check-circle"></i> Voted';
                            btn.classList.add('btn-success');
                            btn.disabled = true;
                        }
                        setTimeout(() => window.location.reload(), 2000);
                    } else {
                        showToast('danger', verifyResult.message);
                        if (btn) {
                            btn.disabled = false;
                            btn.innerHTML = originalText;
                        }
                    }
                } catch (err) {
                    showToast('danger', 'Verification failed.');
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                    }
                }
            })();
        },
        onClose: function () {
            showToast('warning', 'Payment cancelled.');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }
    });
    handler.openIframe();
}

/**
 * Initialize Shortcode Voting
 */
function initShortcodeVoting() {
    const shortcodeForms = document.querySelectorAll('.shortcode-search form, form.shortcode-search');

    shortcodeForms.forEach(form => {
        form.addEventListener('submit', async function (e) {
            e.preventDefault();

            const input = this.querySelector('input[name="shortcode"]');
            const btn = this.querySelector('button[type="submit"]');
            const shortcode = input.value.trim();

            if (!shortcode) return;

            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="loading"></span>';

            try {
                const response = await fetch(`${window.SITE_URL}/vote.php?shortcode=${encodeURIComponent(shortcode)}`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const result = await response.json();

                if (result.success) {
                    // Check if user is logged in (optional, but good for UX)
                    // If voting is paid, we need to show the modal anyway
                    if (result.nominee.voting_type === 'paid') {
                        window.openVoteModal(result.nominee);
                    } else {
                        // For free voting, we could just vote directly or show modal
                        // Let's show modal for consistency so they can choose quantity if allowed
                        window.openVoteModal(result.nominee);
                    }
                } else {
                    showToast('warning', result.message);
                }
            } catch (error) {
                console.error('Shortcode fetch error:', error);
                showToast('danger', 'Failed to find nominee.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        });
    });
}

/**
 * Animated Counters
 */
function initCounters() {
    const counters = document.querySelectorAll('.counter');

    if (counters.length === 0) return;

    const animateCounter = (counter) => {
        const target = parseInt(counter.getAttribute('data-target'));
        const duration = 2000;
        const step = target / (duration / 16);
        let current = 0;

        const updateCounter = () => {
            current += step;
            if (current < target) {
                counter.textContent = Math.floor(current).toLocaleString();
                requestAnimationFrame(updateCounter);
            } else {
                counter.textContent = target.toLocaleString();
            }
        };

        updateCounter();
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                animateCounter(entry.target);
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.5 });

    counters.forEach(counter => observer.observe(counter));
}

/**
 * Toast Notification System
 */
function showToast(type, message) {
    // Remove existing toasts
    const existingToasts = document.querySelectorAll('.toast-notification');
    existingToasts.forEach(toast => toast.remove());

    const toast = document.createElement('div');
    toast.className = `toast-notification alert alert-${type} fade show`;
    toast.style.cssText = `
        position: fixed;
        top: 100px;
        right: 20px;
        z-index: 9999;
        max-width: 400px;
        animation: slideIn 0.3s ease;
    `;
    toast.innerHTML = `
        ${message}
        <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
    `;

    document.body.appendChild(toast);

    // Auto remove after 5 seconds
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 150);
    }, 5000);
}



/**
 * Confirm Action Modal
 */
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

/**
 * Format Number with Commas
 */
function formatNumber(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

/**
 * Live Vote Counter Update (for results page)
 */
function initLiveResults() {
    const resultsContainer = document.querySelector('.live-results-container');
    if (!resultsContainer) return;

    const updateResults = async () => {
        try {
            const eventId = resultsContainer.dataset.eventId;
            const response = await fetch(`${window.SITE_URL}/api/results.php?event_id=${eventId}`);
            const data = await response.json();

            if (data.success) {
                updateResultsUI(data.results);
            }
        } catch (error) {
            console.error('Failed to update results:', error);
        }
    };

    // Update every 10 seconds
    setInterval(updateResults, 10000);
}

function updateResultsUI(results) {
    results.forEach(result => {
        const nomineeEl = document.querySelector(`[data-nominee-id="${result.nominee_id}"]`);
        if (nomineeEl) {
            const progressBar = nomineeEl.querySelector('.progress-bar');
            const voteCount = nomineeEl.querySelector('.vote-count');

            if (progressBar) progressBar.style.width = `${result.percentage}%`;
            if (voteCount) voteCount.textContent = formatNumber(result.votes);
        }
    });
}

// Add CSS animation
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
`;
document.head.appendChild(style);

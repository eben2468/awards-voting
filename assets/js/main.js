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

    // New sections
    const stkPushBanner = document.getElementById('stkPushBanner');
    const stkPendingSection = document.getElementById('stkPendingSection');
    const voteFormSection = document.getElementById('voteFormSection');
    const phoneInputSection = document.getElementById('phoneInputSection');

    let currentPrice = 0;
    let currentForm = null;
    let nomineeData = null;

    // Function to get initials from name
    function getInitials(name) {
        return name
            .split(' ')
            .map(word => word[0])
            .join('')
            .toUpperCase()
            .substring(0, 2);
    }

    // Reset modal to initial state
    function resetModalState() {
        // Show form, hide pending
        form.classList.remove('d-none');
        if (voteFormSection) voteFormSection.classList.remove('d-none');
        if (stkPendingSection) stkPendingSection.classList.add('d-none');
        if (stkPushBanner) stkPushBanner.classList.add('d-none');
        if (phoneInputSection) phoneInputSection.style.display = 'block';

        // Reset network select
        const voterNetworkSelect = document.getElementById('voterNetwork');
        if (voterNetworkSelect) voterNetworkSelect.value = '';

        // Reset phone
        const voterPhoneInput = document.getElementById('voterPhone');
        if (voterPhoneInput) {
            voterPhoneInput.value = '';
            voterPhoneInput.setAttribute('required', 'required');
        }

        // Reset submit button
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Proceed to Payment';
        }

        // Remove any card payment iframe
        const existingIframe = document.getElementById('cardPaymentIframeContainer');
        if (existingIframe) existingIframe.remove();
    }

    // Listen for modal hidden event to reset state
    modal.addEventListener('hidden.bs.modal', resetModalState);

    // Open modal on "Vote" button click (from cards)
    document.querySelectorAll('.btn-buy-votes').forEach(btn => {
        btn.addEventListener('click', function () {
            resetModalState();
            currentForm = this.closest('form');
            nomineeData = null;
            const nomineeName = this.dataset.nomineeName;
            currentPrice = parseFloat(this.dataset.price);

            // Find nominee photo from the card
            const card = this.closest('.card') || this.closest('.nominee-card');
            const photoImg = card.querySelector('.nominee-photo');
            const photo = photoImg ? photoImg.src : '';

            nomineeNameSpan.textContent = nomineeName;

            // Handle initials and photo
            const initialsDiv = document.getElementById('modalNomineeInitials');
            if (initialsDiv) {
                initialsDiv.textContent = getInitials(nomineeName);
                if (photo && !photo.includes('ui-avatars.com')) {
                    nomineePhotoImg.src = photo;
                    nomineePhotoImg.classList.remove('d-none');
                    initialsDiv.classList.add('d-none');
                } else {
                    nomineePhotoImg.classList.add('d-none');
                    initialsDiv.classList.remove('d-none');
                }
            } else {
                nomineePhotoImg.src = photo;
                nomineePhotoImg.classList.remove('d-none');
            }

            unitPriceSpan.textContent = currentPrice.toFixed(2);
            quantityInput.value = 1;
            updateTotal();

            bsModal.show();
        });
    });

    // Function to open modal from shortcode search
    window.openVoteModal = function (data) {
        resetModalState();
        currentForm = null;
        nomineeData = data;
        currentPrice = parseFloat(data.vote_price);

        nomineeNameSpan.textContent = data.name;

        // Handle initials and photo
        const initialsDiv = document.getElementById('modalNomineeInitials');
        if (initialsDiv) {
            initialsDiv.textContent = getInitials(data.name);
            if (data.photo && !data.photo.includes('ui-avatars.com')) {
                nomineePhotoImg.src = data.photo;
                nomineePhotoImg.classList.remove('d-none');
                initialsDiv.classList.add('d-none');
            } else {
                nomineePhotoImg.classList.add('d-none');
                initialsDiv.classList.remove('d-none');
            }
        } else {
            nomineePhotoImg.src = data.photo;
            nomineePhotoImg.classList.remove('d-none');
        }

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

    // Payment method switching logic
    const voterNetworkSelect = document.getElementById('voterNetwork');
    const voterPhoneInput = document.getElementById('voterPhone');

    if (voterNetworkSelect) {
        voterNetworkSelect.addEventListener('change', function () {
            const phoneLabel = phoneInputSection ? phoneInputSection.querySelector('label') : null;
            const phoneHelp = phoneInputSection ? phoneInputSection.querySelector('.form-text') : null;
            
            if (this.value === 'CARD') {
                // Keep phone input visible, update label/help text
                if (phoneInputSection) phoneInputSection.style.display = 'block';
                if (voterPhoneInput) voterPhoneInput.setAttribute('required', 'required');
                if (phoneLabel) {
                    phoneLabel.textContent = 'Phone number';
                }
                if (phoneHelp) {
                    phoneHelp.textContent = "You'll enter your card on NaloPay's secure page next. Your confirmation comes here by SMS.";
                }
            } else {
                // Show phone input, hide card form, reset labels
                if (phoneInputSection) phoneInputSection.style.display = 'block';
                if (voterPhoneInput) voterPhoneInput.setAttribute('required', 'required');
                if (phoneLabel) {
                    phoneLabel.textContent = 'MoMo number';
                }
                if (phoneHelp) {
                    phoneHelp.textContent = "We'll send a payment prompt to this number.";
                }
            }
        });

        // Trigger change event to set initial state if needed
        if (voterNetworkSelect.value) {
            voterNetworkSelect.dispatchEvent(new Event('change'));
        }
    }

    /**
     * Show the STK Pending state inside the modal
     */
    function showStkPendingState(nomineeName, nomineePhoto, shortcode, phoneNumber) {
        // Hide the form
        form.classList.add('d-none');
        if (voteFormSection) voteFormSection.classList.add('d-none');

        // Show banner
        if (stkPushBanner) {
            const bannerText = stkPushBanner.querySelector('.stk-push-text');
            if (bannerText) {
                bannerText.textContent = `Check your phone and approve the payment to complete your vote.`;
            }
            stkPushBanner.classList.remove('d-none');
        }

        // Show pending section
        if (stkPendingSection) {
            // Set nominee info
            const pendingName = document.getElementById('pendingNomineeName');
            const pendingInitials = document.getElementById('pendingNomineeInitials');
            const pendingPhoto = document.getElementById('pendingNomineePhoto');
            const pendingShortcodeBadge = document.getElementById('pendingShortcodeBadge');
            const pendingShortcodeText = document.getElementById('pendingShortcodeText');

            if (pendingName) pendingName.textContent = nomineeName || 'Nominee';

            if (pendingInitials && pendingPhoto) {
                if (nomineePhoto && !nomineePhoto.includes('ui-avatars.com')) {
                    pendingPhoto.src = nomineePhoto;
                    pendingPhoto.classList.remove('d-none');
                    pendingInitials.classList.add('d-none');
                } else {
                    pendingInitials.textContent = getInitials(nomineeName || 'NA');
                    pendingInitials.classList.remove('d-none');
                    pendingPhoto.classList.add('d-none');
                }
            }

            if (pendingShortcodeBadge && pendingShortcodeText && shortcode) {
                pendingShortcodeText.textContent = shortcode;
                pendingShortcodeBadge.classList.remove('d-none');
            } else if (pendingShortcodeBadge) {
                pendingShortcodeBadge.classList.add('d-none');
            }

            stkPendingSection.classList.remove('d-none');
        }
    }

    // ===== FORM SUBMISSION =====
    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        const submitBtn = form.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;

        // Get phone number and network from modal
        const phoneInput = document.getElementById('voterPhone');
        const networkInput = document.getElementById('voterNetwork');
        const phoneValue = phoneInput ? phoneInput.value.replace(/\D/g, '') : '';
        const networkValue = networkInput ? networkInput.value : '';

        // ===== PAYMENT: the server replies with a phone prompt or NaloPay's checkout =====
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="loading"></span> Processing...';

        // Get nominee info for pending state
        const currentNomineeName = nomineeNameSpan ? nomineeNameSpan.textContent : 'Nominee';
        const currentNomineePhoto = nomineePhotoImg && !nomineePhotoImg.classList.contains('d-none') ? nomineePhotoImg.src : '';

        // Get shortcode if available from card
        let shortcode = '';
        if (currentForm) {
            const card = currentForm.closest('.card') || currentForm.closest('.nominee-card');
            const shortcodeEl = card ? card.querySelector('.shortcode') : null;
            shortcode = shortcodeEl ? shortcodeEl.textContent.trim() : '';
        }

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

            // Add phone number to form
            let phoneHiddenInput = currentForm.querySelector('input[name="phone"]');
            if (!phoneHiddenInput) {
                phoneHiddenInput = document.createElement('input');
                phoneHiddenInput.type = 'hidden';
                phoneHiddenInput.name = 'phone';
                currentForm.appendChild(phoneHiddenInput);
            }
            phoneHiddenInput.value = phoneValue;

            // Add network to form
            let networkHiddenInput = currentForm.querySelector('input[name="network"]');
            if (!networkHiddenInput) {
                networkHiddenInput = document.createElement('input');
                networkHiddenInput.type = 'hidden';
                networkHiddenInput.name = 'network';
                currentForm.appendChild(networkHiddenInput);
            }
            networkHiddenInput.value = networkValue;

            // Instead of dispatching submit event and hiding modal immediately,
            // we intercept the AJAX response to show pending state
            try {
                const formData = new FormData(currentForm);
                const response = await fetch(currentForm.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const result = await response.json();

                if (result.success) {
                    if (result.payment_required && result.payment_data) {
                        if (result.payment_data.direct_push) {
                            // STK Push was sent - show pending state IN the modal
                            showStkPendingState(currentNomineeName, currentNomineePhoto, shortcode, phoneValue);
                            window.showPayApproval(result.payment_data.approval_code);
                            showToast('success', result.payment_data.message || 'Payment prompt sent! Please check your phone.');

                            if (result.payment_data.reference) {
                                startPaymentPolling(result.payment_data.reference, bsModal);
                            }
                        } else if (result.payment_data.checkout_url) {
                            // Fallback to hosted checkout - show in iframe
                            handleCardPaymentInModal(result.payment_data, currentNomineeName, currentNomineePhoto);
                        }
                    } else {
                        // Free vote success
                        bsModal.hide();
                        showToast('success', result.message);
                        setTimeout(() => window.location.reload(), 2000);
                    }
                } else {
                    showToast('danger', result.message);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                }
            } catch (error) {
                console.error('Vote submission error:', error);
                showToast('danger', 'An error occurred. Please try again.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            }

        } else if (nomineeData) {
            // Shortcode-initiated vote
            try {
                const formData = new FormData();
                formData.append('nominee_id', nomineeData.id);
                formData.append('category_id', nomineeData.category_id);
                formData.append('event_id', nomineeData.event_id);
                formData.append('quantity', quantityInput.value);
                formData.append('phone', phoneValue);
                formData.append('network', networkValue);

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
                    if (result.payment_required && result.payment_data) {
                        if (result.payment_data.direct_push) {
                            // STK Push sent - show pending state IN the modal
                            showStkPendingState(currentNomineeName, currentNomineePhoto, shortcode, phoneValue);
                            window.showPayApproval(result.payment_data.approval_code);
                            showToast('success', result.payment_data.message || 'Payment prompt sent! Please check your phone.');

                            if (result.payment_data.reference) {
                                startPaymentPolling(result.payment_data.reference, bsModal);
                            }
                        } else if (result.payment_data.checkout_url) {
                            // Fallback to hosted checkout - show in iframe
                            handleCardPaymentInModal(result.payment_data, currentNomineeName, currentNomineePhoto);
                        }
                    } else {
                        bsModal.hide();
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

    /**
     * Handle card payment by showing NaloPay checkout in an iframe inside the modal
     */
    function handleCardPaymentInModal(paymentData, nomineeName, nomineePhoto) {
        if (!paymentData.checkout_url) {
            showToast('danger', 'Payment initialization failed.');
            return;
        }

        // Hide the form and show a loading/iframe state
        form.classList.add('d-none');
        if (voteFormSection) voteFormSection.classList.add('d-none');

        // Create iframe container
        const iframeContainer = document.createElement('div');
        iframeContainer.id = 'cardPaymentIframeContainer';
        iframeContainer.className = 'card-payment-iframe-container';
        iframeContainer.innerHTML = `
            <div class="text-center mb-3">
                <p class="fw-semibold text-dark mb-1">Complete your card payment</p>
                <p class="text-muted small mb-0">You're paying on NaloPay's secure page</p>
            </div>
            <div class="iframe-wrapper">
                <iframe id="cardPaymentIframe" src="${paymentData.checkout_url}" frameborder="0" allowpaymentrequest="true"></iframe>
            </div>
            <div class="text-center mt-3">
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" id="cancelCardPayment">
                    <i class="bi bi-arrow-left"></i> Go Back
                </button>
            </div>
        `;

        // Insert after the form
        form.parentNode.insertBefore(iframeContainer, form.nextSibling);

        // Cancel button handler
        document.getElementById('cancelCardPayment').addEventListener('click', () => {
            iframeContainer.remove();
            resetModalState();
        });

        // Start polling for payment completion
        if (paymentData.reference) {
            startPaymentPolling(paymentData.reference, bsModal);
        }
    }
}

/**
 * Initialize Voting Forms with AJAX and NaloPay Integration
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
 * Handle NaloPay Payment Redirect (fallback for forms outside the modal)
 */
function handlePayment(paymentData, btn, originalText) {
    if (paymentData.checkout_url) {
        // Start polling in background before redirecting
        if (paymentData.reference) {
            startPaymentPolling(paymentData.reference);
        }
        // Redirect to NaloPay Hosted Checkout page
        window.location.href = paymentData.checkout_url;
    } else if (paymentData.direct_push) {
        // Direct STK Push initiated
        showToast('success', paymentData.message || 'Payment prompt sent! Please check your phone.');

        if (paymentData.reference) {
            startPaymentPolling(paymentData.reference);
        }

        if (btn) {
            btn.innerHTML = '<i class="bi bi-clock"></i> Pending...';
            btn.disabled = true;
        }
    } else {
        showToast('danger', 'Payment initialization failed. Please try again.');
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }
}

/**
 * Poll the server to check if payment is successful
 */
function startPaymentPolling(reference, bsModal) {
    console.log('Starting polling for reference:', reference);

    let pollCount = 0;
    const maxPolls = 120; // Poll for 10 minutes (5s * 120)

    const pollInterval = setInterval(async () => {
        pollCount++;
        if (pollCount > maxPolls) {
            clearInterval(pollInterval);
            console.log('Polling timed out');
            showToast('warning', 'Payment verification timed out. If you completed the payment, please refresh the page.');
            return;
        }

        try {
            const response = await fetch(`${window.SITE_URL}/api/check-payment.php?reference=${reference}`);
            const result = await response.json();

            if (result.success && result.status === 'COMPLETED') {
                clearInterval(pollInterval);

                // Hide the modal if it's open
                if (bsModal) {
                    try { bsModal.hide(); } catch (e) { }
                }

                showToast('success', 'Payment successful! Your vote has been recorded.');
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            }
        } catch (error) {
            console.error('Polling error:', error);
        }
    }, 5000); // Poll every 5 seconds
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

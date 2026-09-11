<!-- Ticket Purchase Modal -->
<div class="modal fade" id="ticketPurchaseModal" tabindex="-1" aria-labelledby="ticketPurchaseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-5 overflow-hidden">
            <div class="modal-header border-0 pb-0 position-relative">
                <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 pt-0">

                <!-- STK Push Banner (hidden by default) -->
                <div id="ticketStkPushBanner" class="stk-push-banner d-none">
                    <div class="stk-push-icon">
                        <i class="bi bi-phone-vibrate"></i>
                    </div>
                    <p class="stk-push-text">
                        An STK Push has been sent to your phone. Please approve to complete your purchase.
                    </p>
                    <button type="button" class="stk-push-close" id="ticketStkBannerClose" aria-label="Close">&times;</button>
                </div>

                <!-- Event Info -->
                <div id="ticketFormSection" class="text-center mb-4">
                    <div class="ticket-modal-banner mb-3">
                        <img src="" id="ticketModalBanner" class="rounded-3 w-100" style="max-height: 150px; object-fit: cover;">
                    </div>
                    <h4 id="ticketModalEventTitle" class="fw-bold mb-1 text-dark">Event Title</h4>
                    <p class="text-muted small mb-0">Select your ticket category and complete purchase</p>
                </div>

                <!-- Ticket Purchase Form -->
                <form id="ticketPurchaseForm">
                    <input type="hidden" id="ticketEventId">

                    <div class="mb-4">
                        <label for="ticketCategory" class="form-label fw-bold text-dark mb-2">Ticket Category</label>
                        <select class="form-select rounded-3 py-2" id="ticketCategory" required>
                            <option value="" selected disabled>Choose a category...</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label for="ticketQuantity" class="form-label fw-bold text-dark mb-2">Number of Tickets</label>
                        <div class="input-group vote-counter-group">
                            <button class="btn btn-counter" type="button" id="ticketBtnMinus">-</button>
                            <input type="number" class="form-control vote-input text-center fw-bold" id="ticketQuantity" value="1" min="1" max="50" required>
                            <button class="btn btn-counter" type="button" id="ticketBtnPlus">+</button>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="ticketNetwork" class="form-label fw-bold text-dark mb-2">Payment Method</label>
                        <select class="form-select rounded-3 py-2" id="ticketNetwork" required>
                            <option value="" selected disabled>Choose your payment method...</option>
                            <option value="MTN">MTN Mobile Money</option>
                            <option value="TELECEL">Telecel Cash (Vodafone)</option>
                            <option value="AT">AT Money (AirtelTigo)</option>
                            <option value="CARD">Debit / Credit Card (VISA/MasterCard)</option>
                        </select>
                    </div>

                    <!-- MoMo Phone Number -->
                    <div class="mb-4" id="ticketPhoneSection">
                        <label for="ticketPhone" class="form-label fw-bold text-dark mb-2">
                            MoMo Number <span class="text-muted fw-normal">(for STK Push)</span>
                        </label>
                        <div class="input-group phone-input-group">
                            <span class="input-group-text bg-white border-end-0">+233</span>
                            <input type="tel" class="form-control border-start-0 ps-0" id="ticketPhone" placeholder="24 XXX XXXX" maxlength="10" required>
                        </div>
                        <div class="form-text small text-muted mt-2">Enter your mobile money number to receive a payment prompt.</div>
                    </div>

                    <!-- Card Details Section (hidden by default) -->
                    <div id="ticketCardSection" class="card-details-section d-none">
                        <div class="card-details-header mb-3">
                            <i class="bi bi-credit-card-2-front"></i>
                            <span>Enter Card Details</span>
                            <div class="card-brands">
                                <img src="https://img.icons8.com/color/32/visa.png" alt="Visa" width="28" height="20">
                                <img src="https://img.icons8.com/color/32/mastercard-logo.png" alt="Mastercard" width="28" height="20">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="ticketCardholderName" class="form-label small fw-semibold text-dark">Cardholder Name</label>
                            <input type="text" class="form-control card-input" id="ticketCardholderName" placeholder="Ebenezer Owusu" autocomplete="cc-name">
                        </div>

                        <div class="mb-3">
                            <label for="ticketCardNumber" class="form-label small fw-semibold text-dark">Card Number</label>
                            <div class="input-group card-number-group">
                                <span class="input-group-text card-input-icon"><i class="bi bi-credit-card"></i></span>
                                <input type="text" class="form-control card-input" id="ticketCardNumber" placeholder="1234 5678 9012 3456" maxlength="19" autocomplete="cc-number" inputmode="numeric">
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-7">
                                <label class="form-label small fw-semibold text-dark">Expiry Date</label>
                                <div class="d-flex gap-2">
                                    <select class="form-select card-input card-select" id="ticketCardExpiryMonth" autocomplete="cc-exp-month">
                                        <option value="" disabled selected>MM</option>
                                        <option value="01">01</option>
                                        <option value="02">02</option>
                                        <option value="03">03</option>
                                        <option value="04">04</option>
                                        <option value="05">05</option>
                                        <option value="06">06</option>
                                        <option value="07">07</option>
                                        <option value="08">08</option>
                                        <option value="09">09</option>
                                        <option value="10">10</option>
                                        <option value="11">11</option>
                                        <option value="12">12</option>
                                    </select>
                                    <select class="form-select card-input card-select" id="ticketCardExpiryYear" autocomplete="cc-exp-year">
                                        <option value="" disabled selected>YY</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-5">
                                <label for="ticketCardCvv" class="form-label small fw-semibold text-dark">CVV</label>
                                <div class="input-group card-cvv-group">
                                    <input type="password" class="form-control card-input" id="ticketCardCvv" placeholder="•••" maxlength="4" autocomplete="cc-csc" inputmode="numeric">
                                    <span class="input-group-text card-cvv-info" data-bs-toggle="tooltip" data-bs-placement="top" title="3-digit security code on the back of your card"><i class="bi bi-question-circle"></i></span>
                                </div>
                            </div>
                        </div>
                        <div class="form-text small text-success mt-2 d-flex align-items-center gap-1">
                            <i class="bi bi-shield-check"></i> Your card details are encrypted and processed securely.
                        </div>
                    </div>

                    <div class="payment-summary-box rounded-4 p-4 mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-muted">Price per ticket:</span>
                            <span class="fw-bold text-dark">GHS <span id="ticketUnitPrice">0.00</span></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center pt-3 border-top border-light">
                            <span class="fw-bold text-dark">Total Amount:</span>
                            <span class="fw-bold text-primary fs-4">GHS <span id="ticketTotalAmount">0.00</span></span>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-proceed-payment w-100 rounded-pill py-3 fw-bold shadow-lg" id="btnBuyTicket">
                        <i class="bi bi-ticket-perforated me-2"></i>Purchase Ticket
                    </button>
                </form>

                <!-- STK Push Pending State -->
                <div id="ticketPendingSection" class="stk-pending-section d-none">
                    <div class="text-center">
                        <div class="ticket-modal-banner mb-3">
                            <img src="" id="pendingTicketBanner" class="rounded-3 w-100" style="max-height: 120px; object-fit: cover;">
                        </div>
                        <h4 id="pendingTicketTitle" class="fw-bold mb-1 text-dark">Event Title</h4>

                        <div class="voting-progress-container mt-3 mb-4">
                            <p class="text-muted mb-2">Processing ticket purchase</p>
                            <div class="voting-progress-bar">
                                <div class="voting-progress-fill"></div>
                            </div>
                        </div>

                        <button type="button" class="btn btn-pending-status w-100 rounded-pill py-3" disabled>
                            <i class="bi bi-clock"></i> Pending...
                        </button>
                    </div>
                </div>

                <!-- Success State -->
                <div id="ticketSuccessSection" class="d-none">
                    <div class="text-center py-4">
                        <div class="mb-3">
                            <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                        </div>
                        <h4 class="fw-bold text-dark mb-2">Ticket Purchased!</h4>
                        <p class="text-muted mb-3">Your ticket has been confirmed. Check your phone for the ticket code via SMS.</p>
                        <div class="ticket-success-ref bg-light rounded-3 p-3 mb-3">
                            <small class="text-muted d-block">Reference</small>
                            <strong id="ticketSuccessRef" class="text-primary">TKT-XXXXXXXX</strong>
                        </div>
                        <button type="button" class="btn btn-primary w-100 rounded-pill py-3" data-bs-dismiss="modal">
                            <i class="bi bi-check-lg me-2"></i>Done
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('ticketPurchaseModal');
    if (!modal) return;
    
    const bsModal = new bootstrap.Modal(modal);
    const form = document.getElementById('ticketPurchaseForm');
    const categorySelect = document.getElementById('ticketCategory');
    const quantityInput = document.getElementById('ticketQuantity');
    const networkSelect = document.getElementById('ticketNetwork');
    const phoneInput = document.getElementById('ticketPhone');
    const phoneSection = document.getElementById('ticketPhoneSection');
    const cardSection = document.getElementById('ticketCardSection');
    const unitPriceSpan = document.getElementById('ticketUnitPrice');
    const totalAmountSpan = document.getElementById('ticketTotalAmount');
    const eventIdInput = document.getElementById('ticketEventId');
    const btnBuy = document.getElementById('btnBuyTicket');
    const formSection = document.getElementById('ticketFormSection');
    const pendingSection = document.getElementById('ticketPendingSection');
    const successSection = document.getElementById('ticketSuccessSection');
    const stkBanner = document.getElementById('ticketStkPushBanner');

    let currentCategories = [];
    let pollInterval = null;

    // Open modal on "Buy Ticket" button click
    // Delegate event listener so dynamically added or late elements can also trigger it
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-ticket-buy');
        if (btn) {
            const eventId = btn.dataset.eventId;
            const eventTitle = btn.dataset.eventTitle;
            const banner = btn.dataset.eventBanner;
            const categories = JSON.parse(btn.dataset.categories);
            
            currentCategories = categories;
            
            // Set modal content
            document.getElementById('ticketModalBanner').src = banner;
            document.getElementById('ticketModalEventTitle').textContent = eventTitle;
            eventIdInput.value = eventId;
            
            // Populate categories
            categorySelect.innerHTML = '<option value="" selected disabled>Choose a category...</option>';
            categories.forEach(cat => {
                const opt = document.createElement('option');
                opt.value = cat.id;
                opt.textContent = cat.name + ' - GHS ' + parseFloat(cat.price).toFixed(2);
                opt.dataset.price = cat.price;
                categorySelect.appendChild(opt);
            });
            
            // Reset form
            quantityInput.value = 1;
            unitPriceSpan.textContent = '0.00';
            totalAmountSpan.textContent = '0.00';
            networkSelect.selectedIndex = 0;
            if (networkSelect) {
                networkSelect.dispatchEvent(new Event('change'));
            }
            phoneInput.value = '';
            
            // Show form, hide others
            formSection.classList.remove('d-none');
            form.classList.remove('d-none');
            pendingSection.classList.add('d-none');
            successSection.classList.add('d-none');
            stkBanner.classList.add('d-none');
            btnBuy.disabled = false;
            btnBuy.innerHTML = '<i class="bi bi-ticket-perforated me-2"></i>Purchase Ticket';
            
            bsModal.show();
        }
    });

    // Update price on category change
    categorySelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const price = parseFloat(selectedOption.dataset.price || 0);
        unitPriceSpan.textContent = price.toFixed(2);
        updateTotal();
    });

    // Quantity controls
    document.getElementById('ticketBtnMinus').addEventListener('click', () => {
        let val = parseInt(quantityInput.value) || 1;
        if (val > 1) { quantityInput.value = val - 1; updateTotal(); }
    });
    document.getElementById('ticketBtnPlus').addEventListener('click', () => {
        let val = parseInt(quantityInput.value) || 1;
        if (val < 50) { quantityInput.value = val + 1; updateTotal(); }
    });
    quantityInput.addEventListener('input', updateTotal);

    function updateTotal() {
        const price = parseFloat(unitPriceSpan.textContent) || 0;
        const qty = parseInt(quantityInput.value) || 1;
        totalAmountSpan.textContent = (price * qty).toFixed(2);
    }

    // Network selection toggle
    networkSelect.addEventListener('change', function() {
        const phoneLabel = phoneSection.querySelector('label');
        const phoneHelp = phoneSection.querySelector('.form-text');
        
        if (this.value === 'CARD') {
            phoneSection.classList.remove('d-none');
            phoneInput.setAttribute('required', 'required');
            if (phoneLabel) {
                phoneLabel.innerHTML = 'Phone Number <span class="text-muted fw-normal">(for Ticket SMS)</span>';
            }
            if (phoneHelp) {
                phoneHelp.textContent = 'Enter your phone number to receive your ticket code via SMS.';
            }
            cardSection.classList.remove('d-none');
        } else {
            phoneSection.classList.remove('d-none');
            phoneInput.setAttribute('required', 'required');
            if (phoneLabel) {
                phoneLabel.innerHTML = 'MoMo Number <span class="text-muted fw-normal">(for STK Push)</span>';
            }
            if (phoneHelp) {
                phoneHelp.textContent = 'Enter your mobile money number to receive a payment prompt.';
            }
            cardSection.classList.add('d-none');
        }
    });

    // STK banner close
    const stkBannerCloseBtn = document.getElementById('ticketStkBannerClose');
    if (stkBannerCloseBtn) {
        stkBannerCloseBtn.addEventListener('click', function() {
            stkBanner.classList.add('d-none');
        });
    }

    // Populate card expiry year options dynamically
    (function() {
        const yearSelect = document.getElementById('ticketCardExpiryYear');
        if (yearSelect) {
            const currentYear = new Date().getFullYear();
            for (let i = 0; i < 10; i++) {
                const yr = currentYear + i;
                const opt = document.createElement('option');
                opt.value = String(yr).slice(-2);
                opt.textContent = String(yr).slice(-2);
                yearSelect.appendChild(opt);
            }
        }

        // Card number formatting (add spaces every 4 digits)
        const cardNumberInput = document.getElementById('ticketCardNumber');
        if (cardNumberInput) {
            cardNumberInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                value = value.replace(/(.{4})/g, '$1 ').trim();
                e.target.value = value;
            });
        }

        // CVV - only allow digits
        const cvvInput = document.getElementById('ticketCardCvv');
        if (cvvInput) {
            cvvInput.addEventListener('input', function(e) {
                e.target.value = e.target.value.replace(/\D/g, '');
            });
        }
    })();

    function validateTicketCardDetails() {
        const cardNumber = document.getElementById('ticketCardNumber').value.replace(/\s/g, '');
        const expiryMonth = document.getElementById('ticketCardExpiryMonth').value;
        const expiryYear = document.getElementById('ticketCardExpiryYear').value;
        const cvv = document.getElementById('ticketCardCvv').value;
        const cardholderName = document.getElementById('ticketCardholderName').value.trim();

        if (!cardholderName) {
            alert('Please enter the cardholder name.');
            return false;
        }

        if (cardNumber.length < 13 || cardNumber.length > 19 || !/^\d+$/.test(cardNumber)) {
            alert('Please enter a valid card number.');
            return false;
        }

        if (!luhnCheck(cardNumber)) {
            alert('Invalid card number. Please check and try again.');
            return false;
        }

        if (!expiryMonth || !expiryYear) {
            alert('Please select the card expiry date.');
            return false;
        }

        const now = new Date();
        const currentMonth = now.getMonth() + 1;
        const currentYear = parseInt(String(now.getFullYear()).slice(-2));
        const expMonth = parseInt(expiryMonth);
        const expYear = parseInt(expiryYear);

        if (expYear < currentYear || (expYear === currentYear && expMonth < currentMonth)) {
            alert('Your card has expired. Please use a different card.');
            return false;
        }

        if (cvv.length < 3 || !/^\d+$/.test(cvv)) {
            alert('Please enter a valid CVV (3-4 digits).');
            return false;
        }

        return true;
    }

    function luhnCheck(num) {
        let arr = (num + '').split('').reverse().map(x => parseInt(x));
        let sum = arr.reduce((acc, val, i) => {
            if (i % 2 !== 0) {
                val *= 2;
                if (val > 9) val -= 9;
            }
            return acc + val;
        }, 0);
        return sum % 10 === 0;
    }

    // Reset modal on close
    modal.addEventListener('hidden.bs.modal', function() {
        if (pollInterval) { clearInterval(pollInterval); pollInterval = null; }
    });

    // Form submit
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const eventId = eventIdInput.value;
        const categoryId = categorySelect.value;
        const quantity = parseInt(quantityInput.value) || 1;
        const network = networkSelect.value;
        const phone = phoneInput.value;
        const csrfToken = document.getElementById('global_csrf_token').value;

        if (!categoryId) { alert('Please select a ticket category.'); return; }
        if (!network) { alert('Please select a payment method.'); return; }
        if (network !== 'CARD' && !phone) { alert('Please enter your phone number.'); return; }

        if (network === 'CARD') {
            if (!validateTicketCardDetails()) return;
        }

        // Disable button
        btnBuy.disabled = true;
        btnBuy.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';

        try {
            const formData = new FormData();
            formData.append('event_id', eventId);
            formData.append('ticket_category_id', categoryId);
            formData.append('quantity', quantity);
            formData.append('network', network);
            formData.append('phone', phone);
            formData.append('csrf_token', csrfToken);

            if (network === 'CARD') {
                formData.append('cardholder_name', document.getElementById('ticketCardholderName').value.trim());
                formData.append('card_number', document.getElementById('ticketCardNumber').value.replace(/\s/g, ''));
                formData.append('expiry_month', document.getElementById('ticketCardExpiryMonth').value);
                formData.append('expiry_year', document.getElementById('ticketCardExpiryYear').value);
                formData.append('cvv', document.getElementById('ticketCardCvv').value);
            }

            const response = await fetch(`${window.SITE_URL}/buy-ticket.php`, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            const data = await response.json();

            if (data.success) {
                if (data.payment_required && data.payment_data) {
                    if (data.payment_data.direct_push) {
                        // STK Push sent - show pending state
                        showTicketPending(data.payment_data.reference);
                    } else if (data.payment_data.checkout_url) {
                        // Card / hosted checkout - show in iframe or redirect
                        showCardCheckout(data.payment_data.checkout_url, data.payment_data.reference);
                    }
                } else {
                    // Card processed directly & successfully!
                    showTicketSuccess(data.payment_data.reference);
                }
            } else {
                alert(data.message || 'Failed to process ticket purchase.');
                btnBuy.disabled = false;
                btnBuy.innerHTML = '<i class="bi bi-ticket-perforated me-2"></i>Purchase Ticket';
            }
        } catch (err) {
            console.error('Ticket purchase error:', err);
            alert('An error occurred. Please try again.');
            btnBuy.disabled = false;
            btnBuy.innerHTML = '<i class="bi bi-ticket-perforated me-2"></i>Purchase Ticket';
        }
    });

    function showTicketPending(reference) {
        formSection.classList.add('d-none');
        form.classList.add('d-none');
        pendingSection.classList.remove('d-none');
        stkBanner.classList.remove('d-none');

        document.getElementById('pendingTicketBanner').src = document.getElementById('ticketModalBanner').src;
        document.getElementById('pendingTicketTitle').textContent = document.getElementById('ticketModalEventTitle').textContent;

        // Start polling
        startPaymentPolling(reference);
    }

    function showCardCheckout(checkoutUrl, reference) {
        // For card payments, show in an iframe inside the modal body
        formSection.classList.add('d-none');
        form.classList.add('d-none');
        
        const iframeContainer = document.createElement('div');
        iframeContainer.id = 'ticketIframeContainer';
        iframeContainer.innerHTML = `
            <iframe src="${checkoutUrl}" style="width: 100%; height: 500px; border: none; border-radius: 0.5rem;"></iframe>
        `;
        modal.querySelector('.modal-body').appendChild(iframeContainer);

        // Start polling for payment completion
        startPaymentPolling(reference);
    }

    function startPaymentPolling(reference) {
        let attempts = 0;
        const maxAttempts = 60; // 5 minutes at 5-second intervals

        pollInterval = setInterval(async () => {
            attempts++;
            if (attempts > maxAttempts) {
                clearInterval(pollInterval);
                pollInterval = null;
                return;
            }

            try {
                const resp = await fetch(`${window.SITE_URL}/api/check-payment.php?reference=${reference}`);
                const result = await resp.json();

                if (result.status === 'COMPLETED' || result.status === 'SUCCESS' || result.status === 'success') {
                    clearInterval(pollInterval);
                    pollInterval = null;
                    showTicketSuccess(reference);
                } else if (result.status === 'FAILED' || result.status === 'failed') {
                    clearInterval(pollInterval);
                    pollInterval = null;
                    alert('Payment failed. Please try again.');
                    bsModal.hide();
                }
            } catch (err) {
                console.error('Poll error:', err);
            }
        }, 5000);
    }

    function showTicketSuccess(reference) {
        // Remove any iframe container
        const iframeEl = document.getElementById('ticketIframeContainer');
        if (iframeEl) iframeEl.remove();
        
        formSection.classList.add('d-none');
        form.classList.add('d-none');
        pendingSection.classList.add('d-none');
        stkBanner.classList.add('d-none');
        successSection.classList.remove('d-none');
        document.getElementById('ticketSuccessRef').textContent = reference;
    }
});
</script>

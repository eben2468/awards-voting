<!-- Ticket Purchase Modal -->
<div class="modal fade" id="ticketPurchaseModal" tabindex="-1" aria-labelledby="ticketPurchaseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down pay-modal">
        <div class="modal-content border-0 shadow-lg rounded-5 overflow-hidden">
            <div class="modal-header border-0 pb-0 position-relative">
                <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">

                <!-- STK Push Banner (hidden by default) -->
                <div id="ticketStkPushBanner" class="stk-push-banner d-none">
                    <div class="stk-push-icon">
                        <i class="bi bi-phone-vibrate"></i>
                    </div>
                    <p class="stk-push-text">
                        Check your phone and approve the payment to get your ticket.
                    </p>
                    <button type="button" class="stk-push-close" id="ticketStkBannerClose" aria-label="Close">&times;</button>
                </div>

                <!-- Event Info -->
                <div id="ticketFormSection" class="pay-head">
                    <img src="" id="ticketModalBanner" class="pay-thumb" alt="">
                    <div class="pay-head-text">
                        <h4 id="ticketModalEventTitle">Event Title</h4>
                        <p>Choose your ticket and pay.</p>
                    </div>
                </div>

                <!-- Ticket Purchase Form -->
                <form id="ticketPurchaseForm">
                    <input type="hidden" id="ticketEventId">

                    <fieldset class="pay-field">
                        <legend>Ticket type</legend>
                        <select class="pay-native" id="ticketCategory" required tabindex="-1" aria-hidden="true">
                            <option value="" selected disabled>Choose a category...</option>
                        </select>
                        <div class="pay-tiles" data-pay-for="ticketCategory" data-pay-auto></div>
                        <p class="pay-error" hidden>Choose a ticket type.</p>
                    </fieldset>

                    <div class="pay-field">
                        <label for="ticketQuantity" class="pay-label">Number of tickets</label>
                        <div class="input-group vote-counter-group">
                            <button class="btn btn-counter" type="button" id="ticketBtnMinus" aria-label="One fewer ticket">&minus;</button>
                            <input type="number" class="form-control vote-input text-center fw-bold" id="ticketQuantity" value="1" min="1" max="50" required>
                            <button class="btn btn-counter" type="button" id="ticketBtnPlus" aria-label="One more ticket">+</button>
                        </div>
                    </div>

                    <fieldset class="pay-field">
                        <legend>How do you want to pay?</legend>
                        <select class="pay-native" id="ticketNetwork" required tabindex="-1" aria-hidden="true">
                            <option value="" selected disabled>Choose your payment method...</option>
                            <option value="MTN">MTN Mobile Money</option>
                            <option value="TELECEL">Telecel Cash (Vodafone)</option>
                            <option value="AT">AT Money (AirtelTigo)</option>
                            <option value="CARD">Debit / Credit Card (VISA/MasterCard)</option>
                        </select>
                        <div class="pay-tiles pay-tiles-method" data-pay-for="ticketNetwork"></div>
                        <p class="pay-error" hidden>Choose how you want to pay.</p>
                    </fieldset>

                    <!-- MoMo Phone Number -->
                    <div class="pay-field" id="ticketPhoneSection">
                        <label for="ticketPhone" class="pay-label">MoMo number</label>
                        <div class="input-group phone-input-group">
                            <span class="input-group-text">+233</span>
                            <input type="tel" class="form-control ps-0 pay-phone" id="ticketPhone" placeholder="24 XXX XXXX" maxlength="16" pattern="0?[1-9][0-9]{8}" inputmode="tel" autocomplete="tel-national" required>
                        </div>
                        <div class="form-text small text-muted mt-2">We'll send a payment prompt to this number.</div>
                        <p class="pay-error" hidden>Enter a valid phone number, such as 024 123 4567.</p>
                    </div>

                    <div class="pay-bar">
                        <div class="pay-total">
                            <small>Total</small>
                            <strong>GHS <span id="ticketTotalAmount">0.00</span></strong>
                            <small>GHS <span id="ticketUnitPrice">0.00</span> per ticket</small>
                        </div>
                        <button type="submit" class="btn btn-proceed-payment" id="btnBuyTicket"><i class="bi bi-ticket-perforated me-2"></i>Purchase Ticket</button>
                    </div>
                </form>

                <!-- STK Push Pending State -->
                <div id="ticketPendingSection" class="stk-pending-section d-none">
                    <div class="text-center">
                        <div class="ticket-modal-banner mb-3">
                            <img src="" id="pendingTicketBanner" class="pay-thumb" alt="">
                        </div>
                        <h4 id="pendingTicketTitle" class="fw-bold mb-1 text-dark">Event Title</h4>

                        <div class="voting-progress-container mt-3 mb-4">
                            <p class="text-muted mb-2">Waiting for your approval</p>
                            <div class="voting-progress-bar">
                                <div class="voting-progress-fill"></div>
                            </div>

                            <p class="pay-approval" data-approval hidden>No prompt on your phone? <a data-approval-link href="#"></a> to approve.</p>
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
                opt.dataset.name = cat.name;
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
            
            // A checkout left open from an earlier attempt must not stay on screen
            const oldCheckout = document.getElementById('ticketIframeContainer');
            if (oldCheckout) oldCheckout.remove();

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
                phoneLabel.textContent = 'Phone number';
            }
            if (phoneHelp) {
                phoneHelp.textContent = "You'll enter your card on NaloPay's secure page next. Your ticket code comes here by SMS.";
            }
        } else {
            phoneSection.classList.remove('d-none');
            phoneInput.setAttribute('required', 'required');
            if (phoneLabel) {
                phoneLabel.textContent = 'MoMo number';
            }
            if (phoneHelp) {
                phoneHelp.textContent = "We'll send a payment prompt to this number.";
            }
        }
    });

    // STK banner close
    const stkBannerCloseBtn = document.getElementById('ticketStkBannerClose');
    if (stkBannerCloseBtn) {
        stkBannerCloseBtn.addEventListener('click', function() {
            stkBanner.classList.add('d-none');
        });
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
        if (!phone) { alert('Please enter your phone number.'); return; }

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
                        window.showPayApproval(data.payment_data.approval_code);
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

<!-- Vote Quantity Modal -->
<div class="modal fade" id="voteQuantityModal" tabindex="-1" aria-labelledby="voteQuantityModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-5 overflow-hidden">
            <div class="modal-header border-0 pb-0 position-relative">
                <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 pt-0">

                <!-- ===== STK Push Confirmation Banner (hidden by default) ===== -->
                <div id="stkPushBanner" class="stk-push-banner d-none">
                    <div class="stk-push-icon">
                        <i class="bi bi-phone-vibrate"></i>
                    </div>
                    <p class="stk-push-text">
                        An STK Push has been sent to your phone. Please approve to complete your vote.
                    </p>
                    <button type="button" class="stk-push-close" id="stkBannerClose" aria-label="Close">&times;</button>
                </div>

                <!-- ===== Nominee Info ===== -->
                <div id="voteFormSection" class="text-center mb-4">
                    <div class="nominee-avatar-container mb-3">
                        <div id="modalNomineeInitials" class="nominee-initials shadow-sm mx-auto">KA</div>
                        <img src="" id="modalNomineePhoto" class="rounded-circle shadow-sm mx-auto d-none" style="width: 100px; height: 100px; object-fit: cover; border: 4px solid #fff;">
                    </div>
                    <h3 id="modalNomineeName" class="fw-bold mb-1 text-dark">Kwesi Appiah</h3>
                    <p class="text-muted small mb-0">Enter the number of votes you want to cast</p>
                </div>

                <!-- ===== Main Vote Form ===== -->
                <form id="modalVoteForm">
                    <input type="hidden" id="modalNomineeId">
                    <input type="hidden" id="modalCategoryId">
                    <input type="hidden" id="modalEventId">
                    
                    <div class="mb-4">
                        <label for="voteQuantity" class="form-label fw-bold text-dark mb-2">Number of Votes</label>
                        <div class="input-group vote-counter-group">
                            <button class="btn btn-counter" type="button" id="btnMinus">-</button>
                            <input type="number" class="form-control vote-input text-center fw-bold" id="voteQuantity" value="21" min="1" required>
                            <button class="btn btn-counter" type="button" id="btnPlus">+</button>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="voterNetwork" class="form-label fw-bold text-dark mb-2">Payment Method</label>
                        <select class="form-select rounded-3 py-2" id="voterNetwork" required>
                            <option value="" selected disabled>Choose your payment method...</option>
                            <option value="MTN">MTN Mobile Money</option>
                            <option value="TELECEL">Telecel Cash (Vodafone)</option>
                            <option value="AT">AT Money (AirtelTigo)</option>
                            <option value="CARD">Debit / Credit Card (VISA/MasterCard)</option>
                        </select>
                    </div>

                    <!-- MoMo Phone Number Input -->
                    <div class="mb-4" id="phoneInputSection">
                        <label for="voterPhone" class="form-label fw-bold text-dark mb-2">
                            MoMo Number <span class="text-muted fw-normal">(for STK Push)</span>
                        </label>
                        <div class="input-group phone-input-group">
                            <span class="input-group-text bg-white border-end-0">+233</span>
                            <input type="tel" class="form-control border-start-0 ps-0" id="voterPhone" placeholder="24 XXX XXXX" maxlength="10" required>
                        </div>
                        <div class="form-text small text-muted mt-2">Enter your mobile money number to receive a payment prompt.</div>
                    </div>

                    <!-- ===== Card Details Section (hidden by default) ===== -->
                    <div id="cardDetailsSection" class="card-details-section d-none">
                        <div class="card-details-header mb-3">
                            <i class="bi bi-credit-card-2-front"></i>
                            <span>Enter Card Details</span>
                            <div class="card-brands">
                                <img src="https://img.icons8.com/color/32/visa.png" alt="Visa" width="28" height="20">
                                <img src="https://img.icons8.com/color/32/mastercard-logo.png" alt="Mastercard" width="28" height="20">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="cardholderName" class="form-label small fw-semibold text-dark">Cardholder Name</label>
                            <input type="text" class="form-control card-input" id="cardholderName" placeholder="Ebenezer Owusu" autocomplete="cc-name">
                        </div>

                        <div class="mb-3">
                            <label for="cardNumber" class="form-label small fw-semibold text-dark">Card Number</label>
                            <div class="input-group card-number-group">
                                <span class="input-group-text card-input-icon"><i class="bi bi-credit-card"></i></span>
                                <input type="text" class="form-control card-input" id="cardNumber" placeholder="1234 5678 9012 3456" maxlength="19" autocomplete="cc-number" inputmode="numeric">
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-7">
                                <label class="form-label small fw-semibold text-dark">Expiry Date</label>
                                <div class="d-flex gap-2">
                                    <select class="form-select card-input card-select" id="cardExpiryMonth" autocomplete="cc-exp-month">
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
                                    <select class="form-select card-input card-select" id="cardExpiryYear" autocomplete="cc-exp-year">
                                        <option value="" disabled selected>YY</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-5">
                                <label for="cardCvv" class="form-label small fw-semibold text-dark">CVV</label>
                                <div class="input-group">
                                    <input type="password" class="form-control card-input" id="cardCvv" placeholder="•••" maxlength="4" autocomplete="cc-csc" inputmode="numeric">
                                    <span class="input-group-text card-input-icon" title="3 or 4 digit number on the back of your card"><i class="bi bi-question-circle"></i></span>
                                </div>
                            </div>
                        </div>

                        <div class="card-secure-note">
                            <i class="bi bi-shield-lock-fill"></i>
                            <span>Your card details are encrypted and processed securely.</span>
                        </div>
                    </div>

                    <div class="payment-summary-box rounded-4 p-4 mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-muted">Price per vote:</span>
                            <span class="fw-bold text-dark">GHS <span id="modalUnitPrice">1.00</span></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center pt-3 border-top border-light">
                            <span class="fw-bold text-dark">Total Amount:</span>
                            <span class="fw-bold text-primary fs-4">GHS <span id="modalTotalAmount">21.00</span></span>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-proceed-payment w-100 rounded-pill py-3 fw-bold shadow-lg" id="btnProceedPayment">
                        Proceed to Payment
                    </button>
                </form>

                <!-- ===== STK Push Pending State (replaces the form after STK push) ===== -->
                <div id="stkPendingSection" class="stk-pending-section d-none">
                    <div class="text-center">
                        <div class="nominee-avatar-container mb-3">
                            <div id="pendingNomineeInitials" class="nominee-initials shadow-sm mx-auto d-none">KA</div>
                            <img src="" id="pendingNomineePhoto" class="rounded-circle shadow-sm mx-auto d-none" style="width: 100px; height: 100px; object-fit: cover; border: 4px solid #fff;">
                        </div>
                        <h3 id="pendingNomineeName" class="fw-bold mb-1 text-dark">Nominee Name</h3>

                        <div class="pending-shortcode-badge" id="pendingShortcodeBadge">
                            <span id="pendingShortcodeText"></span>
                        </div>

                        <div class="voting-progress-container mt-3 mb-4">
                            <p class="text-muted mb-2">Voting in progress</p>
                            <div class="voting-progress-bar">
                                <div class="voting-progress-fill"></div>
                            </div>
                        </div>

                        <button type="button" class="btn btn-pending-status w-100 rounded-pill py-3" disabled>
                            <i class="bi bi-clock"></i> Pending...
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
// Populate card expiry year options dynamically
(function() {
    const yearSelect = document.getElementById('cardExpiryYear');
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
    const cardNumberInput = document.getElementById('cardNumber');
    if (cardNumberInput) {
        cardNumberInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/(.{4})/g, '$1 ').trim();
            e.target.value = value;
        });
    }

    // CVV - only allow digits
    const cvvInput = document.getElementById('cardCvv');
    if (cvvInput) {
        cvvInput.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '');
        });
    }

    // STK banner close
    const closeBanner = document.getElementById('stkBannerClose');
    if (closeBanner) {
        closeBanner.addEventListener('click', function() {
            document.getElementById('stkPushBanner').classList.add('d-none');
        });
    }
})();
</script>

<!-- Vote Quantity Modal -->
<div class="modal fade" id="voteQuantityModal" tabindex="-1" aria-labelledby="voteQuantityModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down pay-modal">
        <div class="modal-content border-0 shadow-lg rounded-5 overflow-hidden">
            <div class="modal-header border-0 pb-0 position-relative">
                <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">

                <!-- ===== STK Push Confirmation Banner (hidden by default) ===== -->
                <div id="stkPushBanner" class="stk-push-banner d-none">
                    <div class="stk-push-icon">
                        <i class="bi bi-phone-vibrate"></i>
                    </div>
                    <p class="stk-push-text">
                        Check your phone and approve the payment to complete your vote.
                    </p>
                    <button type="button" class="stk-push-close" id="stkBannerClose" aria-label="Close">&times;</button>
                </div>

                <!-- ===== Nominee Info ===== -->
                <div id="voteFormSection" class="pay-head">
                    <div class="nominee-avatar-container">
                        <div id="modalNomineeInitials" class="nominee-initials">KA</div>
                        <img src="" id="modalNomineePhoto" class="pay-thumb is-round d-none" alt="">
                    </div>
                    <div class="pay-head-text">
                        <h3 id="modalNomineeName">Kwesi Appiah</h3>
                        <p>Choose your votes and pay.</p>
                    </div>
                </div>

                <!-- ===== Main Vote Form ===== -->
                <form id="modalVoteForm">
                    <input type="hidden" id="modalNomineeId">
                    <input type="hidden" id="modalCategoryId">
                    <input type="hidden" id="modalEventId">
                    
                    <div class="pay-field">
                        <label for="voteQuantity" class="pay-label">Number of votes</label>
                        <div class="input-group vote-counter-group">
                            <button class="btn btn-counter" type="button" id="btnMinus" aria-label="One fewer vote">&minus;</button>
                            <input type="number" class="form-control vote-input text-center fw-bold" id="voteQuantity" value="21" min="1" required>
                            <button class="btn btn-counter" type="button" id="btnPlus" aria-label="One more vote">+</button>
                        </div>
                        <div class="pay-quick" aria-label="Quick amounts">
                            <button type="button" data-qty="5">5</button>
                            <button type="button" data-qty="10">10</button>
                            <button type="button" data-qty="20">20</button>
                            <button type="button" data-qty="50">50</button>
                            <button type="button" data-qty="100">100</button>
                        </div>
                    </div>

                    <fieldset class="pay-field">
                        <legend>How do you want to pay?</legend>
                        <select class="pay-native" id="voterNetwork" required tabindex="-1" aria-hidden="true">
                            <option value="" selected disabled>Choose your payment method...</option>
                            <option value="MTN">MTN Mobile Money</option>
                            <option value="TELECEL">Telecel Cash (Vodafone)</option>
                            <option value="AT">AT Money (AirtelTigo)</option>
                            <option value="CARD">Debit / Credit Card (VISA/MasterCard)</option>
                        </select>
                        <div class="pay-tiles pay-tiles-method" data-pay-for="voterNetwork"></div>
                        <p class="pay-error" hidden>Choose how you want to pay.</p>
                    </fieldset>

                    <!-- MoMo Phone Number Input -->
                    <div class="pay-field" id="phoneInputSection">
                        <label for="voterPhone" class="pay-label">MoMo number</label>
                        <div class="input-group phone-input-group">
                            <span class="input-group-text">+233</span>
                            <input type="tel" class="form-control ps-0 pay-phone" id="voterPhone" placeholder="24 XXX XXXX" maxlength="16" pattern="0?[1-9][0-9]{8}" inputmode="tel" autocomplete="tel-national" required>
                        </div>
                        <div class="form-text small text-muted mt-2">We'll send a payment prompt to this number.</div>
                        <p class="pay-error" hidden>Enter a valid phone number, such as 024 123 4567.</p>
                    </div>

                    <div class="pay-bar">
                        <div class="pay-total">
                            <small>Total</small>
                            <strong>GHS <span id="modalTotalAmount">21.00</span></strong>
                            <small>GHS <span id="modalUnitPrice">1.00</span> per vote</small>
                        </div>
                        <button type="submit" class="btn btn-proceed-payment" id="btnProceedPayment">Proceed to Payment</button>
                    </div>
                </form>

                <!-- ===== STK Push Pending State (replaces the form after STK push) ===== -->
                <div id="stkPendingSection" class="stk-pending-section d-none">
                    <div class="text-center">
                        <div class="nominee-avatar-container mb-3">
                            <div id="pendingNomineeInitials" class="nominee-initials mx-auto d-none">KA</div>
                            <img src="" id="pendingNomineePhoto" class="pay-thumb is-round mx-auto d-none" alt="">
                        </div>
                        <h3 id="pendingNomineeName" class="fw-bold mb-1 text-dark">Nominee Name</h3>

                        <div class="pending-shortcode-badge" id="pendingShortcodeBadge">
                            <span id="pendingShortcodeText"></span>
                        </div>

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

            </div>
        </div>
    </div>
</div>

<script>
(function() {
    // STK banner close
    const closeBanner = document.getElementById('stkBannerClose');
    if (closeBanner) {
        closeBanner.addEventListener('click', function() {
            document.getElementById('stkPushBanner').classList.add('d-none');
        });
    }
})();
</script>

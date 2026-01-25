<!-- Vote Quantity Modal -->
<div class="modal fade" id="voteQuantityModal" tabindex="-1" aria-labelledby="voteQuantityModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="voteQuantityModalLabel">Purchase Votes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="text-center mb-4">
                    <div class="nominee-avatar-sm mb-3">
                        <img src="" id="modalNomineePhoto" class="rounded-circle shadow-sm" style="width: 80px; height: 80px; object-fit: cover; border: 3px solid var(--primary);">
                    </div>
                    <h5 id="modalNomineeName" class="fw-bold mb-1"></h5>
                    <p class="text-muted small mb-0">Enter the number of votes you want to cast</p>
                </div>

                <form id="modalVoteForm">
                    <input type="hidden" id="modalNomineeId">
                    <input type="hidden" id="modalCategoryId">
                    <input type="hidden" id="modalEventId">
                    
                    <div class="mb-3">
                        <label for="voteQuantity" class="form-label fw-semibold">Number of Votes</label>
                        <div class="input-group input-group-lg">
                            <button class="btn btn-outline-secondary border-2" type="button" id="btnMinus">-</button>
                            <input type="number" class="form-control border-2 text-center fw-bold" id="voteQuantity" value="1" min="1" required>
                            <button class="btn btn-outline-secondary border-2" type="button" id="btnPlus">+</button>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="voterPhone" class="form-label fw-semibold">
                            Phone Number <span class="text-muted fw-normal">(for SMS confirmation)</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-2">+233</span>
                            <input type="tel" class="form-control border-2" id="voterPhone" placeholder="24 XXX XXXX" maxlength="10" pattern="[0-9]{9,10}">
                        </div>
                        <div class="form-text small">Optional: Receive payment confirmation via SMS</div>
                    </div>

                    <div class="price-summary bg-light rounded-3 p-3 mb-4">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Price per vote:</span>
                            <span class="fw-semibold">GHS <span id="modalUnitPrice">0.00</span></span>
                        </div>
                        <div class="d-flex justify-content-between border-top pt-2">
                            <span class="fw-bold">Total Amount:</span>
                            <span class="fw-bold text-primary fs-5">GHS <span id="modalTotalAmount">0.00</span></span>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100 rounded-pill py-3 fw-bold shadow-sm">
                        Proceed to Payment
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

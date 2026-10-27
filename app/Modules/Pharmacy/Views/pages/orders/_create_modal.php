<div class="modal fade pharmacy-order-create-modal" id="pharmacyOrderCreateModal" tabindex="-1" role="dialog" aria-labelledby="pharmacyOrderCreateTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
        <form class="modal-content" id="pharmacyOrderCreateForm" method="post" action="<?= base_url('pharmacy/orders/create') ?>">
            <div class="modal-header"><h2 class="modal-title h5" id="pharmacyOrderCreateTitle">Create medicine order</h2><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
            <div class="modal-body">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="orderPrescription">Select Prescription</label>
                    <select class="form-control btSelect" data-live-search="true" data-size="8" name="prescription_id" id="orderPrescription" title="Search and select an approved prescription" required aria-describedby="prescription_idErr">
                        <option value="">Search and select an approved prescription</option>
                        <?php foreach (($approvedPrescriptions ?? []) as $prescription): ?>
                            <?php
                            $patientName = trim(implode(' ', array_filter([
                                trim((string) ($prescription['first_name'] ?? '')),
                                trim((string) ($prescription['middle_name'] ?? '')),
                                trim((string) ($prescription['last_name'] ?? '')),
                            ])));
                            $patientLabel = $patientName !== '' ? $patientName : 'Patient name unavailable';
                            $prescriptionNumber = trim((string) ($prescription['prescription_number'] ?? ''));
                            $prescriptionLabel = $prescriptionNumber !== '' ? 'Prescription #' . $prescriptionNumber : 'Prescription number unavailable';
                            ?>
                            <option value="<?= (int) $prescription['id'] ?>"><?= esc($patientLabel . ' — ' . $prescriptionLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="validation-error" id="prescription_idErr"></div>
                </div>
                <div id="pharmacyPrescriptionState" class="pharmacy-prescription-state text-muted" role="status">Select an approved prescription to load its medicines.</div>
                <div id="pharmacyPrescriptionDetails" class="pharmacy-prescription-details" hidden></div>
                <section id="pharmacyOrderPricing" class="pharmacy-order-pricing" aria-labelledby="pharmacyOrderPricingTitle" hidden>
                    <h3 id="pharmacyOrderPricingTitle" class="pharmacy-order-pricing-title">Order Summary</h3>
                    <div class="pharmacy-order-pricing-grid">
                        <div class="pharmacy-order-summary-row pharmacy-order-summary-subtotal"><span>Medicine Subtotal</span><strong id="medicineSubtotalDisplay">₹0.00</strong></div>
                        <div class="form-group mb-0 pharmacy-order-summary-row pharmacy-order-summary-charge"><label for="courierCharges">Courier Charges</label><div class="input-group"><div class="input-group-prepend"><span class="input-group-text">₹</span></div><input class="form-control pharmacy-pricing-input" type="number" name="courier_charges" id="courierCharges" min="0" max="9999999999.99" step="0.01" inputmode="decimal" value="0" aria-describedby="courier_chargesErr"></div><div class="validation-error" id="courier_chargesErr"></div></div>
                        <div class="form-group mb-0 pharmacy-order-summary-control pharmacy-order-summary-discount"><label for="discountType">Discount</label><div class="input-group pharmacy-discount-group"><select class="form-control pharmacy-pricing-input" name="discount_type" id="discountType" aria-label="Discount type"><option value="fixed">Fixed amount</option><option value="percentage">Percentage</option></select><div class="input-group-prepend"><span class="input-group-text" id="discountPrefix">−₹</span></div><input class="form-control pharmacy-pricing-input" type="number" name="discount" id="discountAmount" min="0" max="9999999999.99" step="0.01" inputmode="decimal" value="0" placeholder="Discount amount" aria-label="Discount amount" aria-describedby="discountErr"></div><div class="validation-error" id="discountErr"></div></div>
                        <div class="form-group mb-0 pharmacy-order-summary-row pharmacy-order-summary-charge"><label for="otherCharges">Packaging Charges</label><div class="input-group"><div class="input-group-prepend"><span class="input-group-text">₹</span></div><input class="form-control pharmacy-pricing-input" type="number" name="tax" id="otherCharges" min="0" max="9999999999.99" step="0.01" inputmode="decimal" value="0" aria-describedby="taxErr"></div><div class="validation-error" id="taxErr"></div></div>
                    </div>
                    <div class="pharmacy-order-grand-total"><span>Grand Total</span><strong id="grandTotalDisplay">INR 0.00</strong></div>
                </section>
                <?php if (($approvedPrescriptions ?? []) === []): ?>
                    <div class="alert alert-info mb-0" role="status"><i class="bi bi-info-circle mr-1" aria-hidden="true"></i>No approved prescriptions are available for a new medicine order.</div>
                <?php endif; ?>
            </div>
            <div class="modal-footer"><button class="btn btn-light" type="button" data-dismiss="modal">Close</button><button class="btn btn-primary" type="submit" <?= (($approvedPrescriptions ?? []) === []) ? 'disabled' : '' ?>>Create order</button></div>
        </form>
    </div>
</div>

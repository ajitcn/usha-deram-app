<?= $this->extend(PHARMACY_VIEW . 'layout') ?>
<?= $this->section('main-content') ?>
<main class="dashboard-container pharmacy-billing-page" aria-labelledby="pharmacy-billing-title">
    <?= $this->include(PHARMACY_VIEW . 'pages/billing/_toolbar') ?>
    <section class="card pharmacy-billing-card" aria-label="Billing and payment records">
        <div class="table-responsive pharmacy-billing-table-wrap">
            <table class="table pharmacy-billing-table mb-0">
                <caption class="sr-only">Pharmacy billing and payment records</caption>
                <thead><tr><th scope="col">Invoice</th><th scope="col">Patient</th><th scope="col">Amount</th><th scope="col">Payment status</th><th scope="col">Delivery status</th><th scope="col">Actions</th></tr></thead>
                <tbody>
                <?php if (($rows ?? []) === []): ?>
                    <tr><td colspan="6"><div class="pharmacy-billing-empty" role="status"><i class="bi bi-receipt d-block" aria-hidden="true"></i><strong>No billing records found</strong><span>Payable orders without invoices are shown as unavailable.</span></div></td></tr>
                <?php else: foreach ($rows as $row): ?>
                    <?php $paymentMeta = pharmacy_status_meta('payment', $row['payment_status'] ?? null); $deliveryMeta = pharmacy_status_meta('delivery', $row['delivery_status'] ?? null); ?>
                    <tr>
                        <td data-label="Invoice"><strong class="billing-value" title="<?= esc($row['invoice_number'], 'attr') ?>"><?= esc($row['invoice_number']) ?></strong><small class="billing-meta d-block">Invoice date: <?= esc($row['created_at']) ?></small><small class="billing-meta d-block">Order: <?= esc($row['order_number']) ?></small></td>
                        <td data-label="Patient"><strong class="billing-value d-block" title="<?= esc($row['patient_name'], 'attr') ?>"><?= esc($row['patient_name']) ?></strong><small class="billing-meta d-block"><?= esc($row['patient_number']) ?></small><small class="billing-meta d-block"><?= esc($row['patient_mobile']) ?></small></td>
                        <td data-label="Amount"><strong class="billing-amount"><?= esc($row['currency']) ?> <?= number_format((float) $row['amount'], 2) ?></strong><small class="billing-meta d-block">Subtotal: <?= number_format((float) $row['subtotal'], 2) ?></small><small class="billing-meta d-block">Courier: <?= number_format((float) ($row['courier_charges'] ?? 0), 2) ?></small><?php if ((float) $row['tax'] !== 0.0): ?><small class="billing-meta d-block">Packaging charges: <?= number_format((float) $row['tax'], 2) ?></small><?php endif; ?><?php if ((float) $row['discount'] !== 0.0): ?><small class="billing-meta d-block">Discount: <?= number_format((float) $row['discount'], 2) ?></small><?php endif; ?></td>
                        <td data-label="Payment status"><span class="billing-status-badge <?= esc($paymentMeta['tone'], 'attr') ?>"><?= esc($row['payment_status_label'] ?? $paymentMeta['label']) ?></span><span class="order-meta d-block mt-1">Paid: <?= esc($row['currency']) ?> <?= number_format((float) ($row['paid_amount'] ?? 0), 2) ?></span><span class="order-meta d-block">Balance: <?= esc($row['currency']) ?> <?= number_format((float) ($row['remaining_balance'] ?? 0), 2) ?></span><span class="order-meta d-block">Provider: <?= esc($row['provider_name'] ?? $row['provider'] ?? 'Not recorded') ?></span></td>
                        <td data-label="Delivery status"><span class="billing-status-badge <?= esc($deliveryMeta['tone'], 'attr') ?>"><?= esc($deliveryMeta['label']) ?></span></td>
                        <td data-label="Actions" class="billing-actions-cell"><?= view(PHARMACY_VIEW . 'pages/billing/_actions', ['row' => $row]) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?= $this->include(PHARMACY_VIEW . 'parts/billing_pagination') ?>
    </section>
</main>
<?= $this->include(PHARMACY_VIEW . 'pages/billing/_modal') ?>
<?= $this->include(PHARMACY_VIEW . 'pages/orders/_labels_modal') ?>
<?= $this->section('scripts') ?><?= $this->include(PHARMACY_VIEW . 'pages/billing/_scripts') ?><?= $this->endSection() ?>
<?= $this->endSection() ?>

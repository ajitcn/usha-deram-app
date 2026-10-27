<?= $this->extend(PHARMACY_VIEW . 'layout') ?>
<?= $this->section('main-content') ?>
<main class="dashboard-container pharmacy-billing-page" aria-labelledby="pharmacy-billing-title">
    <?= $this->include(PHARMACY_VIEW . 'pages/billing/_toolbar') ?>
    <section class="pharmacy-billing-grid" aria-label="Billing and payment cards">
        <?php if (($rows ?? []) === []): ?>
            <div class="card pharmacy-billing-empty" role="status"><i class="bi bi-receipt d-block" aria-hidden="true"></i><strong>No billing records found</strong><span>Adjust the search or filters.</span></div>
        <?php else: foreach ($rows as $row): $paymentMeta = pharmacy_status_meta('payment', $row['payment_status'] ?? null); $deliveryMeta = pharmacy_status_meta('delivery', $row['delivery_status'] ?? null); ?>
            <article class="card pharmacy-billing-item">
                <header class="billing-card-header"><div class="min-width-0"><span class="billing-card-eyebrow">Invoice</span><h2 class="h6 mb-1 billing-value" title="<?= esc($row['invoice_number'], 'attr') ?>"><?= esc($row['invoice_number']) ?></h2><div class="billing-card-meta"><span><?= esc($row['created_at']) ?></span><span>Order: <?= esc($row['order_number']) ?></span></div></div><span class="billing-status-badge <?= esc($paymentMeta['tone'], 'attr') ?>"><?= esc($row['payment_status_label'] ?? $paymentMeta['label']) ?></span></header>
                <section class="billing-card-section billing-card-patient" aria-label="Patient and pharmacy"><span class="billing-card-label">Patient / customer</span><strong class="billing-value" title="<?= esc($row['patient_name'], 'attr') ?>"><?= esc($row['patient_name']) ?></strong><div class="billing-card-meta-list"><span class="billing-meta"><?= esc($row['patient_number']) ?></span><span class="billing-meta"><?= esc($row['patient_mobile']) ?></span></div></section>
                <section class="billing-card-section billing-card-amount" aria-label="Invoice totals"><div><span class="billing-card-label">Grand total</span><strong class="billing-amount"><?= esc($row['currency']) ?> <?= number_format((float) $row['amount'], 2) ?></strong></div><div><span class="billing-card-label">Medicine subtotal</span><span class="billing-meta"><?= esc($row['currency']) ?> <?= number_format((float) $row['subtotal'], 2) ?></span></div><div><span class="billing-card-label">Packaging charges</span><span class="billing-meta"><?= esc($row['currency']) ?> <?= number_format((float) ($row['tax'] ?? 0), 2) ?></span></div><div><span class="billing-card-label">Discount</span><span class="billing-meta">&minus;<?= esc($row['currency']) ?> <?= number_format((float) ($row['discount'] ?? 0), 2) ?></span></div><div><span class="billing-card-label">Paid amount</span><span class="billing-meta"><?= esc($row['currency']) ?> <?= number_format((float) ($row['paid_amount'] ?? 0), 2) ?></span></div><div><span class="billing-card-label">Remaining balance</span><span class="billing-meta"><?= esc($row['currency']) ?> <?= number_format((float) ($row['remaining_balance'] ?? 0), 2) ?></span></div></section>
                <section class="billing-card-section billing-card-statuses" aria-label="Payment and delivery status"><div><span class="billing-card-label">Payment status</span><span class="billing-status-badge <?= esc($paymentMeta['tone'], 'attr') ?>"><?= esc($row['payment_status_label'] ?? $paymentMeta['label']) ?></span><small class="d-block mt-1">Provider: <?= esc($row['provider_name'] ?? $row['provider'] ?? 'Not recorded') ?></small></div><div><span class="billing-card-label">Delivery status</span><span class="billing-status-badge <?= esc($deliveryMeta['tone'], 'attr') ?>"><?= esc($deliveryMeta['label']) ?></span></div></section>
                <footer class="billing-card-footer"><?= view(PHARMACY_VIEW . 'pages/billing/_actions', ['row' => $row]) ?></footer>
            </article>
        <?php endforeach; endif; ?>
    </section>
    <?= $this->include(PHARMACY_VIEW . 'parts/billing_pagination') ?>
</main>
<?= $this->include(PHARMACY_VIEW . 'pages/billing/_modal') ?>
<?= $this->include(PHARMACY_VIEW . 'pages/orders/_labels_modal') ?>
<?= $this->section('scripts') ?><?= $this->include(PHARMACY_VIEW . 'pages/billing/_scripts') ?><?= $this->endSection() ?>
<?= $this->endSection() ?>

<?= $this->extend(PHARMACY_VIEW . 'layout') ?>
<?= $this->section('main-content') ?>
<main class="dashboard-container pharmacy-orders-page" aria-labelledby="pharmacy-orders-title">
    <?= $this->include(PHARMACY_VIEW . 'pages/orders/_toolbar') ?>
    <section class="pharmacy-order-grid" aria-label="Medicine order cards">
        <?php if (($orders ?? []) === []): ?>
            <div class="card pharmacy-orders-empty pharmacy-orders-grid-empty" role="status">
                <i class="bi bi-bag-x" aria-hidden="true"></i>
                <strong>No medicine orders found</strong>
                <span>Adjust the search or filters, or create an order from an approved prescription.</span>
            </div>
        <?php else: foreach ($orders as $order): ?>
            <?php
            $orderStatusMeta = pharmacy_status_meta('order', $order['status'] ?? null);
            $paymentStatusMeta = pharmacy_status_meta('payment', $order['payment_status'] ?? null);
            $deliveryStatusValue = trim((string) ($order['delivery_status'] ?? ''));
            $deliveryStatusMeta = pharmacy_status_meta('delivery', $deliveryStatusValue);
            $orderStatusLabel = $orderStatusMeta['label'];
            $paymentStatusLabel = $order['payment_status_label'] ?? $paymentStatusMeta['label'];
            $deliveryStatusLabel = $deliveryStatusMeta['label'];
            $currency = 'INR';
            ?>
            <article class="card pharmacy-order-card pharmacy-order-grid-card">
                <header class="pharmacy-order-card-header">
                    <div class="pharmacy-order-card-heading">
                        <span class="pharmacy-order-card-eyebrow">Medicine order</span>
                        <h2 class="pharmacy-order-card-number order-value" title="<?= esc($order['order_number'], 'attr') ?>"><?= esc($order['order_number']) ?></h2>
                        <div class="pharmacy-order-card-date"><i class="bi bi-calendar3" aria-hidden="true"></i><span><?= esc($order['order_date']) ?></span></div>
                    </div>
                    <span class="pharmacy-status-badge pharmacy-status-badge--<?= esc($orderStatusMeta['tone'], 'attr') ?> order-status-badge"><?= esc($orderStatusLabel) ?></span>
                </header>

                <section class="pharmacy-order-card-section pharmacy-order-card-patient" aria-label="Patient and prescription">
                    <div class="pharmacy-order-card-field">
                        <span class="pharmacy-order-card-label">Patient</span>
                        <strong class="pharmacy-order-card-value" title="<?= esc($order['patient_name'], 'attr') ?>"><?= esc($order['patient_name']) ?></strong>
                    </div>
                    <div class="pharmacy-order-card-reference">
                        <span><span class="pharmacy-order-card-label">Prescription</span><strong><?= esc($order['prescription_number']) ?></strong></span>
                        <span><span class="pharmacy-order-card-label">Patient ID</span><strong><?= esc($order['patient_number']) ?></strong></span>
                    </div>
                </section>

                <section class="pharmacy-order-card-section pharmacy-order-card-medicines" aria-label="Medicine summary">
                    <div class="pharmacy-order-card-section-heading"><span>Medicine summary</span><span class="pharmacy-order-card-count"><?= (int) $order['medicine_count'] ?> <?= (int) $order['medicine_count'] === 1 ? 'medicine' : 'medicines' ?></span></div>
                    <p class="pharmacy-order-card-medicine-names" title="<?= esc($order['medicine_summary'], 'attr') ?>"><?= esc($order['medicine_summary']) ?></p>
                    <div class="pharmacy-order-card-metric"><span>Quantity</span><strong><?= (int) $order['medicine_count'] ?> <?= (int) $order['medicine_count'] === 1 ? 'item' : 'items' ?></strong></div>
                    <div class="pharmacy-order-card-metric"><span>Medicine amount</span><strong><?= esc($currency) ?> <?= number_format((float) ($order['medicine_subtotal'] ?? 0), 2) ?></strong></div>
                </section>

                <section class="pharmacy-order-card-section pharmacy-order-card-pricing" aria-label="Order pricing">
                    <div class="pharmacy-order-card-section-heading"><span>Order total</span><span class="pharmacy-order-card-invoice"><?= esc($order['invoice_number']) ?></span></div>
                    <dl class="pharmacy-order-card-price-list">
                        <div><dt>Medicine subtotal</dt><dd><?= esc($currency) ?> <?= number_format((float) ($order['medicine_subtotal'] ?? 0), 2) ?></dd></div>
                        <div><dt>Courier charges</dt><dd><?= esc($currency) ?> <?= number_format((float) ($order['courier_charges'] ?? 0), 2) ?></dd></div>
                        <div><dt>Packaging charges</dt><dd><?= esc($currency) ?> <?= number_format((float) ($order['other_charges'] ?? 0), 2) ?></dd></div>
                        <div><dt>Discount</dt><dd class="pharmacy-order-card-discount">−<?= esc($currency) ?> <?= number_format((float) ($order['discount'] ?? 0), 2) ?></dd></div>
                        <div class="pharmacy-order-card-total"><dt>Grand total</dt><dd><?= esc($currency) ?> <?= number_format((float) ($order['total_amount'] ?? 0), 2) ?></dd></div>
                    </dl>
                </section>

                <section class="pharmacy-order-card-section pharmacy-order-card-statuses" aria-label="Payment and delivery status">
                    <div class="pharmacy-order-card-status">
                        <span class="pharmacy-order-card-label">Payment</span>
                        <span class="pharmacy-status-badge pharmacy-status-badge--<?= esc($paymentStatusMeta['tone'], 'attr') ?> payment-status-badge"><?= esc($paymentStatusLabel) ?></span>
                        <span class="pharmacy-order-card-meta"><?= esc($order['payment_provider_name'] ?? $order['payment_provider']) ?></span>
                        <span class="pharmacy-order-card-meta">Paid: <?= esc($currency) ?> <?= number_format((float) ($order['paid_amount'] ?? 0), 2) ?> · Balance: <?= esc($currency) ?> <?= number_format((float) ($order['remaining_balance'] ?? 0), 2) ?></span>
                    </div>
                    <div class="pharmacy-order-card-status">
                        <span class="pharmacy-order-card-label">Delivery</span>
                        <span class="pharmacy-status-badge pharmacy-status-badge--<?= esc($deliveryStatusMeta['tone'], 'attr') ?> delivery-status-badge"><?= esc($deliveryStatusLabel) ?></span>
                        <span class="pharmacy-order-card-meta" title="<?= esc($order['tracking_reference'], 'attr') ?>">Tracking: <?= esc($order['tracking_reference']) ?></span>
                    </div>
                </section>

                <footer class="pharmacy-order-card-footer">
                    <span class="pharmacy-order-card-footer-label">Order actions</span>
                    <?= view(PHARMACY_VIEW . 'pages/orders/_actions', ['order' => $order]) ?>
                </footer>
            </article>
        <?php endforeach; endif; ?>
    </section>
    <?= $this->include(PHARMACY_VIEW . 'parts/order_pagination') ?>
</main>
<?= $this->include(PHARMACY_VIEW . 'pages/orders/_detail_modal') ?>
<?= $this->include(PHARMACY_VIEW . 'pages/orders/_create_modal') ?>
<?= $this->include(PHARMACY_VIEW . 'pages/orders/_labels_modal') ?>
<?= $this->include(PHARMACY_VIEW . 'pages/billing/_modal') ?>
<?= $this->include(PHARMACY_VIEW . 'pages/delivery/_modal') ?>
<?= $this->section('scripts') ?><?= $this->include(PHARMACY_VIEW . 'pages/orders/_scripts') ?><?= $this->endSection() ?>
<?= $this->endSection() ?>

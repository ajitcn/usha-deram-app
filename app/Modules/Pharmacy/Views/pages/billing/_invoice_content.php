<?php
$i = $invoice ?? [];
$currency = (string) ($i['currency'] ?? 'INR');
$money = static fn ($value): string => $currency . ' ' . number_format((float) $value, 2);
$shared = !empty($isShared);
?>
<article class="pharmacy-invoice-sheet">
    <header class="pharmacy-invoice-header">
        <div class="pharmacy-invoice-brand-block"><div class="pharmacy-invoice-brand">USHA DERMA</div><p>Pharmacy invoice</p><?php if ($shared): ?><span class="pharmacy-invoice-shared-note">Secure shared invoice &middot; Expires <?= esc($i['share_expires_at'] ?? 'soon') ?></span><?php endif; ?></div>
        <div class="pharmacy-invoice-heading"><span class="pharmacy-invoice-eyebrow">Invoice</span><h1 id="pharmacy-invoice-title"><?= esc($i['invoice_number'] ?? 'Not recorded') ?></h1><span>Invoice date: <?= esc($i['invoice_created_at'] ?? $i['created_at'] ?? 'Not recorded') ?></span></div>
    </header>
    <section class="pharmacy-invoice-parties" aria-label="Invoice parties and references">
        <div><span class="pharmacy-invoice-label">Pharmacy</span><strong><?= esc($i['clinic_name'] ?? 'USHA DERMA Clinic') ?></strong><small>USHA DERMA Pharmacy</small></div>
        <div><span class="pharmacy-invoice-label">Patient / customer</span><strong><?= esc($i['patient_name'] ?? 'Not recorded') ?></strong><small><?= esc($i['patient_number'] ?? 'Not recorded') ?></small></div>
        <div><span class="pharmacy-invoice-label">Order number</span><strong><?= esc($i['order_number'] ?? 'Not recorded') ?></strong><small>Currency: <?= esc($currency) ?></small></div>
        <div><span class="pharmacy-invoice-label">Payment status</span><strong class="pharmacy-invoice-status pharmacy-invoice-status--<?= esc(pharmacy_status_meta('payment', $i['payment_status'] ?? null)['tone'], 'attr') ?>"><?= esc($i['payment_status_label'] ?? pharmacy_status_meta('payment', $i['payment_status'] ?? null)['label']) ?></strong><small>Reference: <?= esc($i['payment_reference'] ?? 'Not recorded') ?></small></div>
    </section>
    <section class="pharmacy-invoice-table-wrap" aria-label="Medicines">
        <table class="pharmacy-invoice-table"><thead><tr><th scope="col">Medicine</th><th scope="col" class="text-right">Qty</th><th scope="col" class="text-right">Rate</th><th scope="col" class="text-right">Amount</th></tr></thead><tbody>
        <?php foreach (($i['items'] ?? []) as $item): ?><tr><td><?= esc($item['description'] ?? $item['medicine_name'] ?? 'Medicine') ?></td><td class="text-right"><?= esc((string) ($item['quantity'] ?? 'Not recorded')) ?></td><td class="text-right"><?= esc($money($item['unit_price'] ?? 0)) ?></td><td class="text-right"><?= esc($money($item['line_total'] ?? 0)) ?></td></tr><?php endforeach; ?>
        <?php if (($i['items'] ?? []) === []): ?><tr><td colspan="4" class="text-center">No medicine items recorded.</td></tr><?php endif; ?>
        </tbody></table>
    </section>
    <section class="pharmacy-invoice-summary" aria-label="Invoice totals and payment history">
        <div class="pharmacy-invoice-payment"><h2>Payment history</h2><?php if (($i['payment_history'] ?? []) === []): ?><p>No payments recorded.</p><?php else: ?><ul><?php foreach ($i['payment_history'] as $payment): $paymentMeta = pharmacy_status_meta('payment', $payment['status'] ?? null); ?><li><div><span><?= esc($payment['paid_at'] ?? 'Not recorded') ?></span><small><?= esc($payment['payment_method'] ?? 'Not recorded') ?> &middot; <?= esc($payment['reference_number'] ?? 'No reference') ?></small></div><strong><?= esc($money($payment['amount'] ?? 0)) ?></strong><em class="pharmacy-invoice-status pharmacy-invoice-status--<?= esc($paymentMeta['tone'], 'attr') ?>"><?= esc($paymentMeta['label']) ?></em></li><?php endforeach; ?></ul><?php endif; ?></div>
        <div class="pharmacy-invoice-totals"><div><span>Medicine subtotal</span><strong><?= esc($money($i['subtotal'] ?? 0)) ?></strong></div><div><span>Packaging charges</span><strong><?= esc($money($i['tax'] ?? 0)) ?></strong></div><div><span>Discount</span><strong>&minus;<?= esc($money($i['discount'] ?? 0)) ?></strong></div><div class="grand-total"><span>Grand total</span><strong><?= esc($money($i['amount'] ?? 0)) ?></strong></div><div><span>Paid amount</span><strong><?= esc($money($i['paid_amount'] ?? 0)) ?></strong></div><div><span>Remaining balance</span><strong><?= esc($money($i['remaining_balance'] ?? 0)) ?></strong></div></div>
    </section>
</article>

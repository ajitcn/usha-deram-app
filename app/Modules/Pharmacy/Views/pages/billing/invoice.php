<?= $this->extend(PHARMACY_VIEW . 'layout') ?>
<?= $this->section('main-content') ?>
<?php $i = $invoice ?? []; $shared = !empty($isShared); $shareUrl = $shared ? '' : base_url('pharmacy/billing/invoice/share/' . (int) ($i['id'] ?? 0)); ?>
<main class="dashboard-container pharmacy-invoice-page" aria-labelledby="pharmacy-invoice-title">
    <div class="pharmacy-invoice-actions no-print">
        <?php if (!$shared): ?><a class="btn btn-light" href="<?= esc(base_url('pharmacy/billing')) ?>">Back to billing</a><?php endif; ?>
        <button class="btn btn-light" type="button" onclick="window.print()"><i class="bi bi-printer mr-1" aria-hidden="true"></i>Print invoice</button>
        <?php if ($shared): ?><a class="btn btn-primary" href="<?= esc(base_url('pharmacy/billing/invoice/shared/' . rawurlencode((string) ($i['share_token'] ?? '')) . '/pdf')) ?>"><i class="bi bi-download mr-1" aria-hidden="true"></i>Download Invoice</a><?php else: ?><a class="btn btn-primary" href="<?= esc(base_url('pharmacy/billing/invoice/' . (int) ($i['id'] ?? 0) . '/pdf')) ?>"><i class="bi bi-download mr-1" aria-hidden="true"></i>Download Invoice</a><a class="btn btn-outline-primary" target="_blank" rel="noopener" href="<?= esc($shareUrl) ?>"><i class="bi bi-share mr-1" aria-hidden="true"></i>Share Invoice</a><?php endif; ?>
    </div>
    <?= $this->include(PHARMACY_VIEW . 'pages/billing/_invoice_content', ['invoice' => $i, 'isShared' => $shared]) ?>
</main>
<?= $this->endSection() ?>

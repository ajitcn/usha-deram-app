<?php $this->setData([
    'eyebrow' => 'Operations / fulfilment',
    'title' => 'Medicine Orders',
    'titleId' => 'pharmacy-orders-title',
    'description' => 'Review prescription fulfilment, stock allocation, payment, and delivery status.',
    'count' => $pagination['total'] ?? 0,
    'countLabel' => 'orders matched',
    'listUrl' => base_url('pharmacy/orders?' . http_build_query(array_merge($filters ?? [], ['view' => 'list', 'page' => 1]))),
    'gridUrl' => base_url('pharmacy/orders?' . http_build_query(array_merge($filters ?? [], ['view' => 'grid', 'page' => 1]))),
    'view' => $view ?? 'list',
    'primaryLabel' => in_array('pharmacy.order.create', session('permissions') ?? [], true) ? 'Create order' : '',
    'primaryUrl' => '#',
    'primaryTarget' => '#pharmacyOrderCreateModal',
]); ?>
<?= $this->include(PHARMACY_VIEW . 'parts/inner_page_header') ?>
<?php
$kpis = $orderKpis ?? [];
$orderStatusOptions = ['pending', 'fulfilled', 'cancelled'];
$paymentStatusOptions = ['unpaid', 'partially_paid', 'paid', 'pending', 'authorized', 'failed', 'refunded', 'cancelled'];
$deliveryStatusOptions = ['pending', 'packed', 'dispatched', 'out_for_delivery', 'delivered', 'failed', 'returned'];
$kpiCards = [
    ['key' => 'total', 'label' => 'Total orders', 'filter' => []],
    ['key' => 'pending', 'label' => 'Pending', 'filter' => ['status' => 'pending']],
    ['key' => 'fulfilled', 'label' => 'Completed', 'filter' => ['status' => 'fulfilled']],
    ['key' => 'cancelled', 'label' => 'Cancelled', 'filter' => ['status' => 'cancelled']],
    ['key' => 'unpaid', 'label' => 'Unpaid', 'filter' => ['payment_status' => 'unpaid']],
    ['key' => 'partially_paid', 'label' => 'Partially paid', 'filter' => ['payment_status' => 'partially_paid']],
    ['key' => 'paid', 'label' => 'Paid', 'filter' => ['payment_status' => 'paid']],
];
$kpiBase = $filters ?? [];
unset($kpiBase['status'], $kpiBase['payment_status'], $kpiBase['page']);
?>
<section class="pharmacy-order-kpis" aria-label="Order and payment summary">
    <?php foreach ($kpiCards as $card): $urlFilters = array_merge($kpiBase, $card['filter'], ['view' => $view ?? 'list', 'page' => 1]); ?>
        <a class="pharmacy-order-kpi <?= $card['filter'] === [] && (($filters['status'] ?? '') === '' && ($filters['payment_status'] ?? '') === '') ? 'is-active' : '' ?>" href="<?= esc(base_url('pharmacy/orders?' . http_build_query($urlFilters)), 'attr') ?>">
            <span><?= esc($card['label']) ?></span><strong><?= (int) ($kpis[$card['key']] ?? 0) ?></strong>
        </a>
    <?php endforeach; ?>
</section>
<form class="pharmacy-orders-filters" method="get" action="<?= base_url('pharmacy/orders') ?>">
    <input type="hidden" name="view" value="<?= esc($view ?? 'list') ?>">
    <div class="form-group mb-0 flex-grow-1"><label for="orderSearch">Search</label><input class="form-control form-control-sm" id="orderSearch" name="search" value="<?= esc($filters['search'] ?? '') ?>" placeholder="Order, patient, prescription, status"></div>
    <div class="form-group mb-0"><label for="orderStatus">Order status</label><select class="form-control form-control-sm" id="orderStatus" name="status"><option value="">All</option><?php foreach ($orderStatusOptions as $option): $meta = pharmacy_status_meta('order', $option); ?><option value="<?= esc($option, 'attr') ?>" <?= ($filters['status'] ?? '') === $option ? 'selected' : '' ?>><?= esc($meta['label']) ?></option><?php endforeach; ?></select></div>
    <div class="form-group mb-0"><label for="paymentStatus">Payment</label><select class="form-control form-control-sm" id="paymentStatus" name="payment_status"><option value="">All</option><?php foreach ($paymentStatusOptions as $option): $meta = pharmacy_status_meta('payment', $option); ?><option value="<?= esc($option, 'attr') ?>" <?= ($filters['payment_status'] ?? '') === $option ? 'selected' : '' ?>><?= esc($meta['label']) ?></option><?php endforeach; ?></select></div>
    <div class="form-group mb-0"><label for="deliveryStatus">Delivery</label><select class="form-control form-control-sm" id="deliveryStatus" name="delivery_status"><option value="">All</option><?php foreach ($deliveryStatusOptions as $option): $meta = pharmacy_status_meta('delivery', $option); ?><option value="<?= esc($option, 'attr') ?>" <?= ($filters['delivery_status'] ?? '') === $option ? 'selected' : '' ?>><?= esc($meta['label']) ?></option><?php endforeach; ?></select></div>
    <div class="form-group mb-0"><label for="orderPageSize">Page size</label><select class="form-control form-control-sm" id="orderPageSize" name="page_size"><?php foreach ([10, 15, 25, 50] as $size): ?><option value="<?= $size ?>" <?= (int) ($filters['page_size'] ?? 15) === $size ? 'selected' : '' ?>><?= $size ?></option><?php endforeach; ?></select></div>
    <div class="form-group mb-0"><label for="orderFrom">From</label><input class="form-control form-control-sm datepicker" id="orderFrom" name="from" placeholder="dd-mm-yyyy" value="<?= esc($filters['from'] ?? '') ?>" autocomplete="off"></div>
    <div class="form-group mb-0"><label for="orderTo">To</label><input class="form-control form-control-sm datepicker" id="orderTo" name="to" placeholder="dd-mm-yyyy" value="<?= esc($filters['to'] ?? '') ?>" autocomplete="off"></div>
    <div class="pharmacy-filter-actions"><button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-search mr-1" aria-hidden="true"></i>Apply</button><a class="btn btn-sm btn-light" href="<?= base_url('pharmacy/orders') ?>">Clear</a></div>
</form>

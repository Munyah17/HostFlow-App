<?php
use App\Services\InvoiceService;
use App\Services\PaymentService;
$balance = InvoiceService::balance($invoice);
?><!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
@page{margin:36px 40px}
body{font-family:"DejaVu Sans",sans-serif;font-size:11px;color:#18263d}
.top{width:100%;border-collapse:collapse;margin-bottom:24px}
.brand{background:#112443;color:#fff;padding:16px 18px;font-size:18px;font-weight:bold}
.brand small{display:block;font-size:8px;letter-spacing:1px;color:#aabbd4;font-weight:normal;margin-top:3px}
h1{font-size:20px;margin:0;color:#112443}
.muted{color:#728198}
table.items{width:100%;border-collapse:collapse;margin-top:18px}
table.items th{background:#f8fafc;color:#728198;font-size:9px;text-align:left;padding:8px;border-bottom:1px solid #e5ebf3}
table.items td{padding:8px;border-bottom:1px solid #eef2f7}
.r{text-align:right}
.totals{width:260px;margin-left:auto;margin-top:12px;border-collapse:collapse}
.totals td{padding:6px 8px}
.totals .grand td{font-weight:bold;font-size:13px;border-top:2px solid #112443}
.stamp{display:inline-block;padding:5px 12px;border-radius:12px;font-weight:bold;font-size:11px}
.paid{background:#e8f5ef;color:#15805d}.pending{background:#fff3e3;color:#aa741d}.overdue{background:#fcebee;color:#c54455}.cancelled,.draft{background:#eef2f7;color:#5d6b82}
.foot{margin-top:30px;font-size:9px;color:#728198;border-top:1px solid #e5ebf3;padding-top:10px}
</style>
</head>
<body>
<table class="top"><tr>
  <td style="width:55%;vertical-align:top"><div class="brand">◈ <?= e(setting('business_name')) ?><small>WEB HOSTING · EMAIL HOSTING</small></div>
    <div class="muted" style="margin-top:8px"><?= nl2br(e(setting('business_address'))) ?><br><?= e(setting('business_email')) ?> <?= e(setting('business_phone')) ?><br><?= e(setting('business_website')) ?></div></td>
  <td style="vertical-align:top;text-align:right"><h1><?= $invoice['status'] === 'paid' ? 'RECEIPT / INVOICE' : 'INVOICE' ?></h1>
    <div style="font-size:14px;margin:4px 0"><b><?= e($invoice['number']) ?></b></div>
    <span class="stamp <?= e($invoice['status']) ?>"><?= e(strtoupper($invoice['status'])) ?></span>
    <div class="muted" style="margin-top:8px">Issued: <?= date_fmt($invoice['issue_date']) ?><br>Due: <?= date_fmt($invoice['due_date']) ?><?php if ($invoice['paid_at']): ?><br>Paid: <?= date_fmt($invoice['paid_at']) ?><?php endif; ?></div></td>
</tr></table>

<div><span class="muted">BILL TO</span><br><b><?= e($invoice['business_name'] ?: $invoice['full_name']) ?></b><?php if ($invoice['business_name']): ?><br><?= e($invoice['full_name']) ?><?php endif; ?><br><?= e($invoice['email']) ?><?php if ($invoice['address']): ?><br><?= e($invoice['address']) ?><?php endif; ?><br><?= e(trim(($invoice['city'] ?? '') . ' ' . ($invoice['country'] ?? ''))) ?></div>

<table class="items">
  <thead><tr><th>Description</th><th class="r">Qty</th><th class="r">Unit price</th><th class="r">Amount</th></tr></thead>
  <tbody>
  <?php foreach ($items as $item): ?>
    <tr><td><?= e($item['description']) ?></td><td class="r"><?= rtrim(rtrim(number_format((float) $item['quantity'], 2), '0'), '.') ?></td><td class="r"><?= money($item['unit_price'], $invoice['currency']) ?></td><td class="r"><?= money($item['amount'], $invoice['currency']) ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
<table class="totals">
  <tr><td>Subtotal</td><td class="r"><?= money($invoice['subtotal'], $invoice['currency']) ?></td></tr>
  <?php if ((float) $invoice['discount'] > 0): ?><tr><td>Discount</td><td class="r">-<?= money($invoice['discount'], $invoice['currency']) ?></td></tr><?php endif; ?>
  <tr class="grand"><td>Total (<?= e($invoice['currency']) ?>)</td><td class="r"><?= money($invoice['total'], $invoice['currency']) ?></td></tr>
  <tr><td>Paid</td><td class="r"><?= money($invoice['amount_paid'], $invoice['currency']) ?></td></tr>
  <tr><td><b>Balance due</b></td><td class="r"><b><?= money($balance, $invoice['currency']) ?></b></td></tr>
</table>

<?php if ($payments): ?>
<table class="items">
  <thead><tr><th>Payment date</th><th>Method</th><th>Reference</th><th class="r">Amount</th></tr></thead>
  <tbody>
  <?php foreach ($payments as $p): ?>
    <tr><td><?= date_fmt($p['paid_at']) ?></td><td><?= e(PaymentService::methodLabel($p['gateway'], $p['method'])) ?></td><td><?= e($p['gateway_reference'] ?: $p['reference']) ?></td><td class="r"><?= money($p['amount'], $p['currency']) ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<?php if ($balance > 0 && setting('bank_instructions')): ?>
<div style="margin-top:18px"><b>Payment instructions</b><br><span class="muted"><?= nl2br(e(setting('bank_instructions'))) ?></span></div>
<?php endif; ?>
<div class="foot"><?= e(setting('invoice_footer')) ?></div>
</body>
</html>

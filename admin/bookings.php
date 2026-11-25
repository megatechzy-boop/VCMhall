<?php
declare(strict_types=1);
$bookingStore = dirname(__DIR__) . '/server-patch/booking-store.php';
if (!is_file($bookingStore)) $bookingStore = dirname(__DIR__) . '/booking-store.php';
require $bookingStore;
require __DIR__ . '/dashboard-lib.php';
require __DIR__ . '/settings-lib.php';
header('Cache-Control: no-store'); header('X-Frame-Options: DENY'); header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; img-src 'self' data:; form-action 'self'; base-uri 'self'; frame-ancestors 'none'");
require_once __DIR__ . '/session.php'; admin_start_session();
function bh(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function booking_payment_label(float $paid, float $total): string { return $paid <= 0 ? 'Not Paid' : ($paid + 0.001 < $total ? 'Partial' : 'Paid'); }
try { $config=booking_config(); $db=booking_db(); $passwordHash=(string)($config['admin_password_hash']??''); }
catch(Throwable $error) { http_response_code(503); exit('The admin panel is still being set up. Please contact the person who manages the website.'); }
if (!admin_session_authenticated($db, $passwordHash)) { header('Location: index.php'); exit; }
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$notice=(string)($_SESSION['admin_notice']??''); unset($_SESSION['admin_notice']);
$tabs=['confirmed'=>'Confirmed','cancelled'=>'Cancelled','blocked'=>'Unavailable dates'];
$tab=(string)($_GET['tab']??'confirmed'); if(!isset($tabs[$tab])) $tab='confirmed';
$date=(string)($_GET['date']??''); if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) $date='';
$hall=strtolower((string)($_GET['hall']??'')); if(!in_array($hall,['big','small'],true)) $hall='';
$canMoney=admin_can('money'); $canManage=admin_can('booking.manage');
$payment=(string)($_GET['payment']??''); if(!$canMoney||!in_array($payment,['unpaid','partial','paid'],true)) $payment='';
$page=max(1,min(100000,(int)($_GET['page']??1))); $perPage=50; $total=0; $rows=[]; $metrics=['upcoming'=>0,'month'=>0,'pending'=>0]; $schemaReady=admin_schema_ready($db);
if($schemaReady) {
    $today=booking_today(); $monthStart=date('Y-m-01 00:00:00'); $monthEnd=date('Y-m-01 00:00:00',strtotime('+1 month',strtotime($monthStart)));
    $stats=$db->prepare("SELECT SUM(CASE WHEN b.record_type='booking' AND b.status='confirmed' AND b.event_date>=? THEN 1 ELSE 0 END) upcoming, SUM(CASE WHEN b.record_type='booking' AND b.status='confirmed' AND b.confirmed_at>=? AND b.confirmed_at<? THEN 1 ELSE 0 END) month_confirmed, SUM(CASE WHEN b.record_type='booking' AND b.status='confirmed' AND b.total_amount>COALESCE(p.paid,0) THEN 1 ELSE 0 END) pending_payment FROM bookings b LEFT JOIN (SELECT booking_id,SUM(amount) paid FROM booking_payments GROUP BY booking_id) p ON p.booking_id=b.id");
    $stats->execute([$today,$monthStart,$monthEnd]); $metrics=$stats->fetch()?:$metrics;
    $where=$tab==='confirmed' ? "b.record_type='booking' AND b.status='confirmed'" : ($tab==='cancelled' ? "b.record_type='booking' AND b.status='cancelled'" : "b.record_type='block' AND b.status='blocked'");
    $params=[]; if($date!==''){$where.=' AND b.event_date=?';$params[]=$date;} if($hall!==''){$where.=' AND b.hall=?';$params[]=$hall;}
    if($payment!=='' && $tab==='confirmed') { $where .= $payment==='paid' ? ' AND COALESCE(p.paid,0)>=b.total_amount AND b.total_amount>0' : ($payment==='partial' ? ' AND COALESCE(p.paid,0)>0 AND COALESCE(p.paid,0)<b.total_amount' : ' AND COALESCE(p.paid,0)=0'); }
    $join=" FROM bookings b LEFT JOIN (SELECT booking_id,SUM(amount) paid,COUNT(*) payment_count FROM booking_payments GROUP BY booking_id) p ON p.booking_id=b.id";
    $count=$db->prepare('SELECT COUNT(*)'.$join.' WHERE '.$where);$count->execute($params);$total=(int)$count->fetchColumn();
    $page=max(1,min($page,max(1,(int)ceil($total/$perPage))));
    $export=(string)($_GET['export']??'');$isExport=in_array($export,['excel','pdf'],true); if($isExport) admin_require('export');$limit=$isExport?5000:$perPage;$offset=$isExport?0:(($page-1)*$perPage);
    $query=$db->prepare('SELECT b.*,COALESCE(p.paid,0) paid_amount,COALESCE(p.payment_count,0) payment_count'.$join.' WHERE '.$where.' ORDER BY b.event_date ASC,b.id DESC LIMIT '.$limit.' OFFSET '.$offset);$query->execute($params);$rows=$query->fetchAll();
    if($isExport) { if($export==='excel')admin_excel_export($rows); admin_pdf_export($rows); }
}
// Payments are recorded from this page (index.php handles the save and returns here).
$canRecordPayment=$canMoney&&admin_can('payment.record')&&$tab==='confirmed';
$paymentMethods=admin_payment_methods($db); $paymentMethodLabels=['cash'=>'Cash','bank-transfer'=>'Bank transfer','upi'=>'UPI','cheque'=>'Cheque','card'=>'Card','other'=>'Other'];
$paymentLimit=$schemaReady&&settings_schema_ready($db)?max(1,min(5,(int)setting($db,'max_payment_steps','5'))):5;
$paymentData=['limit'=>$paymentLimit,'labels'=>$paymentMethodLabels,'canEditTotal'=>$canManage,'rows'=>[],'payments'=>[]];
if($canRecordPayment&&$rows){
    $related=admin_related($db,$rows); $paymentData['payments']=$related['payments'];
    foreach($rows as $r) $paymentData['rows'][(string)$r['id']]=['ref'=>'VCM-'.str_pad((string)$r['id'],6,'0',STR_PAD_LEFT),'name'=>$r['name'],'event'=>$r['event_type'],'date'=>date('d M Y',strtotime($r['event_date'])),'total'=>(float)$r['total_amount'],'paid'=>(float)$r['paid_amount'],'source'=>$r['source']?:'other'];
}
$base=['tab'=>$tab,'date'=>$date,'hall'=>$hall,'payment'=>$payment];$exportQuery=http_build_query(array_filter($base,static fn($v)=>$v!==''));$pageCount=max(1,(int)ceil($total/$perPage));
?><!doctype html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Bookings | Venue Admin</title><link rel="stylesheet" href="admin.css?v=<?=filemtime(__DIR__.'/admin.css')?>"><link rel="stylesheet" href="theme.css?v=<?= filemtime(__DIR__ . '/theme.css') ?>"></head><body class="admin-page"><div class="dashboard-layout"><?php $activeAdminSection='bookings';require __DIR__.'/sidebar.php';?><main class="dashboard-main"><header class="topbar"><button class="icon-button menu-toggle" id="menuToggle" type="button" aria-label="Open menu" aria-controls="sidebar" aria-expanded="false">☰</button><div class="page-title"><p class="eyebrow">MANAGEMENT</p><h1>Bookings</h1><p>Confirmed reservations, cancellations and unavailable dates.</p></div></header>
<?php if($notice!==''):?><p class="notice" role="status"><?=bh($notice)?></p><?php endif;?>
<?php if(!$schemaReady):?><section class="migration-card"><h2>Admin setup needs attention</h2><p>The booking records need a one-time setup update. Please contact the person who maintains the website.</p></section><?php else:?>
<section class="metric-grid" aria-label="Booking summary"><?php $metricCards=[['Upcoming',$metrics['upcoming']??0,'▣','green'],['Confirmed this month',$metrics['month_confirmed']??0,'✓','amber']]; if($canMoney) $metricCards[]=['Pending payment',$metrics['pending_payment']??0,'₹','maroon']; foreach($metricCards as [$label,$value,$icon,$tone]):?><article class="metric-card"><div><span><?=bh($label)?></span><strong><?=bh($value)?></strong></div><i class="metric-icon <?=bh($tone)?>"><?=bh($icon)?></i></article><?php endforeach;?></section>
<section class="records-card" id="records"><div class="section-heading"><div><p class="eyebrow">RESERVATIONS</p><h2><?=bh($tabs[$tab])?></h2></div><span class="record-count"><?=bh($total)?> records</span></div><nav class="record-tabs" aria-label="Booking lists"><?php foreach($tabs as $key=>$label):?><a class="<?=$tab===$key?'active':''?>" href="?<?=bh(http_build_query(['tab'=>$key]))?>"><?=bh($label)?></a><?php endforeach;?></nav>
<form class="filters" method="get"><input type="hidden" name="tab" value="<?=bh($tab)?>"><label><span class="sr-only">Event date</span><input type="date" name="date" value="<?=bh($date)?>"></label><label><span class="sr-only">Hall</span><select name="hall"><option value="">Both halls</option><option value="big" <?=$hall==='big'?'selected':''?>>Big Hall</option><option value="small" <?=$hall==='small'?'selected':''?>>Small Hall</option></select></label><?php if($tab==='confirmed'&&$canMoney):?><label><span class="sr-only">Payment status</span><select name="payment"><option value="">All payment statuses</option><option value="unpaid" <?=$payment==='unpaid'?'selected':''?>>Not paid</option><option value="partial" <?=$payment==='partial'?'selected':''?>>Partially paid</option><option value="paid" <?=$payment==='paid'?'selected':''?>>Paid</option></select></label><?php endif;?><button class="filter-button">Show results</button><a class="clear-button" href="?tab=<?=bh($tab)?>">Clear filters</a><?php if(admin_can('export')):?><div class="export-actions"><a href="?<?=bh($exportQuery.'&export=excel')?>">Export Excel</a><a href="?<?=bh($exportQuery.'&export=pdf')?>">Export PDF</a></div><?php endif;?></form>
<div class="table-wrap"><table><thead><tr><th>Reference</th><th>Event date</th><th>Hall</th><th>Guest</th><th>Event / guests</th><?php if($canMoney):?><th>Payment</th><?php endif;?><th>Status</th><th>Actions</th></tr></thead><tbody><?php foreach($rows as $row):$balance=max(0,(float)$row['total_amount']-(float)$row['paid_amount']);$paymentLabel=booking_payment_label((float)$row['paid_amount'],(float)$row['total_amount']);?><tr><td><strong>VCM-<?=str_pad((string)$row['id'],6,'0',STR_PAD_LEFT)?></strong><small><?=bh(date('d M Y',strtotime($row['created_at'])))?></small></td><td><strong><?=bh(date('d M Y',strtotime($row['event_date'])))?></strong><small><?=bh(date('l',strtotime($row['event_date'])))?></small></td><td><?=bh(ucfirst($row['hall']))?> Hall</td><td><strong><?=bh($row['name'])?></strong><small><?=bh($row['phone'])?></small></td><td><?=bh($row['event_type'])?><small><?=$row['guests']?bh($row['guests']).' guests':'Guest count not set'?></small></td><?php if($canMoney):?><td><span class="payment-status <?=strtolower(str_replace(' ','-',$paymentLabel))?>"><?=bh($tab==='blocked'?'—':$paymentLabel)?></span><?php if($tab!=='blocked'):?><small>₹<?=number_format((float)$row['paid_amount'],0)?> / ₹<?=number_format((float)$row['total_amount'],0)?></small><?php endif;?></td><?php endif;?><td><span class="status-badge status-<?=bh($row['status'])?>"><?=bh($tab==='blocked'?'Date unavailable':ucfirst($row['status']))?></span></td><td><?php if($canRecordPayment):?><button type="button" class="view-button payment-open" data-payment-id="<?=bh($row['id'])?>">₹ Payments</button><?php endif;?><?php if(!$canManage):?><?php if(!$canRecordPayment):?><span class="muted">View only</span><?php endif;?><?php elseif($row['status']!=='cancelled'&&$row['status']!=='blocked'):?><form method="post" action="index.php"><input type="hidden" name="csrf" value="<?=bh($_SESSION['csrf'])?>"><input type="hidden" name="id" value="<?=bh($row['id'])?>"><input type="hidden" name="return_to" value="bookings.php"><button class="danger-link" name="action" value="cancel">Cancel booking</button></form><?php elseif($row['status']==='blocked'):?><form method="post" action="index.php"><input type="hidden" name="csrf" value="<?=bh($_SESSION['csrf'])?>"><input type="hidden" name="id" value="<?=bh($row['id'])?>"><input type="hidden" name="return_to" value="bookings.php?tab=blocked"><button class="danger-link" name="action" value="cancel">Make available</button></form><?php else:?><span class="muted">No actions</span><?php endif;?></td></tr><?php endforeach;?><?php if(!$rows):?><tr><td colspan="<?=$canMoney?8:7?>" class="empty-state"><strong><?=bh($tab==='blocked'?'No unavailable dates found':($tab==='cancelled'?'No cancelled bookings found':'No confirmed bookings found'))?></strong><span>Try changing the selected filters.</span></td></tr><?php endif;?></tbody></table></div><?php if($total>$perPage):?><footer class="dashboard-pagination"><span>Showing <?=bh(($page-1)*$perPage+1)?>–<?=bh(min($page*$perPage,$total))?> of <?=bh($total)?></span><nav aria-label="Booking pages"><?php if($page>1):?><a href="?<?=bh(http_build_query(array_merge($base,['page'=>$page-1])))?>">Previous</a><?php endif;?><strong>Page <?=bh($page)?> of <?=bh($pageCount)?></strong><?php if($page<$pageCount):?><a href="?<?=bh(http_build_query(array_merge($base,['page'=>$page+1])))?>">Next</a><?php endif;?></nav></footer><?php endif;?></section><?php endif;?></main></div>
<?php if($canRecordPayment):?>
<dialog id="paymentDialog" class="payment-dialog" aria-labelledby="paymentDialogTitle">
<div class="modal-form">
<header><div><p class="eyebrow" id="paymentDialogRef"></p><h2 id="paymentDialogTitle">Payments</h2><p class="helper-text" id="paymentDialogGuest"></p></div><button type="button" class="icon-button" data-payment-close aria-label="Close">×</button></header>
<div class="payment-summary"><div><span>Agreed total</span><strong id="pdTotal"></strong></div><div><span>Received</span><strong id="pdPaid"></strong></div><div><span>Still to pay</span><strong id="pdBalance"></strong></div></div>
<div id="pdList" class="timeline"></div>
<form method="post" action="index.php" class="compact-form" id="pdTotalForm">
<input type="hidden" name="csrf" value="<?=bh($_SESSION['csrf'])?>"><input type="hidden" name="id" data-pd-id><input type="hidden" name="source" data-pd-source><input type="hidden" name="return_to" value="bookings.php">
<h3>Agreed total</h3>
<p class="helper-text" id="pdTotalHint">Set the total amount agreed for this booking before recording payments.</p>
<label>Agreed total (₹)<input type="number" name="total_amount" id="pdTotalInput" min="0" max="99999999" step="1" required></label>
<button class="secondary-button" name="action" value="save_details">Save total</button>
</form>
<form method="post" action="index.php" class="compact-form" id="pdPaymentForm">
<input type="hidden" name="csrf" value="<?=bh($_SESSION['csrf'])?>"><input type="hidden" name="id" data-pd-id><input type="hidden" name="return_to" value="bookings.php">
<h3>Record a payment</h3>
<label>Amount received (₹)<input type="number" name="amount" id="pdAmount" min="0.01" max="99999999" step="0.01" required></label>
<label>Payment method<select name="payment_method"><?php foreach($paymentMethods as $method):?><option value="<?=bh($method)?>"><?=bh($paymentMethodLabels[$method]??ucfirst($method))?></option><?php endforeach;?></select></label>
<label>Receipt or transaction reference (optional)<input type="text" name="payment_reference" maxlength="100"></label>
<label>Notes (optional)<input type="text" name="payment_notes" maxlength="500"></label>
<button class="primary-button" name="action" value="add_payment">Save payment</button>
</form>
<p class="notice" id="pdBlocked" hidden></p>
</div>
</dialog>
<script id="bookingPaymentData" type="application/json"><?=json_encode($paymentData,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR)?></script>
<script src="bookings-payments.js?v=<?=filemtime(__DIR__.'/bookings-payments.js')?>" defer></script>
<?php endif;?>
<script src="admin.js?v=<?=filemtime(__DIR__.'/admin.js')?>" defer></script></body></html>

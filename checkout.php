<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/voucher.php';
requireLogin();

// Load saved phone from profile (if any)
$profilePhone = '';
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT phone FROM users WHERE id = ?");
    $stmt->execute([(int)$_SESSION['user_id']]);
    $profilePhone = (string)($stmt->fetchColumn() ?: '');
}

$cart = $_SESSION['cart'] ?? [];
if (empty($cart)) {
    header('Location: /foodbyte/cart.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$ids  = array_keys($cart);
$stmt = $pdo->prepare("SELECT id, price FROM items WHERE id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")");
$stmt->execute($ids);
$dbPrices = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

foreach ($cart as $id => $item) {
    if (isset($dbPrices[$id])) {
        $_SESSION['cart'][$id]['price'] = $dbPrices[$id];
    }
}
$cart = $_SESSION['cart'];

$subtotal = array_sum(array_map(fn($i) => $i['price'] * $i['qty'], $cart));
$delivery = 100;

$discount      = 0.0;
$voucherCode   = null;
$voucherId     = null;
$voucherNotice = null;

if (!empty($_SESSION['applied_voucher'])) {
    $applied = $_SESSION['applied_voucher'];
    $check   = validateVoucher($pdo, $applied['code'], $subtotal, (int) $_SESSION['user_id']);
    if ($check['ok']) {
        $discount    = $check['discount'];
        $voucherCode = $check['voucher']['code'];
        $voucherId   = (int) $check['voucher']['id'];
        $_SESSION['applied_voucher']['discount'] = $discount;
    } else {
        unset($_SESSION['applied_voucher']);
        $voucherNotice = $check['error'];
    }
}

$total  = max(0, $subtotal - $discount) + $delivery;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf_token'] ?? '')) {
        http_response_code(403);
        die('Invalid request token. Please refresh and try again.');
    }

    $name          = trim($_POST['name']    ?? '');
    $phoneSource   = $_POST['phone_source'] ?? 'custom';
    $phone         = trim($_POST['phone']   ?? '');
    $address       = trim($_POST['address'] ?? '');
    $paymentMethod = $_POST['payment_method'] ?? 'cod';

    // If user chose "Use from Profile", trust the server-side profile value,
    // never the (potentially tampered) submitted phone field.
    if ($phoneSource === 'profile' && $profilePhone !== '') {
        $phone = $profilePhone;
    }

    $latitude  = isset($_POST['latitude'])  && $_POST['latitude']  !== '' ? (float)$_POST['latitude']  : null;
    $longitude = isset($_POST['longitude']) && $_POST['longitude'] !== '' ? (float)$_POST['longitude'] : null;

    if (!in_array($paymentMethod, ['cod', 'esewa'], true)) $errors[] = "Please select a valid payment method.";
    if (!$name)    $errors[] = "Name is required.";
    if (!$address) $errors[] = "Address is required.";
    if ($latitude === null || $longitude === null) $errors[] = "Please select your delivery location on the map.";

    // Phone validation: required, max 10 chars, optional '+' then digits, min 7 chars.
    if (!$phone) {
        $errors[] = "Phone number is required.";
    } elseif (mb_strlen($phone) > 10) {
        $errors[] = "Max amount of numbers is 10.";
    } elseif (!preg_match('/^\+?[0-9]+$/', $phone) || mb_strlen($phone) < 7) {
        $errors[] = "Enter a valid phone number (7-10 digits, optional leading +).";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            $finalDiscount = 0.0; $finalVoucherId = null; $finalVoucherCode = null;

            if (!empty($_SESSION['applied_voucher'])) {
                $applied = $_SESSION['applied_voucher'];
                $vstmt = $pdo->prepare("SELECT * FROM vouchers WHERE id = ? FOR UPDATE");
                $vstmt->execute([(int) $applied['voucher_id']]);
                $vrow  = $vstmt->fetch(PDO::FETCH_ASSOC);
                $check = validateVoucher($pdo, $applied['code'], (float) $subtotal, (int) $_SESSION['user_id']);
                if (!$vrow || !$check['ok']) {
                    $pdo->rollBack();
                    unset($_SESSION['applied_voucher']);
                    $errors[] = "Your voucher is no longer valid: " . ($check['error'] ?? 'please re-apply.');
                    $discount = 0.0; $total = $subtotal + $delivery;
                } else {
                    $finalDiscount = $check['discount'];
                    $finalVoucherId = (int) $vrow['id'];
                    $finalVoucherCode = $vrow['code'];
                }
            }

            if (empty($errors)) {
                $finalTotal    = max(0, $subtotal - $finalDiscount) + $delivery;
                $paymentStatus = 'pending';

                $stmt = $pdo->prepare("
                    INSERT INTO orders
                        (user_id, name, phone, address, latitude, longitude, total, delivery_charge,
                         voucher_id, voucher_code, discount_amount, payment_method, payment_status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $_SESSION['user_id'], $name, $phone, $address,
                    $latitude, $longitude,
                    $finalTotal, $delivery,
                    $finalVoucherId, $finalVoucherCode, $finalDiscount,
                    $paymentMethod, $paymentStatus,
                ]);
                $orderId = (int) $pdo->lastInsertId();

                $stmt = $pdo->prepare("INSERT INTO order_items (order_id, item_id, quantity, price) VALUES (?, ?, ?, ?)");
                foreach ($cart as $item) {
                    $stmt->execute([$orderId, $item['id'], $item['qty'], $item['price']]);
                }

                if ($paymentMethod === 'cod') {
                    if ($finalVoucherId !== null) {
                        $pdo->prepare("UPDATE vouchers SET usage_count = usage_count + 1 WHERE id = ?")
                            ->execute([$finalVoucherId]);
                    }
                    $pdo->prepare("INSERT INTO payment_transactions (order_id, gateway, amount, status, gateway_response)
                        VALUES (?, 'cod', ?, 'cod_pending', 'Cash on delivery selected')")
                        ->execute([$orderId, $finalTotal]);
                    $pdo->commit();
                    $_SESSION['cart'] = [];
                    unset($_SESSION['applied_voucher']);
                    $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Order placed! Pay on delivery.'];
                    header('Location: /foodbyte/payment/order_success.php?order_id=' . $orderId);
                    exit;
                }

                $pdo->commit();
                header('Location: /foodbyte/payment/esewa_initiate.php?order_id=' . $orderId);
                exit;
            }

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = "Something went wrong. Please try again.";
            error_log('Checkout error: ' . $e->getMessage());
        }
    }
}

$pageTitle = 'Checkout';
include 'includes/header.php';
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
.checkout-container{max-width:600px;margin:3rem auto;padding:0 2rem}
.checkout-box{background:var(--warm-white);border-radius:16px;border:1px solid var(--border);overflow:hidden;box-shadow:var(--shadow)}
.checkout-box h2{padding:1.3rem 1.8rem;font-family:'Playfair Display',serif;font-size:1.3rem;font-weight:800;border-bottom:1px solid var(--border);background:var(--cream)}
.checkout-form{padding:1.8rem}
.form-group{margin-bottom:1.3rem}
.form-group label{display:block;font-size:.85rem;font-weight:600;margin-bottom:.4rem;color:var(--charcoal)}
.form-group input,.form-group textarea{width:100%;padding:.7rem 1rem;border:1.5px solid var(--border);border-radius:10px;font-family:'DM Sans',sans-serif;font-size:.92rem;background:white;transition:border .2s;outline:none;box-sizing:border-box}
.form-group input:focus,.form-group textarea:focus{border-color:var(--orange)}
.form-group textarea{resize:vertical;min-height:60px}

/* Phone source toggle */
.phone-source-options{display:flex;gap:.6rem;flex-wrap:wrap;margin-bottom:.7rem}
.phone-source-opt{flex:1;min-width:180px;display:flex;align-items:center;gap:.5rem;padding:.65rem .9rem;border:1.5px solid var(--border);border-radius:10px;font-size:.88rem;font-weight:500;cursor:pointer;transition:all .2s;background:#fff;margin-bottom:0}
.phone-source-opt:has(input:checked){border-color:var(--orange);background:#FFF6EE}
.phone-source-opt input{accent-color:var(--orange);width:16px;height:16px;flex-shrink:0}
.phone-source-opt small{color:var(--gray);font-weight:400;display:block;font-size:.78rem;margin-top:.1rem}
.phone-source-opt span{display:flex;flex-direction:column;line-height:1.2}
input[name="phone"][readonly]{background:#F5F2EE;cursor:not-allowed;color:var(--gray)}

/* Inline phone error (live, before submit) */
.phone-live-error{
    display:none;
    margin-top:.4rem;
    color:#C62828;
    font-size:.82rem;
    font-weight:600;
}
.phone-live-error.show{display:block}

.map-label{display:block;font-size:.85rem;font-weight:600;color:var(--charcoal);margin-bottom:.5rem}
.map-section{border:1.5px solid var(--border);border-radius:12px;overflow:hidden;margin-bottom:1.3rem}
.map-section-header{padding:.7rem 1rem;background:var(--cream);border-bottom:1px solid var(--border);display:flex;align-items:center;gap:.5rem}
.map-hint{font-size:.82rem;color:var(--gray);flex:1}
.map-badge{font-size:.73rem;background:var(--orange);color:white;border-radius:20px;padding:.15rem .55rem;font-weight:700}
.map-search-bar{display:flex;gap:.5rem;padding:.7rem 1rem;border-bottom:1px solid var(--border);background:white}
.map-search-bar input{flex:1;padding:.5rem .8rem;border:1.5px solid var(--border);border-radius:8px;font-size:.86rem;font-family:'DM Sans',sans-serif;outline:none;transition:border .2s;box-sizing:border-box}
.map-search-bar input:focus{border-color:var(--orange)}
.map-search-btn,.map-locate-btn{padding:0 .8rem;border:none;border-radius:8px;font-weight:700;font-size:.8rem;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .2s;white-space:nowrap;height:36px}
.map-search-btn{background:var(--charcoal);color:#fff}
.map-search-btn:hover{background:#000}
.map-locate-btn{background:var(--cream);color:var(--charcoal);border:1.5px solid var(--border)}
.map-locate-btn:hover{border-color:var(--orange)}
#delivery-map{width:100%;height:300px;background:#e8e8e8}
.map-error-msg{display:none;padding:.9rem 1rem;background:#FFEBEE;color:#C62828;font-size:.84rem;font-weight:600;text-align:center}
.map-status{padding:.55rem 1rem;font-size:.82rem;display:flex;align-items:center;gap:.45rem;border-top:1px solid var(--border);min-height:2.1rem}
.map-status.none{background:#FFF3F3;color:#C62828}
.map-status.pinned{background:#F1F8E9;color:#2E7D32}
.payment-method-section{border-top:1px solid var(--border);padding:1.3rem 1.8rem;background:#FAFAFA}
.payment-method-section h3{font-family:'Playfair Display',serif;font-size:1.05rem;font-weight:800;margin-bottom:.8rem}
.method-options{display:flex;flex-direction:column;gap:.6rem}
.method-card{display:flex;align-items:center;gap:.8rem;padding:.9rem 1rem;border:1.5px solid var(--border);border-radius:10px;cursor:pointer;background:white;transition:all .15s}
.method-card:hover{border-color:var(--orange)}
.method-card input[type=radio]{accent-color:var(--orange);width:18px;height:18px}
.method-card.selected{border-color:var(--orange);background:#FFF7F2;box-shadow:0 0 0 3px rgba(232,82,26,.08)}
.method-card .method-icon{width:42px;height:42px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;background:var(--cream)}
.method-card.esewa .method-icon{background:#60BB46;color:white;font-weight:800;font-family:'Playfair Display',serif;font-size:1rem}
.method-card .method-info{flex:1}
.method-card .method-name{font-weight:700;font-size:.95rem}
.method-card .method-desc{font-size:.8rem;color:var(--gray)}
.voucher-section{border-top:1px solid var(--border);padding:1.3rem 1.8rem;background:#FFFAF5}
.voucher-section h3{font-family:'Playfair Display',serif;font-size:1.05rem;font-weight:800;margin-bottom:.8rem}
.voucher-input-row{display:flex;gap:.5rem}
.voucher-input-row input{flex:1;padding:.6rem .9rem;border:1.5px solid var(--border);border-radius:9px;font-size:.9rem;text-transform:uppercase;letter-spacing:.5px;font-family:'DM Sans',sans-serif;outline:none}
.voucher-input-row input:focus{border-color:var(--orange)}
.voucher-btn{padding:0 1.1rem;background:var(--charcoal);color:#fff;border:none;border-radius:9px;font-weight:700;font-size:.85rem;cursor:pointer;transition:background .2s;font-family:'DM Sans',sans-serif}
.voucher-btn:hover{background:#000}
.voucher-btn:disabled{background:#999;cursor:not-allowed}
.voucher-applied{background:#E8F5E9;border:1px solid #A5D6A7;border-radius:9px;padding:.7rem 1rem;display:flex;justify-content:space-between;align-items:center;font-size:.88rem}
.voucher-applied .code{font-weight:700;color:#1B5E20;letter-spacing:.5px}
.voucher-applied .remove-link{background:none;border:none;color:#C62828;cursor:pointer;font-size:.82rem;font-weight:600;text-decoration:underline;font-family:'DM Sans',sans-serif}
.voucher-msg{margin-top:.6rem;font-size:.83rem;min-height:1.1em}
.voucher-msg.error{color:#C62828}
.voucher-msg.success{color:#2E7D32}
.payment-section{border-top:1px solid var(--border);padding:1.5rem 1.8rem}
.payment-section h3{font-family:'Playfair Display',serif;font-size:1.1rem;font-weight:800;margin-bottom:1rem}
.payment-row{display:flex;justify-content:space-between;padding:.4rem 0;font-size:.92rem;color:var(--gray)}
.payment-row.discount{color:#2E7D32;font-weight:600}
.payment-row.total{font-weight:800;font-size:1.05rem;color:var(--charcoal);padding-top:.8rem;margin-top:.5rem;border-top:1px solid var(--border)}
.errors{background:#FFEBEE;border:1px solid #FFCDD2;border-radius:10px;padding:1rem 1.2rem;margin-bottom:1rem;color:#C62828;font-size:.88rem}
.errors ul{margin-left:1.2rem}
.notice{background:#FFF8E1;border:1px solid #FFE082;border-radius:10px;padding:.8rem 1rem;margin-bottom:1rem;color:#8D6E00;font-size:.85rem}
.place-btn{width:100%;background:var(--orange);color:#fff;border:none;border-radius:10px;padding:.9rem;font-size:.95rem;font-weight:700;cursor:pointer;transition:all .2s;font-family:'DM Sans',sans-serif;margin-top:1.2rem}
.place-btn:hover{background:var(--orange-dark)}
</style>

<div class="checkout-container">
 <div class="checkout-box">
  <h2>Delivery Details</h2>
  <div class="checkout-form">
   <?php if(!empty($errors)): ?>
   <div class="errors"><ul><?php foreach($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
   <?php endif; ?>
   <?php if($voucherNotice): ?>
   <div class="notice">Warning: Voucher removed: <?= htmlspecialchars($voucherNotice) ?></div>
   <?php endif; ?>

   <form method="POST" id="checkout-form">
    <input type="hidden" name="csrf"      value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="latitude"  id="input-lat" value="<?= htmlspecialchars($_POST['latitude']  ?? '') ?>">
    <input type="hidden" name="longitude" id="input-lng" value="<?= htmlspecialchars($_POST['longitude'] ?? '') ?>">

    <div class="form-group">
     <label>Name *</label>
     <input type="text" name="name" value="<?= htmlspecialchars($_POST['name']??$_SESSION['username']??'') ?>" required>
    </div>
    <div class="form-group">
     <label>Phone Number *</label>
     <?php if ($profilePhone !== ''): ?>
       <?php
        $submittedSource = $_POST['phone_source'] ?? 'profile';
        $useProfile = ($submittedSource === 'profile');
       ?>
       <div class="phone-source-options">
         <label class="phone-source-opt">
           <input type="radio" name="phone_source" value="profile" <?= $useProfile ? 'checked' : '' ?>>
           <span>Use from Profile <small><?= htmlspecialchars($profilePhone) ?></small></span>
         </label>
         <label class="phone-source-opt">
           <input type="radio" name="phone_source" value="custom" <?= $useProfile ? '' : 'checked' ?>>
           <span>Enter Different Number</span>
         </label>
       </div>
       <input type="tel" name="phone" id="phone-field"
              value="<?= htmlspecialchars($useProfile ? $profilePhone : ($_POST['phone'] ?? '')) ?>"
              maxlength="10"
              pattern="^\+?[0-9]{6,9}$|^[0-9]{7,10}$"
              title="Up to 10 characters: digits only, optionally starting with +"
              <?= $useProfile ? 'readonly' : '' ?> required>
       <div class="phone-live-error" id="phone-live-error">Max amount of numbers is 10.</div>
     <?php else: ?>
       <input type="hidden" name="phone_source" value="custom">
       <input type="tel" name="phone" id="phone-field"
              value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
              maxlength="10"
              pattern="^\+?[0-9]{6,9}$|^[0-9]{7,10}$"
              title="Up to 10 characters: digits only, optionally starting with +"
              placeholder="e.g. 9812345678"
              required>
       <div class="phone-live-error" id="phone-live-error">Max amount of numbers is 10.</div>
       <small style="display:block; margin-top:0.4rem; color:var(--gray); font-size:0.78rem;">
         Tip: <a href="profile.php" style="color:var(--orange); font-weight:600;">save a phone number to your profile</a> for one-tap checkout.
       </small>
     <?php endif; ?>
    </div>

    <!-- MAP SECTION -->
    <label class="map-label">Delivery Location *</label>
    <div class="map-section">
     <div class="map-section-header">
      <span class="map-hint">Click the map to drop a pin, drag to adjust, or search by name</span>
      <span class="map-badge">Required</span>
     </div>
     <div class="map-search-bar">
      <input type="text" id="map-search-input" placeholder="Search address or area..." autocomplete="off">
      <button type="button" class="map-search-btn" id="map-search-btn">Search</button>
      <button type="button" class="map-locate-btn" id="map-locate-btn">Locate Me</button>
     </div>
     <div id="map-error-msg" class="map-error-msg">Map could not be loaded. Please type your address below.</div>
     <div id="delivery-map"></div>
     <div id="map-status" class="map-status none">
      <span>Pin</span>
      <span id="map-status-text">No location selected - click the map to place a pin</span>
     </div>
    </div>

    <div class="form-group">
     <label>Address / Delivery Note *</label>
     <textarea name="address" required placeholder="Building, floor, landmark, or any delivery note..."><?= htmlspecialchars($_POST['address']??'') ?></textarea>
    </div>

    <!-- VOUCHER -->
    <div class="voucher-section">
     <h3>Have a voucher?</h3>
     <div id="voucher-empty" style="<?= $voucherCode ? 'display:none' : '' ?>">
      <div class="voucher-input-row">
       <input type="text" id="voucher-code" placeholder="Enter voucher code" autocomplete="off">
       <button type="button" id="voucher-apply-btn" class="voucher-btn">Apply</button>
      </div>
     </div>
     <div id="voucher-applied" class="voucher-applied" style="<?= $voucherCode ? '' : 'display:none' ?>">
      <div>Voucher <span class="code" id="voucher-applied-code"><?= htmlspecialchars($voucherCode ?? '') ?></span> applied</div>
      <button type="button" id="voucher-remove-btn" class="remove-link">Remove</button>
     </div>
     <div id="voucher-msg" class="voucher-msg"></div>
    </div>

    <!-- PAYMENT METHOD -->
    <div class="payment-method-section">
     <h3>Payment Method</h3>
     <div class="method-options">
      <label class="method-card selected" id="method-cod">
       <input type="radio" name="payment_method" value="cod" checked>
       <div class="method-icon">&#128181;</div>
       <div class="method-info">
        <div class="method-name">Cash on Delivery</div>
        <div class="method-desc">Pay in cash when your order arrives</div>
       </div>
      </label>
      <label class="method-card esewa" id="method-esewa">
       <input type="radio" name="payment_method" value="esewa">
       <div class="method-icon">eS</div>
       <div class="method-info">
        <div class="method-name">eSewa</div>
        <div class="method-desc">Pay securely online via eSewa wallet</div>
       </div>
      </label>
     </div>
    </div>

    <!-- ORDER SUMMARY -->
    <div class="payment-section">
     <h3>Order Summary</h3>
     <div class="payment-row"><span>Subtotal</span><span>Rs <?= number_format($subtotal,0) ?></span></div>
     <div class="payment-row discount" id="row-discount" style="<?= $discount > 0 ? '' : 'display:none' ?>">
      <span>Discount <span id="discount-code"><?= $voucherCode ? '('.htmlspecialchars($voucherCode).')' : '' ?></span></span>
      <span>- Rs <span id="discount-amount"><?= number_format($discount,0) ?></span></span>
     </div>
     <div class="payment-row"><span>Delivery Charge</span><span>Rs <?= $delivery ?></span></div>
     <div class="payment-row total"><span>Total Amount</span><span>Rs <span id="total-amount"><?= number_format($total,0) ?></span></span></div>
    </div>

    <button type="submit" class="place-btn" id="place-btn">Place Order</button>
   </form>
  </div>
 </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function(){
 var map,marker;
 var latInput=document.getElementById('input-lat');
 var lngInput=document.getElementById('input-lng');
 var statusEl=document.getElementById('map-status');
 var statusTxt=document.getElementById('map-status-text');
 var errMsg=document.getElementById('map-error-msg');
 var DEF_LAT=27.7172,DEF_LNG=85.3240,DEF_ZOOM=13;

 function setPin(lat,lng,label){
  latInput.value=lat.toFixed(7);
  lngInput.value=lng.toFixed(7);
  statusEl.className='map-status pinned';
  statusTxt.textContent='Selected: '+(label||(lat.toFixed(5)+', '+lng.toFixed(5)));
 }

 function placeMarker(lat,lng,label){
  if(marker){marker.setLatLng([lat,lng]);}
  else{
   marker=L.marker([lat,lng],{draggable:true}).addTo(map);
   marker.on('dragend',function(e){var p=e.target.getLatLng();reverseGeocode(p.lat,p.lng);});
  }
  setPin(lat,lng,label);
 }

 function reverseGeocode(lat,lng){
  fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat='+lat+'&lon='+lng)
   .then(function(r){return r.json();})
   .then(function(d){
    setPin(lat,lng,d.display_name||'');
    var ta=document.querySelector('textarea[name="address"]');
    if(ta&&!ta.value.trim()&&d.display_name)ta.value=d.display_name;
   })
   .catch(function(){setPin(lat,lng);});
 }

 try{
  map=L.map('delivery-map').setView([DEF_LAT,DEF_LNG],DEF_ZOOM);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{
   attribution:'Map data OpenStreetMap contributors',maxZoom:19
  }).addTo(map);
  if(latInput.value&&lngInput.value){
   var rl=parseFloat(latInput.value),rn=parseFloat(lngInput.value);
   placeMarker(rl,rn);map.setView([rl,rn],16);
  }
  map.on('click',function(e){placeMarker(e.latlng.lat,e.latlng.lng);reverseGeocode(e.latlng.lat,e.latlng.lng);});
 }catch(e){
  errMsg.style.display='block';
  document.getElementById('delivery-map').style.display='none';
  statusEl.style.display='none';
 }

 var searchInput=document.getElementById('map-search-input');
 var searchBtn=document.getElementById('map-search-btn');
 function doSearch(){
  var q=searchInput.value.trim();if(!q)return;
  searchBtn.textContent='...';searchBtn.disabled=true;
  fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&q='+encodeURIComponent(q))
   .then(function(r){return r.json();})
   .then(function(data){
    if(data&&data.length){
     var lat=parseFloat(data[0].lat),lng=parseFloat(data[0].lon);
     map.setView([lat,lng],16);placeMarker(lat,lng,data[0].display_name);
    }else{alert('Location not found. Try a different search term.');}
   })
   .catch(function(){alert('Search failed. Please check your connection.');})
   .finally(function(){searchBtn.textContent='Search';searchBtn.disabled=false;});
 }
 searchBtn.addEventListener('click',doSearch);
 searchInput.addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();doSearch();}});

 document.getElementById('map-locate-btn').addEventListener('click',function(){
  if(!navigator.geolocation){alert('Geolocation not supported.');return;}
  var btn=this;btn.textContent='...';btn.disabled=true;
  navigator.geolocation.getCurrentPosition(
   function(pos){
    var lat=pos.coords.latitude,lng=pos.coords.longitude;
    map.setView([lat,lng],16);placeMarker(lat,lng);reverseGeocode(lat,lng);
    btn.textContent='Locate Me';btn.disabled=false;
   },
   function(){
    alert('Could not get location. Please allow access or click the map.');
    btn.textContent='Locate Me';btn.disabled=false;
   }
  );
 });

 // ---------- Phone source toggle ----------
 (function(){
  var phoneRadios = document.querySelectorAll('input[name="phone_source"][type="radio"]');
  var phoneField  = document.getElementById('phone-field');
  if (!phoneField) return;

  var profilePhone = <?= json_encode($profilePhone) ?>;

  if (phoneRadios.length) {
   var customMemory = (phoneField.value && phoneField.value !== profilePhone) ? phoneField.value : '';

   function applyMode(){
    var checked = document.querySelector('input[name="phone_source"]:checked');
    if (!checked) return;
    if (checked.value === 'profile') {
     if (!phoneField.readOnly && phoneField.value !== profilePhone) {
      customMemory = phoneField.value;
     }
     phoneField.value    = profilePhone;
     phoneField.readOnly = true;
    } else {
     phoneField.readOnly = false;
     if (phoneField.value === profilePhone) phoneField.value = customMemory;
     phoneField.focus();
    }
    // Re-validate length state when switching modes
    validatePhoneLive();
   }

   phoneRadios.forEach(function(r){ r.addEventListener('change', applyMode); });
   applyMode();
  }

  // ---------- Live length check (Max 10) ----------
  var liveErr = document.getElementById('phone-live-error');
  function validatePhoneLive(){
   if (!liveErr) return;
   if (phoneField.value.length >= 10) {
    // Browser already caps at maxlength=10 — show the message so the
    // user understands why typing stopped.
    liveErr.classList.add('show');
    liveErr.textContent = 'Max amount of numbers is 10.';
   } else {
    liveErr.classList.remove('show');
   }
  }
  phoneField.addEventListener('input', validatePhoneLive);
  // Hide message after a few seconds if user backspaces below 10
  validatePhoneLive();
 })();

 // Voucher
 const csrf=<?= json_encode($_SESSION['csrf_token']) ?>;
 const codeInput=document.getElementById('voucher-code');
 const applyBtn=document.getElementById('voucher-apply-btn');
 const removeBtn=document.getElementById('voucher-remove-btn');
 const emptyBox=document.getElementById('voucher-empty');
 const appliedBox=document.getElementById('voucher-applied');
 const codeLabel=document.getElementById('voucher-applied-code');
 const msgEl=document.getElementById('voucher-msg');
 const rowDisc=document.getElementById('row-discount');
 const discAmtEl=document.getElementById('discount-amount');
 const discCodeEl=document.getElementById('discount-code');
 const totalEl=document.getElementById('total-amount');
 const placeBtn=document.getElementById('place-btn');
 function fmt(n){return Number(n).toLocaleString('en-IN',{maximumFractionDigits:0});}
 function setMsg(t,c){msgEl.textContent=t||'';msgEl.className='voucher-msg'+(c?' '+c:'');}
 function showApplied(code,discount,total){
  emptyBox.style.display='none';appliedBox.style.display='flex';
  codeLabel.textContent=code;rowDisc.style.display='';
  discAmtEl.textContent=fmt(discount);discCodeEl.textContent='('+code+')';totalEl.textContent=fmt(total);
 }
 function showEmpty(total){
  emptyBox.style.display='';appliedBox.style.display='none';
  rowDisc.style.display='none';codeInput.value='';
  if(total!==undefined)totalEl.textContent=fmt(total);
 }
 async function callEndpoint(payload){
  const res=await fetch('/foodbyte/apply_voucher.php',{
   method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},
   body:new URLSearchParams({csrf,...payload})
  });return res.json();
 }
 applyBtn.addEventListener('click',async()=>{
  const code=codeInput.value.trim();
  if(!code){setMsg('Please enter a voucher code.','error');return;}
  applyBtn.disabled=true;setMsg('Checking...');
  try{const d=await callEndpoint({action:'apply',code});
   if(d.ok){showApplied(d.code,d.discount,d.total);setMsg('Voucher applied!','success');}
   else setMsg(d.error||'Could not apply voucher.','error');
  }catch(e){setMsg('Network error. Please try again.','error');}
  finally{applyBtn.disabled=false;}
 });
 removeBtn.addEventListener('click',async()=>{
  removeBtn.disabled=true;
  try{const d=await callEndpoint({action:'remove'});if(d.ok)showEmpty(d.total);}
  catch(e){setMsg('Network error. Please try again.','error');}
  finally{removeBtn.disabled=false;}
 });
 codeInput.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();applyBtn.click();}});

 // Payment method
 const radios=document.querySelectorAll('input[name="payment_method"]');
 function updateMethodUI(){
  document.querySelectorAll('.method-card').forEach(c=>c.classList.remove('selected'));
  const checked=document.querySelector('input[name="payment_method"]:checked');
  if(checked){
   checked.closest('.method-card').classList.add('selected');
   placeBtn.textContent=checked.value==='esewa'?'Pay with eSewa':'Place Order';
  }
 }
 radios.forEach(r=>r.addEventListener('change',updateMethodUI));
 updateMethodUI();
})();
</script>

<?php include 'includes/footer.php'; ?>
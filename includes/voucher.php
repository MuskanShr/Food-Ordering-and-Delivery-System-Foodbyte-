<?php
/**
 * Voucher validation helper.
 *
 * Single source of truth for voucher rules. Used by:
 *   - apply_voucher.php       (AJAX, when the user clicks "Apply")
 *   - checkout.php            (re-validation at order placement)
 *   - payment/esewa_success   (idempotent finalisation)
 *
 * IMPORTANT: This function does NOT increment usage_count.
 * That happens in:
 *   - checkout.php (COD orders, at placement)
 *   - payment/esewa_success.php (eSewa orders, at payment success)
 * Both paths use SELECT ... FOR UPDATE to avoid race conditions.
 *
 * @return array{ok:bool, error?:string, voucher?:array, discount?:float}
 */
function validateVoucher(PDO $pdo, string $code, float $subtotal, int $userId): array
{
    $code = strtoupper(trim($code));

    if ($code === '') {
        return ['ok' => false, 'error' => 'Please enter a voucher code.'];
    }

    $stmt = $pdo->prepare("SELECT * FROM vouchers WHERE code = ?");
    $stmt->execute([$code]);
    $v = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$v) {
        return ['ok' => false, 'error' => 'Invalid voucher code.'];
    }

    if ((int) $v['is_active'] !== 1) {
        return ['ok' => false, 'error' => 'This voucher is no longer active.'];
    }

    $now = new DateTime('now');

    if (!empty($v['valid_from']) && $now < new DateTime($v['valid_from'])) {
        return ['ok' => false, 'error' => 'This voucher is not valid yet.'];
    }
    if (!empty($v['valid_until']) && $now > new DateTime($v['valid_until'])) {
        return ['ok' => false, 'error' => 'This voucher has expired.'];
    }

    if ($v['usage_limit'] !== null && (int) $v['usage_count'] >= (int) $v['usage_limit']) {
        return ['ok' => false, 'error' => 'This voucher has reached its usage limit.'];
    }

    if ($subtotal < (float) $v['min_order_amount']) {
        return [
            'ok'    => false,
            'error' => 'Minimum order of Rs ' . number_format($v['min_order_amount'], 0)
                       . ' required for this voucher.',
        ];
    }

    // One redemption per user. Failed orders don't count as a use,
    // so an abandoned eSewa attempt doesn't permanently lock the user out.
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM orders
         WHERE user_id = ? AND voucher_id = ? AND payment_status != 'failed'"
    );
    $stmt->execute([$userId, $v['id']]);
    if ((int) $stmt->fetchColumn() > 0) {
        return ['ok' => false, 'error' => 'You have already used this voucher.'];
    }

    // Compute discount.
    if ($v['discount_type'] === 'percent') {
        $discount = $subtotal * ((float) $v['discount_value'] / 100);
        if (!empty($v['max_discount_amount'])) {
            $discount = min($discount, (float) $v['max_discount_amount']);
        }
    } else {
        $discount = (float) $v['discount_value'];
    }

    // Never let the discount exceed the subtotal (no negative totals).
    $discount = min($discount, $subtotal);
    $discount = round($discount, 2);

    return ['ok' => true, 'voucher' => $v, 'discount' => $discount];
}

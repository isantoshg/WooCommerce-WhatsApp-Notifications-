<?php
if (!defined('ABSPATH')) exit;

class WWN_Cron_Handler {

    public function __construct() {
        add_action('wwn_abandoned_cart_cron', [$this, 'process_abandoned_carts']);
    }

    public function process_abandoned_carts() {
        $opt   = get_option(WWN_OPTION_KEY, []);
        $delay = max(30, intval($opt['abandon_delay_m'] ?? 240)); // minutes
        $threshold = time() - ($delay * 60);

        $store = get_option(WWN_CART_OPTION, []);
        if (empty($store) || !is_array($store)) return;

        foreach ($store as $sid => $row) {
            $last = intval($row['last_ts'] ?? 0);
            $notified = intval($row['notified'] ?? 0);
            if ($notified) continue;
            if ($last === 0 || $last > $threshold) continue;

            // Try to get a phone number:
            $phone = '';
            // 1) If logged in user:
            $uid = intval($row['user_id'] ?? 0);
            if ($uid) {
                $phone = get_user_meta($uid, 'billing_phone', true);
            }
            // 2) Guests: no guaranteed phone; we can’t message unless phone exists.
            // In practice, use checkout field capture or a lead form/OTP to get phone earlier.

            if (!$phone) {
                WWN_Send_Message::log("Skip abandoned for $sid (no phone).");
                // Mark as checked once so we don’t loop forever; or leave it pending if you expect phone later.
                $store[$sid]['notified'] = 1;
                continue;
            }

            // Build message text
            $items = $row['items'] ?? [];
            $first = $items[0]['name'] ?? 'your items';
            $msg = "You left $first in your cart. Complete your order now to avoid stock-out!";

            // Send
            $ok = WWN_Send_Message::send_text($phone, $msg);

            // Mark notified to avoid repeats
            if ($ok) {
                $store[$sid]['notified'] = 1;
            }
        }

        update_option(WWN_CART_OPTION, $store, false);
    }
}

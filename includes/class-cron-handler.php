<?php
if (!defined('ABSPATH')) exit;

class WWN_Cron_Handler {

    public function __construct() {
        add_action('wwn_abandoned_cart_cron', [$this, 'process_abandoned_carts']);
    }

    public function process_abandoned_carts() {
        $opt       = get_option(WWN_OPTION_KEY, []);
        $delay     = max(30, intval($opt['abandon_delay_m'] ?? 240));
        $threshold = time() - ($delay * 60);

        $phone_number_id = $opt['phone_number_id'] ?? '';
        $access_token    = $opt['access_token'] ?? '';

        if (!$phone_number_id || !$access_token) {
            WWN_Send_Message::log("Abandoned cart cron aborted: missing WhatsApp credentials.");
            return;
        }

        $store = get_option(WWN_CART_OPTION, []);
        if (empty($store) || !is_array($store)) return;

        foreach ($store as $sid => $row) {
            $last = intval($row['last_ts'] ?? 0);
            $notified = intval($row['notified'] ?? 0);

            if ($notified) continue;
            if ($last === 0 || $last > $threshold) continue;

            $phone = '';
            $uid = intval($row['user_id'] ?? 0);
            if ($uid) {
                $phone = get_user_meta($uid, 'billing_phone', true);
            }

            if (!$phone) {
                WWN_Send_Message::log("Skip abandoned cart $sid (no phone available).");
                $store[$sid]['notified'] = 1;
                continue;
            }

            $items = $row['items'] ?? [];
            $first_item_name = $items[0]['name'] ?? 'your item';
            $first_item_qty  = $items[0]['quantity'] ?? 1;

            $url = "https://graph.facebook.com/v22.0/{$phone_number_id}/messages";
            $payload = [
                "messaging_product" => "whatsapp",
                "to" => $phone,
                "type" => "template",
                "template" => [
                    "name" => "abandoned_cart_notify",
                    "language" => ["code" => "en"],
                    "components" => [
                        [
                            "type" => "body",
                            "parameters" => [
                                [
                                    "type" => "text",
                                    "parameter_name" => "product_name",
                                    "text" => $first_item_name
                                ],
                                [
                                    "type" => "text",
                                    "parameter_name" => "quantity",
                                    "text" => $first_item_qty
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            $ok = $this->send_whatsapp_template($url, $access_token, $payload, $sid);

            if ($ok) {
                $store[$sid]['notified'] = 1;
            }
        }

        update_option(WWN_CART_OPTION, $store, false);
    }

    private function send_whatsapp_template($url, $access_token, $payload, $sid) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$access_token}",
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, wp_json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        curl_close($ch);

        if (curl_errno($ch)) {
            $err = curl_error($ch);
            WWN_Send_Message::log("Abandoned cart $sid cURL Error: $err");
            return false;
        }

        WWN_Send_Message::log("Abandoned cart $sid template sent successfully. Response: $response");
        return true;
    }
}

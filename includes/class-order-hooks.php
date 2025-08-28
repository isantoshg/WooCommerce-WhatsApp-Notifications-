<?php
if (!defined('ABSPATH')) exit;

class WWN_Order_Hooks {
    private $opt;
    private $template_lang = 'en';
    private $brand_name;

    public function __construct() {
        $this->opt = get_option(WWN_OPTION_KEY, []);
        $this->brand_name = strtolower($this->opt['brand_name'] ?? 'ratinia');
        if (!class_exists('WWN_DB_Handler')) {
            error_log('[WWN ERROR] WWN_DB_Handler not loaded.');
            return;
        }

        add_action('woocommerce_order_status_changed', [$this, 'on_order_status_changed'], 10, 4);
        // error_log('[WWN DEBUG] Order hooks registered');
    }

    /**
     * Save order info into DB when status changes
     */
    public function on_order_status_changed($order_id, $old_status, $new_status, $order) {
        if (!class_exists('WWN_DB_Handler')) return;

        $phone      = WWN_DB_Handler::sanitize_phone($order->get_billing_phone(), '91');
        $user_name  = $order->get_billing_first_name();
        $user_email = $order->get_billing_email();

        // Prevent duplicate entry (check existing before insert/update)
        WWN_DB_Handler::upsert_order($order_id, $new_status, $phone, '', $user_name, $user_email);

        // error_log("[WWN DEBUG] Order {$order_id} saved (Status: {$new_status}, Phone: {$phone})");

        $this->notify_status($order_id, $new_status, $order);
    }

    /**
     * Send WhatsApp notification based on status
     */
    private function notify_status($order_id, $new_status, $order) {
        if (!class_exists('WWN_DB_Handler')) return;

        $meta_key = "_wwn_sent_{$new_status}";
        if (get_post_meta($order_id, $meta_key, true)) {
            error_log("[WWN DEBUG] WhatsApp already sent for Order {$order_id} (Status: {$new_status})");
            return;
        }

        $customer_phone = WWN_DB_Handler::get_phone_by_order($order_id);
        if (!$customer_phone) {
            error_log("[WWN ERROR] No phone found for Order {$order_id}, aborting.");
            return;
        }

        $phone_number_id = $this->opt['phone_number_id'] ?? '';
        $access_token    = $this->opt['access_token'] ?? '';
        if (!$phone_number_id || !$access_token) {
            error_log("[WWN ERROR] Missing WhatsApp API credentials.");
            return;
        }

        $url = "https://graph.facebook.com/v22.0/{$phone_number_id}/messages";
        $user_name   = $order->get_billing_first_name();
        $order_total = $order->get_total();

        // error_log("========================================");
        // error_log($this->brand_name);
        // error_log("========================================");
        // Template selection
        $template_name = in_array($new_status, ['completed', 'processing']) 
            ? $this->brand_name
            : 'order_pending';

        $status_for_db = in_array($new_status, ['completed', 'processing']) 
            ? 'completed' 
            : 'pending';

        // --- Customer Payload ---
        $customer_payload = [
            "messaging_product" => "whatsapp",
            "to"                => $customer_phone,
            "type"              => "template",
            "template"          => [
                "name"     => $template_name,
                "language" => ["code" => $this->template_lang],
                 "components" => [
                    [
                        "type" => "body",
                        "parameters" => [
                             [
                                "type" => "text",
                                "parameter_name" => "user_name",
                                "text" => $user_name 
                            ],
                            [
                                "type" => "text",
                                "parameter_name" => "order_id",
                                "text" => "OID{$order_id}RC"
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $this->send_whatsapp($url, $access_token, $customer_payload, $order_id, 'customer', $status_for_db);

        // --- Admin Payload ---
        // $admin_phone = $this->opt['admin_phone'] ?? '';
        // if ($admin_phone) {
        //     $admin_payload = [
        //         "messaging_product" => "whatsapp",
        //         "to"                => $admin_phone,
        //         "type"              => "template",
        //         "template"          => [
        //             "name"     => "admin_order_notify",
        //             "language" => ["code" => $this->template_lang],
        //             "components" => [
        //               [
        //                 "type" => "body",
        //                 "parameters" => [
        //                      [
        //                         "type" => "text",
        //                         "parameter_name" => "user_name",
        //                         "text" => $user_name 
        //                     ],
        //                     [
        //                         "type" => "text",
        //                         "parameter_name" => "order_id",
        //                         "text" => "OID{$order_id}RC"
        //                     ]
        //                 ]
        //             ]
        //             ]
        //         ]
        //     ];

        //     $this->send_whatsapp($url, $access_token, $admin_payload, $order_id, 'admin', $status_for_db);
        // }

        // update_post_meta($order_id, $meta_key, 1);
    }

    /**
     * Handle WhatsApp API request with logging
     */
    private function send_whatsapp($url, $access_token, $payload, $order_id, $type = 'customer', $status_for_db = 'completed') {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$access_token}",
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, wp_json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $err = curl_error($ch);
            WWN_DB_Handler::update_status($order_id, 'failed');
            WWN_DB_Handler::insert_log($order_id, ucfirst($type)." cURL Error: {$err}");
            error_log("[WWN ERROR] cURL ({$type}) Order {$order_id} - {$err}");
        } else {
            $decoded = json_decode($response, true);
            if ($http_code >= 200 && $http_code < 300 && isset($decoded['messages'][0]['id'])) {
                WWN_DB_Handler::update_status($order_id, $status_for_db);
                WWN_DB_Handler::insert_log($order_id, ucfirst($type)." Success: ". $decoded['messages'][0]['id']);
                error_log("[WWN DEBUG] WhatsApp ({$type}) sent for Order {$order_id}, MsgID: ".$decoded['messages'][0]['id']);
            } else {
                $error_msg = $decoded['error']['message'] ?? 'Unknown API error';
                WWN_DB_Handler::update_status($order_id, 'failed');
                WWN_DB_Handler::insert_log($order_id, ucfirst($type)." API Error: {$error_msg}");
                error_log("[WWN ERROR] WhatsApp API ({$type}) failed for Order {$order_id} - {$error_msg}");
            }
        }

        curl_close($ch);
    }
}

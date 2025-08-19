<?php
if (!defined('ABSPATH')) exit;

class WWN_Order_Hooks
{
    private $opt;
    private $template_name = 'ratinia';
    private $template_lang = 'en';

    public function __construct()
    {
        $this->opt = get_option(WWN_OPTION_KEY, []);
        $this->template_name = strtolower($this->opt['brand_name'] ?? 'ratinia');

        if (!class_exists('WWN_DB_Handler')) {
            error_log('WWN ERROR: WWN_DB_Handler not loaded.');
            return;
        }

        add_action('woocommerce_order_status_changed', [$this, 'on_order_status_changed'], 10, 4);
        add_action('woocommerce_order_status_changed', [$this, 'on_order_notify_any_status'], 20, 4);

        error_log("WWN DEBUG: Order hooks registered");
    }

    public function on_order_status_changed($order_id, $old_status, $new_status, $order)
    {
        if (!class_exists('WWN_DB_Handler')) return;

        $raw_phone  = method_exists($order, 'get_billing_phone') ? $order->get_billing_phone() : '';
        $phone      = WWN_DB_Handler::sanitize_phone($raw_phone, '91');
        $user_name  = method_exists($order, 'get_billing_first_name') ? $order->get_billing_first_name() : '';
        $user_email = method_exists($order, 'get_billing_email') ? $order->get_billing_email() : '';

        WWN_DB_Handler::upsert_order($order_id, $new_status, $phone, '', $user_name, $user_email);

        error_log("WWN DEBUG: Order {$order_id} saved with phone {$phone}, name {$user_name}, email {$user_email}, status {$new_status}");
    }

    public function on_order_notify_any_status($order_id, $old_status, $new_status, $order)
    {
        if (!class_exists('WWN_DB_Handler')) return;

        $order_meta_key = "_wwn_sent_{$new_status}";
        if (get_post_meta($order_id, $order_meta_key, true)) {
            error_log("WWN DEBUG: WhatsApp already sent for order {$order_id} status {$new_status}");
            return;
        }

        $customer_phone = WWN_DB_Handler::get_phone_by_order($order_id);
        if (!$customer_phone) {
            error_log("WWN ERROR: No phone found for order {$order_id}, aborting WhatsApp send.");
            return;
        }

        $user_name   = $order->get_billing_first_name();
        $order_total = $order->get_total();

        $phone_number_id = $this->opt['phone_number_id'] ?? '';
        $access_token    = $this->opt['access_token'] ?? '';

        if (!$phone_number_id || !$access_token) {
            error_log("WWN ERROR: Missing WhatsApp API credentials.");
            return;
        }

        $url = "https://graph.facebook.com/v22.0/{$phone_number_id}/messages";

        // Decide template based on status
        if (in_array($new_status, ['completed', 'processing'])) {
            $template_name = 'orderconfirm';
            $status_for_db = 'completed';
        } else {
            $template_name = 'order_other_status'; // create this template later
            $status_for_db = 'pending';
        }

        // Customer Payload
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

        // Admin Payload
        $admin_phone = $this->opt['admin_phone'] ?? '';
        if ($admin_phone) {
            $admin_payload = [
                "messaging_product" => "whatsapp",
                "to"                => $admin_phone,
                "type"              => "template",
                "template"          => [
                    "name"     => "admin_order_notify",
                    "language" => ["code" => $this->template_lang],
                    "components" => [
                        [
                            "type" => "body",
                            "parameters" => [
                                [
                                    "type" => "text",
                                    "parameter_name" => "order_info",
                                    "text" => "New Order #{$order_id} by {$user_name} (₹{$order_total}) - Status: {$new_status}"
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            $this->send_whatsapp($url, $access_token, $admin_payload, $order_id, 'admin', $status_for_db);
        }

        update_post_meta($order_id, $order_meta_key, 1);
    }

    private function send_whatsapp($url, $access_token, $payload, $order_id, $type = 'customer', $status_for_db = 'completed')
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$access_token}",
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, wp_json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $err = curl_error($ch);
            WWN_DB_Handler::update_status($order_id, 'failed');
            WWN_DB_Handler::insert_log($order_id, ucfirst($type) . " cURL Error: {$err}");
            error_log("WWN ERROR: cURL Error ({$type}) for order {$order_id} - {$err}");
        } else {
            WWN_DB_Handler::update_status($order_id, $status_for_db); // Dynamic DB status
            WWN_DB_Handler::insert_log($order_id, ucfirst($type) . " Success: {$response}");
            error_log("WWN DEBUG: WhatsApp message ({$type}) sent for order {$order_id}");
        }

        curl_close($ch);
    }
}

<?php
if (!defined('ABSPATH')) exit;

class WWN_Order_Hooks
{
    private $opt;
    private $template_name = 'ratinia'; // Default template name, can be changed in settings
    private $template_lang = 'en'; // Template language code

    public function __construct()
    {
        $this->opt = get_option(WWN_OPTION_KEY, []);

        // Template name from brand
        $this->template_name = strtolower($this->opt['brand_name'] ?? 'ratinia');

        // Safety: ensure DB handler is loaded
        if (!class_exists('WWN_DB_Handler')) {
            error_log('WWN ERROR: WWN_DB_Handler not loaded. Check require_once in main plugin file.');
            return; // hard exit: prevents fatal on hooks
        }

        // Store/Update every status change
        add_action('woocommerce_order_status_changed', [$this, 'on_order_status_changed'], 10, 4);

        // Send on completed (guarded against duplicates)
        add_action('woocommerce_order_status_processing', [$this, 'on_order_completed'], 10, 1);
        add_action('woocommerce_order_status_completed', [$this, 'on_order_completed'], 10, 1);
        // If you find duplicate sends, comment the next hook:
        // add_action('woocommerce_thankyou', [$this, 'on_order_completed'], 10, 1);

        error_log("WWN DEBUG: Order hooks registered");
    }

    /**
     * Save or update record for ANY status transition
     */
    public function on_order_status_changed($order_id, $old_status, $new_status, $order)
    {
        if (!class_exists('WWN_DB_Handler')) return;

        // Grab billing phone, name & email
        $raw_phone  = method_exists($order, 'get_billing_phone') ? $order->get_billing_phone() : '';
        $phone      = WWN_DB_Handler::sanitize_phone($raw_phone, '91');
        $user_name  = method_exists($order, 'get_billing_first_name') ? $order->get_billing_first_name() : '';
        $user_email = method_exists($order, 'get_billing_email') ? $order->get_billing_email() : '';

        // Upsert into notifications table
        WWN_DB_Handler::upsert_order($order_id, $new_status, $phone, '', $user_name, $user_email);

        error_log("WWN DEBUG: Order {$order_id} saved with phone {$phone}, name {$user_name}, email {$user_email}, status {$new_status}");
    }

    /**
     * Send WhatsApp message when order is completed (only once)
     */
    public function on_order_completed($order_id)
    {
        if (!class_exists('WWN_DB_Handler')) return;

        error_log("WWN DEBUG: on_order_completed triggered for Order ID {$order_id}");

        $order = wc_get_order($order_id);
        if (!$order) {
            error_log("WWN ERROR: Order not found for ID {$order_id}");
            return;
        }

        // Duplicate-send guard
        if (get_post_meta($order_id, '_wwn_completed_sent', true)) {
            error_log("WWN DEBUG: Skipping duplicate send for Order {$order_id}");
            return;
        }

        // Phone must come from our table
        $customer_phone = WWN_DB_Handler::get_phone_by_order($order_id);
        error_log("WWN DEBUG: Phone fetched from table for order {$order_id}: {$customer_phone}");

        if (!$customer_phone) {
            error_log("WWN ERROR: No phone in table for Order {$order_id}; aborting send.");
            return;
        }

        // Build product image for header (optional)
        $product_image_url = '';
        $items = $order->get_items();
        if (!empty($items)) {
            $first_item = reset($items);
            $product_id = $first_item->get_product_id();
            $img_id     = get_post_thumbnail_id($product_id);
            if ($img_id) {
                $product_image_url = wp_get_attachment_url($img_id);
                error_log("WWN DEBUG: Found product image for order {$order_id}: {$product_image_url}");
            }
        }
        if (!$product_image_url) {
            $product_image_url = "https://yourdomain.com/default-image.jpg";
            error_log("WWN DEBUG: No product image found, using default for order {$order_id}");
        }

        // WhatsApp API creds
        $phone_number_id = $this->opt['phone_number_id'] ?? '';
        $access_token    = $this->opt['access_token'] ?? '';

        if (!$phone_number_id || !$access_token) {
            error_log("WWN ERROR: Missing WhatsApp API credentials. Aborting send for order {$order_id}");
            return;
        }

        $url  = "https://graph.facebook.com/v22.0/{$phone_number_id}/messages";

        $payload = array(
            "messaging_product" => "whatsapp",
            "to"                => $customer_phone,          // e.g. 918882935655
            "type"              => "template",
            "template"          => array(
                "name"     => "orderconfirm",               // Template name (screenshot ke hisaab se)
                "language" => array("code" => $this->template_lang), // ya en_US agar aisa approve hai
                "components" => array(
                    array(
                        "type"       => "body",
                        "parameters" => array(
                            array(
                                "type"           => "text",
                                "parameter_name" => "order_id",      // IMPORTANT for NAMED variables
                                "text"           => "OID" . $order_id . "RC"
                            )
                        )
                    )
                )
            )
        );



        // $payload = array(
        //     "messaging_product" => "whatsapp",
        //     "to"                => $customer_phone,
        //     "type"              => "template",
        //     "template"          => array(
        //         "name"     => $this->template_name,
        //         "language" => array("code" => $this->template_lang)
        //     )
        // );


        // In case we are use this image template so we are use this template
        // $payload = array(
        //     "messaging_product" => "whatsapp",
        //     "to"                => $customer_phone,
        //     "type"              => "template",
        //     "template"          => array(
        //         "name"     => $this->template_name,
        //         "language" => array("code" => $this->template_lang),
        //         "components" => array(
        //             array(
        //                 "type"       => "header",
        //                 "parameters" => array(
        //                     array(
        //                         "type"  => "image",
        //                         "image" => array("link" => $product_image_url)
        //                     )
        //                 )
        //             )
        //         )
        //     )
        // );

        error_log("WWN DEBUG: Payload for order {$order_id}: " . print_r($payload, true));
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer {$access_token}",
            "Content-Type: application/json"
        ));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, wp_json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $err = curl_error($ch);
            WWN_DB_Handler::update_status($order_id, 'failed');
            error_log("WWN ERROR: cURL Error for order {$order_id} - {$err}");
            // Insert log table entry
            WWN_DB_Handler::insert_log($order_id, "cURL Error: {$err}");
        } else {
            WWN_DB_Handler::update_status($order_id, 'completed');

            // Insert log table entry
            WWN_DB_Handler::insert_log($order_id, "Success: {$response}");

            update_post_meta($order_id, '_wwn_completed_sent', 1);
            error_log("WWN DEBUG: Marked order {$order_id} as sent");
        }

        // if (curl_errno($ch)) {
        //     $err = curl_error($ch);
        //     error_log("WWN ERROR: cURL Error for order {$order_id} - {$err}");
        //     WWN_DB_Handler::update_status($order_id, 'failed');
        // } else {
        //     error_log("WWN DEBUG: WhatsApp API Response for order {$order_id} - " . $response);
        //     WWN_DB_Handler::update_status($order_id, 'completed');

        //     // Mark sent to avoid duplicates
        //     update_post_meta($order_id, '_wwn_completed_sent', 1);
        //     error_log("WWN DEBUG: Marked order {$order_id} as sent");
        // }

        curl_close($ch);
    }
}

<?php
if (!defined('ABSPATH')) exit;

class WWN_Manual_Offer_Sender
{

    public function __construct()
    {
        add_action('wp_ajax_wwn_send_offer_test', [$this, 'send_test_offer']);
        add_action('wp_ajax_wwn_send_offer_all',  [$this, 'send_offer_all']);
    }

    /**
     * Send offer to a test number only
     */
    public function send_test_offer()
    {
        $phone    = preg_replace('/\D+/', '', sanitize_text_field($_POST['phone'] ?? ''));
        $template = sanitize_text_field($_POST['template'] ?? '');

        if (!$phone || !$template) {
            wp_send_json_error('Test number or template name missing.');
        }

        $this->send_whatsapp($phone, $template);
        wp_send_json_success("Test offer sent to {$phone} using template '{$template}'.");
    }

    /**
     * Send offer to all users (registered + guest order numbers)
     */
    public function send_offer_all()
    {
        $template = sanitize_text_field($_POST['template'] ?? '');
        if (!$template) {
            wp_send_json_error('Template name missing.');
        }

        $phones = [];

        // --- Registered users ---
        $args = [
            'role__in' => ['customer', 'subscriber'],
            'fields'   => ['ID'],
        ];
        $users = get_users($args);
        foreach ($users as $user_id) {
            $phone = get_user_meta($user_id, 'billing_phone', true);
            if ($phone) $phones[] = preg_replace('/\D+/', '', $phone);
        }

        // --- Guest users / past orders ---
        $orders = get_posts([
            'post_type'      => 'shop_order',
            'post_status'    => ['wc-completed', 'wc-processing', 'wc-on-hold'],
            'numberposts'    => -1,
            'fields'         => 'ids',
        ]);
        foreach ($orders as $order_id) {
            $phone = get_post_meta($order_id, '_billing_phone', true);
            if ($phone) $phones[] = preg_replace('/\D+/', '', $phone);
        }

        // Remove duplicates
        $phones = array_unique($phones);

        $count = 0;
        foreach ($phones as $phone) {
            if ($this->send_whatsapp($phone, $template)) $count++;
        }

        wp_send_json_success("Offer sent to {$count} unique phone numbers using template '{$template}'.");
    }

    /**
     * Send WhatsApp message via API
     */
    private function send_whatsapp($phone, $template_name)
    {
        $opt = get_option(WWN_OPTION_KEY, []);
        $phone_number_id = $opt['phone_number_id'] ?? '';
        $access_token    = $opt['access_token'] ?? '';

        if (!$phone_number_id || !$access_token || !$phone) return false;

        $url = "https://graph.facebook.com/v22.0/{$phone_number_id}/messages";

        $payload = [
            "messaging_product" => "whatsapp",
            "to"                => $phone,
            "type"              => "template",
            "template"          => [
                "name"     => $template_name,
                "language" => ["code" => "en"],
            ]
        ];

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

        // --- Logging to WP debug if enabled ---
        if (!empty($opt['enable_logs'])) {
            $log = "[WWN OFFER] To: {$phone}, Template: {$template_name}, Response: {$response}";
            error_log($log);
        }

        // --- Save to database ---
        global $wpdb;
        $table = $wpdb->prefix . 'wwn_sent_offers';
        $wpdb->insert($table, [
            'phone'         => $phone,
            'template_name' => $template_name,
            'sent_at'       => current_time('mysql'),
            'sent_by'       => wp_get_current_user()->user_login ?: 'system',
        ]);

        return true;
    }
}

new WWN_Manual_Offer_Sender();

<?php
if (!defined('ABSPATH')) exit;

/**
 * Meta WhatsApp Cloud API sender.
 * Uses: POST https://graph.facebook.com/{API_VERSION}/{PHONE_NUMBER_ID}/messages
 */
class WWN_Send_Message {

    protected static $settings;
    const API_VERSION = 'v22.0'; // Change here when Meta updates API version

    public static function boot() {
        self::$settings = get_option(WWN_OPTION_KEY, []);
    }

    /**
     * Normalize phone to E.164 with + prefix for API.
     */
    protected static function format_phone($raw) {
        $digits = preg_replace('/\D+/', '', $raw);
        if (!$digits) return '';
        if (strlen($digits) < 10) return ''; // Too short to be valid
        return '+' . $digits;
    }

    /**
     * Send simple text message.
     * Returns: array|false
     */
    public static function send_text($to_phone, $text) {
        $opt = self::$settings ?: get_option(WWN_OPTION_KEY, []);

        $phone_number_id = trim($opt['phone_number_id'] ?? '');
        $token           = trim($opt['access_token'] ?? '');
        $brand           = trim($opt['brand_name'] ?? 'Your Brand'); // BRAND_NAME_PLACEHOLDER

        if (!$phone_number_id || !$token) {
            self::log('❌ Missing phone_number_id or access_token.');
            return false;
        }

        $to = self::format_phone($to_phone);
        if (!$to) {
            self::log('❌ Invalid recipient phone: ' . $to_phone);
            return false;
        }

        $text = '[' . $brand . '] ' . trim($text);

        $url = sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            self::API_VERSION,
            rawurlencode($phone_number_id)
        );

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => ['body' => $text]
        ];

        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json'
            ],
            'timeout' => 20,
            'body'    => wp_json_encode($payload),
        ];

        $res = wp_remote_post($url, $args);

        if (is_wp_error($res)) {
            self::log('⚠️ WP Error: ' . $res->get_error_message());
            return false;
        }

        $code = wp_remote_retrieve_response_code($res);
        $body = wp_remote_retrieve_body($res);
        $decoded = json_decode($body, true);

        self::log("📤 Send to $to, HTTP $code, Response: $body");

        if ($code >= 200 && $code < 300 && !empty($decoded['messages'][0]['id'])) {
            return [
                'success' => true,
                'id'      => $decoded['messages'][0]['id'],
                'response'=> $decoded
            ];
        } else {
            $error_msg = $decoded['error']['message'] ?? 'Unknown API error';
            $error_code = $decoded['error']['code'] ?? '';
            self::log("❌ WhatsApp API Error ($error_code): $error_msg");
            return false;
        }
    }

    /**
     * Send interactive buttons.
     */
    public static function send_buttons($to_phone, $header_text, $body_text, $buttons = []) {
        $opt = self::$settings ?: get_option(WWN_OPTION_KEY, []);
        $phone_number_id = trim($opt['phone_number_id'] ?? '');
        $token           = trim($opt['access_token'] ?? '');
        $brand           = trim($opt['brand_name'] ?? 'Your Brand'); // BRAND_NAME_PLACEHOLDER

        if (!$phone_number_id || !$token) {
            self::log('❌ Missing phone_number_id or access_token for buttons.');
            return false;
        }

        $to = self::format_phone($to_phone);
        if (!$to) {
            self::log('❌ Invalid phone for buttons: ' . $to_phone);
            return false;
        }

        $body_text = '[' . $brand . '] ' . trim($body_text);

        $btns = [];
        $i = 1;
        foreach ($buttons as $title) {
            if ($i > 3) break;
            $btns[] = [
                'type'  => 'reply',
                'reply' => ['id' => 'btn_' . $i, 'title' => $title]
            ];
            $i++;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'header' => ['type' => 'text', 'text' => $header_text],
                'body'   => ['text' => $body_text],
                'action' => ['buttons' => $btns]
            ]
        ];

        $url = sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            self::API_VERSION,
            rawurlencode($phone_number_id)
        );

        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json'
            ],
            'timeout' => 20,
            'body'    => wp_json_encode($payload),
        ];

        $res = wp_remote_post($url, $args);
        if (is_wp_error($res)) {
            self::log('⚠️ Buttons WP Error: ' . $res->get_error_message());
            return false;
        }

        $body = wp_remote_retrieve_body($res);
        self::log('📤 Buttons response: ' . $body);
        return true;
    }

    public static function log($msg) {
        $opt = self::$settings ?: get_option(WWN_OPTION_KEY, []);
        if (!empty($opt['enable_logs']) && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WWN] ' . $msg);
        }
    }
}

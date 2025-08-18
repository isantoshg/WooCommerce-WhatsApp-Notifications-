<?php
if (!defined('ABSPATH')) exit;

class WWN_DB_Handler
{

    /**
     * Return table name with prefix
     */
    private static function table_name()
    {
        global $wpdb;
        return $wpdb->prefix . 'wwn_notifications';
    }

    /**
     * Sanitize to E.164-like digits-only (without '+')
     * Default country code = 91 (India). Change if needed.
     */
    public static function sanitize_phone($raw, $default_cc = '91')
    {
        $digits = preg_replace('/\D+/', '', (string)$raw);
        if (!$digits) return '';

        // Already starts with country code?
        if (strpos($digits, $default_cc) !== 0) {
            // Remove leading zeros and prepend default CC
            $digits = $default_cc . ltrim($digits, '0');
        }
        return $digits;
    }

    /**
     * Insert new row
     */
    // public static function insert_order($order_id, $status, $phone_number = '', $message = '') {
    //     global $wpdb;
    //     $table = self::table_name();

    //     $wpdb->insert(
    //         $table,
    //         array(
    //             'order_id'     => (int) $order_id,
    //             'phone_number' => $phone_number,
    //             'status'       => $status,
    //             'message'      => $message,
    //             'created_at'   => current_time('mysql'),
    //             'updated_at'   => current_time('mysql'),
    //         ),
    //         array('%d','%s','%s','%s','%s','%s')
    //     );
    // }
    public static function insert_order($order_id, $status, $phone_number = '', $message = '', $user_name = '', $user_email = '')
    {
        global $wpdb;
        $table = self::table_name();

        $wpdb->insert(
            $table,
            array(
                'order_id'     => (int) $order_id,
                'user_name'    => $user_name,
                'user_email'   => $user_email,
                'phone_number' => $phone_number,
                'status'       => $status,
                'message'      => $message,
                'created_at'   => current_time('mysql'),
                'updated_at'   => current_time('mysql'),
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
    }
    /**
     * Update status for an order (no-op if not found)
     */
    public static function update_status($order_id, $status)
    {
        global $wpdb;
        $table = self::table_name();

        $wpdb->update(
            $table,
            array(
                'status'     => $status,
                'updated_at' => current_time('mysql'),
            ),
            array('order_id' => (int)$order_id),
            array('%s', '%s'),
            array('%d')
        );
    }

    /**
     * If exists -> update status (+ optionally phone if blank).
     * Else -> insert.
     */
    // public static function upsert_order($order_id, $status, $phone_number = '', $message = '') {
    //     $row = self::get_order($order_id);

    //     if ($row) {
    //         // Update status always
    //         self::update_status($order_id, $status);

    //         // If we have a new phone and DB phone is empty, update phone too
    //         if ($phone_number && empty($row->phone_number)) {
    //             self::update_phone($order_id, $phone_number);
    //         }
    //     } else {
    //         self::insert_order($order_id, $status, $phone_number, $message);
    //     }
    // }
    public static function upsert_order($order_id, $status, $phone_number = '', $message = '', $user_name = '', $user_email = '')
    {
        $row = self::get_order($order_id);

        if ($row) {
            self::update_status($order_id, $status);
            if ($phone_number && empty($row->phone_number)) {
                self::update_phone($order_id, $phone_number);
            }
        } else {
            self::insert_order($order_id, $status, $phone_number, $message, $user_name, $user_email);
        }
    }
    /**
     * Update only phone
     */
    public static function update_phone($order_id, $phone_number)
    {
        global $wpdb;
        $table = self::table_name();

        $wpdb->update(
            $table,
            array(
                'phone_number' => $phone_number,
                'updated_at'   => current_time('mysql'),
            ),
            array('order_id' => (int)$order_id),
            array('%s', '%s'),
            array('%d')
        );
    }

    /**
     * Get row as OBJECT (->phone_number, ->status, ...)
     */
    public static function get_order($order_id)
    {
        global $wpdb;
        $table = self::table_name();

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE order_id = %d LIMIT 1", (int)$order_id)
        );
    }

    /**
     * Helper: just get phone (or '')
     */
    public static function get_phone_by_order($order_id)
    {
        $row = self::get_order($order_id);
        return $row && !empty($row->phone_number) ? $row->phone_number : '';
    }

    /**
     * Logs insert function
     */
    public static function insert_log($order_id, $log)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wwn_logs';

        $wpdb->insert(
            $table,
            array(
                'order_id'   => (int) $order_id,
                'log'        => $log,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ),
            array('%d', '%s', '%s', '%s')
        );
    }
}

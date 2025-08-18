<?php
if (!defined('ABSPATH')) exit;

class WWN_Settings_Page {

    public function __construct() {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this, 'register']);
        add_action('admin_enqueue_scripts', [$this, 'assets']);
    }

    public function menu() {
        add_menu_page(
            __('WhatsApp Notify', 'wp-whatsapp-notify'),
            __('WhatsApp Notify', 'wp-whatsapp-notify'),
            'manage_options',
            'wwn-settings',
            [$this, 'render'],
            'dashicons-whatsapp',
            56
        );
    }

    public function register() {
        register_setting('wwn_settings_group', WWN_OPTION_KEY, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitize'],
        ]);
    }

    public function sanitize($input) {
        $out = get_option(WWN_OPTION_KEY, array());

        // Sanitize basic fields
        $out['app_id']          = sanitize_text_field($input['app_id'] ?? '');
        $out['phone_number_id'] = sanitize_text_field($input['phone_number_id'] ?? '');
        $out['access_token']    = trim($input['access_token'] ?? '');
        $out['brand_name']      = sanitize_text_field($input['brand_name'] ?? 'Your Brand');

        // Phone: allow only digits, min length check
        $phone = preg_replace('/\D+/', '', $input['admin_phone'] ?? '');
        if (strlen($phone) < 8) {
            add_settings_error(WWN_OPTION_KEY, 'invalid_phone', __('Invalid phone number. Please enter in format CountryCode+Number (e.g. 91XXXXXXXXXX).', 'wp-whatsapp-notify'));
        }
        $out['admin_phone'] = $phone;

        // Abandoned cart delay: min 30 mins
        $delay = intval($input['abandon_delay_m'] ?? 240);
        if ($delay < 30) {
            add_settings_error(WWN_OPTION_KEY, 'invalid_delay', __('Delay must be at least 30 minutes.', 'wp-whatsapp-notify'));
            $delay = 30;
        }
        $out['abandon_delay_m'] = $delay;

        // Enable logs
        $out['enable_logs'] = !empty($input['enable_logs']) ? 1 : 0;

        // Token required check
        if (empty($out['access_token'])) {
            add_settings_error(WWN_OPTION_KEY, 'missing_token', __('Access Token is required to send messages.', 'wp-whatsapp-notify'));
        }

        return $out;
    }

    public function assets($hook) {
        if ($hook === 'toplevel_page_wwn-settings') {
            wp_enqueue_style('wwn-admin', WWN_PLUGIN_URL . 'assets/css/admin-style.css', [], WWN_VERSION);
            wp_add_inline_script('jquery-core', "
                jQuery(document).ready(function($){
                    $('.toggle-token').on('click', function(){
                        let field = $('#wwn_token_field');
                        if(field.attr('type') === 'password'){
                            field.attr('type','text');
                            $(this).text('Hide');
                        } else {
                            field.attr('type','password');
                            $(this).text('Show');
                        }
                    });
                });
            ");
        }
    }

    public function render() {
        if (!current_user_can('manage_options')) return;
        $opt = get_option(WWN_OPTION_KEY, []);
        settings_errors(WWN_OPTION_KEY);
        ?>
        <div class="wrap wwn-wrap">
            <h1>WhatsApp Notify <small style="opacity:0.6;">(Meta Cloud API)</small></h1>
            <p>Enter your Meta WhatsApp Cloud API credentials. These are required for sending automated WhatsApp messages for orders and abandoned carts.</p>

            <form method="post" action="options.php" class="wwn-card">
                <?php settings_fields('wwn_settings_group'); ?>
                <?php $v = fn($k,$d='') => esc_attr($opt[$k] ?? $d); ?>

                <table class="form-table">
                    <tr>
                        <th><label for="app_id">App ID <span style="color:red">*</span></label></th>
                        <td><input type="text" id="app_id" name="<?php echo WWN_OPTION_KEY; ?>[app_id]" value="<?php echo $v('app_id'); ?>" class="regular-text" required /></td>
                    </tr>

                    <tr>
                        <th><label for="phone_number_id">Phone Number ID <span style="color:red">*</span></label></th>
                        <td><input type="text" id="phone_number_id" name="<?php echo WWN_OPTION_KEY; ?>[phone_number_id]" value="<?php echo $v('phone_number_id'); ?>" class="regular-text" required /></td>
                    </tr>

                    <tr>
                        <th><label for="wwn_token_field">Access Token <span style="color:red">*</span></label></th>
                        <td>
                            <div class="wwn-token-field">
                            <input type="password" id="wwn_token_field" name="<?php echo WWN_OPTION_KEY; ?>[access_token]" value="<?php echo $v('access_token'); ?>" class="regular-text" required />
                            <button type="button" class="button toggle-token" style="margin-top:4px;">Show</button>
                            </div>
                            <p class="description">Get this from your <a href="https://developers.facebook.com/apps/" target="_blank">Meta Developer Dashboard</a>.</p>
                        </td>
                    </tr>

                    <tr>
                        <th><label for="admin_phone">Admin Phone <span style="color:red">*</span></label></th>
                        <td>
                            <input type="text" id="admin_phone" placeholder="91XXXXXXXXXX" name="<?php echo WWN_OPTION_KEY; ?>[admin_phone]" value="<?php echo $v('admin_phone'); ?>" class="regular-text" required />
                            <p class="description">CountryCode + Number, without plus sign.</p>
                        </td>
                    </tr>

                    <tr>
                        <th><label for="brand_name">Brand Name</label></th>
                        <td>
                            <input type="text" id="brand_name" name="<?php echo WWN_OPTION_KEY; ?>[brand_name]" value="<?php echo $v('brand_name','Your Brand'); ?>" class="regular-text" />
                            <p class="description">Shown in message body until WhatsApp Green Tick is verified.</p>
                        </td>
                    </tr>

                    <tr>
                        <th><label for="abandon_delay_m">Abandoned Cart Delay (minutes)</label></th>
                        <td><input type="number" id="abandon_delay_m" min="30" step="15" name="<?php echo WWN_OPTION_KEY; ?>[abandon_delay_m]" value="<?php echo $v('abandon_delay_m',240); ?>" /></td>
                    </tr>

                    <tr>
                        <th>Enable Logs</th>
                        <td><label><input type="checkbox" name="<?php echo WWN_OPTION_KEY; ?>[enable_logs]" value="1" <?php checked(1, intval($opt['enable_logs'] ?? 0)); ?> /> Yes</label></td>
                    </tr>
                </table>

                <?php submit_button('Save Settings'); ?>
            </form>

            <hr />
        </div>
        <?php
    }

    private function send_broadcast_occupation($occupation, $message) {
        $args = [
            'meta_key'   => 'billing_occupation',
            'meta_value' => $occupation,
            'fields'     => ['ID']
        ];
        $users = get_users($args);
        $count = 0;
        foreach ($users as $u) {
            $phone = get_user_meta($u->ID, 'billing_phone', true);
            if (!$phone) continue;
            WWN_Send_Message::send_text($phone, $message);
            $count++;
        }
        return $count;
    }
}

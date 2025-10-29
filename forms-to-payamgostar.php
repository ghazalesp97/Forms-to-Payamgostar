<?php
/**
 * Plugin Name: Forms to Payamgostar
 * Plugin URI:  https://github.com/ghazalesp97/Forms-to-Payamgostar
 * Description: Send WordPress form submissions (custom and popular forms) to Payamgostar CRM via AJAX with optional reCAPTCHA.
 * Version:     1.3.0
 * Author:      Ghazaleh Samanipour
 * Text Domain: forms-to-payamgostar
 */

if (!defined('ABSPATH')) exit;

class Payamgostar_Form_Handler {

    private $api_url;

    public function __construct() {
        $this->api_url = get_option('ftp_api_url', '');

        // Hooks for popular forms
        add_action('wpcf7_mail_sent', [$this, 'handle_cf7']);
        add_action('gform_after_submission', [$this, 'handle_gf'], 10, 2);
        add_action('forminator_form_after_save_entry', [$this, 'handle_forminator'], 10, 2);
        add_action('wpforms_process_complete', [$this, 'handle_wpforms'], 10, 4);

        // AJAX for custom form
        add_action('wp_ajax_payamgostar_submit', [$this, 'ajax_submit']);
        add_action('wp_ajax_nopriv_payamgostar_submit', [$this, 'ajax_submit']);

        // Admin settings
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);

        // Front-end scripts
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
    }

    /** ---------------- SETTINGS ---------------- **/

    public function add_settings_page() {
        add_options_page(
            'Payamgostar Settings',
            'Payamgostar',
            'manage_options',
            'forms-to-payamgostar',
            [$this, 'settings_page_html']
        );
    }

    public function register_settings() {
        register_setting('forms_to_payamgostar', 'ftp_api_url', [
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => '',
        ]);
        register_setting('forms_to_payamgostar', 'ftp_recaptcha_site_key', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);
        register_setting('forms_to_payamgostar', 'ftp_recaptcha_secret_key', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);
    }

    public function settings_page_html() { ?>
        <div class="wrap">
            <h1>Forms to Payamgostar</h1>
            <form method="post" action="options.php">
                <?php settings_fields('forms_to_payamgostar'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Payamgostar API URL</th>
                        <td>
                            <input type="url" name="ftp_api_url"
                                   value="<?php echo esc_attr(get_option('ftp_api_url')); ?>"
                                   size="50" required />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">reCAPTCHA Site Key</th>
                        <td>
                            <input type="text" name="ftp_recaptcha_site_key"
                                   value="<?php echo esc_attr(get_option('ftp_recaptcha_site_key')); ?>"
                                   size="50" />
                            <p class="description">Leave empty to disable CAPTCHA.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">reCAPTCHA Secret Key</th>
                        <td>
                            <input type="text" name="ftp_recaptcha_secret_key"
                                   value="<?php echo esc_attr(get_option('ftp_recaptcha_secret_key')); ?>"
                                   size="50" />
                            <p class="description">Leave empty to disable CAPTCHA.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
    <?php }

    /** ---------------- FRONT-END SCRIPT ---------------- **/

    public function enqueue_frontend_scripts() {
        // Capture UTMs and store in cookies
        wp_enqueue_script(
            'payamgostar-utm-capture',
            plugin_dir_url(__FILE__) . 'utm-capture.js',
            [],
            '1.0',
            true
        );
        wp_enqueue_script(
            'payamgostar-form',
            plugin_dir_url(__FILE__) . 'payamgostar-form.js',
            ['jquery'],
            '1.0',
            true
        );

        // Pass AJAX URL to JS
        wp_localize_script('payamgostar-form', 'Payamgostar', [
            'ajax_url' => admin_url('admin-ajax.php')
        ]);
    }

    /** ---------------- API SUBMISSION ---------------- **/

    private function send_to_payamgostar($data) {
        if (empty($this->api_url)) return new WP_Error('no_api', 'API URL not set.');

        $name        = sanitize_text_field($data['name'] ?? '');
        $email       = sanitize_email($data['email'] ?? '');
        $phone       = sanitize_text_field($data['phone'] ?? '');
        $company     = sanitize_text_field($data['company'] ?? '');
        $industry    = sanitize_text_field($data['industry'] ?? '');
        $usersnumber = sanitize_text_field($data['users'] ?? '');
        $formtype    = sanitize_text_field($data['formtype'] ?? 'Lead');
        $source      = sanitize_text_field($data['source'] ?? 'درخواست تماس');
        $refcode     = sanitize_text_field($data['refcode'] ?? '');

        $extended_properties = [['key' => 'refcode', 'value' => $refcode]];

        // --- UTM detection (from custom cookie keys) ---
        $utm_source   = $_COOKIE['_utmso'] ?? '';
        $utm_medium   = $_COOKIE['_utmme'] ?? '';
        $utm_term     = $_COOKIE['_utmte'] ?? '';
        $utm_campaign = $_COOKIE['_utmca'] ?? '';
        $utm_content  = $_COOKIE['_utmco'] ?? '';

        // Fallback: Try parsing from referer if missing
        if (empty($utm_source) && isset($_SERVER['HTTP_REFERER'])) {
            $referer = parse_url($_SERVER['HTTP_REFERER']);
            if (isset($referer['query'])) {
                parse_str($referer['query'], $refererParams);
                $utm_source   = $refererParams['utm_source']   ?? $utm_source;
                $utm_medium   = $refererParams['utm_medium']   ?? $utm_medium;
                $utm_term     = $refererParams['utm_term']     ?? $utm_term;
                $utm_campaign = $refererParams['utm_campaign'] ?? $utm_campaign;
                $utm_content  = $refererParams['utm_content']  ?? $utm_content;
            }
        }



        $payload = [
            'name'               => $name,
            'email'              => $email,
            'phone'              => $phone,
            'company'            => $company,
            'industry'           => $industry,
            'usersnumber'        => $usersnumber,
            'formtype'           => $formtype,
            'source'             => $source,
            'ipaddress'          => $_SERVER['REMOTE_ADDR'] ?? '',
            'referrer'           => $_SERVER['HTTP_REFERER'] ?? '',
            'extendedProperties' => $extended_properties,
            'utmmedium'          => $utm_medium ,
			'utmsource'          => $utm_source ,
			'utmterm'            => $utm_term ,
            'utmcampaign'        => $utm_campaign,
            'utmcontent'         => $utm_content,
        ];

        $response = wp_remote_post($this->api_url, [
            'body'    => wp_json_encode($payload),
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'timeout' => 15,
        ]);


if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('Payamgostar Response Code: ' . wp_remote_retrieve_response_code($response));
    error_log('Payamgostar Response Headers: ' . print_r(wp_remote_retrieve_headers($response), true));
    error_log('Payamgostar Response Body: ' . substr(wp_remote_retrieve_body($response), 0, 500)); // limit to avoid spam
}


        

        return $response;
    }

    /** ---------------- AJAX HANDLER ---------------- **/

    public function ajax_submit() {
        $data = $_POST;

        // reCAPTCHA
        $site_key   = get_option('ftp_recaptcha_site_key', '');
        $secret_key = get_option('ftp_recaptcha_secret_key', '');
        if (!empty($site_key) && !empty($secret_key)) {
            $captcha = $data['g-recaptcha-response'] ?? '';
            if (empty($captcha)) {
                wp_send_json_error(['message' => 'لطفاً کپچا را تکمیل کنید.']);
            }

            $response = wp_remote_post("https://www.google.com/recaptcha/api/siteverify", [
                'body' => [
                    'secret'   => $secret_key,
                    'response' => $captcha,
                    'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
                ],
            ]);

            $result = json_decode(wp_remote_retrieve_body($response), true);

            if (empty($result['success'])) {
                wp_send_json_error(['message' => 'تأیید کپچا ناموفق بود. لطفاً دوباره تلاش کنید.']);
            }
        }

        // Send to API
        $api_response = $this->send_to_payamgostar($data);

        if (is_wp_error($api_response) || wp_remote_retrieve_response_code($api_response) !== 200) {
            wp_send_json_error(['message' => 'خطا در ارسال اطلاعات به سرور. لطفاً دوباره تلاش کنید.']);
        }

        wp_send_json_success(['message' => 'فرم شما با موفقیت ارسال شد.']);
    }

    /** ---------------- OTHER FORM PLUGINS ---------------- **/

    public function handle_cf7($form) {
        $submission = WPCF7_Submission::get_instance();
        if ($submission) {
            $this->send_to_payamgostar($submission->get_posted_data());
        }
    }

    public function handle_gf($entry, $form) {
        $this->send_to_payamgostar($entry);
    }

    public function handle_forminator($entry, $form_id) {
        $this->send_to_payamgostar($entry->get_fields());
    }

    public function handle_wpforms($fields, $entry, $form_data, $entry_id) {
        $this->send_to_payamgostar($fields);
    }
}

new Payamgostar_Form_Handler();

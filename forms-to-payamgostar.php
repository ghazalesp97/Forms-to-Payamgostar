<?php
/**
 * Plugin Name: Forms to Payamgostar
 * Plugin URI:  https://github.com/ghazalesp97/Forms-to-Payamgostar
 * Description: Send WordPress form submissions (Elementor, CF7, Gravity Forms, WPForms, Forminator, and custom forms) to Payamgostar CRM.
 * Version:     1.0.0
 * Author:      Ghazaleh Samanipour
 * Text Domain: forms-to-payamgostar
 */

if (!defined('ABSPATH')) exit;

class Payamgostar_Form_Handler {

    private $api_url;

    public function __construct() {
        // API URL from settings
        $this->api_url = get_option('ftp_api_url', '');

        // Hooks for popular form plugins
        add_action('wpcf7_mail_sent', [$this, 'handle_cf7']);
        add_action('gform_after_submission', [$this, 'handle_gf'], 10, 2);
        add_action('forminator_form_after_save_entry', [$this, 'handle_forminator'], 10, 2);
        add_action('wpforms_process_complete', [$this, 'handle_wpforms'], 10, 4);
        add_action('init', [$this, 'handle_custom']);

        // Admin settings page
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
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
    }

    public function settings_page_html() {
        ?>
        <div class="wrap">
            <h1>Forms to Payamgostar</h1>
            <form method="post" action="options.php">
                <?php settings_fields('forms_to_payamgostar'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Payamgostar API URL</th>
                        <td>
                            <input type="url" name="ftp_api_url" value="<?php echo esc_attr(get_option('ftp_api_url')); ?>" size="50" required />
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /** ---------------- FORM HANDLERS ---------------- **/

    private function send_to_payamgostar($data) {
        if (empty($this->api_url)) return;

        // Sanitize input
        $name = sanitize_text_field($data['name'] ?? '');
        $email = sanitize_email($data['email'] ?? '');
        $phone = sanitize_text_field($data['phone'] ?? '');
        $company = sanitize_text_field($data['company'] ?? '');
        $industry = sanitize_text_field($data['industry'] ?? '');
        $usersnumber = sanitize_text_field($data['users'] ?? '');
        $formtype = sanitize_text_field($data['formtype'] ?? 'Lead');
        $source = sanitize_text_field($data['source'] ?? '');
        $refcode = sanitize_text_field($data['refcode'] ?? '');

        // Extended properties array
        $extended_properties = [
            [
                'key'   => 'refcode',
                'value' => $refcode
            ]
        ];

        // Build payload
        $payload = [
            'name'              => $name,
            'email'             => $email,
            'phone'             => $phone,
            'company'           => $company,
            'industry'          => $industry,
            'usersnumber'       => $usersnumber,
            'formtype'          => $formtype,
            'source'            => $source,
            'ipaddress'         => $_SERVER['REMOTE_ADDR'] ?? '',
            'referrer'          => $_SERVER['HTTP_REFERER'] ?? '',
            'extendedProperties'=> $extended_properties,
        ];

        // Send request
        $response = wp_remote_post($this->api_url, [
            'body'    => wp_json_encode($payload),
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'timeout' => 15,
        ]);

        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Payamgostar Payload: ' . wp_json_encode($payload));
            error_log('Payamgostar Response: ' . print_r($response, true));
        }
    }

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

    public function handle_custom() {
        if (!empty($_POST['payamgostar_form'])) {
            $this->send_to_payamgostar($_POST);
        }
    }
}

new Payamgostar_Form_Handler();

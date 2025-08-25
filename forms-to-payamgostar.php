<?php
/**
 * Plugin Name: Forms to Payamgostar
 * Plugin URI:  https://yourwebsite.com/forms-to-payamgostar
 * Description: Send form submissions from WordPress directly to Payamgostar CRM.
 * Version:     1.0.0
 * Author:      Ghazaleh Samanipour
 * Text Domain: forms-to-payamgostar
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Prevent direct access
}

class Forms_To_Payamgostar {

    private $api_url;

    public function __construct() {
        $this->api_url = get_option('ftp_api_url', ''); 

        // Hooks for popular form plugins
        add_action('wpcf7_mail_sent', [$this, 'handle_cf7']);
        add_action('gform_after_submission', [$this, 'handle_gf'], 10, 2);
        add_action('forminator_form_after_save_entry', [$this, 'handle_forminator'], 10, 2);
        add_action('wpforms_process_complete', [$this, 'handle_wpforms'], 10, 4);

        // Custom forms (with hidden field `payamgostar_form=1`)
        add_action('init', [$this, 'handle_custom']);

        // Settings page
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
        register_setting('forms_to_payamgostar', 'ftp_api_url');
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
                            <input type="text" name="ftp_api_url" value="<?php echo esc_attr(get_option('ftp_api_url')); ?>" size="50"/>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /** ---------------- FORM HANDLERS ---------------- **/

    public function handle_cf7($form) {
        $submission = WPCF7_Submission::get_instance();
        if ($submission) {
            $this->send($submission->get_posted_data());
        }
    }

    public function handle_gf($entry, $form) {
        $this->send($entry);
    }

    public function handle_forminator($entry, $form_id) {
        $this->send($entry->get_fields());
    }

    public function handle_wpforms($fields, $entry, $form_data, $entry_id) {
        $this->send($fields);
    }

    public function handle_custom() {
        if (!empty($_POST['payamgostar_form'])) {
            $this->send($_POST);
        }
    }

    /** ---------------- API SENDER ---------------- **/

    private function send($data) {
        if (empty($this->api_url)) return;

        $payload = [
            'name'     => $data['name'] ?? '',
            'email'    => $data['email'] ?? '',
            'phone'    => $data['phone'] ?? '',
            'company'  => $data['company'] ?? '',
            'industry' => $data['industry'] ?? '',
            'users'    => $data['users'] ?? '',
            'formtype' => $data['formtype'] ?? 'Lead',
            'source'   => $data['source'] ?? '',
            'refcode'  => $data['refcode'] ?? '',
            'ip'       => $_SERVER['REMOTE_ADDR'],
            'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
        ];

        $response = wp_remote_post($this->api_url, [
            'body'    => wp_json_encode($payload),
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'timeout' => 15,
        ]);

        error_log('Payamgostar Payload: ' . wp_json_encode($payload));
        error_log('Payamgostar Response: ' . print_r($response, true));
    }
}

new Forms_To_Payamgostar();
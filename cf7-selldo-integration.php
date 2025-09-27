<?php
/**
 * Plugin Name: CF7 to Sell.do Integration
 * Description: Sends Contact Form 7 submissions to Sell.do CRM API with form selection and SRD option in settings.
 * Version: 1.3
 * Author: Muhammad Azam
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Hook into CF7 before email is sent
add_action('wpcf7_before_send_mail', 'cf7_to_selldo_integration');

function cf7_to_selldo_integration($contact_form) {
    $submission = WPCF7_Submission::get_instance();
    if (!$submission) {
        return;
    }

    $data = $submission->get_posted_data();

    // Get selected form ID and SRD from settings
    $selected_form_id = get_option('cf7_selldo_form_id', '');
    $srd_value        = get_option('cf7_selldo_srd', '');
    $api_key          = get_option('cf7_selldo_api_key', '');

    if (empty($api_key)) {
        error_log('Sell.do API Error: API key not set in settings.');
        return;
    }

    // Only proceed if this form matches the selected form
    if ($contact_form->id() != $selected_form_id) {
        return;
    }

    // 🔧 Map CF7 fields -> Sell.do fields
    $name  = isset($data['your-name']) ? sanitize_text_field($data['your-name']) : '';
    $email = isset($data['your-email']) ? sanitize_email($data['your-email']) : '';
    $phone = isset($data['mobileno']) ? sanitize_text_field($data['mobileno']) : '';
    $note  = isset($data['your-service']) ? sanitize_textarea_field($data['your-service']) : '';

    // Sell.do API endpoint
    $url = "https://app.sell.do/api/leads/create";

    // Payload in Sell.do format
    $payload = array(
        'api_key' => $api_key,
        'sell_do' => array(
            'form' => array(
                'lead' => array(
                    'name'  => $name,
                    'email' => $email,
                    'phone' => $phone,
                ),
                'note' => array(
                    'content' => $note,
                ),
            ),
            'campaign' => array(
                'srd' => $srd_value
            ),
        ),
    );

    // Send data to Sell.do
    $response = wp_remote_post($url, array(
        'method'      => 'POST',
        'body'        => $payload,
        'timeout'     => 30,
        'redirection' => 5,
        'blocking'    => true,
    ));

    // Debug log
    if (is_wp_error($response)) {
        error_log('Sell.do API Error: ' . $response->get_error_message());
    } else {
        error_log('Sell.do API Response: ' . wp_remote_retrieve_body($response));
    }
}

/**
 * -------------------------
 * ADMIN SETTINGS PAGE
 * -------------------------
 */

// Add menu item
add_action('admin_menu', 'cf7_selldo_menu');
function cf7_selldo_menu() {
    add_options_page(
        'CF7 Sell.do Settings',
        'CF7 Sell.do',
        'manage_options',
        'cf7-selldo',
        'cf7_selldo_settings_page'
    );
}

// Register settings
add_action('admin_init', 'cf7_selldo_settings');
function cf7_selldo_settings() {
    register_setting('cf7_selldo_options', 'cf7_selldo_api_key');
    register_setting('cf7_selldo_options', 'cf7_selldo_form_id');
    register_setting('cf7_selldo_options', 'cf7_selldo_srd');
}

// Settings page HTML
function cf7_selldo_settings_page() {
    // Get all CF7 forms
    $forms = get_posts(array(
        'post_type' => 'wpcf7_contact_form',
        'numberposts' => -1
    ));
    ?>
    <div class="wrap">
        <h1>CF7 to Sell.do Integration</h1>
        <form method="post" action="options.php">
            <?php settings_fields('cf7_selldo_options'); ?>
            <?php do_settings_sections('cf7_selldo_options'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Sell.do API Key</th>
                    <td>
                        <input type="text" name="cf7_selldo_api_key" value="<?php echo esc_attr(get_option('cf7_selldo_api_key', '')); ?>" style="width: 400px;" />
                        <p class="description">Enter your Sell.do API Key here.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Select CF7 Form</th>
                    <td>
                        <select name="cf7_selldo_form_id" style="width: 400px;">
                            <option value="">-- Select a Form --</option>
                            <?php foreach ($forms as $form) : ?>
                                <option value="<?php echo esc_attr($form->ID); ?>" <?php selected(get_option('cf7_selldo_form_id'), $form->ID); ?>>
                                    <?php echo esc_html($form->post_title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Choose which Contact Form 7 form should send data to Sell.do.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">SRD Value</th>
                    <td>
                        <input type="text" name="cf7_selldo_srd" value="<?php echo esc_attr(get_option('cf7_selldo_srd', '')); ?>" style="width: 400px;" />
                        <p class="description">Enter the SRD value for the selected form.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

<?php
/**
 * Admin Settings Class
 *
 * @package Advanced_Woo_Checkout_Fields
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AWCF_Admin class
 */
class AWCF_Admin {

    /**
     * Single instance of the class
     *
     * @var AWCF_Admin
     */
    private static $instance = null;

    /**
     * Settings page slug
     *
     * @var string
     */
    private $page_slug = 'awcf-settings';

    /**
     * Get single instance of the class
     *
     * @return AWCF_Admin
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
    }

    /**
     * Add admin menu under WooCommerce
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'Advanced Checkout Fields', 'advanced-woo-checkout-fields' ),
            __( 'Checkout Fields', 'advanced-woo-checkout-fields' ),
            'manage_woocommerce',
            $this->page_slug,
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'awcf_settings_group',
            'awcf_settings',
            array(
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize_settings' ),
                'default'           => AWCF()->get_default_settings(),
            )
        );
    }

    /**
     * Sanitize settings
     *
     * @param array $input Input settings.
     * @return array
     */
    public function sanitize_settings( $input ) {
        $sanitized = array();

        // Section 1: Ship to different address
        $sanitized['force_ship_to_different'] = isset( $input['force_ship_to_different'] ) ? (bool) $input['force_ship_to_different'] : false;

        // Section 2: Field controls
        $sanitized['fields'] = array();
        if ( isset( $input['fields'] ) && is_array( $input['fields'] ) ) {
            $valid_states = array( 'enabled', 'disabled', 'required' );
            foreach ( $input['fields'] as $field_key => $state ) {
                $field_key = sanitize_key( $field_key );
                $state     = sanitize_key( $state );
                if ( in_array( $state, $valid_states, true ) ) {
                    $sanitized['fields'][ $field_key ] = $state;
                }
            }
        }

        // Section 3: VAT compliance mode
        $sanitized['vat_mode_enabled']      = isset( $input['vat_mode_enabled'] ) ? (bool) $input['vat_mode_enabled'] : false;
        $sanitized['vat_checkbox_label']    = isset( $input['vat_checkbox_label'] ) ? sanitize_text_field( $input['vat_checkbox_label'] ) : '';
        $sanitized['company_name_label']    = isset( $input['company_name_label'] ) ? sanitize_text_field( $input['company_name_label'] ) : '';
        $sanitized['company_code_label']    = isset( $input['company_code_label'] ) ? sanitize_text_field( $input['company_code_label'] ) : '';
        $sanitized['company_vat_label']     = isset( $input['company_vat_label'] ) ? sanitize_text_field( $input['company_vat_label'] ) : '';
        $sanitized['company_address_label'] = isset( $input['company_address_label'] ) ? sanitize_text_field( $input['company_address_label'] ) : '';
        $sanitized['company_info_message']  = isset( $input['company_info_message'] ) ? wp_kses_post( $input['company_info_message'] ) : '';

        return $sanitized;
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_admin_assets( $hook ) {
        if ( 'woocommerce_page_' . $this->page_slug !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'awcf-admin',
            AWCF_PLUGIN_URL . 'assets/css/awcf-admin.css',
            array(),
            AWCF_VERSION
        );
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        $settings        = AWCF()->get_settings();
        $default_fields  = AWCF()->get_default_checkout_fields();
        $defaults        = AWCF()->get_default_settings();
        ?>
        <div class="wrap awcf-settings-wrap">
            <h1><?php esc_html_e( 'Advanced Checkout Fields', 'advanced-woo-checkout-fields' ); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields( 'awcf_settings_group' ); ?>
                
                <!-- Section 1: Ship to Different Address -->
                <div class="awcf-section">
                    <h2><?php esc_html_e( 'Ship to Different Address', 'advanced-woo-checkout-fields' ); ?></h2>
                    <p class="description">
                        <?php esc_html_e( 'Force the shipping address section to always be visible and require customers to explicitly enter shipping information separately from billing information.', 'advanced-woo-checkout-fields' ); ?>
                    </p>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <?php esc_html_e( 'Force Separate Shipping Address', 'advanced-woo-checkout-fields' ); ?>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           name="awcf_settings[force_ship_to_different]" 
                                           value="1" 
                                           <?php checked( ! empty( $settings['force_ship_to_different'] ) ); ?> />
                                    <?php esc_html_e( 'Always show shipping address fields and require separate shipping information', 'advanced-woo-checkout-fields' ); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Section 2: Checkout Fields Control -->
                <div class="awcf-section">
                    <h2><?php esc_html_e( 'Checkout Fields Control', 'advanced-woo-checkout-fields' ); ?></h2>
                    <p class="description">
                        <?php esc_html_e( 'Configure the visibility and requirement status of default WooCommerce checkout fields.', 'advanced-woo-checkout-fields' ); ?>
                    </p>

                    <!-- Billing Fields -->
                    <h3><?php esc_html_e( 'Billing Fields', 'advanced-woo-checkout-fields' ); ?></h3>
                    <table class="wp-list-table widefat fixed striped awcf-fields-table">
                        <thead>
                            <tr>
                                <th class="column-field"><?php esc_html_e( 'Field', 'advanced-woo-checkout-fields' ); ?></th>
                                <th class="column-status"><?php esc_html_e( 'Status', 'advanced-woo-checkout-fields' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $default_fields['billing'] as $field_key => $field_label ) : ?>
                                <?php
                                $current_state = isset( $settings['fields'][ $field_key ] ) ? $settings['fields'][ $field_key ] : 'enabled';
                                ?>
                                <tr>
                                    <td class="column-field">
                                        <strong><?php echo esc_html( $field_label ); ?></strong>
                                        <code><?php echo esc_html( $field_key ); ?></code>
                                    </td>
                                    <td class="column-status">
                                        <select name="awcf_settings[fields][<?php echo esc_attr( $field_key ); ?>]">
                                            <option value="enabled" <?php selected( $current_state, 'enabled' ); ?>>
                                                <?php esc_html_e( 'Enabled (Optional)', 'advanced-woo-checkout-fields' ); ?>
                                            </option>
                                            <option value="required" <?php selected( $current_state, 'required' ); ?>>
                                                <?php esc_html_e( 'Required', 'advanced-woo-checkout-fields' ); ?>
                                            </option>
                                            <option value="disabled" <?php selected( $current_state, 'disabled' ); ?>>
                                                <?php esc_html_e( 'Disabled', 'advanced-woo-checkout-fields' ); ?>
                                            </option>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Shipping Fields -->
                    <h3><?php esc_html_e( 'Shipping Fields', 'advanced-woo-checkout-fields' ); ?></h3>
                    <table class="wp-list-table widefat fixed striped awcf-fields-table">
                        <thead>
                            <tr>
                                <th class="column-field"><?php esc_html_e( 'Field', 'advanced-woo-checkout-fields' ); ?></th>
                                <th class="column-status"><?php esc_html_e( 'Status', 'advanced-woo-checkout-fields' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $default_fields['shipping'] as $field_key => $field_label ) : ?>
                                <?php
                                $current_state = isset( $settings['fields'][ $field_key ] ) ? $settings['fields'][ $field_key ] : 'enabled';
                                ?>
                                <tr>
                                    <td class="column-field">
                                        <strong><?php echo esc_html( $field_label ); ?></strong>
                                        <code><?php echo esc_html( $field_key ); ?></code>
                                    </td>
                                    <td class="column-status">
                                        <select name="awcf_settings[fields][<?php echo esc_attr( $field_key ); ?>]">
                                            <option value="enabled" <?php selected( $current_state, 'enabled' ); ?>>
                                                <?php esc_html_e( 'Enabled (Optional)', 'advanced-woo-checkout-fields' ); ?>
                                            </option>
                                            <option value="required" <?php selected( $current_state, 'required' ); ?>>
                                                <?php esc_html_e( 'Required', 'advanced-woo-checkout-fields' ); ?>
                                            </option>
                                            <option value="disabled" <?php selected( $current_state, 'disabled' ); ?>>
                                                <?php esc_html_e( 'Disabled', 'advanced-woo-checkout-fields' ); ?>
                                            </option>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Section 3: VAT Compliance Mode -->
                <div class="awcf-section">
                    <h2><?php esc_html_e( 'VAT Compliance Mode', 'advanced-woo-checkout-fields' ); ?></h2>
                    <p class="description">
                        <?php esc_html_e( 'Enable VAT compliance mode to add a company checkbox and additional company fields at checkout. When the checkbox is checked, customers can enter their company details for VAT invoicing purposes.', 'advanced-woo-checkout-fields' ); ?>
                    </p>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <?php esc_html_e( 'Enable VAT Compliance Mode', 'advanced-woo-checkout-fields' ); ?>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           name="awcf_settings[vat_mode_enabled]" 
                                           value="1" 
                                           <?php checked( ! empty( $settings['vat_mode_enabled'] ) ); ?> />
                                    <?php esc_html_e( 'Add company checkbox and fields to checkout', 'advanced-woo-checkout-fields' ); ?>
                                </label>
                            </td>
                        </tr>
                    </table>

                    <h3><?php esc_html_e( 'Field Labels', 'advanced-woo-checkout-fields' ); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="vat_checkbox_label">
                                    <?php esc_html_e( 'Company Checkbox Label', 'advanced-woo-checkout-fields' ); ?>
                                </label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="vat_checkbox_label" 
                                       name="awcf_settings[vat_checkbox_label]" 
                                       value="<?php echo esc_attr( $settings['vat_checkbox_label'] ?? $defaults['vat_checkbox_label'] ); ?>" 
                                       class="regular-text" />
                                <p class="description">
                                    <?php esc_html_e( 'Internal field name: billing_is_company', 'advanced-woo-checkout-fields' ); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="company_name_label">
                                    <?php esc_html_e( 'Company Name Label', 'advanced-woo-checkout-fields' ); ?>
                                </label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="company_name_label" 
                                       name="awcf_settings[company_name_label]" 
                                       value="<?php echo esc_attr( $settings['company_name_label'] ?? $defaults['company_name_label'] ); ?>" 
                                       class="regular-text" />
                                <p class="description">
                                    <?php esc_html_e( 'Internal field name: billing_company_name', 'advanced-woo-checkout-fields' ); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="company_code_label">
                                    <?php esc_html_e( 'Company Code Label', 'advanced-woo-checkout-fields' ); ?>
                                </label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="company_code_label" 
                                       name="awcf_settings[company_code_label]" 
                                       value="<?php echo esc_attr( $settings['company_code_label'] ?? $defaults['company_code_label'] ); ?>" 
                                       class="regular-text" />
                                <p class="description">
                                    <?php esc_html_e( 'Internal field name: billing_company_code', 'advanced-woo-checkout-fields' ); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="company_vat_label">
                                    <?php esc_html_e( 'Company VAT Code Label', 'advanced-woo-checkout-fields' ); ?>
                                </label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="company_vat_label" 
                                       name="awcf_settings[company_vat_label]" 
                                       value="<?php echo esc_attr( $settings['company_vat_label'] ?? $defaults['company_vat_label'] ); ?>" 
                                       class="regular-text" />
                                <p class="description">
                                    <?php esc_html_e( 'Internal field name: billing_company_vat', 'advanced-woo-checkout-fields' ); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="company_address_label">
                                    <?php esc_html_e( 'Company Address Label', 'advanced-woo-checkout-fields' ); ?>
                                </label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="company_address_label" 
                                       name="awcf_settings[company_address_label]" 
                                       value="<?php echo esc_attr( $settings['company_address_label'] ?? $defaults['company_address_label'] ); ?>" 
                                       class="regular-text" />
                                <p class="description">
                                    <?php esc_html_e( 'Internal field name: billing_company_address', 'advanced-woo-checkout-fields' ); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="company_info_message">
                                    <?php esc_html_e( 'Information Message', 'advanced-woo-checkout-fields' ); ?>
                                </label>
                            </th>
                            <td>
                                <textarea id="company_info_message" 
                                          name="awcf_settings[company_info_message]" 
                                          rows="3" 
                                          class="large-text"><?php echo esc_textarea( $settings['company_info_message'] ?? $defaults['company_info_message'] ); ?></textarea>
                                <p class="description">
                                    <?php esc_html_e( 'This message will be displayed below the company fields when the company checkbox is checked.', 'advanced-woo-checkout-fields' ); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <?php submit_button( __( 'Save Settings', 'advanced-woo-checkout-fields' ) ); ?>
            </form>
        </div>
        <?php
    }
}

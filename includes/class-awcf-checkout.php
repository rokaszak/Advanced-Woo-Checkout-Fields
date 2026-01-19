<?php
/**
 * Checkout Modifications Class
 *
 * @package Advanced_Woo_Checkout_Fields
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AWCF_Checkout class
 */
class AWCF_Checkout {

    /**
     * Single instance of the class
     *
     * @var AWCF_Checkout
     */
    private static $instance = null;

    /**
     * Get single instance of the class
     *
     * @return AWCF_Checkout
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
        // Section 1: Ship to different address
        add_filter( 'woocommerce_ship_to_different_address_checked', array( $this, 'force_ship_to_different_address' ) );
        
        // Section 2 & 3: Modify checkout fields
        add_filter( 'woocommerce_checkout_fields', array( $this, 'modify_checkout_fields' ), 20 );
        
        // VAT compliance: Display info message
        add_action( 'woocommerce_after_checkout_billing_form', array( $this, 'display_company_info_message' ) );
        
        // Validation
        add_action( 'woocommerce_checkout_process', array( $this, 'validate_checkout' ) );
        
        // Save order meta (HPOS compatible)
        add_action( 'woocommerce_checkout_create_order', array( $this, 'save_order_meta' ), 20, 2 );
        
        // Display in admin order details
        add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_admin_order_meta' ), 10, 1 );
        
        // Display in order emails
        add_action( 'woocommerce_email_after_order_table', array( $this, 'display_email_order_meta' ), 10, 4 );
        
        // Enqueue frontend scripts
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_checkout_scripts' ) );
        
        // Add inline styles for hiding ship to different checkbox
        add_action( 'wp_head', array( $this, 'add_inline_styles' ) );
    }

    /**
     * Force ship to different address checkbox to be checked
     *
     * @param bool $checked Current checked state.
     * @return bool
     */
    public function force_ship_to_different_address( $checked ) {
        $settings = AWCF()->get_settings();
        
        if ( ! empty( $settings['force_ship_to_different'] ) ) {
            return true;
        }
        
        return $checked;
    }

    /**
     * Add inline styles
     */
    public function add_inline_styles() {
        if ( ! is_checkout() ) {
            return;
        }

        $settings = AWCF()->get_settings();
        
        // Hide the ship to different address checkbox if forced
        if ( ! empty( $settings['force_ship_to_different'] ) ) {
            ?>
            <style type="text/css">
                .woocommerce-shipping-fields #ship-to-different-address {
                    display: none !important;
                }
                .woocommerce-shipping-fields .shipping_address {
                    display: block !important;
                }
            </style>
            <?php
        }

        // Hide company fields initially if VAT mode is enabled
        if ( ! empty( $settings['vat_mode_enabled'] ) ) {
            ?>
            <style type="text/css">
                .awcf-company-field,
                .awcf-company-info-message {
                    display: none;
                }
                .awcf-company-field.awcf-visible,
                .awcf-company-info-message.awcf-visible {
                    display: block;
                }
            </style>
            <?php
        }
    }

    /**
     * Modify checkout fields based on settings
     *
     * @param array $fields Checkout fields.
     * @return array
     */
    public function modify_checkout_fields( $fields ) {
        $settings = AWCF()->get_settings();

        // Section 2: Apply field controls
        if ( ! empty( $settings['fields'] ) && is_array( $settings['fields'] ) ) {
            foreach ( $settings['fields'] as $field_key => $state ) {
                // Determine section (billing or shipping)
                if ( strpos( $field_key, 'billing_' ) === 0 ) {
                    $section = 'billing';
                } elseif ( strpos( $field_key, 'shipping_' ) === 0 ) {
                    $section = 'shipping';
                } else {
                    continue;
                }

                // Check if field exists
                if ( ! isset( $fields[ $section ][ $field_key ] ) ) {
                    continue;
                }

                switch ( $state ) {
                    case 'disabled':
                        unset( $fields[ $section ][ $field_key ] );
                        break;
                    case 'required':
                        $fields[ $section ][ $field_key ]['required'] = true;
                        break;
                    case 'enabled':
                        $fields[ $section ][ $field_key ]['required'] = false;
                        break;
                }
            }
        }

        // Section 3: VAT Compliance Mode
        if ( ! empty( $settings['vat_mode_enabled'] ) ) {
            $defaults = AWCF()->get_default_settings();

            // Add company checkbox at the end of billing fields
            $fields['billing']['billing_is_company'] = array(
                'type'     => 'checkbox',
                'label'    => $settings['vat_checkbox_label'] ?? $defaults['vat_checkbox_label'],
                'required' => false,
                'class'    => array( 'form-row-wide', 'awcf-company-checkbox' ),
                'clear'    => true,
                'priority' => 120,
            );

            // Add company fields (hidden by default, shown via JS when checkbox is checked)
            $fields['billing']['billing_company_name'] = array(
                'type'        => 'text',
                'label'       => $settings['company_name_label'] ?? $defaults['company_name_label'],
                'required'    => false,
                'class'       => array( 'form-row-wide', 'awcf-company-field' ),
                'clear'       => true,
                'priority'    => 121,
                'placeholder' => '',
            );

            $fields['billing']['billing_company_code'] = array(
                'type'        => 'text',
                'label'       => $settings['company_code_label'] ?? $defaults['company_code_label'],
                'required'    => false,
                'class'       => array( 'form-row-wide', 'awcf-company-field' ),
                'clear'       => true,
                'priority'    => 122,
                'placeholder' => '',
            );

            $fields['billing']['billing_company_vat'] = array(
                'type'        => 'text',
                'label'       => $settings['company_vat_label'] ?? $defaults['company_vat_label'],
                'required'    => false,
                'class'       => array( 'form-row-wide', 'awcf-company-field' ),
                'clear'       => true,
                'priority'    => 123,
                'placeholder' => '',
            );

            $fields['billing']['billing_company_address'] = array(
                'type'        => 'text',
                'label'       => $settings['company_address_label'] ?? $defaults['company_address_label'],
                'required'    => false,
                'class'       => array( 'form-row-wide', 'awcf-company-field' ),
                'clear'       => true,
                'priority'    => 124,
                'placeholder' => '',
            );
        }

        return $fields;
    }

    /**
     * Display company info message after billing form
     */
    public function display_company_info_message() {
        $settings = AWCF()->get_settings();

        if ( empty( $settings['vat_mode_enabled'] ) ) {
            return;
        }

        $defaults = AWCF()->get_default_settings();
        $message  = $settings['company_info_message'] ?? $defaults['company_info_message'];

        if ( empty( $message ) ) {
            return;
        }
        ?>
        <div class="awcf-company-info-message">
            <p class="awcf-info-text"><?php echo wp_kses_post( $message ); ?></p>
        </div>
        <?php
    }

    /**
     * Validate checkout fields
     */
    public function validate_checkout() {
        $settings = AWCF()->get_settings();

        // Validate forced ship to different address
        if ( ! empty( $settings['force_ship_to_different'] ) ) {
            // Check if shipping fields are filled
            $shipping_fields = array( 'shipping_first_name', 'shipping_last_name', 'shipping_address_1', 'shipping_city', 'shipping_postcode', 'shipping_country' );
            
            foreach ( $shipping_fields as $field ) {
                // Only validate if the field is not disabled
                $field_state = $settings['fields'][ $field ] ?? 'enabled';
                if ( $field_state === 'disabled' ) {
                    continue;
                }

                // phpcs:ignore WordPress.Security.NonceVerification.Missing
                if ( empty( $_POST[ $field ] ) ) {
                    wc_add_notice(
                        sprintf(
                            /* translators: %s: field name */
                            __( '%s is a required field.', 'advanced-woo-checkout-fields' ),
                            '<strong>' . esc_html( ucwords( str_replace( array( 'shipping_', '_' ), array( '', ' ' ), $field ) ) ) . '</strong>'
                        ),
                        'error'
                    );
                }
            }
        }

        // Validate VAT compliance fields
        if ( ! empty( $settings['vat_mode_enabled'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $is_company = isset( $_POST['billing_is_company'] ) && ! empty( $_POST['billing_is_company'] );

            if ( $is_company ) {
                $company_fields = array(
                    'billing_company_name'    => $settings['company_name_label'] ?? __( 'Company Name', 'advanced-woo-checkout-fields' ),
                    'billing_company_code'    => $settings['company_code_label'] ?? __( 'Company Code', 'advanced-woo-checkout-fields' ),
                    'billing_company_vat'     => $settings['company_vat_label'] ?? __( 'Company VAT Code', 'advanced-woo-checkout-fields' ),
                    'billing_company_address' => $settings['company_address_label'] ?? __( 'Company Address', 'advanced-woo-checkout-fields' ),
                );

                foreach ( $company_fields as $field_key => $field_label ) {
                    // phpcs:ignore WordPress.Security.NonceVerification.Missing
                    if ( empty( $_POST[ $field_key ] ) ) {
                        wc_add_notice(
                            sprintf(
                                /* translators: %s: field name */
                                __( '%s is a required field.', 'advanced-woo-checkout-fields' ),
                                '<strong>' . esc_html( $field_label ) . '</strong>'
                            ),
                            'error'
                        );
                    }
                }
            }
        }
    }

    /**
     * Save order meta (HPOS compatible)
     *
     * @param WC_Order $order Order object.
     * @param array    $data  Posted data.
     */
    public function save_order_meta( $order, $data ) {
        $settings = AWCF()->get_settings();

        if ( empty( $settings['vat_mode_enabled'] ) ) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $is_company = isset( $_POST['billing_is_company'] ) && ! empty( $_POST['billing_is_company'] );

        $order->update_meta_data( '_billing_is_company', $is_company ? 'yes' : 'no' );

        if ( $is_company ) {
            $company_fields = array(
                'billing_company_name',
                'billing_company_code',
                'billing_company_vat',
                'billing_company_address',
            );

            foreach ( $company_fields as $field_key ) {
                // phpcs:ignore WordPress.Security.NonceVerification.Missing
                if ( isset( $_POST[ $field_key ] ) ) {
                    $order->update_meta_data( '_' . $field_key, sanitize_text_field( wp_unslash( $_POST[ $field_key ] ) ) );
                }
            }
        }
    }

    /**
     * Display company meta in admin order details
     *
     * @param WC_Order $order Order object.
     */
    public function display_admin_order_meta( $order ) {
        $is_company = $order->get_meta( '_billing_is_company' );

        if ( 'yes' !== $is_company ) {
            return;
        }

        $settings = AWCF()->get_settings();
        $defaults = AWCF()->get_default_settings();

        $company_name    = $order->get_meta( '_billing_company_name' );
        $company_code    = $order->get_meta( '_billing_company_code' );
        $company_vat     = $order->get_meta( '_billing_company_vat' );
        $company_address = $order->get_meta( '_billing_company_address' );
        ?>
        <div class="awcf-admin-company-details" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e5e5e5;">
            <h3 style="margin-bottom: 10px;"><?php esc_html_e( 'Company Information', 'advanced-woo-checkout-fields' ); ?></h3>
            
            <?php if ( $company_name ) : ?>
                <p>
                    <strong><?php echo esc_html( $settings['company_name_label'] ?? $defaults['company_name_label'] ); ?>:</strong>
                    <?php echo esc_html( $company_name ); ?>
                </p>
            <?php endif; ?>
            
            <?php if ( $company_code ) : ?>
                <p>
                    <strong><?php echo esc_html( $settings['company_code_label'] ?? $defaults['company_code_label'] ); ?>:</strong>
                    <?php echo esc_html( $company_code ); ?>
                </p>
            <?php endif; ?>
            
            <?php if ( $company_vat ) : ?>
                <p>
                    <strong><?php echo esc_html( $settings['company_vat_label'] ?? $defaults['company_vat_label'] ); ?>:</strong>
                    <?php echo esc_html( $company_vat ); ?>
                </p>
            <?php endif; ?>
            
            <?php if ( $company_address ) : ?>
                <p>
                    <strong><?php echo esc_html( $settings['company_address_label'] ?? $defaults['company_address_label'] ); ?>:</strong>
                    <?php echo esc_html( $company_address ); ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Display company meta in order emails
     *
     * @param WC_Order $order         Order object.
     * @param bool     $sent_to_admin Whether email is sent to admin.
     * @param bool     $plain_text    Whether email is plain text.
     * @param WC_Email $email         Email object.
     */
    public function display_email_order_meta( $order, $sent_to_admin, $plain_text, $email ) {
        $is_company = $order->get_meta( '_billing_is_company' );

        if ( 'yes' !== $is_company ) {
            return;
        }

        $settings = AWCF()->get_settings();
        $defaults = AWCF()->get_default_settings();

        $company_name    = $order->get_meta( '_billing_company_name' );
        $company_code    = $order->get_meta( '_billing_company_code' );
        $company_vat     = $order->get_meta( '_billing_company_vat' );
        $company_address = $order->get_meta( '_billing_company_address' );

        if ( $plain_text ) {
            echo "\n" . esc_html__( 'Company Information', 'advanced-woo-checkout-fields' ) . "\n";
            echo "========================================\n";
            
            if ( $company_name ) {
                echo esc_html( $settings['company_name_label'] ?? $defaults['company_name_label'] ) . ': ' . esc_html( $company_name ) . "\n";
            }
            if ( $company_code ) {
                echo esc_html( $settings['company_code_label'] ?? $defaults['company_code_label'] ) . ': ' . esc_html( $company_code ) . "\n";
            }
            if ( $company_vat ) {
                echo esc_html( $settings['company_vat_label'] ?? $defaults['company_vat_label'] ) . ': ' . esc_html( $company_vat ) . "\n";
            }
            if ( $company_address ) {
                echo esc_html( $settings['company_address_label'] ?? $defaults['company_address_label'] ) . ': ' . esc_html( $company_address ) . "\n";
            }
            echo "\n";
        } else {
            ?>
            <div style="margin-bottom: 40px;">
                <h2 style="color: #96588a; display: block; font-family: &quot;Helvetica Neue&quot;, Helvetica, Roboto, Arial, sans-serif; font-size: 18px; font-weight: bold; line-height: 130%; margin: 0 0 18px; text-align: left;">
                    <?php esc_html_e( 'Company Information', 'advanced-woo-checkout-fields' ); ?>
                </h2>
                <table cellspacing="0" cellpadding="6" style="width: 100%; border: 1px solid #e5e5e5;" border="1">
                    <tbody>
                        <?php if ( $company_name ) : ?>
                            <tr>
                                <th scope="row" style="text-align: left; border: 1px solid #e5e5e5; padding: 12px;">
                                    <?php echo esc_html( $settings['company_name_label'] ?? $defaults['company_name_label'] ); ?>
                                </th>
                                <td style="text-align: left; border: 1px solid #e5e5e5; padding: 12px;">
                                    <?php echo esc_html( $company_name ); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php if ( $company_code ) : ?>
                            <tr>
                                <th scope="row" style="text-align: left; border: 1px solid #e5e5e5; padding: 12px;">
                                    <?php echo esc_html( $settings['company_code_label'] ?? $defaults['company_code_label'] ); ?>
                                </th>
                                <td style="text-align: left; border: 1px solid #e5e5e5; padding: 12px;">
                                    <?php echo esc_html( $company_code ); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php if ( $company_vat ) : ?>
                            <tr>
                                <th scope="row" style="text-align: left; border: 1px solid #e5e5e5; padding: 12px;">
                                    <?php echo esc_html( $settings['company_vat_label'] ?? $defaults['company_vat_label'] ); ?>
                                </th>
                                <td style="text-align: left; border: 1px solid #e5e5e5; padding: 12px;">
                                    <?php echo esc_html( $company_vat ); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php if ( $company_address ) : ?>
                            <tr>
                                <th scope="row" style="text-align: left; border: 1px solid #e5e5e5; padding: 12px;">
                                    <?php echo esc_html( $settings['company_address_label'] ?? $defaults['company_address_label'] ); ?>
                                </th>
                                <td style="text-align: left; border: 1px solid #e5e5e5; padding: 12px;">
                                    <?php echo esc_html( $company_address ); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php
        }
    }

    /**
     * Enqueue checkout scripts
     */
    public function enqueue_checkout_scripts() {
        if ( ! is_checkout() ) {
            return;
        }

        $settings = AWCF()->get_settings();

        if ( empty( $settings['vat_mode_enabled'] ) ) {
            return;
        }

        wp_enqueue_script(
            'awcf-checkout',
            AWCF_PLUGIN_URL . 'assets/js/awcf-checkout.js',
            array( 'jquery' ),
            AWCF_VERSION,
            true
        );
    }
}

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
        
        // Section titles via gettext filter
        add_filter( 'gettext', array( $this, 'modify_checkout_titles' ), 20, 3 );
        
        // Section 2: Set required/disabled status for billing and shipping fields (priority 10, before checkout_fields)
        add_filter( 'woocommerce_billing_fields', array( $this, 'modify_billing_fields' ), 10 );
        add_filter( 'woocommerce_shipping_fields', array( $this, 'modify_shipping_fields' ), 10 );
        
        // Section 2 & 3: Modify checkout fields (add custom fields)
        add_filter( 'woocommerce_checkout_fields', array( $this, 'modify_checkout_fields' ), 20 );
        
        // Reorder checkout sections
        add_action( 'woocommerce_checkout_before_customer_details', array( $this, 'maybe_reorder_checkout_sections' ), 1 );
        
        // VAT compliance: Display info message
        add_action( 'woocommerce_after_checkout_billing_form', array( $this, 'display_company_info_message' ) );
        
        // Validation
        add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_checkout' ), 10, 2 );
        
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
     * Modify checkout section titles via gettext filter
     *
     * @param string $translated Translated text.
     * @param string $text       Original text.
     * @param string $domain     Text domain.
     * @return string
     */
    public function modify_checkout_titles( $translated, $text, $domain ) {
        // Only process woocommerce domain
        if ( 'woocommerce' !== $domain ) {
            return $translated;
        }

        // Only process specific strings to avoid unnecessary processing
        if ( 'Billing details' !== $text && 'Billing &amp; Shipping' !== $text && 'Ship to a different address?' !== $text ) {
            return $translated;
        }

        // Use static variable to prevent recursion
        static $is_processing = false;
        if ( $is_processing ) {
            return $translated;
        }
        $is_processing = true;

        $settings = AWCF()->get_settings();
        $defaults = AWCF()->get_default_settings();

        // Modify billing title
        if ( 'Billing details' === $text || 'Billing &amp; Shipping' === $text ) {
            $custom_title = $settings['billing_title'] ?? $defaults['billing_title'];
            if ( ! empty( $custom_title ) && $custom_title !== $defaults['billing_title'] ) {
                $is_processing = false;
                return $custom_title;
            }
        }

        // Modify shipping title / checkbox label
        if ( 'Ship to a different address?' === $text ) {
            $custom_title = $settings['shipping_title'] ?? $defaults['shipping_title'];
            if ( ! empty( $custom_title ) && $custom_title !== $defaults['shipping_title'] ) {
                $is_processing = false;
                return $custom_title;
            }
        }

        $is_processing = false;
        return $translated;
    }

    /**
     * Maybe reorder checkout sections (shipping first)
     */
    public function maybe_reorder_checkout_sections() {
        $settings = AWCF()->get_settings();
        
        if ( ( $settings['checkout_order'] ?? 'billing_first' ) === 'shipping_first' ) {
            // Remove default actions
            remove_action( 'woocommerce_checkout_billing', array( WC()->checkout(), 'checkout_form_billing' ) );
            remove_action( 'woocommerce_checkout_shipping', array( WC()->checkout(), 'checkout_form_shipping' ) );
            
            // Re-add in reversed order
            add_action( 'woocommerce_checkout_billing', array( WC()->checkout(), 'checkout_form_shipping' ), 10 );
            add_action( 'woocommerce_checkout_shipping', array( WC()->checkout(), 'checkout_form_billing' ), 10 );
        }
    }

    /**
     * Add inline styles
     */
    public function add_inline_styles() {
        if ( ! is_checkout() ) {
            return;
        }

        $settings = AWCF()->get_settings();
        
        // Ensure shipping address is always visible when forced (checkbox removal handled by JS)
        if ( ! empty( $settings['force_ship_to_different'] ) ) {
            ?>
            <style type="text/css">
                /* Ensure shipping address is always visible */
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
     * Get field settings with legacy support
     *
     * @param array  $settings Plugin settings.
     * @param string $field_key Field key.
     * @return array
     */
    private function get_field_settings( $settings, $field_key ) {
        // Check for new format
        if ( isset( $settings['fields'][ $field_key ] ) && is_array( $settings['fields'][ $field_key ] ) ) {
            return $settings['fields'][ $field_key ];
        }
        
        // Legacy format conversion
        if ( isset( $settings['fields'][ $field_key ] ) && is_string( $settings['fields'][ $field_key ] ) ) {
            $legacy_state = $settings['fields'][ $field_key ];
            return array(
                'status'   => $legacy_state === 'disabled' ? 'disabled' : 'enabled',
                'required' => $legacy_state === 'required',
            );
        }
        
        // Default - field enabled, not required (let WooCommerce defaults handle it)
        return array(
            'status'   => 'enabled',
            'required' => null, // null means don't override WooCommerce default
        );
    }

    /**
     * Modify billing fields - set required/disabled status
     *
     * @param array $fields Billing fields.
     * @return array
     */
    public function modify_billing_fields( $fields ) {
        $settings = AWCF()->get_settings();

        if ( ! empty( $settings['fields'] ) && is_array( $settings['fields'] ) ) {
            foreach ( $settings['fields'] as $field_key => $field_data ) {
                // Only process billing fields
                if ( strpos( $field_key, 'billing_' ) !== 0 ) {
                    continue;
                }

                // Get field settings (handles both new and legacy format)
                $field_settings = $this->get_field_settings( $settings, $field_key );

                // Handle disabled status
                if ( $field_settings['status'] === 'disabled' ) {
                    if ( isset( $fields[ $field_key ] ) ) {
                        unset( $fields[ $field_key ] );
                    }
                    continue;
                }

                // Handle required setting
                if ( isset( $fields[ $field_key ] ) && $field_settings['required'] !== null ) {
                    $fields[ $field_key ]['required'] = (bool) $field_settings['required'];
                }
            }
        }

        return $fields;
    }

    /**
     * Modify shipping fields - set required/disabled status
     *
     * @param array $fields Shipping fields.
     * @return array
     */
    public function modify_shipping_fields( $fields ) {
        $settings = AWCF()->get_settings();

        if ( ! empty( $settings['fields'] ) && is_array( $settings['fields'] ) ) {
            foreach ( $settings['fields'] as $field_key => $field_data ) {
                // Only process shipping fields
                if ( strpos( $field_key, 'shipping_' ) !== 0 ) {
                    continue;
                }

                // Get field settings (handles both new and legacy format)
                $field_settings = $this->get_field_settings( $settings, $field_key );

                // Handle disabled status
                if ( $field_settings['status'] === 'disabled' ) {
                    if ( isset( $fields[ $field_key ] ) ) {
                        unset( $fields[ $field_key ] );
                    }
                    continue;
                }

                // Handle required setting
                if ( isset( $fields[ $field_key ] ) && $field_settings['required'] !== null ) {
                    $fields[ $field_key ]['required'] = (bool) $field_settings['required'];
                }
            }
        }

        return $fields;
    }

    /**
     * Modify checkout fields based on settings
     *
     * @param array $fields Checkout fields.
     * @return array
     */
    public function modify_checkout_fields( $fields ) {
        $settings = AWCF()->get_settings();

        // Add shipping phone field if it doesn't exist
        if ( ! isset( $fields['shipping']['shipping_phone'] ) ) {
            $fields['shipping']['shipping_phone'] = array(
                'type'        => 'tel',
                'label'       => __( 'Phone', 'woocommerce' ),
                'required'    => false,
                'class'       => array( 'form-row-wide' ),
                'clear'       => true,
                'priority'    => 100,
                'validate'    => array( 'phone' ),
                'autocomplete' => 'tel',
            );
        }

        // VAT Compliance Mode: Add company checkbox and fields
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
            // These fields are NOT required in PHP - requirement is handled by JS and validation
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
     *
     * Note: Billing and shipping field validation is handled natively by WooCommerce
     * based on the 'required' flag set via woocommerce_billing_fields and woocommerce_shipping_fields filters.
     * This method only validates custom VAT compliance fields.
     *
     * @param array    $data   Posted checkout data.
     * @param WP_Error $errors Validation errors.
     */
    public function validate_checkout( $data, $errors ) {
        $settings = AWCF()->get_settings();

        // Validate VAT compliance fields (conditional validation)
        if ( ! empty( $settings['vat_mode_enabled'] ) ) {
            $is_company = isset( $data['billing_is_company'] ) && ! empty( $data['billing_is_company'] );

            if ( $is_company ) {
                $defaults = AWCF()->get_default_settings();
                $company_fields = array(
                    'billing_company_name'    => $settings['company_name_label'] ?? $defaults['company_name_label'],
                    'billing_company_code'    => $settings['company_code_label'] ?? $defaults['company_code_label'],
                    'billing_company_vat'     => $settings['company_vat_label'] ?? $defaults['company_vat_label'],
                    'billing_company_address' => $settings['company_address_label'] ?? $defaults['company_address_label'],
                );

                foreach ( $company_fields as $field_key => $field_label ) {
                    if ( empty( $data[ $field_key ] ) ) {
                        $errors->add(
                            $field_key . '_required',
                            sprintf(
                                /* translators: %s: field name */
                                __( '<strong>%s</strong> is a required field.', 'woocommerce' ),
                                esc_html( $field_label )
                            ),
                            array( 'id' => $field_key )
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

        // Load script if VAT mode is enabled OR if force ship to different is enabled
        $vat_mode_enabled = ! empty( $settings['vat_mode_enabled'] );
        $force_ship_to_different = ! empty( $settings['force_ship_to_different'] );

        if ( ! $vat_mode_enabled && ! $force_ship_to_different ) {
            return;
        }

        wp_enqueue_script(
            'awcf-checkout',
            AWCF_PLUGIN_URL . 'assets/js/awcf-checkout.js',
            array( 'jquery' ),
            AWCF_VERSION,
            true
        );

        // Localize script with settings and labels
        wp_localize_script(
            'awcf-checkout',
            'awcf_params',
            array(
                'force_ship_to_different' => $force_ship_to_different,
                'vat_mode_enabled'        => $vat_mode_enabled,
                'optional_text'           => esc_html__( '(optional)', 'woocommerce' ),
            )
        );
    }
}

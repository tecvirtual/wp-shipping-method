<?php
/**
 * Plugin Name: Vex Fedex Terrestre Shipping
 * Plugin URI: https://www.vexsoluciones.com/
 * Description: Custom Shipping Method for WooCommerce
 * Version: 1.0.0
 * Author: Igor Benić
 * Author URI: https://www.vexsoluciones.com/
 * License: GPL-3.0+
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Domain Path: /lang
 * Text Domain: vexsoluciones
 */
if ( ! defined( 'WPINC' ) ) {
    die;
}
/*
* Check if WooCommerce is active
*/
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    function vexfedex_shipping_method() {
        if ( ! class_exists( 'Vex_Shipping_Method' ) ) {
            class Vex_Shipping_Method extends WC_Shipping_Method {
                /**
                 * Constructor for your shipping class
                 *
                 * @access public
                 * @return void
                 */
                public function __construct() {
                    $this->id                 = 'vexfedex';
                    $this->method_title       = __( 'VexFedex Shipping', 'vexfedex' );
                    $this->method_description = __( 'Custom Shipping Method for VexFedex', 'vexfedex' );
                    // Availability & Countries
                    $this->availability = 'including';
                    $this->supports=['shipping-zones'];
                    $this->countries = array(
                        'US', // Unites States of America
                        'CA', // Canada
                        'DE', // Germany
                        'GB', // United Kingdom
                        'IT',   // Italy
                        'ES', // Spain
                        'MX'  // Croatia
                    );
                    $this->init();
                    $this->enabled = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'yes';
                    $this->title = isset( $this->settings['title'] ) ? $this->settings['title'] : __( 'VexFedex Shipping', 'vexfedex' );
                }
                /**
                 * Init your settings
                 *
                 * @access public
                 * @return void
                 */
                function init() {
                    // Load the settings API
                    $this->init_form_fields();
                    $this->init_settings();
                    // Save settings in admin if you have any defined
                    add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
                }
                /**
                 * Define settings field for this shipping
                 * @return void
                 */
                function init_form_fields() {
                    $this->form_fields = array(
                        'enabled' => array(
                            'title' => __( 'Enable', 'vexfedex' ),
                            'type' => 'checkbox',
                            'description' => __( 'Enable this shipping.', 'vexfedex' ),
                            'default' => 'yes'
                        ),
                        'title' => array(
                            'title' => __( 'Title', 'vexfedex' ),
                            'type' => 'text',
                            'description' => __( 'Title to be display on site', 'vexfedex' ),
                            'default' => __( 'VexFedex Shipping', 'vexfedex' )
                        ),
                        'weight' => array(
                            'title' => __( 'Weight (kg)', 'vexfedex' ),
                            'type' => 'number',
                            'description' => __( 'Maximum allowed weight', 'vexfedex' ),
                            'default' => 100
                        ),
                    );
                }
                /**
                 * This function is used to calculate the shipping cost. Within this function we can check for weights, dimensions and other parameters.
                 *
                 * @access public
                 * @param mixed $package
                 * @return void
                 */
                public function calculate_shipping(  $package = array()  ) {

                    $weight = 0;
                    $cost = 0;
                    $country = $package["destination"]["country"];
                    foreach ( $package['contents'] as $item_id => $values )
                    {
                        $_product = $values['data'];
                        $weight = $weight + $_product->get_weight() * $values['quantity'];
                    }
                    $weight = wc_get_weight( $weight, 'kg' );
                    if( $weight <= 10 ) {
                        $cost = 0;
                    } elseif( $weight <= 30 ) {
                        $cost = 5;
                    } elseif( $weight <= 50 ) {
                        $cost = 10;
                    } else {
                        $cost = 20;
                    }
                    $countryZones = array(
                        'MX' => 0,
                        'US' => 3,
                        'GB' => 2,
                        'CA' => 3,
                        'ES' => 2,
                        'DE' => 1,
                        'IT' => 1
                    );
                    $zonePrices = array(
                        0 => 10,
                        1 => 30,
                        2 => 50,
                        3 => 70
                    );
                    $zoneFromCountry = $countryZones[ $country ];
                    $priceFromZone = $zonePrices[ $zoneFromCountry ];
                    $cost += $priceFromZone;
                    $rate = array(
                        'id' => $this->id,
                        'label' => $this->title,
                        'cost' => $cost
                    );
                    $this->add_rate( $rate );

                }
            }
        }
    }
    add_action( 'woocommerce_shipping_init', 'vexfedex_shipping_method' );
    function add_vexfedex_shipping_method( $methods ) {
        $methods[] = 'Vex_Shipping_Method';
        return $methods;
    }
    add_filter( 'woocommerce_shipping_methods', 'add_vexfedex_shipping_method' );
    function vexfedex_validate_order( $posted )   {
        $packages = WC()->shipping->get_packages();
        $chosen_methods = WC()->session->get( 'chosen_shipping_methods' );

        if( is_array( $chosen_methods ) && in_array( 'vexfedex', $chosen_methods ) ) {

            foreach ( $packages as $i => $package ) {
                if ( $chosen_methods[ $i ] != "vexfedex" ) {

                    continue;

                }
                $Vex_Shipping_Method = new Vex_Shipping_Method();
                $weightLimit = (int) $Vex_Shipping_Method->settings['weight'];
                $weight = 0;
                foreach ( $package['contents'] as $item_id => $values )
                {
                    $_product = $values['data'];
                    $weight = $weight + $_product->get_weight() * $values['quantity'];
                }
                $weight = wc_get_weight( $weight, 'kg' );

                if( $weight > $weightLimit ) {
                    $message = sprintf( __( 'Sorry, %d kg exceeds the maximum weight of %d kg for %s', 'vexfedex' ), $weight, $weightLimit, $Vex_Shipping_Method->title );

                    $messageType = "error";
                    if( ! wc_has_notice( $message, $messageType ) ) {

                        wc_add_notice( $message, $messageType );

                    }
                }
            }
        }
    }
    add_action( 'woocommerce_review_order_before_cart_contents', 'vexfedex_validate_order' , 10 );
    add_action( 'woocommerce_after_checkout_validation', 'vexfedex_validate_order' , 10 );
}
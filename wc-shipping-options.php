<?php

/**
 * @package Woocommerce Shipping Options
 * @version 1.0
 */
/*
  Plugin Name: Woocommerce Shipping Options
  Plugin URI: http://wordpress.org/plugins/.../
  Description: Add new shipping method where user can add extra information via html select options.
  Author: Taavi Aasver
  Version: 1.0
  Author URI: http://taaviaasver.com/
 */

if (!class_exists('WC_Shipping_Options')) {
    
    function wcso_shipping_methods_init() {
    
        class WC_Shipping_Options extends WC_Shipping_Method {
            
            public function __construct() {
                $this->id = 'wcso_local_shipping';
                $this->method_title = __( 'Local Delivery With Options', 'wc_shipping_options' );
                $this->title = __('Local Delivery With Options');
                $this->options_array_label = 'wcso_shipping_options';
                $this->method_description = __('Local shipping with user selectable options');
                add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
                add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_shipping_options' ) );
                $this->init();
            }

            /**
             * Init settings
             *
             * @access public
             * @return void
             */
            function init() {
                // Load the settings API
                $this->init_form_fields();
                $this->init_settings();
                
                // Define user set variables
		$this->title        = $this->get_option( 'title' );
		$this->type         = $this->get_option( 'type' );
		$this->fee          = $this->get_option( 'fee' );
		$this->type         = $this->get_option( 'type' );
		$this->codes        = $this->get_option( 'codes' );
		$this->availability = $this->get_option( 'availability' );
		$this->countries    = $this->get_option( 'countries' );

                $this->get_shipping_options();
                
                add_filter( 'woocommerce_shipping_methods', array(&$this, 'add_wcso_shipping_methods'));
                add_action('woocommerce_cart_totals_after_shipping', array(&$this, 'wcso_review_order_shipping_options'));
                add_action('woocommerce_review_order_after_shipping', array(&$this, 'wcso_review_order_shipping_options'));
                add_action( 'woocommerce_checkout_update_order_meta', array(&$this, 'wcso_field_update_shipping_order_meta'), 10, 2);
                if (is_admin()) {
                    add_action( 'woocommerce_admin_order_data_after_shipping_address', array(&$this, 'wcso_display_shipping_admin_order_meta'), 10, 2 );
                }
            }
            
            /**
            * calculate_shipping function.
            *
            * @access public
            * @param array $package (default: array())
            * @return void
            */
           function calculate_shipping($package = array()) {
                $shipping_total = 0;
                $fee = ( trim($this->fee) == '' ) ? 0 : $this->fee;

                if ($this->type == 'fixed')
                    $shipping_total = $this->fee;

                if ($this->type == 'percent')
                    $shipping_total = $package['contents_cost'] * ( $this->fee / 100 );

                if ($this->type == 'product') {
                    foreach ($package['contents'] as $item_id => $values) {
                        $_product = $values['data'];

                        if ($values['quantity'] > 0 && $_product->needs_shipping()) {
                            $shipping_total += $this->fee * $values['quantity'];
                        }
                    }
                }

                $rate = array(
                    'id' => $this->id,
                    'label' => $this->title,
                    'cost' => $shipping_total
                );

                $this->add_rate($rate);
            }

            /**
             * init_form_fields function.
             *
             * @access public
             * @return void
             */
            function init_form_fields() {
                $this->form_fields = array(
                    'enabled' => array(
                        'title' => __('Enable', 'woocommerce'),
                        'type' => 'checkbox',
                        'label' => __('Enable Local Delivery With Options ', 'wc_shipping_options'),
                        'default' => 'no'
                    ),
                    'title' => array(
                        'title' => __('Title', 'woocommerce'),
                        'type' => 'text',
                        'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
                        'default' => __('Local Delivery With Options', 'wc_shipping_options'),
                        'desc_tip' => true,
                    ),
                    'type' => array(
                        'title' => __('Fee Type', 'woocommerce'),
                        'type' => 'select',
                        'description' => __('How to calculate delivery charges', 'woocommerce'),
                        'default' => 'fixed',
                        'options' => array(
                            'fixed' => __('Fixed amount', 'woocommerce'),
                            'percent' => __('Percentage of cart total', 'woocommerce'),
                            'product' => __('Fixed amount per product', 'woocommerce'),
                        ),
                        'desc_tip' => true,
                    ),
                    'fee' => array(
                        'title' => __('Delivery Fee', 'woocommerce'),
                        'type' => 'price',
                        'description' => __('What fee do you want to charge for local delivery, disregarded if you choose free. Leave blank to disable.', 'woocommerce'),
                        'default' => '',
                        'desc_tip' => true,
                        'placeholder' => wc_format_localized_price(0)
                    ),
                    'shipping_options_table' => array(
                        'type' => 'shipping_options_table'
                    ),
                    'codes' => array(
                        'title' => __('Zip/Post Codes', 'woocommerce'),
                        'type' => 'textarea',
                        'description' => __('What zip/post codes would you like to offer delivery to? Separate codes with a comma. Accepts wildcards, e.g. P* will match a postcode of PE30.', 'woocommerce'),
                        'default' => '',
                        'desc_tip' => true,
                        'placeholder' => '12345, 56789 etc'
                    ),
                    'availability' => array(
                        'title' => __('Method availability', 'woocommerce'),
                        'type' => 'select',
                        'default' => 'all',
                        'class' => 'availability',
                        'options' => array(
                            'all' => __('All allowed countries', 'woocommerce'),
                            'specific' => __('Specific Countries', 'woocommerce')
                        )
                    ),
                    'countries' => array(
                        'title' => __('Specific Countries', 'woocommerce'),
                        'type' => 'multiselect',
                        'class' => 'chosen_select',
                        'css' => 'width: 450px;',
                        'default' => '',
                        'options' => WC()->countries->get_shipping_countries(),
                        'custom_attributes' => array(
                            'data-placeholder' => __('Select some countries', 'woocommerce')
                        )
                    )
                );
            }
            
            /**
            * admin_options function.
            *
            * @access public
            * @return void
            */
           function admin_options() {
                   ?>
                   <h3><?php echo $this->method_title; ?></h3>
                   <p><?php _e( 'Local delivery is a simple shipping method for delivering orders locally.', 'woocommerce' ); ?></p>
                   <table class="form-table">
                           <?php $this->generate_settings_html(); ?>
                   </table> <?php
           }
           
           /**
             * is_available function.
             *
             * @access public
             * @param array $package
             * @return bool
             */
            function is_available($package) {

                if ($this->enabled == "no")
                    return false;

                // If post codes are listed, let's use them.
                $codes = '';
                if ($this->codes != '') {
                    foreach (explode(',', $this->codes) as $code) {
                        $codes[] = $this->clean($code);
                    }
                }

                if (is_array($codes)) {

                    $found_match = false;

                    if (in_array($this->clean($package['destination']['postcode']), $codes)) {
                        $found_match = true;
                    }


                    // Pattern match
                    if (!$found_match) {

                        $customer_postcode = $this->clean($package['destination']['postcode']);
                        foreach ($codes as $c) {
                            $pattern = '/^' . str_replace('_', '[0-9a-zA-Z]', $c) . '$/i';
                            if (preg_match($pattern, $customer_postcode)) {
                                $found_match = true;
                                break;
                            }
                        }
                    }


                    // Wildcard search
                    if (!$found_match) {

                        $customer_postcode = $this->clean($package['destination']['postcode']);
                        $customer_postcode_length = strlen($customer_postcode);

                        for ($i = 0; $i <= $customer_postcode_length; $i++) {

                            if (in_array($customer_postcode, $codes)) {
                                $found_match = true;
                            }

                            $customer_postcode = substr($customer_postcode, 0, -2) . '*';
                        }
                    }

                    if (!$found_match) {
                        return false;
                    }
                }

                // Either post codes not setup, or post codes are in array... so lefts check countries for backwards compatibility.
                if ($this->availability == 'specific') {
                    $ship_to_countries = $this->countries;
                } else {
                    $ship_to_countries = array_keys(WC()->countries->get_shipping_countries());
                }

                if (is_array($ship_to_countries)) {
                    if (!in_array($package['destination']['country'], $ship_to_countries)) {
                        return false;
                    }
                }

                // Yay! We passed!
                return apply_filters('woocommerce_shipping_' . $this->id . '_is_available', true, $package);
            }

            /**
             * clean function.
             *
             * @access public
             * @param mixed $code
             * @return string
             */
            function clean($code) {
                return str_replace('-', '', sanitize_title($code)) . ( strstr($code, '*') ? '*' : '' );
            }
            
            /**
            * validate_shipping_options_table_field function.
            *
            * @access public
            * @param mixed $key
            * @return bool
            */
            function validate_shipping_options_table_field( $key ) {
                return false;
            }
            
            /**
             * generate_options_table_html function.
             *
             * @access public
             * @return string
             */
            function generate_shipping_options_table_html() {
                ob_start();
                ?>
                    <tr valign="top">
                        <th scope="row" class="titledesc"><?php _e('Delivery Options', 'woocommerce'); ?>:</th>
                        <td class="forminp" id="<?php echo $this->id; ?>_options">
                        <table class="shippingrows widefat" cellspacing="0">
                            <thead>
                                <tr>
                                    <th class="check-column"><input type="checkbox"></th>
                                    <th class="options-th"><?php _e('Option', 'woocommerce'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                                $i = -1;
                                if ($this->shipping_options) :
                                    foreach ($this->shipping_options as $option) :
                                        $i++;
                            ?>
                                        <tr class="option-tr">
                                            <th class="check-column"><input type="checkbox" name="select" /></th>
                                            <td><input type="text" name="<?php echo esc_attr($this->id . '_options[' . $i . ']') ?>" value="<?php echo $option; ?>"></td>
                                        </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="4"><a href="#" class="add button"><?php _e('Add Option', 'woocommerce'); ?></a> <a href="#" class="remove button"><?php _e('Delete selected options', 'woocommerce'); ?></a></th>
                                </tr>
                            </tfoot>
                        </table>
                        <script type="text/javascript">
                            jQuery(function() {

                                jQuery('#<?php echo $this->id; ?>_options').on( 'click', 'a.add', function(){
                                    var size = jQuery('#<?php echo $this->id; ?>_options tbody .option-tr').size();
                                    jQuery('<tr class="option-tr"><th class="check-column"><input type="checkbox" name="select" /></th>' +
                                           '<td><input type="text" name="<?php echo esc_attr($this->id . '_options') ?>[' + size + ']" /></td></tr>')
                                        .appendTo('#<?php echo $this->id; ?>_options table tbody');
                                    return false;
                                });

                                // Remove row
                                jQuery('#<?php echo $this->id; ?>_options').on( 'click', 'a.remove', function(){
                                    var answer = confirm("<?php _e('Delete the selected options?', 'woocommerce'); ?>");
                                    if (answer) {
                                        jQuery('#<?php echo $this->id; ?>_options table tbody tr th.check-column input:checked').each(function(i, el){
                                                jQuery(el).closest('tr').remove();
                                        });
                                    }
                                    return false;
                                });

                            });
                        </script>
                        </td>
                    </tr>
                <?php
                return ob_get_clean();
            }
            
            /**
             * process_shipping_options function.
             *
             * @access public
             * @return void
             */
            function process_shipping_options() {
                
                $options = array();

                if (isset($_POST[$this->id . '_options']))
                    $options = array_map('wc_clean', $_POST[$this->id . '_options']);

                update_option($this->options_array_label, $options);
                
                $this->get_shipping_options();
            }

            /**
            * get_shipping_options function.
            *
            * @access public
            * @return void
            */
           function get_shipping_options() {
                   $this->shipping_options = array_filter( (array) get_option( $this->options_array_label ) );
           }
           
           function wcso_review_order_shipping_options() {
                global $woocommerce;
                $chosen_method = $woocommerce->session->get('chosen_shipping_methods');
                if (is_array($chosen_method) && in_array($this->id, $chosen_method)) {
                    echo '<tr class="shipping_option">';
                    echo '<th>' . $this->title . '</th>';
                    echo '<td><select name="shipping_option" class="input-select" id="shipping_option">';
                    echo '<option>---</option>';
                    foreach ($this->shipping_options as $option) {
                        echo '<option value="' . esc_attr($option) . '" ' . selected( $woocommerce->session->_chosen_shipping_option, esc_attr($option) ) . '>' . $option . '</option>';
                    }
                    echo '</select></td></tr>';
                    
                    ?>
                        <script>
                            var options = document.getElementsByName("shipping_option");
                            if (options.length >= 1) {
                                options[0].addEventListener("change", function() {
                                    var data = "action=wcso_save_selected&shipping_option=" + this.value;
                                    var xmlhttp;
                                    if (window.XMLHttpRequest) {
                                        xmlhttp = new XMLHttpRequest();
                                    } else {
                                        xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
                                    }
                                    xmlhttp.open('GET', '<?php echo admin_url( 'admin-ajax.php' ); ?>?' + data, true);
                                    xmlhttp.send();
                                });
                            }
                        </script>
                    <?php
                }
            }
            
            function wcso_field_update_shipping_order_meta( $order_id, $posted ) {
                global $woocommerce;
                if (in_array($this->id, $posted['shipping_method'])) {
                    if ( isset( $_POST['shipping_option'] ) && !empty( $_POST['shipping_option'] ) ) {
                        update_post_meta( $order_id, 'wcso_shipping_option', sanitize_text_field( $_POST['shipping_option'] ) );
                        $woocommerce->session->_chosen_shipping_option = sanitize_text_field( $_POST['shipping_option'] );
                    } else if ($woocommerce->session->_chosen_shipping_option) { //visible  in cart, hidden in checkout
                        update_post_meta( $order_id, 'wcso_shipping_option', $woocommerce->session->_chosen_shipping_option );
                    }
                }
            }
          
            function wcso_display_shipping_admin_order_meta($order){
                $selected_option = get_post_meta( $order->id, 'wcso_shipping_option', true );
                if ($selected_option) {
                    echo '<p><strong>' . $this->title . ':</strong> ' . get_post_meta( $order->id, 'wcso_shipping_option', true ) . '</p>';
                }
            }
            
            function add_wcso_shipping_methods( $methods ) {
                $methods[] = $this; 
                return $methods;
            }
            
        }
        
        new WC_Shipping_Options();

    }
    
    add_action('woocommerce_shipping_init', 'wcso_shipping_methods_init');

    
    add_action( 'wp_ajax_wcso_save_selected', 'wcso_save_selected' );  
    add_action( 'wp_ajax_nopriv_wcso_save_selected', 'wcso_save_selected' );
    function wcso_save_selected() {
        if ( isset( $_GET['shipping_option'] ) && !empty( $_GET['shipping_option'] ) ) {
            global $woocommerce;
            $selected_option = $_GET['shipping_option'];
            $woocommerce->session->_chosen_shipping_option = sanitize_text_field( $selected_option );
        }
        die();
    }
}

?>

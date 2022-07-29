<?php
/**
 * Plugin Name: Custom Display for WooCommerce Attributes 
 * Plugin URI: https://awecodebox.com
 * Description: Custom Display for WooCommerce Attributes .
 * Version: 1.0.0
 * Author: AweCodeBox
 * Author URI: https://awecodebox.com
 * Text Domain: styler
 * License: GPL2
 *
 * @package CustomDWA
 */

namespace CustomDWA;

class Init {

	/**
	 * Instance of this class.
	 *
	 * @since   1.0.0
	 * @access  public
	 * @var     CustomDWA
	 */
	public static $instance;

    /**
	 * Provides access to a single instance of a module using the singleton pattern.
	 *
	 * @since   1.0.0
	 * @return  object
	 */
	public static function get_instance() {

		if ( self::$instance === null ) {

			self::$instance = new self();
		}
		return self::$instance;
	}

    /**
     * Class Constructor
     * 
     * @since 1.0.0
     * @package CustomDWA
     */
    public function __construct() {

        $this->hooks();
    }

    /**
     * CustomDWA Hooks
     * 
     * @since 1.0.0
     * @package CustomDWA
     */
    protected function hooks() {

        remove_action( 'woocommerce_product_additional_information', 'wc_display_product_attributes', 10 );
        if ( get_option( 'custom_product_attr_display' ) && get_option( 'custom_product_attr_display' ) !== 'hidden'  ) {
            add_action( get_option( 'custom_product_attr_display' ), [$this, 'woocommerce_custom_attr_display'], 10 );
        }
        add_filter( 'woocommerce_products_general_settings', [$this, 'woocommerce_products_general_settings'] );
    }

    /**
     * CustomDWA Hooks
     * 
     * @since 1.0.0
     * @package CustomDWA
     */
    public function woocommerce_custom_attr_display() {
        global $product;
        
        $product_attributes = array();

        // Display weight and dimensions before attribute list.
        $display_dimensions = apply_filters( 'wc_product_enable_dimensions_display', $product->has_weight() || $product->has_dimensions() );

        if ( $display_dimensions && $product->has_weight() ) {
            $product_attributes['weight'] = array(
                'label' => __( 'Weight', 'woocommerce' ),
                'value' => wc_format_weight( $product->get_weight() ),
            );
        }

        if ( $display_dimensions && $product->has_dimensions() ) {
            $product_attributes['dimensions'] = array(
                'label' => __( 'Dimensions', 'woocommerce' ),
                'value' => wc_format_dimensions( $product->get_dimensions( false ) ),
            );
        }

        // Add product attributes to list.
        $attributes = array_filter( $product->get_attributes(), 'wc_attributes_array_filter_visible' );

        foreach ( $attributes as $attribute ) {
            $values = array();

            if ( $attribute->is_taxonomy() ) {
                $attribute_taxonomy = $attribute->get_taxonomy_object();
                $attribute_values   = wc_get_product_terms( $product->get_id(), $attribute->get_name(), array( 'fields' => 'all' ) );

                foreach ( $attribute_values as $attribute_value ) {
                    $value_name = esc_html( $attribute_value->name );

                    if ( $attribute_taxonomy->attribute_public ) {
                        $values[] = '<a href="' . esc_url( get_term_link( $attribute_value->term_id, $attribute->get_name() ) ) . '" rel="tag">' . $value_name . '</a>';
                    } else {
                        $values[] = $value_name;
                    }
                }
            } else {
                $values = $attribute->get_options();

                foreach ( $values as &$value ) {
                    $value = make_clickable( esc_html( $value ) );
                }
            }

            $product_attributes[ 'attribute_' . sanitize_title_with_dashes( $attribute->get_name() ) ] = array(
                'label' => wc_attribute_label( $attribute->get_name() ),
                'value' => apply_filters( 'woocommerce_attribute', wpautop( wptexturize( implode( ', ', $values ) ) ), $attribute, $values ),
            );
        }

        /**
         * Hook: woocommerce_display_product_attributes.
         *
         * @since 3.6.0.
         * @param array $product_attributes Array of atributes to display; label, value.
         * @param WC_Product $product Showing attributes for this product.
         */
        $product_attributes = apply_filters( 'woocommerce_display_product_attributes', $product_attributes, $product );

        $this->woocommerce_custom_attr_display_output( $product_attributes );
    }

    /**
     * CustomDWA Hooks
     * 
     * @since 1.0.0
     * @package CustomDWA
     */
    public function woocommerce_custom_attr_display_output( $product_attributes ) {
        if ( ! $product_attributes ) {
            return;
        }
        
        ?>
        <table class="woocommerce-product-attributes shop_attributes">
            <tr class="woocommerce-product-attributes-item woocommerce-product-attributes-item--<?php echo esc_attr( $product_attribute_key ); ?>">
                <?php
                foreach ( $product_attributes as $product_attribute_key => $product_attribute ) :
                    if ( strpos( $product_attribute_key, '_pa_') === false  ) {  ?>
                        <th class="woocommerce-product-attributes-item__label"><?php echo wp_kses_post( $product_attribute['label'] ); ?></th>
                        <?php
                    }
                endforeach; ?>
            </tr>
            <?php foreach ( $product_attributes as $product_attribute_key => $product_attribute ) : ?>
                <?php
                $product_attributes[$product_attribute_key]['value'] = str_replace( ['<p>','</p>'], '', $product_attributes[$product_attribute_key]['value']);
                $product_attributes[$product_attribute_key]['value'] = explode( ',', $product_attributes[$product_attribute_key]['value'] );			
                ?>
            <?php endforeach; ?>
            
            <?php
            $merge = [];
            foreach ( $product_attributes as $product_attribute_key => $product_attribute ) :
                if ( strpos( $product_attribute_key, '_pa_') === false  ) {
                    $merge[] = $product_attribute['value'];
                    $size[] = sizeof( $product_attribute['value'] ); 
                }
            endforeach;
            // Fix empty value
            foreach ( $size as $key => $value ) {
                if ( max($size) > sizeof( $merge[$key] ) ) {
                    $merge[$key][] = '';
                }
            }
            for ($i=0; $i <= sizeof($merge); $i++) { 
                ?>
                <tr class="woocommerce-product-attributes-item woocommerce-product-attributes-item--<?php echo esc_attr( $product_attribute_key ); ?>">
                    <?php
                        for ($k=0; $k <= sizeof($merge[$i]); $k++) { 
                        if ( isset( $merge[$k][$i] ) ) {
                            ?>
                            <td class="woocommerce-product-attributes-item__value"> <?php echo wp_kses_post( $merge[$k][$i] ); ?> </td>
                            <?php
                        } else {
                            ?>
                            <td class="woocommerce-product-attributes-item__value"></td>
                            <?php              
                        }
                    }
                    ?>
                </tr>
            <?php } ?>
        </table>
        <?php
    }

    /**
     * Add Plugin Settings to WooCommerce Product settings tab
     * 
     * @since 1.0.0
     * @package CustomDWA
     */
    public function woocommerce_products_general_settings( $settings ) {
        
        $settings[] = array(
            'title' => __( 'Custom Product Attribute Display', 'woocommerce' ),
            'type'  => 'title',
            'id'    => 'custom_product_attr_display_options',
        );

        $settings[] = array(
            'title'    => __( 'Custom Product Attribute Display', 'woocommerce' ),
            'desc'     => __( 'Location To display Custom Tabel.', 'woocommerce' ),
            'id'       => 'custom_product_attr_display',
            'class'    => 'wc-enhanced-select',
            'css'      => 'min-width:300px;',
            'default'  => 'hidden',
            'type'     => 'select',
            'options'  => array(
                'hidden'                                => __( 'Hidden', 'woocommerce' ),
                'woocommerce_single_product_summary'    => __( 'Single product summary', 'woocommerce' ),
                'woocommerce_before_add_to_cart_form'   => __( 'Before add to cart form', 'woocommerce' ),
                'woocommerce_after_add_to_cart_form'    => __( 'After add to cart form', 'woocommerce' ),
            ),
            'desc_tip' => true,
        );

        $settings[] = array(
            'type' => 'sectionend',
            'id'   => 'custom_product_attr_display_options',
        );
        

        return $settings;
    }

}

Init::get_instance();
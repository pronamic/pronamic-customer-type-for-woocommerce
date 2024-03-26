<?php
/**
 * Pronamic Customer Type for WooCommerce
 * 
 * @package pronamic-woocommerce-customer-type
 * 
 * Plugin Name: Pronamic Customer Type for WooCommerce
 * Description: Streamline your sales process! This plugin allows customers to clearly indicate a personal or business purchase.
 *
 * Version: 1.0.0
 * 
 * Author: Pronamic
 * Author URI: https://www.pronamic.eu/
 *
 * Text Domain: pronamic-customer-type-for-woocommerce
 * Domain Path: /languages/
 */

namespace Pronamic\WooCommerceCustomerType;

use WC_Order;

/**
 * Plugin class.
 */
class Plugin {
	/**
	 * Setup.
	 * 
	 * @return void
	 */
	public function setup() {
		\add_filter( 'woocommerce_billing_fields', [ $this, 'add_customer_type_field' ] );
		\add_filter( 'woocommerce_billing_fields', [ $this, 'set_billing_company_requirement' ] );

		\add_action( 'woocommerce_after_checkout_form', [ $this, 'after_checkout_form' ] );

		\add_action( 'woocommerce_checkout_process', [ $this, 'process_checkout' ] );

		\add_action( 'woocommerce_checkout_create_order', [ $this, 'checkout_create_order' ], 10, 2 );

		\add_action( 'woocommerce_admin_order_data_after_order_details', [ $this, 'admin_order_data_after_order_details' ] );
	}

	/**
	 * Add customer type field.
	 * 
	 * @link https://woocommerce.com/document/tutorial-customising-checkout-fields-using-actions-and-filters/
	 * @link https://github.com/woocommerce/woocommerce/blob/7.7.0/plugins/woocommerce/includes/class-wc-countries.php#L1642-L1648
	 * @param array $fields Fields.
	 * @return array
	 */
	public function add_customer_type_field( $fields ) {
		$fields['pronamic_customer_type'] = [
			'label'    => \__( 'Customer type', 'pronamic-woocommerce-customer-type' ),
			'required' => true,
			'type'     => 'radio',
			'class'    => [
				'form-row-wide',
				'pronamic-radio',
			],
			'options'  => [
				'business' => \__( 'Business', 'pronamic-woocommerce-customer-type' ),
				'private'  => \__( 'Private', 'pronamic-woocommerce-customer-type' ),
			],
			'default'  => 'business',
			'priority' => 0,
		];

		return $fields;
	}

	/**
	 * Set billing company requirement.
	 * 
	 * @link https://woocommerce.com/document/tutorial-customising-checkout-fields-using-actions-and-filters/
	 * @link https://github.com/woocommerce/woocommerce/blob/7.7.0/plugins/woocommerce/includes/class-wc-countries.php#L1642-L1648
	 * @param array $fields Fields.
	 * @return array
	 */
	public function set_billing_company_requirement( $fields ) {
		if ( ! \array_key_exists( 'billing_company', $fields ) ) {
			return $fields;
		}

		$required = true;

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verification is done upstream by WooCommerce?

		if ( \array_key_exists( 'pronamic_customer_type', $_POST ) ) {
			$customer_type = \sanitize_text_field( \wp_unslash( $_POST['pronamic_customer_type'] ) );

			$required = ( 'business' === $customer_type );
		}

		/// phpcs:enable WordPress.Security.NonceVerification.Missing

		$fields['billing_company']['required'] = $required;

		return $fields;
	}

	/**
	 * After checkout form.
	 * 
	 * @link https://github.com/woocommerce/woocommerce/blob/7.7.0/plugins/woocommerce/templates/checkout/form-checkout.php#L66
	 * @return void
	 */
	public function after_checkout_form() {
		?>
		<script type="text/javascript">
			function toggle( type ) {
				document.querySelector( '#billing_company_field' ).style.display = ( 'business' === type ) ? 'revert' : 'none';
				document.querySelector( '#woocommerce_eu_vat_number_field' ).style.display = ( 'business' === type ) ? 'revert' : 'none';
			}

			document.querySelector( '#pronamic_customer_type_private' ).addEventListener( 'change',function() {
				toggle( this.checked ? 'private' : '' );
			} );

			document.querySelector( '#pronamic_customer_type_business' ).addEventListener( 'change',function() {
				toggle( this.checked ? 'business' : '' );
			} );
		</script>
		<?php
	}

	/**
	 * Process checkout.
	 *
	 * @link https://woocommerce.com/document/tutorial-customising-checkout-fields-using-actions-and-filters/#adding-a-custom-special-field
	 * @link https://github.com/woocommerce/woocommerce/blob/7.6.0/plugins/woocommerce/includes/class-wc-checkout.php#L1236
	 * @return void
	 * @throws \Exception Throws an exception if the customer type could not be determined.
	 */
	public function process_checkout() {
		$customer_type = '';

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verification is done upstream by WooCommerce?

		if ( \array_key_exists( 'pronamic_customer_type', $_POST ) ) {
			$customer_type = \sanitize_text_field( \wp_unslash( $_POST['pronamic_customer_type'] ) );
		}

		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! \in_array( $customer_type, [ 'business', 'private' ], true ) ) {
			throw new \Exception(
				\esc_html__( 'Due to a technical problem, it is unclear whether this concerns a business or private order, try again or contact us.', 'pronamic-woocommerce-customer-type' )
			);
		}
	}

	/**
	 * Update order meta.
	 * 
	 * @link https://github.com/woocommerce/woocommerce/blob/7.6.0/plugins/woocommerce/includes/class-wc-checkout.php#L441-L446
	 * @link https://woocommerce.com/document/developing-using-woocommerce-crud-objects/
	 * @link https://github.com/woocommerce/woocommerce/wiki/Order-and-Order-Line-Item-Data
	 * @param WC_Order $order Order.
	 * @param array    $data  Post data.
	 * @return void
	 */
	public function checkout_create_order( $order, $data ) {
		if ( ! \array_key_exists( 'pronamic_customer_type', $data ) ) {
			return;
		}

		$customer_type = $data['pronamic_customer_type'];

		$order->update_meta_data( '_pronamic_customer_type', $customer_type );
	}

	/**
	 * Admin order data after order details.
	 * 
	 * @link https://github.com/woocommerce/woocommerce/blob/7.6.0/plugins/woocommerce/includes/admin/meta-boxes/class-wc-meta-box-order-data.php#L349
	 * @param WC_Order $order WooCommerce order.
	 * @return void
	 */
	public function admin_order_data_after_order_details( $order ) {
		$customer_type = $order->get_meta( '_pronamic_customer_type' );

		?>
		<p class="form-field form-field-wide">
			<?php \esc_html_e( 'Customer type:', 'pronamic-woocommerce-customer-type' ); ?>

			<?php

			switch ( $customer_type ) {
				case 'business':
					echo \esc_html( \_x( 'Business', 'customer-type', 'pronamic-woocommerce-customer-type' ) );

					break;
				case 'private':
					echo \esc_html( \_x( 'Private', 'customer-type', 'pronamic-woocommerce-customer-type' ) );

					break;
				default:
					\esc_html_e( 'Unkown', 'pronamic-woocommerce-customer-type' );

					break;
			}

			?>
		</p>
		<?php
	}
}

$pronamic_woocommserce_customer_type_plugin = new Plugin();

$pronamic_woocommserce_customer_type_plugin->setup();

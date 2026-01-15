<?php
/**
 * Class WC_Gateway_LYpay
 *
 * @package WC_LYpay_Payment
 */

defined( 'ABSPATH' ) || exit;

/**
 * WC_Gateway_LYpay Class.
 */
class WC_Gateway_LYpay extends WC_Payment_Gateway {

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {
		$this->id                 = 'lypay';
		$this->icon               = ''; // URL of the icon that will be displayed on checkout page near your gateway name
		$this->has_fields         = false;
		$this->method_title       = __( 'LYpay', 'wc-lypay-payment' );
		$this->method_description = __( 'Allows payments via LYpay (IBAN transfer).', 'wc-lypay-payment' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables.
		$this->title        = $this->get_option( 'title' );
		$this->description  = $this->get_option( 'description' );
		$this->instructions = $this->get_option( 'instructions', __( 'Please verify the instructions below.', 'wc-lypay-payment' ) );
		$this->iban         = $this->get_option( 'iban' );
		$this->whatsapp     = $this->get_option( 'whatsapp' );
		$this->email        = $this->get_option( 'email' );
		$this->order_status = $this->get_option( 'order_status', 'wc-on-hold' );

		// Actions.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );

		// Customer Emails.
		add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
	}

	/**
	 * Initialize Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'      => array(
				'title'   => __( 'Enable/Disable', 'wc-lypay-payment' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable LYpay Payment', 'wc-lypay-payment' ),
				'default' => 'yes',
			),
			'title'        => array(
				'title'       => __( 'Title', 'wc-lypay-payment' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'wc-lypay-payment' ),
				'default'     => __( 'LYpay', 'wc-lypay-payment' ),
				'desc_tip'    => true,
			),
			'description'  => array(
				'title'       => __( 'Description', 'wc-lypay-payment' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description that the customer will see on your checkout.', 'wc-lypay-payment' ),
				'default'     => __( 'Pay quickly and securely with LYpay.', 'wc-lypay-payment' ),
				'desc_tip'    => true,
			),
			'instructions' => array(
				'title'       => __( 'Instructions', 'wc-lypay-payment' ),
				'type'        => 'textarea',
				'description' => __( 'Instructions that will be added to the thank you page and emails.', 'wc-lypay-payment' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'order_status' => array(
				'title'       => __( 'Initial Order Status', 'wc-lypay-payment' ),
				'type'        => 'select',
				'description' => __( 'Choose the status orders should be set to after checkout.', 'wc-lypay-payment' ),
				'default'     => 'wc-on-hold',
				'desc_tip'    => true,
				'options'     => array(
					'wc-pending'    => _x( 'Pending payment', 'Order status', 'woocommerce' ),
					'wc-processing' => _x( 'Processing', 'Order status', 'woocommerce' ),
					'wc-on-hold'    => _x( 'On hold', 'Order status', 'woocommerce' ),
					'wc-completed'  => _x( 'Completed', 'Order status', 'woocommerce' ),
				),
			),
			'iban'         => array(
				'title'             => __( 'IBAN', 'wc-lypay-payment' ),
				'type'              => 'text',
				'description'       => __( 'Your IBAN for receiving payments (Must be exactly 25 characters).', 'wc-lypay-payment' ),
				'default'           => '',
				'desc_tip'          => true,
				'custom_attributes' => array(
					'minlength' => '25',
					'maxlength' => '25',
				),
			),
			'whatsapp'     => array(
				'title'       => __( 'WhatsApp Number', 'wc-lypay-payment' ),
				'type'        => 'text',
				'description' => __( 'WhatsApp number for customers to send receipts (Optional).', 'wc-lypay-payment' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'email'        => array(
				'title'       => __( 'Email Address', 'wc-lypay-payment' ),
				'type'        => 'email',
				'description' => __( 'Email address for customers to send receipts (Optional).', 'wc-lypay-payment' ),
				'default'     => '',
				'desc_tip'    => true,
			),
		);
	}

	/**
	 * Validate IBAN Field.
	 *
	 * @param string $key Field key.
	 * @param string $value Field value.
	 * @return string
	 */
	public function validate_iban_field( $key, $value ) {
		$value = trim( $value );
		if ( ! empty( $value ) && 25 !== strlen( $value ) ) {
			WC_Admin_Settings::add_error( __( 'IBAN must be exactly 25 characters long.', 'wc-lypay-payment' ) );
			return $this->get_option( 'iban' ); // Return old value
		}
		return $value;
	}

	/**
	 * Output for the order received page.
	 *
	 * @param int $order_id Order ID.
	 */
	public function thankyou_page( $order_id ) {
		$this->display_payment_instructions();
	}

	/**
	 * Add content to the WC emails.
	 *
	 * @param WC_Order $order Order object.
	 * @param bool     $sent_to_admin Sent to admin.
	 * @param bool     $plain_text Email format: plain text or HTML.
	 */
	public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
		if ( $this->id !== $order->get_payment_method() || $sent_to_admin ) {
			return;
		}
		$this->display_payment_instructions();
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		// Mark as on-hold (we're awaiting the payment) or user configured status.
		// Note: wc_reduce_stock_levels() is called below, which mimics COD behavior.
		$order->update_status( $this->order_status, __( 'Order received via LYpay.', 'wc-lypay-payment' ) );

		// Reduce stock levels.
		wc_reduce_stock_levels( $order_id );

		// Remove cart.
		WC()->cart->empty_cart();

		// Return thankyou redirect.
		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	/**
	 * Output payment fields on the checkout.
	 */
	public function payment_fields() {
		if ( $this->description ) {
			echo wpautop( wp_kses_post( $this->description ) );
		}
		$this->display_payment_instructions();
	}

	/**
	 * Helpher to display payment instructions with dynamic data.
	 */
	private function display_payment_instructions() {
		// Icons (Simple SVGs)
		$icon_iban     = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 1.2em; height: 1.2em; vertical-align: middle; margin-right: 5px;"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>';
		$icon_whatsapp = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" fill="#ffffff" style="width: 1.2em; height: 1.2em; vertical-align: middle; margin-right: 5px;"><path d="M380.9 97.1C339 55.1 283.2 32 223.9 32c-122.4 0-222 99.6-222 222 0 39.1 10.2 77.3 29.6 111L0 480l117.7-30.9c32.4 17.7 68.9 27 106.1 27h.1c122.3 0 224.1-99.6 224.1-222 0-59.3-25.2-115-67.1-157zm-157 341.6c-33.2 0-65.7-8.9-94-25.7l-6.7-4-69.8 18.3L72 359.2l-4.4-7c-18.5-29.4-28.2-63.3-28.2-98.2 0-101.7 82.8-184.5 184.6-184.5 49.3 0 95.6 19.2 130.4 54.1 34.8 34.9 56.2 81.2 56.1 130.5 0 101.8-84.9 184.6-186.6 184.6zm101.2-138.2c-5.5-2.8-32.8-16.2-37.9-18-5.1-1.9-8.8-2.8-12.5 2.8-3.7 5.6-14.3 18-17.6 21.8-3.2 3.7-6.5 4.2-12 1.4-32.6-16.3-54-29.1-75.5-66-5.7-9.8 5.7-9.1 16.3-30.3 1.8-3.7 .9-6.9-.5-9.7-1.4-2.8-12.5-30.1-17.1-41.2-4.5-10.8-9.1-9.3-12.5-9.5-3.2-.2-6.9-.2-10.6-.2-3.7 0-9.7 1.4-14.8 6.9-5.1 5.6-19.4 19-19.4 46.3 0 27.3 19.9 53.7 22.6 57.4 2.8 3.7 39.1 59.7 94.8 83.8 35.2 15.2 49 16.5 66.6 13.9 10.7-1.6 32.8-13.4 37.4-26.4 4.6-13 4.6-24.1 3.2-26.4-1.3-2.5-5-3.9-10.5-6.6z"/></svg>';
		$icon_email    = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 1.2em; height: 1.2em; vertical-align: middle; margin-right: 5px;"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>';

		?>
		<style>
			.wc-lypay-instructions {
				border: 1px solid #e5e5e5;
				border-radius: 5px;
				padding: 15px;
				margin-top: 10px;
				font-size: 0.95em;
			}
			.wc-lypay-details {
				list-style: none !important;
				margin: 15px 0 !important;
				padding: 0 !important;
			}
			.wc-lypay-details li {
				margin-bottom: 10px;
				display: flex;
				align-items: center;
				flex-wrap: wrap;
			}
			.wc-lypay-details li strong {
				display: flex;
				align-items: center;
				margin-right: 8px;
			}
			.wc-lypay-details li span, .wc-lypay-details li a {
				font-weight: 500;
			}
			.lypay-whatsapp svg {
				stroke: #ffffffff;
			}
			.lypay-whatsapp a {
				color: #25D366; 
				text-decoration: none;
			}
			.lypay-whatsapp a:hover {
				text-decoration: underline;
			}
			.lypay-reminder {
				border-top: 1px dashed #ddd;
				padding-top: 10px;
				font-size: 0.9em;
				font-style: italic;
			}
		</style>

		<div class="wc-lypay-instructions">
			<?php if ( $this->instructions ) : ?>
				<div style="margin-bottom: 15px;"><?php echo wp_kses_post( wpautop( $this->instructions ) ); ?></div>
			<?php endif; ?>

			<ul class="wc-lypay-details">
				<?php if ( $this->iban ) : ?>
					<li class="lypay-iban">
						<strong><?php echo $icon_iban; ?><?php esc_html_e( 'IBAN:', 'wc-lypay-payment' ); ?></strong>
						<span style="font-family: monospace; font-size: 1.1em; letter-spacing: 0.5px;"><?php echo esc_html( $this->iban ); ?></span>
					</li>
				<?php endif; ?>

				<?php if ( $this->whatsapp ) : ?>
					<li class="lypay-whatsapp">
						<strong><?php echo $icon_whatsapp; ?><?php esc_html_e( 'WhatsApp:', 'wc-lypay-payment' ); ?></strong>
						<span>
							<a href="https://wa.me/<?php echo esc_attr( preg_replace( '/[^0-9]/', '', $this->whatsapp ) ); ?>" target="_blank" rel="noopener noreferrer">
								<?php echo esc_html( $this->whatsapp ); ?>
							</a>
						</span>
					</li>
				<?php endif; ?>

				<?php if ( $this->email ) : ?>
					<li class="lypay-email">
						<strong><?php echo $icon_email; ?><?php esc_html_e( 'Email:', 'wc-lypay-payment' ); ?></strong>
						<span><a href="mailto:<?php echo esc_attr( $this->email ); ?>"><?php echo esc_html( $this->email ); ?></a></span>
					</li>
				<?php endif; ?>
			</ul>

			<div class="lypay-reminder">
				<p style="margin:0;">
					<?php 
					/* translators: %s: Order number placeholder */
					printf( 
						esc_html__( 'Please send the payment receipt via WhatsApp or Email including your Order Number.', 'wc-lypay-payment' ) 
					); 
					?>
				</p>
			</div>
		</div>
		<?php
	}
}

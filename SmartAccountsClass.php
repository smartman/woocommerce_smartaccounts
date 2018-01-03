<?php

include_once( 'SmartAccountsClient.php' );
include_once( 'SmartAccountsSalesInvoice.php' );
include_once( 'SmartAccountsApi.php' );
include_once( 'SmartAccountsPayment.php' );
include_once( 'SmartAccountsArticle.php' );
include_once( dirname( __FILE__ ) . '/../woocommerce/woocommerce.php' );

class SmartAccountsClass {

	public static function orderStatusProcessing( $order_id ) {

		//try catch makes sure your store will operate even if there are errors
		try {
			$order = new WC_Order( $order_id );

			if ( strlen( get_post_meta( $order_id, 'smartaccounts_invoice_id', true ) ) > 0 ) {
				return; //Smartaccounts order is already created
			}

			$saClient       = new SmartAccountsClient( $order );
			$client         = $saClient->getClient();
			$saSalesInvoice = new SmartAccountsSalesInvoice( $order, $client );

			$invoice   = $saSalesInvoice->saveInvoice();
			$saPayment = new SmartAccountsPayment( $order, $invoice );
			$saPayment->createPayment();
			update_post_meta( $order_id, 'smartaccounts_invoice_id', $invoice['invoice']['invoiceNumber'] );
		} catch ( Exception $exception ) {
			$handle = fopen( 'error.log', 'a' );
			if ( $handle ) {
				fwrite( $handle, $exception->getMessage() );
				fwrite( $handle, $exception->getTraceAsString() );
				fclose( $handle );
			}
		}
	}


	function registerSettings() {
		register_setting( 'smartaccounts_options', 'sa_api_pk' );
		register_setting( 'smartaccounts_options', 'sa_api_sk' );
		register_setting( 'smartaccounts_options', 'sa_api_payment_account' );
		register_setting( 'smartaccounts_options', 'sa_api_shipping_code', [ 'default' => 'shipping' ] );
	}

	function optionsPage() {
		add_submenu_page( 'woocommerce', 'SmartAccounts settings', "SmartAccounts", 'manage_woocommerce', 'SmartAccounts', 'SmartAccountsClass::options_page_html' );
	}

	function options_page_html() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
        <div class="wrap">
            <h1><?= esc_html( get_admin_page_title() ); ?></h1>
            <form action="options.php" method="post">
                <table class="form-table">
                    <tr valign="top">
                        <th>SmartAccounts public key</th>
                        <td>
                            <input name="sa_api_pk" size="50"
                                   value="<?php echo esc_attr( get_option( 'sa_api_pk' ) ); ?>"/>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th>SmartAccounts private key</th>
                        <td>
                            <input name="sa_api_sk" size="50"
                                   value="<?php echo esc_attr( get_option( 'sa_api_sk' ) ); ?>"/>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th>SmartAccounts payments Bank account name</th>
                        <td>
                            <input name="sa_api_payment_account" size="50"
                                   value="<?php echo esc_attr( get_option( 'sa_api_payment_account' ) ); ?>"/>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th>SmartAccounts shipping item name</th>
                        <td>
                            <input name="sa_api_shipping_code" size="50"
                                   value="<?php echo esc_attr( get_option( 'sa_api_shipping_code' ) ); ?>"/>
                        </td>
                    </tr>

                </table>
				<?php

				settings_fields( 'smartaccounts_options' );
				submit_button( 'Salvesta seaded' );
				?>
            </form>
        </div>
		<?php
	}

}



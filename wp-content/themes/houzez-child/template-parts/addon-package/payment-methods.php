<?php

global $current_user;

wp_get_current_user();
$userID = $current_user->ID;
$package_id = houzez_get_user_package_id( $userID );

$tax = 0;

if(!empty($package_id)) {
    $tax = (int)get_post_meta( $package_id, 'fave_package_tax', true );
}

$terms_conditions = houzez_option('payment_terms_condition');

$allowed_html_array = array(
    'a' => array(
        'href' => array(),
        'title' => array(),
        'target' => array()
    )
);

if ($_GET['option'] == 'week') {
    $title = 'Property of the Week';
    $price = 25;
}

if ($_GET['option'] == 'featured') {
    $title = 'Featured Property';
    $price = 15;
}

$price = $price * (100 + $tax) / 100;

$enable_paypal = houzez_option('enable_paypal');
$enable_stripe = houzez_option('enable_stripe');
$enable_2checkout = houzez_option('enable_2checkout');
$enable_wireTransfer = houzez_option('enable_wireTransfer');
$enable_bitcoin = houzez_option('enable_bitcoin');
$enable_applepay = houzez_option('enable_applepay');
$enable_googlepay = houzez_option('enable_googlepay');

?>

<div class="method-select-block">

    <?php if( $enable_paypal != 0 ) { ?>
    <div class="method-row">
        <div class="method-select">
            <div class="radio">
                <label>
                    <input type="radio" class="payment-paypal" name="houzez_payment_type" value="paypal" 
                        <?php if (!isset($_GET['state'])) echo 'checked'; ?>>
                    <?php esc_html_e( 'Paypal', 'houzez'); ?>
                </label>
            </div>
        </div>
        <div class="method-type">
            <img src="<?php echo get_stylesheet_directory_uri(); ?>/icons/paypal-icon.png" alt="paypal">
        </div>
    </div>
    <?php } ?>

    <?php if( $enable_stripe != 0 ) { ?>
    <div class="method-row">
        <div class="method-select">
            <div class="radio">
                <label>
                    <input type="radio" class="payment-stripe" name="houzez_payment_type" value="stripe">
                    <?php esc_html_e( 'Pay by Credit Card', 'houzez'); ?>
                </label>
                <input type="hidden" name="houzez_option_price" value="<?php echo $price; ?>">
                <input type="hidden" name="houzez_option_name" value="<?php echo $_GET['option']; ?>">
                <input type="hidden" name="houzez_property_id" value="<?php echo $_GET['post']; ?>">
                <?php houzez_stripe_payment_membership( '', $price, $title ); ?>
            </div>
        </div>
        <div class="method-type">
            <img src="<?php echo get_stylesheet_directory_uri(); ?>/icons/credit-card-icon.png" alt="credit card">
        </div>
    </div>
    <?php } ?>

    <?php if( $enable_2checkout != 0 && is_user_logged_in() && $enable_stripe != 1 ) { ?>
        <div class="method-row">
            <div class="method-select">
                <div class="radio">
                    <label>
                        <input type="radio" class="payment-2checkout" name="houzez_payment_type" value="2checkout">
                        <?php esc_html_e( 'Credit Card', 'houzez'); ?>
                    </label>
                </div>
            </div>
            <div class="method-type">
                <img src="<?php echo get_template_directory_uri(); ?>/images/2checkout.jpg" alt="2checkout">
            </div>
        </div>
    <?php } ?>

    <?php if( $enable_bitcoin != 0 ) { ?>
    <div class="method-row">
        <div class="method-select">
            <div class="radio">
                <label>
                    <input type="radio" class="payment-bitcoin" name="houzez_payment_type" value="bitcoin"
                        <?php if (isset($_GET['state'])) echo 'checked'; ?>>
                    <?php esc_html_e( 'Bitcoin', 'houzez' ); ?>
                </label>
            </div>
            <input type="hidden" value="https://www.coinbase.com/oauth/authorize/?response_type=code&client_id=<?php echo houzez_option('coinbaseID')?>&redirect_uri=https%3A%2F%2Famstaging.unfstaging.com%2Fadd-on-payment&state=<?php echo $price; ?>%2C<?php echo $_GET['post']; ?>%2C<?php echo $_GET['option']; ?>" />
        </div>
        <div class="method-type">
            <img src="<?php echo get_stylesheet_directory_uri(); ?>/icons/bitcoin-icon.png" alt="bitcoin">
        </div>
    </div>
    <?php } ?>

    <?php if( $enable_googlepay != 0 ) { ?>
    <div class="method-row">
        <div class="method-select">
            <div class="radio">
                <label>
                    <input type="radio" class="payment-googlepay" name="houzez_payment_type" value="googlepay">
                    <?php esc_html_e( 'Google Pay', 'houzez' ); ?>
                </label>
                <?php houzez_googlepay_payment( $_GET['post'], $price, $title, $_GET['option'] ); ?>
            </div>
        </div>
        <div class="method-type">
            <img src="<?php echo get_stylesheet_directory_uri(); ?>/icons/googlepay-icon.png" alt="googlepay">
        </div>
    </div>
    <?php } ?>

    <?php if( $enable_applepay != 0 ) { ?>
    <div class="method-row">
        <div class="method-select">
            <div class="radio">
                <label>
                    <input type="radio" class="payment-applepay" name="houzez_payment_type" value="applepay">
                    <?php esc_html_e( 'Apple Pay', 'houzez' ); ?>
                </label>
                <?php houzez_applepay_payment( $_GET['post'], $price, $title, $_GET['option'] ); ?>
            </div>
        </div>
        <div class="method-type">
            <img src="<?php echo get_stylesheet_directory_uri(); ?>/icons/applepay-icon.png" alt="applepay">
        </div>
    </div>
	<?php } ?>

</div>

<button id="houzez_complete_option" type="submit" class="btn btn-success btn-submit">
	<?php esc_html_e( 'Complete Package Option', 'houzez' ); ?>
</button>
<span class="help-block">
	<?php echo sprintf( wp_kses(__( 'By clicking "Complete Package Option" you agree to our <a target="_blank" href="%s">Terms & Conditions</a>', 'houzez' ), $allowed_html_array), 'https://www.affordablemallorca.com/legal'/*get_permalink($terms_conditions)*/ ); ?>
</span>
<?php
function register_my_session(){
    if( ! session_id() ) {
        session_start();
    }
}
add_action('init', 'register_my_session');

function editor_from_post_type() {
    global $pagenow;
    
    if ($pagenow == 'post.php' && isset($_GET['post']) && get_post_type($_GET['post']) == 'houzez_packages')
        add_post_type_support( 'houzez_packages', 'editor' );
}
add_action('admin_init', 'editor_from_post_type');

add_action('admin_head', 'custom_styles');
function custom_styles() {
  echo '<style>
    .form-field input, .form-field textarea {
        width: auto !important;
    }
    .rwmb-button-wrapper .rwmb-input {
        text-align: center;
    }
    .billing.rwmb-row .rwmb-column {
        margin-right: 1%;
    }
    .billing.rwmb-row .rwmb-column:nth-child(2) {
        width: 6%;
    }
    .billing.rwmb-row .rwmb-column-2 {
        width: 15%;
    }
    .billing.rwmb-row .rwmb-column-6 {
        float: right;
        margin: 0 0 0 2.5%;
    }
    #fave_billing_custom_value,
    #fave_billing_custom_option,
    #fave_billing_unit_add,
    .payment_option {
        margin-top: 25px;
    }
    .billing.rwmb-row>div:nth-child(2),
    .billing.rwmb-row>div:nth-child(3),
    .payment:not(.selected) {
        display: none;
    }
    .wp-core-ui .payment_option button.button {
        background: none !important;
        border: none;
        border-radius: 0;
        box-shadow: none;
        color: #ff0000;
        height: 25px;
        padding: 0 3px;
    }
    .wp-core-ui .payment_option button.button:hover {
        border-bottom: 1px solid #ff0000;
    }
  </style>';
}

function houzez_cancel_user_membership($user_id){}

add_action( 'wp_ajax_nopriv_houzez_register_redirect', 'houzez_register_redirect', 200 );
add_action( 'wp_ajax_houzez_register_redirect', 'houzez_register_redirect', 200 );

function houzez_register_redirect() {
    check_ajax_referer('houzez_register_nonce', 'houzez_register_security');

    $allowed_html = array();

    $usermane          = trim( sanitize_text_field( wp_kses( $_POST['username'], $allowed_html ) ));
    $email             = trim( sanitize_text_field( wp_kses( $_POST['useremail'], $allowed_html ) ));
    $term_condition    = wp_kses( $_POST['term_condition'], $allowed_html );
    $enable_password = houzez_option('enable_password');
    $response = $_POST["g-recaptcha-response"];

    $user_role = get_option( 'default_role' );

    if( isset( $_POST['role'] ) && $_POST['role'] != '' ){
        $user_role = isset( $_POST['role'] ) ? sanitize_text_field( wp_kses( $_POST['role'], $allowed_html ) ) : $user_role;
    } else {
        $user_role = $user_role;
    }

    $term_condition = ( $term_condition == 'on') ? true : false;

    if( !$term_condition ) {
        echo json_encode( array( 'success' => false, 'msg' => esc_html__('You need to agree with terms & conditions.', 'houzez-login-register') ) );
        wp_die();
    }

    if( empty( $usermane ) ) {
        echo json_encode( array( 'success' => false, 'msg' => esc_html__('The username field is empty.', 'houzez-login-register') ) );
        wp_die();
    }
    if( strlen( $usermane ) < 3 ) {
        echo json_encode( array( 'success' => false, 'msg' => esc_html__('Minimum 3 characters required', 'houzez-login-register') ) );
        wp_die();
    }
    if (preg_match("/^[0-9A-Za-z_]+$/", $usermane) == 0) {
        echo json_encode( array( 'success' => false, 'msg' => esc_html__('Invalid username (do not use special characters or spaces)!', 'houzez-login-register') ) );
        wp_die();
    }
    if( username_exists( $usermane ) ) {
        echo json_encode( array( 'success' => false, 'msg' => esc_html__('This username is already registered.', 'houzez-login-register') ) );
        wp_die();
    }
    if( empty( $email ) ) {
        echo json_encode( array( 'success' => false, 'msg' => esc_html__('The email field is empty.', 'houzez-login-register') ) );
        wp_die();
    }

    if( email_exists( $email ) ) {
        echo json_encode( array( 'success' => false, 'msg' => esc_html__('This email address is already registered.', 'houzez-login-register') ) );
        wp_die();
    }

    if( !is_email( $email ) ) {
        echo json_encode( array( 'success' => false, 'msg' => esc_html__('Invalid email address.', 'houzez-login-register') ) );
        wp_die();
    }

    if( $enable_password == 'yes' ){
        $user_pass         = trim( sanitize_text_field(wp_kses( $_POST['register_pass'] ,$allowed_html) ) );
        $user_pass_retype  = trim( sanitize_text_field(wp_kses( $_POST['register_pass_retype'] ,$allowed_html) ) );

        if ($user_pass == '' || $user_pass_retype == '' ) {
            echo json_encode( array( 'success' => false, 'msg' => esc_html__('One of the password field is empty!', 'houzez-login-register') ) );
            wp_die();
        }

        if ($user_pass !== $user_pass_retype ){
            echo json_encode( array( 'success' => false, 'msg' => esc_html__('Passwords do not match', 'houzez-login-register') ) );
            wp_die();
        }
    }

    houzez_google_recaptcha_callback();

    if($enable_password == 'yes' ) {
        $user_password = $user_pass;
    } else {
        $user_password = wp_generate_password( $length=12, $include_standard_special_chars=false );
    }
    $user_id = wp_create_user( $usermane, $user_password, $email );

    if ( is_wp_error($user_id) ) {
        echo json_encode( array( 'success' => false, 'msg' => $user_id ) );
        wp_die();
    } else {

        wp_update_user( array( 'ID' => $user_id, 'role' => $user_role ) );

        if( $enable_password =='yes' ) {
            echo json_encode( array( 'success' => true, 'msg' => esc_html__('Your account was created and you can login now!', 'houzez-login-register') ) );
        } else {
            echo json_encode( array( 'success' => true, 'msg' => esc_html__('An email with the generated password was sent!', 'houzez-login-register') ) );
        }

        $user_as_agent = houzez_option('user_as_agent');

        if( $user_as_agent == 'yes' ) {
            if ($user_role == 'houzez_agent' || $user_role == 'author') {
                houzez_register_as_agent($usermane, $email, $user_id);

            } else if ($user_role == 'houzez_agency') {
                houzez_register_as_agency($usermane, $email, $user_id);
            }
        }
        houzez_wp_new_user_notification( $user_id, $user_password );

        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);
    }
    wp_die();
}

add_action( 'wp_enqueue_scripts', 'my_scripts', 100 );

function my_scripts() {
    wp_dequeue_script( 'houzez_ajax_calls' );
    wp_deregister_script( 'houzez_ajax_calls' );

    global $paged, $post, $current_user;

    $property_lat = $property_map = $property_streetView = $is_singular_property = $login_redirect = '';
    $property_lng = $google_map_needed = $fave_main_menu_trans = $header_map_selected_city = $current_template = $markerPricePins = '';
    $advanced_search_rent_status = $advanced_search_price_range_rent_status = 'for-rent';

    wp_get_current_user();
    $userID = $current_user->ID;

    if (is_rtl()) {
        $houzez_rtl = "yes";
    } else {
        $houzez_rtl = "no";
    }

    $after_login_redirect = houzez_option('login_redirect');

    if ($after_login_redirect == 'same_page') {

        if (is_tax()) {
            $login_redirect = get_term_link(get_query_var('term'), get_query_var('taxonomy'));
        } else {
            if (is_home() || is_front_page()) {
                $login_redirect = site_url();
            } else {
                if (!is_404() && !is_search() && !is_author()) {
                    $login_redirect = get_permalink($post->ID);
                }
            }
        }

    } else {
        $login_redirect = houzez_option('login_redirect_link');
    }

    if (!is_404() && !is_search() && !is_tax() && !is_author()) {
        $fave_main_menu_trans = get_post_meta($post->ID, 'fave_main_menu_trans', true);
        $header_map_selected_city = get_post_meta($post->ID, 'fave_map_city', false);
        $current_template = get_page_template_slug($post->ID);
    }

    $simple_logo = houzez_option('custom_logo', '', 'url');

    if (is_singular('property')) {
        $property_location = get_post_meta(get_the_ID(), 'fave_property_location', true);
        if (!empty($property_location)) {
            $lat_lng = explode(',', $property_location);
            $property_lat = $lat_lng[0];
            $property_lng = $lat_lng[1];

            $property_map = get_post_meta(get_the_ID(), 'fave_property_map', true);
            $property_streetView = get_post_meta(get_the_ID(), 'fave_property_map_street_view', true);
        }
        $is_singular_property = 'yes';
    }

    if (taxonomy_exists('property_status')) {
        $term_exist = get_term_by('id', $advanced_search_rent_status_id, 'property_status');
        if ($term_exist) {
            $advanced_search_rent_status = get_term($advanced_search_rent_status_id, 'property_status');
            if (!is_wp_error($advanced_search_rent_status)) {
                $advanced_search_rent_status = $advanced_search_rent_status->slug;
            }
        }

        $term_exist_2 = get_term_by('id', $advanced_search_rent_status_id_price_range, 'property_status');
        if ($term_exist_2) {
            $advanced_search_price_range_rent_status = get_term($advanced_search_rent_status_id_price_range, 'property_status');
            if (!is_wp_error($advanced_search_price_range_rent_status)) {
                $advanced_search_price_range_rent_status = $advanced_search_price_range_rent_status->slug;
            }
        }
    }

    $currency_symbol = '';

    $advanced_search_widget_min_price = houzez_option('advanced_search_widget_min_price');
    if (empty($advanced_search_widget_min_price)) {
        $advanced_search_widget_min_price = '0';
    }
    $advanced_search_widget_max_price = houzez_option('advanced_search_widget_max_price');
    if (empty($advanced_search_widget_max_price)) {
        $advanced_search_widget_max_price = '2500000';
    }


    $advanced_search_min_price_range_for_rent = houzez_option('advanced_search_min_price_range_for_rent');
    if (empty($advanced_search_min_price_range_for_rent)) {
        $advanced_search_min_price_range_for_rent = '0';
    }
    $advanced_search_max_price_range_for_rent = houzez_option('advanced_search_max_price_range_for_rent');
    if (empty($advanced_search_max_price_range_for_rent)) {
        $advanced_search_max_price_range_for_rent = '6000';
    }
    
    $advanced_search_widget_min_area = houzez_option('advanced_search_widget_min_area');
    if (empty($advanced_search_widget_min_area)) {
        $advanced_search_widget_min_area = '0';
    }

    $advanced_search_widget_max_area = houzez_option('advanced_search_widget_max_area');
    if (empty($advanced_search_widget_max_area)) {
        $advanced_search_widget_max_area = '600';
    }

    $googlemap_zoom_level = houzez_option('googlemap_zoom_level');
    $googlemap_pin_cluster = houzez_option('googlemap_pin_cluster');
    $googlemap_zoom_cluster = houzez_option('googlemap_zoom_cluster');

    $map_cluster = houzez_option('map_cluster', '', 'url');
    if (!empty($map_cluster)) {
        $clusterIcon = $map_cluster;
    } else {
        $clusterIcon = get_template_directory_uri() . '/images/map/cluster-icon.png';
    }

    if (is_page_template('template/property-listings-map.php') || is_page_template('template/submit_property.php') || is_page_template('template/submit_property_without_login.php') || $header_type == 'property_map' || is_singular('property') || is_singular('houzez_agency') || $content_has_map_shortcode || $enable_radius_search != 0) {
        $google_map_needed = 'yes';
    }

    if (is_front_page()) {
        $paged = (get_query_var('page')) ? get_query_var('page') : 1;
    }

    $search_result_page = houzez_option('search_result_page');
    $search_keyword = isset($_GET['keyword']) ? sanitize_text_field($_GET['keyword']) : '';
    $search_feature = isset($_GET['feature']) ? ($_GET['feature']) : $meta_features;
    $search_country = isset($_GET['country']) ? sanitize_text_field($_GET['country']) : '';
    $search_state = isset($_GET['state']) ? sanitize_text_field($_GET['state']) : $meta_states;
    $search_city = isset($_GET['location']) ? sanitize_text_field($_GET['location']) : $meta_locations;
    $search_area = isset($_GET['area']) ? sanitize_text_field($_GET['area']) : $meta_area;
    $search_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : $meta_status;
    $search_label = isset($_GET['label']) ? sanitize_text_field($_GET['label']) : $meta_labels;
    $search_type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : $meta_types;
    $search_bedrooms = isset($_GET['bedrooms']) ? sanitize_text_field($_GET['bedrooms']) : '';
    $search_bathrooms = isset($_GET['bathrooms']) ? sanitize_text_field($_GET['bathrooms']) : '';
    $search_min_price = isset($_GET['min-price']) ? sanitize_text_field($_GET['min-price']) : $meta_min_price;
    $search_max_price = isset($_GET['max-price']) ? sanitize_text_field($_GET['max-price']) : $meta_max_price;
    $search_currency = isset($_GET['currency']) ? sanitize_text_field($_GET['currency']) : '';
    $search_min_area = isset($_GET['min-area']) ? sanitize_text_field($_GET['min-area']) : '';
    $search_max_area = isset($_GET['max-area']) ? sanitize_text_field($_GET['max-area']) : '';
    $search_property_id = isset($_GET['property_id']) ? sanitize_text_field($_GET['property_id']) : '';
    $search_publish_date = isset($_GET['publish_date']) ? sanitize_text_field($_GET['publish_date']) : '';

    $prop_no_halfmap = 10;
    if (is_page_template(array('template/property-listings-map.php'))) {
        $prop_no_halfmap = get_post_meta($post->ID, 'fave_prop_no_halfmap', true);
    }

    $search_location = isset($_GET['search_location']) ? esc_attr($_GET['search_location']) : false;
    $use_radius = 'on';
    $search_lat = isset($_GET['lat']) ? (float)$_GET['lat'] : false;
    $search_long = isset($_GET['lng']) ? (float)$_GET['lng'] : false;
    $search_radius = isset($_GET['radius']) ? (int)$_GET['radius'] : false;

    $sort_by = isset($_GET['sortby']) ? sanitize_text_field($_GET['sortby']) : $sort_halfmap;

    $measurement_unit_adv_search = houzez_option('measurement_unit_adv_search');
    if ($measurement_unit_adv_search == 'sqft') {
        $measurement_unit_adv_search = houzez_option('measurement_unit_sqft_text');
    } elseif ($measurement_unit_adv_search == 'sq_meter') {
        $measurement_unit_adv_search = houzez_option('measurement_unit_square_meter_text');
    }

    $thousands_separator = houzez_option('thousands_separator');

    $property_top_area = houzez_option('prop-top-area');
    if (isset($_GET['s_top'])) {
        $property_top_area = $_GET['s_top'];
    }

    $keyword_field = houzez_option('keyword_field');
    $keyword_autocomplete = houzez_option('keyword_autocomplete');

    $houzez_default_radius = houzez_option('houzez_default_radius');
    if (isset($_GET['radius'])) {
        $houzez_default_radius = $_GET['radius'];
    }

    $enable_radius_search = houzez_option('enable_radius_search');
    $enable_radius_search_halfmap = houzez_option('enable_radius_search_halfmap');

    $houzez_primary_color = houzez_option('houzez_primary_color');

    $geo_country_limit = houzez_option('geo_country_limit');
    $geocomplete_country = '';
    if ($geo_country_limit != 0) {
        $geocomplete_country = houzez_option('geocomplete_country');
    }

    $houzez_logged_in = 'yes';
    if (!is_user_logged_in()) {
        $houzez_logged_in = 'no';
    }

    $custom_fields_array = array();
    if(class_exists('Houzez_Fields_Builder')) {
        $fields_array = Houzez_Fields_Builder::get_form_fields();
        
        if(!empty($fields_array)){
            foreach ( $fields_array as $value ){
                $field_title = $value->label;
                $field = $value->field_id;
                if($value->is_search == 'yes') {
                    $custom_fields_array[$field] = isset($_GET[$field]) ? sanitize_text_field($_GET[$field]) : '';
                }
                
            }
        }
    }

    $markerPricePins = houzez_option('markerPricePins');
    if(isset($_GET['marker']) && $_GET['marker'] == 'pricePins') {
        $markerPricePins = 'yes';
    }

    $enable_reCaptcha = houzez_option('enable_reCaptcha');

    wp_enqueue_script('houzez_ajax_calls', get_stylesheet_directory_uri() . '/js/houzez_ajax_calls.js', array('jquery'));
    wp_localize_script('houzez_ajax_calls', 'HOUZEZ_ajaxcalls_vars',
        array(
            'admin_url' => get_admin_url(),
            'houzez_rtl' => $houzez_rtl,
            'redirect_type' => $after_login_redirect,
            'login_redirect' => $login_redirect,
            'login_loading' => esc_html__('Sending user info, please wait...', 'houzez'),
            'direct_pay_text' => esc_html__('Processing, Please wait...', 'houzez'),
            'user_id' => $userID,
            'transparent_menu' => $fave_main_menu_trans,
            'simple_logo' => $simple_logo,
            'property_lat' => $property_lat,
            'property_lng' => $property_lng,
            'property_map' => $property_map,
            'property_map_street' => $property_streetView,
            'is_singular_property' => $is_singular_property,
            'process_loader_refresh' => 'fa fa-spin fa-refresh',
            'process_loader_spinner' => 'fa fa-spin fa-spinner',
            'process_loader_circle' => 'fa fa-spin fa-circle-o-notch',
            'process_loader_cog' => 'fa fa-spin fa-cog',
            'success_icon' => 'fa fa-check',
            'set_as_featured' => esc_html__('Set as Featured', 'houzez'),
            'remove_featured' => esc_html__('Remove From Featured', 'houzez'),
            'prop_featured' => esc_html__('Featured', 'houzez'),
            'featured_listings_none' => esc_html__('You have used all the "Featured" listings in your package.', 'houzez'),
            'prop_sent_for_approval' => esc_html__('Sent for Approval', 'houzez'),
            'paypal_connecting' => esc_html__('Connecting to paypal, Please wait... ', 'houzez'),
            'mollie_connecting' => esc_html__('Connecting to mollie, Please wait... ', 'houzez'),
            'bitcoin_connecting' => esc_html__('Connecting to bitcoin, Please wait... ', 'houzez'),
            'confirm' => esc_html__('Are you sure you want to delete?', 'houzez'),
            'confirm_featured' => esc_html__('Are you sure you want to make this a featured listing?', 'houzez'),
            'confirm_featured_remove' => esc_html__('Are you sure you want to remove from featured listing?', 'houzez'),
            'confirm_relist' => esc_html__('Are you sure you want to relist this property?', 'houzez'),
            'delete_property' => esc_html__('Processing, please wait...', 'houzez'),
            'delete_confirmation' => esc_html__('Are you sure you want to delete?', 'houzez'),
            'not_found' => esc_html__("We didn't find any results", 'houzez'),
            'for_rent' => $advanced_search_rent_status,
            'for_rent_price_range' => $advanced_search_price_range_rent_status,
            'currency_symbol' => $currency_symbol,
            'advanced_search_widget_min_price' => $advanced_search_widget_min_price,
            'advanced_search_widget_max_price' => $advanced_search_widget_max_price,
            'advanced_search_min_price_range_for_rent' => $advanced_search_min_price_range_for_rent,
            'advanced_search_max_price_range_for_rent' => $advanced_search_max_price_range_for_rent,
            'advanced_search_widget_min_area' => $advanced_search_widget_min_area,
            'advanced_search_widget_max_area' => $advanced_search_widget_max_area,
            'advanced_search_price_slide' => houzez_option('adv_search_price_slider'),
            'fave_page_template' => basename(get_page_template()),
            'google_map_style' => houzez_option('googlemap_stype'),
            'googlemap_default_zoom' => $googlemap_zoom_level,
            'googlemap_pin_cluster' => $googlemap_pin_cluster,
            'googlemap_zoom_cluster' => $googlemap_zoom_cluster,
            'map_icons_path' => get_template_directory_uri() . '/images/map/',
            'infoboxClose' => get_template_directory_uri() . '/images/map/close.png',
            'clusterIcon' => $clusterIcon,
            'google_map_needed' => $google_map_needed,
            'paged' => $paged,
            'search_result_page' => $search_result_page,
            'search_keyword' => stripslashes($search_keyword),
            'search_country' => $search_country,
            'search_state' => $search_state,
            'search_city' => $search_city,
            'search_feature' => $search_feature,
            'search_area' => $search_area,
            'search_status' => $search_status,
            'search_label' => $search_label,
            'search_type' => $search_type,
            'search_bedrooms' => $search_bedrooms,
            'search_bathrooms' => $search_bathrooms,
            'search_min_price' => $search_min_price,
            'search_max_price' => $search_max_price,
            'search_currency' => $search_currency,
            'search_min_area' => $search_min_area,
            'search_max_area' => $search_max_area,
            'search_property_id' => $search_property_id,
            'search_publish_date' => $search_publish_date,
            'search_no_posts' => $prop_no_halfmap,

            'search_location' => $search_location,
            'use_radius' => $use_radius,
            'search_lat' => $search_lat,
            'search_long' => $search_long,
            'search_radius' => $search_radius,

            'transportation' => esc_html__('Transportation', 'houzez'),
            'supermarket' => esc_html__('Supermarket', 'houzez'),
            'schools' => esc_html__('Schools', 'houzez'),
            'libraries' => esc_html__('Libraries', 'houzez'),
            'pharmacies' => esc_html__('Pharmacies', 'houzez'),
            'hospitals' => esc_html__('Hospitals', 'houzez'),
            'sort_by' => $sort_by,
            'measurement_updating_msg' => esc_html__('Updating, Please wait...', 'houzez'),
            'autosearch_text' => esc_html__('Searching...', 'houzez'),
            'currency_updating_msg' => esc_html__('Updating Currency, Please wait...', 'houzez'),
            'currency_position' => houzez_option('currency_position'),
            'submission_currency' => houzez_option('currency_paid_submission'),
            'wire_transfer_text' => esc_html__('To be paid', 'houzez'),
            'direct_pay_thanks' => esc_html__('Thank you. Please check your email for payment instructions.', 'houzez'),
            'direct_payment_title' => esc_html__('Direct Payment Instructions', 'houzez'),
            'direct_payment_button' => esc_html__('SEND ME THE INVOICE', 'houzez'),
            'direct_payment_details' => houzez_option('direct_payment_instruction'),
            'measurement_unit' => $measurement_unit_adv_search,
            'header_map_selected_city' => $header_map_selected_city,
            'thousands_separator' => $thousands_separator,
            'current_tempalte' => $current_template,
            'monthly_payment' => esc_html__('Monthly Payment', 'houzez'),
            'weekly_payment' => esc_html__('Weekly Payment', 'houzez'),
            'bi_weekly_payment' => esc_html__('Bi-Weekly Payment', 'houzez'),
            'compare_button_url' => houzez_get_template_link_2('template/template-compare.php'),
            'template_thankyou' => houzez_get_template_link('template/template-thankyou.php'),
            'compare_page_not_found' => esc_html__('Please create page using compare properties template', 'houzez'),
            'property_detail_top' => esc_attr($property_top_area),
            'keyword_search_field' => $keyword_field,
            'keyword_autocomplete' => $keyword_autocomplete,
            'houzez_date_language' => $houzez_date_language,
            'houzez_default_radius' => $houzez_default_radius,
            'enable_radius_search' => $enable_radius_search,
            'enable_radius_search_halfmap' => $enable_radius_search_halfmap,
            'houzez_primary_color' => $houzez_primary_color,
            'geocomplete_country' => $geocomplete_country,
            'houzez_logged_in' => $houzez_logged_in,
            'ipinfo_location' => houzez_option('ipinfo_location'),
            'gallery_autoplay' => houzez_option('gallery_autoplay'),
            'stripe_page' => houzez_get_template_link('template-advanced-stripe-charge.php'),
            'twocheckout_page' => houzez_get_template_link('template/template-2checkout.php'),
            'custom_fields' => json_encode($custom_fields_array),
            'markerPricePins' => esc_attr($markerPricePins),
            'houzez_reCaptcha' => $enable_reCaptcha
        )
    );

    wp_enqueue_script( 'numeric', get_stylesheet_directory_uri() . '/js//numeric-1.2.6.js', array('jquery') );
    wp_enqueue_script( 'solar', get_stylesheet_directory_uri() . '/js/solar.js', array('jquery') );
    wp_enqueue_script( 'custom', get_stylesheet_directory_uri() . '/js/custom.js', array('jquery') );

    if (is_page_template( 'template-map-search.php' )) {
        $googlemap_api_key = houzez_option('googlemap_api_key');

        $minify_js = houzez_option('minify_js');
        $js_minify_prefix = '';

        if ($minify_js != 0) {
            $js_minify_prefix = '.min';
        }

        wp_enqueue_script('google-map', 'https://maps.googleapis.com/maps/api/js?libraries=places&language=' . get_locale() . '&key=' . esc_html($googlemap_api_key), array('jquery'), '1.0', false);
        wp_enqueue_script('google-map-info-box', get_template_directory_uri() . '/js/infobox' . $js_minify_prefix . '.js', array('google-map'), '1.1.9', false);
        wp_enqueue_script('google-map-marker-clusterer', get_template_directory_uri() . '/js/markerclusterer' . $js_minify_prefix . '.js', array('google-map'), '2.1.1', false);
        wp_enqueue_script('oms.min.js', get_template_directory_uri() . '/js/oms.min.js', array('google-map'), '1.12.2', false);

        wp_enqueue_script( 'richmarker', get_stylesheet_directory_uri() . '/js/richmarker.js', array('jquery') );
        
        wp_enqueue_script( 'map', get_stylesheet_directory_uri() . '/js/map.js', array('jquery') );
    }

    if (is_page_template('template/user_dashboard_invoices.php') || is_page_template('template/user_dashboard_properties.php') || is_page_template('template/user_dashboard_messages.php') || is_page_template('template/submit_property.php') || is_page_template('template/submit_property_without_login.php') || is_page_template('template/user_dashboard_floor_plans.php') || is_page_template('template/user_dashboard_multi_units.php')) {
            wp_dequeue_script('houzez_property');
            wp_deregister_script('houzez_property');

            wp_enqueue_script('plupload');
            wp_enqueue_script('jquery-ui-sortable');

            wp_enqueue_script('validate.min', get_template_directory_uri() . '/js/jquery.validate.min.js', array('jquery'), '1.14.0', true);

            wp_enqueue_script('houzez_property', get_stylesheet_directory_uri() . '/js/houzez_property.js', array('jquery', 'plupload', 'jquery-ui-sortable'));

            $prop_req_fields = houzez_option('required_fields');
            $enable_paid_submission = houzez_option('enable_paid_submission');

            if( $enable_paid_submission == 'membership') {
                $user_package_id = houzez_get_user_package_id($userID);
                $package_images = get_post_meta( $user_package_id, 'fave_package_images', true );
                $package_unlimited_images = get_post_meta( $user_package_id, 'fave_unlimited_images', true );
                if( $package_unlimited_images != 1 && !empty($package_images)) {
                    $max_prop_images = $package_images;
                } else {
                    $max_prop_images = houzez_option('max_prop_images');
                }
            } else {
                $max_prop_images = houzez_option('max_prop_images');
            }

            $is_edit_property = isset($_GET['edit_property']) ? $_GET['edit_property'] : 'no';

            $prop_data = array(
                'ajaxURL' => admin_url('admin-ajax.php'),
                'verify_nonce' => wp_create_nonce('verify_gallery_nonce'),
                'verify_file_type' => esc_html__('Valid file formats', 'houzez'),
                'msg_digits' => esc_html__('Please enter only digits', 'houzez'),
                'max_prop_images' => $max_prop_images,
                'image_max_file_size' => houzez_option('image_max_file_size'),
                'plan_title_text' => esc_html__('Plan Title', 'houzez'),
                'plan_size_text' => esc_html__('Plan Size', 'houzez'),
                'plan_bedrooms_text' => esc_html__('Plan Bedrooms', 'houzez'),
                'plan_bathrooms_text' => esc_html__('Plan Bathrooms', 'houzez'),
                'plan_price_text' => esc_html__('Plan Price', 'houzez'),
                'plan_price_postfix_text' => esc_html__('Price Postfix', 'houzez'),
                'plan_image_text' => esc_html__('Plan Image', 'houzez'),
                'plan_description_text' => esc_html__('Plan Description', 'houzez'),
                'plan_upload_text' => esc_html__('Upload', 'houzez'),

                'mu_title_text' => esc_html__('Title', 'houzez'),
                'mu_type_text' => esc_html__('Property Type', 'houzez'),
                'mu_beds_text' => esc_html__('Bedrooms', 'houzez'),
                'mu_baths_text' => esc_html__('Bathrooms', 'houzez'),
                'mu_size_text' => esc_html__('Property Size', 'houzez'),
                'mu_size_postfix_text' => esc_html__('Size Postfix', 'houzez'),
                'mu_price_text' => esc_html__('Property Price', 'houzez'),
                'mu_price_postfix_text' => esc_html__('Price Postfix', 'houzez'),
                'mu_availability_text' => esc_html__('Availability Date', 'houzez'),

                'prop_title' => $prop_req_fields['title'],
                //'description' => $prop_req_fields['description'],
                'prop_type' => $prop_req_fields['prop_type'],
                'prop_status' => $prop_req_fields['prop_status'],
                'prop_lifestyle' => $prop_req_fields['prop_lifestyle'],
                'prop_region' => $prop_req_fields['prop_region'],
                'prop_labels' => $prop_req_fields['prop_labels'],
                'prop_price' => $prop_req_fields['sale_rent_price'],
                'prop_sec_price' => $prop_req_fields['prop_second_price'],
                'price_label' => $prop_req_fields['price_label'],
                'prop_id' => $prop_req_fields['prop_id'],
                'bedrooms' => $prop_req_fields['bedrooms'],
                'bathrooms' => $prop_req_fields['bathrooms'],
                'area_size' => $prop_req_fields['area_size'],
                'land_area' => $prop_req_fields['land_area'],
                'garages' => $prop_req_fields['garages'],
                'year_built' => $prop_req_fields['year_built'],
                'property_map_address' => $prop_req_fields['property_map_address'],
                /*'neighborhood' => $prop_req_fields['neighborhood'],
                'city' => $prop_req_fields['city'],
                'state' => $prop_req_fields['state'],*/
                'houzez_logged_in' => $houzez_logged_in,
                'process_loader_refresh' => 'fa fa-spin fa-refresh',
                'process_loader_spinner' => 'fa fa-spin fa-spinner',
                'process_loader_circle' => 'fa fa-spin fa-circle-o-notch',
                'process_loader_cog' => 'fa fa-spin fa-cog',
                'success_icon' => 'fa fa-check',
                'login_loading' => esc_html__('Sending user info, please wait...', 'houzez'),
                'processing_text' => esc_html__('Processing, Please wait...', 'houzez'),
                'add_listing_msg' => esc_html__('Submitting, Please wait...', 'houzez'),
                'is_edit_property' => $is_edit_property,

            );

            wp_localize_script('houzez_property', 'houzezProperty', $prop_data);
    }
}

add_action('admin_enqueue_scripts', 'custom_scripts');
if (is_admin() ){
    function custom_scripts(){
        wp_enqueue_script('ftmetajs', get_template_directory_uri() .'/js/admin/init.js', array('jquery','media-upload','thickbox'));
        wp_enqueue_style( 'houzez-admin.css', get_template_directory_uri(). '/css/admin/admin.css', array(), HOUZEZ_THEME_VERSION, 'all' );

        wp_enqueue_script('houzez-admin-ajax', get_template_directory_uri() .'/js/admin/houzez-admin-ajax.js', array('jquery'));
        wp_enqueue_script( 'custom', get_stylesheet_directory_uri() . '/js/admin.js', array('jquery') );
        wp_localize_script('houzez-admin-ajax', 'houzez_admin_vars',
            array( 'ajaxurl'            => admin_url('admin-ajax.php'),
                'paid_status'        =>  __('Paid','houzez')

            )
        );

        if ( ! did_action( 'wp_enqueue_media' ) ) {
            wp_enqueue_media();
        }

        if ( isset( $_GET['taxonomy'] ) && ( $_GET['taxonomy'] == 'property_lifestyle' || $_GET['taxonomy'] == 'property_region' ) ) {
            wp_enqueue_style( 'wp-color-picker' );
            wp_enqueue_script( 'houzez_taxonomies', get_template_directory_uri().'/js/admin/metaboxes-taxonomies.js', array( 'jquery', 'wp-color-picker' ), 'houzez' );
        }
    }
}

add_action( 'save_post', 'my_save_post_function', 10, 3 );

function my_save_post_function( $post_ID, $post, $update ) {
    if ($post->post_type == 'houzez_packages') {
        $time = get_post_meta($post_ID, 'fave_billing_time_unit');
        $option = $time[0];

        $opt = get_post_meta($post_ID, 'fave_billing_custom_option');
        $cOpt = $opt[0];

        switch ($option) {
            case '':
                $option1 = get_post_meta( $post_ID, 'fave_payment_option1', true );
                $option2 = get_post_meta( $post_ID, 'fave_payment_option2', true );
                $option3 = get_post_meta( $post_ID, 'fave_payment_option3', true );
                $option4 = get_post_meta( $post_ID, 'fave_payment_option4', true );
                $option5 = get_post_meta( $post_ID, 'fave_payment_option5', true );
                $option6 = get_post_meta( $post_ID, 'fave_payment_option6', true );
                $option7 = get_post_meta( $post_ID, 'fave_payment_option7', true );

                if ($option1 != '' && $option1 > 0)
                    update_post_meta($post_ID, 'fave_billing_time_unit', 'DAY');
                if ($option2 != '' && $option2 > 0)
                    update_post_meta($post_ID, 'fave_billing_time_unit', 'WEEK');
                if ($option3 != '' && $option3 > 0)
                    update_post_meta($post_ID, 'fave_billing_time_unit', 'MONTH');
                if ($option4 != '' && $option4 > 0)
                    update_post_meta($post_ID, 'fave_billing_time_unit', 'MONTH');
                if ($option5 != '' && $option5 > 0)
                    update_post_meta($post_ID, 'fave_billing_time_unit', 'MONTH');
                if ($option6 != '' && $option6 > 0)
                    update_post_meta($post_ID, 'fave_billing_time_unit', 'YEAR');
                if ($option7 != '' && $option7 > 0) {
                    if ($cOpt == 'custom1')
                        update_post_meta($post_ID, 'fave_billing_time_unit', 'DAY');
                    else if ($cOpt == 'custom2')
                        update_post_meta($post_ID, 'fave_billing_time_unit', 'WEEK');
                    else if ($cOpt == 'custom3')
                        update_post_meta($post_ID, 'fave_billing_time_unit', 'MONTH');
                }

                break;
            case 'option1':
                update_post_meta($post_ID, 'fave_billing_time_unit', 'DAY');
                break;
            case 'option2':
                update_post_meta($post_ID, 'fave_billing_time_unit', 'WEEK');
                break;
            case 'option3':
            case 'option4':
            case 'option5':
                update_post_meta($post_ID, 'fave_billing_time_unit', 'MONTH');
                break;
            case 'option6':
                update_post_meta($post_ID, 'fave_billing_time_unit', 'YEAR');
                break;
            case 'option7':
                if ($cOpt == 'custom1')
                    update_post_meta($post_ID, 'fave_billing_time_unit', 'DAY');
                else if ($cOpt == 'custom2')
                    update_post_meta($post_ID, 'fave_billing_time_unit', 'WEEK');
                else if ($cOpt == 'custom3')
                    update_post_meta($post_ID, 'fave_billing_time_unit', 'MONTH');

                break;
        }

        $unit = get_post_meta($post_ID, 'fave_billing_unit');

        if ($unit[0] == '0')
            update_post_meta($post_ID, 'fave_billing_unit', '1');
    }
}

function houzez_payment_option($post_id) {
    $option1 = get_post_meta( $post_id, 'fave_payment_option1', true );
    $option2 = get_post_meta( $post_id, 'fave_payment_option2', true );
    $option3 = get_post_meta( $post_id, 'fave_payment_option3', true );
    $option4 = get_post_meta( $post_id, 'fave_payment_option4', true );
    $option5 = get_post_meta( $post_id, 'fave_payment_option5', true );
    $option6 = get_post_meta( $post_id, 'fave_payment_option6', true );
    $option7 = get_post_meta( $post_id, 'fave_payment_option7', true );

    $flag = false;

    if ($option1 != '' && $option1 > 0)
        $flag = true;
    if ($option2 != '' && $option2 > 0)
        $flag = true;
    if ($option3 != '' && $option3 > 0)
        $flag = true;
    if ($option4 != '' && $option4 > 0)
        $flag = true;
    if ($option5 != '' && $option5 > 0)
        $flag = true;
    if ($option6 != '' && $option6 > 0)
        $flag = true;
    if ($option7 != '' && $option7 > 0)
        $flag = true;

    return $flag;
}

/**
 * Override function to display price with currency symbol
 */
function houzez_listing_price() {
    global $wpdb;

    $currency_code = get_post_meta( get_the_ID(), 'fave_currency', true);

    $result = $wpdb->get_results(" SELECT currency_symbol FROM " . $wpdb->prefix . "houzez_currencies where currency_code='$currency_code'");

    if (sizeof($result) > 0)
        $symbol = $result[0]->currency_symbol;
    else
        $symbol = '€';

    $sale_price = get_post_meta( get_the_ID(), 'fave_property_price', true );
    $sale_price = number_format( $sale_price , 0, '', ',' );

    $status = get_the_terms( get_the_ID(), 'property_status' );

    if ($status[0]->slug == 'for-rent' || $status[0]->slug == 'vermietung' || $status[0]->slug == 'alquiler')
        echo $symbol . $sale_price . '/mo';
    else
        echo $symbol . $sale_price;
}

function houzez_listing_price_v1() {
    global $wpdb;

    $currency_code = get_post_meta( get_the_ID(), 'fave_currency', true);

    $result = $wpdb->get_results(" SELECT currency_symbol FROM " . $wpdb->prefix . "houzez_currencies where currency_code='$currency_code'");

    if (sizeof($result) > 0)
        $symbol = $result[0]->currency_symbol;
    else
        $symbol = '€';

    $sale_price = get_post_meta( get_the_ID(), 'fave_property_price', true );
    $sale_price = number_format( $sale_price , 0, '', ',' );
    
    $status = get_the_terms( get_the_ID(), 'property_status' );
    
    if ($status[0]->slug == 'for-rent' || $status[0]->slug == 'vermietung' || $status[0]->slug == 'alquiler')
        return $symbol . $sale_price . '/mo';
    else
        return $symbol . $sale_price;
}

function houzez_listing_price_v2($post_id) {
    global $wpdb;

    $currency_code = get_post_meta( $post_id, 'fave_currency', true);

    $result = $wpdb->get_results(" SELECT currency_symbol FROM " . $wpdb->prefix . "houzez_currencies where currency_code='$currency_code'");

    if (sizeof($result) > 0)
        $symbol = $result[0]->currency_symbol;
    else
        $symbol = '€';

    $sale_price = get_post_meta( $post_id, 'fave_property_price', true );
    $sale_price = number_format( $sale_price , 0, '', ',' );

    $status = get_the_terms( $post_id, 'property_status' );

    if ($status[0]->slug == 'for-rent' || $status[0]->slug == 'vermietung' || $status[0]->slug == 'alquiler')
        echo $symbol . $sale_price . '/mo';
    else
        echo $symbol . $sale_price;
}

/**
 * Rest API Initialization
 */
add_action('rest_api_init', 'register_api');
function register_api() {
    register_rest_route( 'v1', '/houzez_map_search', array(
      'methods' => 'GET',
      'callback' => 'houzez_map_search',
    ));

    register_rest_route( 'v1', '/houzez_map_listing', array(
      'methods' => 'POST',
      'callback' => 'houzez_map_listing',
    ));

    register_rest_route( 'v1', '/houzez_remove_prop_week', array(
      'methods' => 'POST',
      'callback' => 'houzez_remove_prop_week',
    ));

    register_rest_route( 'v1', '/houzez_doc_upload', array(
      'methods' => 'POST',
      'callback' => 'houzez_doc_upload',
    ));

    register_rest_route( 'v1', '/houzez_doc_share', array(
      'methods' => 'POST',
      'callback' => 'houzez_doc_share',
    ));

    register_rest_route( 'v1', '/houzez_doc_remove', array(
      'methods' => 'POST',
      'callback' => 'houzez_doc_remove',
    ));

    register_rest_route( 'v1', 'houzez_get_rate', array(
      'methods' => 'POST',
      'callback' => 'houzez_get_rate',
    ));
}

/**
 * Theme Option Update for Redux options
 */
add_filter("redux/options/houzez_options/sections", 'update_redux_options');
function update_redux_options($sections){
    $i = 1;
    $index = 0;
    while ($index == 0) {
        if ($sections[$i]['id'] == 'property-required-fields') {
            $keys = (array_keys($sections[$i]['fields']));

            $options = $sections[$i]['fields'][$keys[0]]['options'];

            $arr = array();
            foreach ($options as $key => $value) {
                if ($key == 'prop_labels') {
                    $arr['prop_lifestyle'] = 'Lifestyle';
                    $arr['prop_region'] = 'Region';
                }

                $arr[$key] = $value;
            }

            $sections[$i]['fields'][$keys[0]]['options'] = $arr;
        }

        if ($sections[$i]['id'] == 'property-lightbox') {
            $index = $i;
        }

        $i++;
    }

    for ($i = sizeof($sections); $i > $index; $i--) {
        $sections[$i + 2] = $sections[$i];
    }

    $sections[$index + 1] = array(
        'title' => 'Document Upload',
        'id' => 'document-upload',
        'desc' => '',
        'icon' => 'el-icon-cog el-icon-small',
        'priority' => $index + 1,
        'fields' => array(
            array(
                'id' => 'ftp_url',
                'type' => 'text',
                'title' => 'FTP URL',
                'desc' => '',
                'section_id' => 'document-upload'
            ),
            array(
                'id' => 'ftp_username',
                'type' => 'text',
                'title' => 'FTP User Name',
                'desc' => '',
                'section_id' => 'document-upload'
            ),
            array(
                'id' => 'ftp_password',
                'type' => 'text',
                'title' => 'FTP User Password',
                'desc' => '',
                'section_id' => 'document-upload'
            ),
        )
    );

    $sections[$index + 2] = array(
        'title' => 'Document Share',
        'id' => 'document-share',
        'desc' => '',
        'icon' => 'el el-envelope el el-small',
        'priority' => $index + 2,
        'fields' => array(
            array(
                'id' => 'share_subject',
                'type' => 'text',
                'title' => 'Sharing Email Subject',
                'desc' => '',
                'section_id' => 'document-share'
            ),
            array(
                'id' => 'share_verbiage',
                'type' => 'textarea',
                'title' => 'Sharing Email Verbiage',
                'desc' => '',
                'section_id' => 'document-share'
            )
        )
    );

    $i = 1;
    $index = 0;
    while ($index == 0) {
        if ($sections[$i]['id'] == 'mem-wire-payment') {
            $index = $i;
        }

        $i++;
    }

    for ($i = sizeof($sections); $i > $index; $i--) {
        $sections[$i + 3] = $sections[$i];
    }

    $sections[$index + 1] = array(
        'title' => 'Bitcoin',
        'id' => 'mem-bitcoin-payment',
        'desc' => '',
        'subsection' => true,
        'priority' => $index + 1,
        'fields' => array(
            array(
                'id' => 'enable_bitcoin',
                'type' => 'switch',
                'title' => 'Enable Bitcoin',
                'required' => array('enable_paid_submission', '!=', 'no'),
                'desc' => '',
                'subtitle' => '',
                'default' => 0,
                'on' => 'Enabled',
                'off' => 'Disabled',
                'section_id' => 'mem-bitcoin-payment'
            ),
            array(
                'id' => 'coinbaseID',
                'type' => 'text',
                'required' => array('enable_bitcoin', '=', '1'),
                'title' => 'Coinbase Client ID',
                'subtitle' => '',
                'desc' => '',
                'default' => '',
                'section_id' => 'mem-bitcoin-payment'
            )
        )
    );

    $sections[$index + 2] = array(
        'title' => 'Apple Pay',
        'id' => 'mem-apple-payment',
        'desc' => '',
        'subsection' => true,
        'priority' => $index + 2,
        'fields' => array(
            array(
                'id' => 'enable_applepay',
                'type' => 'switch',
                'title' => 'Enable Apple Pay',
                'required' => array('enable_paid_submission', '!=', 'no'),
                'desc' => '',
                'subtitle' => '',
                'default' => 0,
                'on' => 'Enabled',
                'off' => 'Disabled',
                'section_id' => 'mem-apple-payment'
            )
        )
    );

    $sections[$index + 3] = array(
        'title' => 'Google Pay',
        'id' => 'mem-google-payment',
        'desc' => '',
        'subsection' => true,
        'priority' => $index + 3,
        'fields' => array(
            array(
                'id' => 'enable_googlepay',
                'type' => 'switch',
                'title' => 'Enable Google Pay',
                'required' => array('enable_paid_submission', '!=', 'no'),
                'desc' => '',
                'subtitle' => '',
                'default' => 0,
                'on' => 'Enabled',
                'off' => 'Disabled',
                'section_id' => 'mem-google-payment'
            ),
            array(
                'id' => 'merchantID',
                'type' => 'text',
                'required' => array('enable_googlepay', '=', '1'),
                'title' => 'Google Merchant ID',
                'subtitle' => '',
                'desc' => '',
                'default' => '',
                'section_id' => 'mem-google-payment'
            )
        )
    );

    $search_field = 0;
    $search_index = 0;
    $property_section = 0;
    $footer_section = 0;
    $footer_cols = 0;

    for ($i = 1; $i < sizeof($sections) + 1; $i++) {
        if ($sections[$i]['id'] == 'adv-search-fields') {
            $search_field = $i;

            $fields = $sections[$i]['fields'];
            $keys = array_keys($fields);

            for ($j = $keys[0]; $j < $keys[sizeof($keys) - 1] + 1; $j++) {
                if ($fields[$j]['id'] == 'adv_show_hide') {
                    $search_index = $j;
                }
            }
        }

        if ($sections[$i]['id'] == 'property-section') {
            $property_section = $i;
        }

        if($sections[$i]['id'] == 'footer') {
            $footer_section = $i;

            foreach ($sections[$footer_section]['fields'] as $fields) {
                if ($fields['id'] == 'footer_cols') {
                    $footer_cols = $fields['priority'];
                }
            }
        }
    }

    $add_option = array(
        'lifestyle' => 'Lifestyle',
        'region' => 'Region'
    );

    $add_default = array(
        'lifestyle' => '1',
        'region' => '1'
    );

    $sections[$search_field]['fields'][$search_index]['options'] = 
        array_insert_after($sections[$search_field]['fields'][$search_index]['options'], 'type', $add_option);
    $sections[$search_field]['fields'][$search_index]['default'] = 
        array_insert_after($sections[$search_field]['fields'][$search_index]['default'], 'type', $add_default);

    unset($sections[$search_field]['fields'][$search_index]['options']['label']);
    unset($sections[$search_field]['fields'][$search_index]['default']['label']);

    $key_arr = array_keys($sections[$property_section]['fields']);
    $property_field_id = $key_arr[0];

    $sections[$property_section]['fields'][$property_field_id]['options']['enabled'] =
        array_insert_after($sections[$property_section]['fields'][$property_field_id]['options']['enabled'], 
            'floor_plans', array('solar_perspective' => 'Solar Perstpective'));

    $six_cols = array(
        'alt' => '6 Column',
        'img' => ReduxFramework::$_url . 'assets/img/4cl.png'
    );

    $sections[$footer_section]['fields'][$footer_cols]['options']['six_cols'] = $six_cols;
    
    return $sections;
}

function array_insert_after( array $array, $key, array $new ) {
    $keys = array_keys( $array );
    $index = array_search( $key, $keys );
    $pos = false === $index ? count( $array ) : $index + 1;
    
    return array_merge( array_slice( $array, 0, $pos ), $new, array_slice( $array, $pos ) );
}

/**
 * Add, Remove, Update Meta box
 * For Package Creation, Solar Perstpective Creation
 */
add_filter('rwmb_meta_boxes', 'update_custom_metabox', 1000);
function update_custom_metabox($meta_boxes) {
    $options = array(
        'Daily', 'Weekly', 'Monthly', 'Every 3 months',
        'Every 6 months', 'Yearly', 'Custom'
    );

    for ($j = 0; $j < sizeof($meta_boxes); $j++) {
        // Package Creation
        if ($meta_boxes[$j]['pages'][0] == 'houzez_packages') {
            for ($i = sizeof($meta_boxes[$j]['fields']) + 28; $i > 4; $i--) {
                if ($i > 32) {
                    $meta_boxes[$j]['fields'][$i] = $meta_boxes[$j]['fields'][$i - 31];
                } else {
                    $index = floor(($i - 1) / 4);

                    switch ($i) {
                        case 5:
                        case 9:
                        case 13:
                        case 17:
                        case 21:
                        case 25:
                        case 29:
                            $meta_boxes[$j]['fields'][$i] = array(
                                'name' => 'Billing Interval:',
                                'type' => 'custom_html',
                                'std' => '<span>' . $options[$index - 1] . '</span>',
                                'columns' => 4
                            );
                            break;
                        case 6:
                        case 10:
                        case 14:
                        case 18:
                        case 22:
                        case 26:
                        case 30:
                            $meta_boxes[$j]['fields'][$i] = array(
                                'id' => 'fave_payment_option' . $index,
                                'name' => 'Amount',
                                'type' => 'text',
                                'std' => '',
                                'columns' => 3
                            );
                            break;
                        case 7:
                        case 11:
                        case 15:
                        case 19:
                        case 23:
                        case 27:
                        case 31:
                            $meta_boxes[$j]['fields'][$i] = array(
                                'id' => 'fave_plan_option' . $index,
                                'name' => 'Plan ID',
                                'type' => 'text',
                                'std' => '',
                                'columns' => 3
                            );
                            break;
                        case 8:
                        case 12:
                        case 16:
                        case 20:
                        case 24:
                        case 28:
                        case 32:
                            $meta_boxes[$j]['fields'][$i] = array(
                                'id' => 'fave_payment_btn' . $index,
                                'name' => '',
                                'type' => 'button',
                                'std' => 'Remove',
                                'class' => 'payment_option',
                                'columns' => 2
                            );
                            break;
                    }
                }
            }

            $meta_boxes[$j]['fields'][4] = $meta_boxes[$j]['fields'][1];

            $meta_boxes[$j]['fields'][0]['name'] = 'Billing Interval';
            $meta_boxes[$j]['fields'][0]['options'] = array(
                '' => 'Select from the following',
                'option1' => 'Daily',
                'option2' => 'Weekly',
                'option3' => 'Monthly',
                'option4' => 'Every 3 months',
                'option5' => 'Every 6 months',
                'option6' => 'Yearly',
                'option7' => 'Custom'
            );

            $meta_boxes[$j]['fields'][0]['columns'] = 2;

            $meta_boxes[$j]['fields'][1] = array(
                'id' => 'fave_billing_custom_value',
                'name' => '',
                'type' => 'number',
                'std' => '0',
                'columns' => 1
            );

            $meta_boxes[$j]['fields'][2] = array(
                'id' => 'fave_billing_custom_option',
                'name' => '',
                'type' => 'select',
                'std' => '',
                'options' => array(
                    '' => 'Select from the following',
                    'custom1' => 'days',
                    'custom2' => 'weeks',
                    'custom3' => 'months'
                ),
                'columns' => 2
            );

            $meta_boxes[$j]['fields'][3] = array(
                'id' => 'fave_billing_unit_add',
                'name' => '',
                'type' => 'button',
                'std' => 'Add',
                'class' => 'billing',
                'columns' => 1
            );

            ksort($meta_boxes[$j]['fields']);

            $meta_boxes[$j]['fields'][sizeof($meta_boxes[$j]['fields']) - 1]['columns'] = 12;
            $meta_boxes[$j]['fields'][sizeof($meta_boxes[$j]['fields']) - 1]['type'] = 'number';

            $meta_boxes[$j]['fields'][sizeof($meta_boxes[$j]['fields'])] = array(
                'id' => 'fave_encrypt_doc',
                'name' => 'Encryption and Document Storage',
                'type' => 'checkbox',
                'desc' => 'Enable',
                'std' => '',
                'columns' => 6
            );

            $meta_boxes[$j]['fields'][sizeof($meta_boxes[$j]['fields']) + 1] = array(
                'id' => 'fave_video_upload',
                'name' => 'Video Upload',
                'type' => 'checkbox',
                'desc' => 'Enable',
                'std' => '',
                'columns' => 6
            );
        }

        // Solar Perspective Creation
        if ($meta_boxes[$j]['pages'][0] == 'property' && $meta_boxes[$j]['tabs']) {
            $perspective = array(
                'id' => 'fave_perspective',
                'name' => 'What direction does the front of the house face?',
                'type' => 'select',
                'options' => array(
                    '' => '',
                    'north' => 'North',
                    'northeast' => 'Northeast',
                    'east' => 'East',
                    'southeast' => 'Southeast',
                    'south' => 'South',
                    'southwest' => 'Southwest',
                    'west' => 'West',
                    'northwest' => 'Northwest'
                ),
                'std' => '',
                'columns' => 6,
                'tab' => 'property_details'
            );

            $k = 0;
            $fields = array();
            foreach ($meta_boxes[$j]['fields'] as $field) {
                $fields[$k++] = $field;
            }

            $meta_boxes[$j]['fields'] = $fields;

            for ($k = sizeof($meta_boxes[$j]['fields']); $k > 14; $k-- ) {
                $meta_boxes[$j]['fields'][$k] = $meta_boxes[$j]['fields'][$k - 1];
            }

            $meta_boxes[$j]['fields'][15] = $perspective;
        }
    }

    return $meta_boxes;
}

/**
 * Remove theme's template for custom templates
 */
function houzez_remove_page_templates( $templates ) {
    unset( $templates['template/template-packages.php'] );
    unset( $templates['template/template-payment.php'] );
    unset( $templates['template/user_dashboard_membership.php'] );
    unset( $templates['template/user_dashboard_properties.php'] );
    return $templates;
}
add_filter( 'theme_page_templates', 'houzez_remove_page_templates' );

/**
 * Homepage Advanced Search
 */
vc_remove_element('hz-advance-search');

if( !function_exists('houzez_advance_search_update') ) {
    function houzez_advance_search_update($atts, $content = null)
    {
        extract(shortcode_atts(array(
            'search_title' => ''
        ), $atts));

        ob_start();

        $lang = '';

        if (get_site_url() != home_url())
            $lang = substr(home_url(), strlen(get_site_url()) + 7);

        $search_template = get_site_url() . '/advanced-search';

        $houzez_local = houzez_get_localization();
        $adv_search_price_slider = houzez_option('adv_search_price_slider');
        $hide_empty = false;
        ?>

        <input type="hidden" id="min_price" value="<?php echo houzez_option('advanced_search_widget_min_price'); ?>" />
        <input type="hidden" id="max_price" value="<?php echo houzez_option('advanced_search_widget_max_price'); ?>" />

        <div class="advanced-search advanced-search-module houzez-adv-price-range front">
            <h3 class="advance-title"><?php echo esc_html__('Search Properties'); ?></h3>

            <form autocomplete="off" method="get" action="<?php echo esc_url($search_template); ?>">
                <?php if ($lang != '') { ?>
                <input type="hidden" name="lang" value="<?php echo $lang; ?>" />
                <?php } ?>
                
                <div class="row">
                    <input type="hidden" id="type" name="status" value="for-sale" />
                    <div class="col-md-2 col-sm-6 buy-btn">
                        <button type="button" class="btn btn-primary btn-type"><?php echo esc_html__('Buy'); ?></button>
                    </div>
                    <div class="col-md-2 col-sm-6 rent-btn">
                        <button type="button" class="btn btn-type"><?php echo esc_html__('Rent'); ?></button>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-10 col-sm-12 has-search">
                        <span class="fa fa-search form-control-feedback"></span>
                        <input type="text" name="city" class="form-control" 
                            placeholder="<?php echo esc_html__('Neighborhood, City'); ?>" />
                    </div>
                    <div class="col-md-2 col-sm-12">
                        <button type="submit" class="btn btn-secondary">
                            <?php echo strtoupper($houzez_local['search']); ?>
                        </button>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-8 col-sm-12 select-advanced-main">
                        <div class="col-md-3 col-sm-6">
                            <select class="selectpicker bs-select-hidden" name="lifestyle">
                            <?php
                                echo '<option value="">' . esc_html__('Lifestyle') . '</option>';

                                $prop_lifestyle = get_terms(
                                    array(
                                        "property_lifestyle"
                                    ),
                                    array(
                                        'orderby' => 'name',
                                        'order' => 'ASC',
                                        'hide_empty' => $hide_empty,
                                        'parent' => 0
                                    )
                                );
                                houzez_hirarchical_options('property_lifestyle', $prop_lifestyle, '');
                            ?>
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <select class="selectpicker bs-select-hidden" name="region">
                            <?php
                                echo '<option value="">' . esc_html__('Location') . '</option>';

                                $prop_region = get_terms(
                                    array(
                                        "property_region"
                                    ),
                                    array(
                                        'orderby' => 'name',
                                        'order' => 'ASC',
                                        'hide_empty' => $hide_empty,
                                        'parent' => 0
                                    )
                                );
                                houzez_hirarchical_options('property_region', $prop_region, '');
                            ?>
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <select class="selectpicker bs-select-hidden" name="type">
                            <?php
                                echo '<option value="">' . esc_html__('Property Type') . '</option>';

                                $prop_type = get_terms(
                                    array(
                                        "property_type"
                                    ),
                                    array(
                                        'orderby' => 'name',
                                        'order' => 'ASC',
                                        'hide_empty' => $hide_empty,
                                        'parent' => 0
                                    )
                                );
                                houzez_hirarchical_options('property_type', $prop_type, '');
                            ?>
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <?php 
                                $searched_currency = isset($_GET['currency']) ? $_GET['currency'] : '';
                                $currencies = Houzez_Currencies::get_currency_codes();
                            ?>
                            <select class="selectpicker bs-select-hidden" name="currency">
                                <option value=""><?php echo esc_html__('Fiat/Crypto') ?></option>
                            <?php
                                foreach($currencies as $currency) {
                                    echo '<option '.selected( $currency->currency_code, $searched_currency, false).' value="'.$currency->currency_code.'">'.$currency->currency_code.'</option>';
                                }
                            ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-12 range-advanced-main">
                        <?php if( $adv_search_price_slider != 0 ) { ?>
                            <div class="range-text col-md-6 col-lg-4">
                                <input type="hidden" name="min-price" class="min-price-range-hidden range-input" readonly >
                                <input type="hidden" name="max-price" class="max-price-range-hidden range-input" readonly >
                                <span class="range-title"><?php echo $houzez_local['price_range']; ?></span>
                            </div>
                            <div class="range-wrap col-md-6 col-lg-8">
                                <span class="min-price-range"></span>
                                <div class="price-range-advanced"></div>
                                <span class="max-price-range"></span>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </form>
        </div>

        <?php
        $result = ob_get_contents();
        ob_end_clean();
        return $result;
    }

    add_shortcode('hz-advance-search-update', 'houzez_advance_search_update');
}

vc_map( array(
    "name"  =>  esc_html__( "Advanced Search", "houzez" ),
    "description"           => '',
    "base"                  => "hz-advance-search-update",
    'category'              => "By Favethemes",
    "class"                 => "",
    'admin_enqueue_js'      => "",
    'admin_enqueue_css'     => "",
    "icon"                  => "icon-advance-search",
    "params"                => array(
        array(
            "param_name" => "search_title",
            "type" => "textfield",
            "value" => '',
            "heading" => esc_html__("Title:", "houzez" ),
            "description" => esc_html__( "Enter section title", "houzez" ),
            "save_always" => true
        )
    )
) );

/**
 * Draw Map Search
 */
function houzez_map_search() {
    global $wp_query;
    global $wpdb;

    $lang = $_GET['lang'];

    $status = $_GET['status'];
    $city = $_GET['city'];
    $lifestyle = $_GET['lifestyle'];
    $region = $_GET['region'];
    $type = $_GET['type'];
    $currency = $_GET['currency'];
    $min_price = $_GET['min_price'];
    $max_price = $_GET['max_price'];
    $target = $_GET['target'];

    $search_query = array(
        'post_type' => 'property',
        'posts_per_page' => -1,
        'post_status' => 'publish'
    );

    if ( !empty($status) ) {
        $tax_query[] = array(
            'taxonomy' => 'property_status',
            'field' => 'slug',
            'terms' => $status
        );
    }

    if ( !empty($lifestyle) ) {
        $tax_query[] = array(
            'taxonomy' => 'property_lifestyle',
            'field' => 'slug',
            'terms' => $lifestyle
        );
    }

    if ( !empty($region) ) {
        $tax_query[] = array(
            'taxonomy' => 'property_region',
            'field' => 'slug',
            'terms' => $region
        );
    }

    if ( !empty($type) ) {
        $tax_query[] = array(
            'taxonomy' => 'property_type',
            'field' => 'slug',
            'terms' => $type
        );
    }

    $tax_count = count($tax_query);

    if ($tax_count > 0) {
        $tax_query['relation'] = 'AND';

        $search_query['tax_query'] = $tax_query;
    }

    $min_price = doubleval( houzez_clean( $min_price ) );
    $max_price = doubleval( houzez_clean( $max_price ) );

    if ($currency != '' && $currency != 'EUR') {
        if ($currency == 'XBT')
            $from = 'BTC';
        else
            $from = $currency;

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://api.coinbase.com/v2/exchange-rates?currency=' . $from);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($ch);

        curl_close($ch);

        $result = json_decode($result);
        $arr = array();

        foreach ($result->data->rates as $key => $value) {
            $arr[$key] = $value;
        }

        $min_price = $min_price * $arr['EUR'];
        $max_price = $max_price * $arr['EUR'];
    }

    if ( $max_price > $min_price ) {
        $meta_query[] = array(
            'key' => 'fave_property_price',
            'value' => array($min_price, $max_price),
            'type' => 'NUMERIC',
            'compare' => 'BETWEEN',
        );
    }

    $meta_count = count($meta_query);

    if ($meta_count > 0) {
        $meta_query['relation'] = 'AND';

        $search_query['meta_query'] = $meta_query;
    }

    $wp_query = new WP_Query( $search_query );

    $query = $wp_query->request;
    $query = str_replace('wp_posts.*', 'wp_posts.id', $query);

    $propIDs = get_props($city, $query);

    if ($lang != 'en') {
        $enProps = array();

        for ($i = 0; $i < sizeof($propIDs); $i++)
            array_push($enProps, strval(icl_object_id($propIDs[$i], 'property', false, 'en')));

        $term = get_term_by('slug', $status, 'property_status');
        $term_id = $term->term_id;

        $data = $wpdb->get_results("SELECT term_id FROM " . $wpdb->prefix . "terms where slug='$status'");
        $en_term_id = $data[0]->term_id;

        $search = "wp_term_relationships.term_taxonomy_id IN (" . $term_id .")";
        $replace = "wp_term_relationships.term_taxonomy_id IN (" . $en_term_id .")";

        $enQuery = str_replace($search, $replace, $query);

        $search = "( wpml_translations.language_code = '" . $lang . "' OR 0 ) AND";
        $replace = "( wpml_translations.language_code = 'en' OR 0 ) AND";

        $enQuery = str_replace($search, $replace, $enQuery);

        $enPropIDs = get_props($city, $enQuery);

        $enPropIDs = array_diff($enPropIDs, $enProps);

        $propIDs = array_merge($propIDs, $enPropIDs);
    }

    $featured = array();
    $week = array();
    $normal = array();

    foreach ($propIDs as $property){
        $prop_featured = get_post_meta( $property, 'fave_featured', true );
        $prop_week     = get_post_meta( $property, 'fave_week', true );

        if ($prop_featured == 1)
            array_push($featured, $property);
        else if ($prop_week == 1)
            array_push($week, $property);
        else
            array_push($normal, $property);
    }

    $propIDs = array_merge($week, $featured, $normal);

    $location_arr = array();
    $price_arr = array();
    $id_arr = array();

    foreach ($propIDs as $property) {
        $location = get_post_meta($property, 'fave_property_location', true);
        array_push($location_arr, $location);

        $curr = get_post_meta($property, 'fave_currency', true);

        if ($curr == '')
            $curr = 'EUR';

        $price = get_post_meta($property, 'fave_property_price', true);

        if ($currency == '') {
            $result = $wpdb->get_results("SELECT currency_symbol FROM " . $wpdb->prefix . "houzez_currencies where currency_code='$curr'");

            if (sizeof($result) > 0)
                $symbol = $result[0]->currency_symbol;
            else
                $symbol = '€';
        } else {
            if ($curr == 'XBT')
                $from = 'BTC';
            else
                $from = $curr;

            if ($currency == 'XBT')
                $to = 'BTC';
            else
                $to = $currency;

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, 'https://api.coinbase.com/v2/exchange-rates?currency=' . $from);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

            $result = curl_exec($ch);

            curl_close($ch);

            $result = json_decode($result);
            $curArr = array();

            foreach ($result->data->rates as $key => $value) {
                $curArr[$key] = $value;
            }

            $price = $price * $curArr[$to];
        }

        $price = number_format ( $price , 0, '', ',' );
        $price = $symbol . $price;

        array_push($price_arr, $price);
        array_push($id_arr, $property);
    }

    $result = array(
        'location' => $location_arr,
        'price' => $price_arr,
        'id' => $id_arr
    );

    return $result;
}

function get_props($city, $query) {
    global $wpdb;

    $arr = array();

    $properties = $wpdb->get_results($query);

    foreach ($properties as $property) {
        $flag = false;

        if ( empty($city) ) {
            $flag = true;
        } else {
            $cities = get_the_terms($property->id, 'property_city');
            $city_arr = wp_list_pluck($cities, 'name');

            if (in_array($city, $city_arr))
                $flag = true;

            $title = get_the_title($property->id);

            if (strpos(strtolower($title), strtolower($city)) !== false)
                $flag = true;

            $desc = $desc = get_post_field('post_content', $property->id);

            if (strpos(strtolower($desc), strtolower($city)) !== false)
                $flag = true;

            $address = get_post_meta( $property->id, 'fave_property_map_address', true );

            if (strpos(strtolower($address), strtolower($city)) !== false)
                $flag = true;
        }

        if ($flag)
            array_push($arr, $property->id);
    }

    return $arr;
}

function houzez_map_listing() {
    global $wpdb;

    $result = array();

    $id_arr = $_POST['ids'];
    $currency = $_POST['currency'];

    $data = $wpdb->get_results("SELECT currency_symbol FROM " . $wpdb->prefix . "houzez_currencies where currency_code='$currency'");

    if (sizeof($data) > 0)
        $symbol = $data[0]->currency_symbol;
    else
        $symbol = '€';

    if (sizeof($id_arr) > 0) {
        for ($i = 0; $i < sizeof($id_arr); $i++) {
            $content = '';

            $content .= '<div id="ID-' . $id_arr[$i] .'" class="item-wrap infobox_trigger prop_addon">';
            $content .= '<div class="property-item-v2">';

            $content .= '<div class="figure-block">';
            $content .= '<figure class="item-thumb">';

            $week = get_post_meta($id_arr[$i], 'fave_week', true);
            if ($week == '1')
                $content .= '<span class="label-week label">Property of the Week</span>';

            $featured = get_post_meta($id_arr[$i], 'fave_featured', true);
            if ($featured == '1')
                $content .= '<span class="label-featured label">Featured</span>';

            $content .= get_the_post_thumbnail($id_arr[$i], 'houzez-property-thumb-image-v2');
            $content .= '<ul class="actions">';
            $content .= '<li><span class="add_fav" data-placement="top" data-toggle="tooltip"';
            $content .= ' data-original-title="Favorite" data-propid="' . $id_arr[$i] . '">';
            $content .= '<i class="fa fa-heart"></i></span></li>';
            $content .= '<li><span data-toggle="tooltip" data-placement="top" title=""';
            $content .= ' data-original-title="(' . sizeof(get_post_meta($id_arr[$i], 'fave_property_images')) . ')">';
            $content .= '<i class="fa fa-camera"></i></span></li>';
            $content .= '</ul>';
            $content .= '</figure>';
            $content .= '</div>';

            $content .= '<div class="item-body">';
            $content .= '<div class="item-detail"><p>';
            $content .= wp_trim_words(get_post_field('post_content', $id_arr[$i]), 20);
            $content .= '</p></div>';
            $content .= '<div class="item-title"><h2 class="property-title">' . get_the_title($id_arr[$i]) .'</h2></div>';
            $content .= '<div class="item-info">';
            $content .= '<ul class="item-amenities">';

            $bed = get_post_meta($id_arr[$i], 'fave_property_bedrooms', true);
            $content .= '<li>';
            $content .= '<img src="' . get_stylesheet_directory_uri() . '/icons/rooms.png">';
            $content .= '<span>' . $bed . '</span>';
            $content .= '</li>';

            $bath = get_post_meta($id_arr[$i], 'fave_property_bathrooms', true);
            $content .= '<li>';
            $content .= '<img src="' . get_stylesheet_directory_uri() . '/icons/bathtub.png">';
            $content .= '<span>' . $bath . '</span>';
            $content .= '</li>';

            $size = get_post_meta($id_arr[$i], 'fave_property_size', true);
            $content .= '<li>';
            $content .= '<img src="' . get_stylesheet_directory_uri() . '/icons/house.png">';
            $content .= '<span>' . $size . ' m²</span>';
            $content .= '</li>';

            $content .= '<li><a target="_blank" href="' . get_the_permalink($id_arr[$i]) . '" class="btn btn btn-primary">';
            $content .= 'Details &gt;</a></li>';

            $content .= '</ul>';
            $content .= '</div>';
            $content .= '<div class="item-price-block"><span class="item-price">';

            $curr = get_post_meta($id_arr[$i], 'fave_currency', true);

            if ($curr == '')
                $curr = 'EUR';

            $price = get_post_meta($id_arr[$i], 'fave_property_price', true);

            if ($currency == '') {
                $data = $wpdb->get_results("SELECT currency_symbol FROM " . $wpdb->prefix . "houzez_currencies where currency_code='$curr'");

                if (sizeof($data) > 0)
                    $symbol = $data[0]->currency_symbol;
                else
                    $symbol = '€';
            } else {
                if ($curr == 'XBT')
                    $from = 'BTC';
                else
                    $from = $curr;

                if ($currency == 'XBT')
                    $to = 'BTC';
                else
                    $to = $currency;

                $ch = curl_init();

                curl_setopt($ch, CURLOPT_URL, 'https://api.coinbase.com/v2/exchange-rates?currency=' . $from);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

                $data = curl_exec($ch);

                curl_close($ch);

                $data = json_decode($data);
                $arr = array();

                foreach ($data->data->rates as $key => $value) {
                    $arr[$key] = $value;
                }

                $price = $price * $arr[$to];
            }

            $price = number_format ( $price , 0, '', ',' );
            $price = $symbol . $price;

            $status = wp_get_post_terms($id_arr[$i], 'property_status', array('fields' => 'slugs'));
            $status = $status[0];

            if ($status == 'for-rent')
                $status = '/mo';
            else
                $status = '';

            $content .= $price . $status . '</span></div>';
            $content .= '</div>';

            $content .= '</div></div>';

            array_push($result, $content);
        }
    }

    return $result;
}

/* Add metaboxes to property city */
function houzez_get_all_region( $selected = '' ) {
    $taxonomy       =   'property_region';
    $args = array(
        'hide_empty'    => false
    );
    $tax_terms      =   get_terms($taxonomy,$args);
    $select_region    =   '';

    foreach ($tax_terms as $tax_term) {
        $select_region.= '<option value="' . $tax_term->slug.'" ';
        if($tax_term->slug == $selected){
            $select_state.= ' selected="selected" ';
        }
        $select_region.= ' >' . $tax_term->name . '</option>';
    }
    return $select_region;
}

function houzez_property_city_add_meta_fields() {
    $houzez_meta = houzez_get_property_city_meta();
    $all_regions = houzez_get_all_region();
    ?>

    <div class="form-field">
        <label><?php _e( 'Which region has this city?', 'houzez' ); ?></label>
        <select name="fave[parent_state]" class="widefat">
            <?php echo $all_regions; ?>
        </select>
        <p class="description"><?php _e( 'Select region which has this city.', 'houzez' ); ?></p>
    </div>



    <?php
}

add_action( 'property_city_add_form_fields', 'houzez_property_city_add_meta_fields' );

/**
 * Custom taxonomy for custom post type 'Property'
 */
add_action( 'admin_menu', 'remove_label_taxonomy', 999 );
function remove_label_taxonomy() {
    remove_submenu_page('edit.php?post_type=property', 'edit-tags.php?taxonomy=property_label&amp;post_type=property');
    remove_meta_box('property_labeldiv', 'property', 'normal');
}

add_action( 'admin_menu', 'remove_state_taxonomy', 999 );
function remove_state_taxonomy() {
    remove_submenu_page('edit.php?post_type=property', 'edit-tags.php?taxonomy=property_state&amp;post_type=property');
    remove_meta_box('property_statediv', 'property', 'normal');
}

add_action('init', 'overwrite_theme_post_types', 1000);
function overwrite_theme_post_types() {
    $labels_lifestyle = array(
        'name' => __( 'Lifestyles', 'read' ),
        'singular_name' => __( 'Lifestyle', 'read' ),
        'search_items' =>  __( 'Search', 'read' ),
        'all_items' => __( 'All', 'read' ),
        'parent_item' => __( 'Parent Lifestyle', 'read' ),
        'parent_item_colon' => __( 'Parent Lifestyle:', 'read' ),
        'edit_item' => __( 'Edit', 'read' ),
        'update_item' => __( 'Update', 'read' ),
        'add_new_item' => __( 'Add New Lifestyle', 'read' ),
        'new_item_name' => __( 'New Lifestyle Name', 'read' ),
        'menu_name' => __( 'Lifestyles', 'read' )
    );

    register_taxonomy(
        'property_lifestyle', 'property',
        array(
            'hierarchical' => true,
            'labels' => $labels_lifestyle,
            'show_ui' => true,
            'public' => true,
            'query_var' => true,
            'rewrite' => array(
                'slug' => 'property_lifestyle'
            )
        )
    );

    $labels_region = array(
        'name' => __( 'Regions', 'read' ),
        'singular_name' => __( 'Region', 'read' ),
        'search_items' =>  __( 'Search', 'read' ),
        'all_items' => __( 'All', 'read' ),
        'parent_item' => __( 'Parent Region', 'read' ),
        'parent_item_colon' => __( 'Parent Region:', 'read' ),
        'edit_item' => __( 'Edit', 'read' ),
        'update_item' => __( 'Update', 'read' ),
        'add_new_item' => __( 'Add New Region', 'read' ),
        'new_item_name' => __( 'New Region Name', 'read' ),
        'menu_name' => __( 'Regions', 'read' )
    );

    register_taxonomy(
        'property_region', 'property',
        array(
            'hierarchical' => true,
            'labels' => $labels_region,
            'show_ui' => true,
            'public' => true,
            'query_var' => true,
            'rewrite' => array(
                'slug' => 'property_region'
            )
        )
    );

    $prop_city = array(
        'id' => 'fave_prop_region_meta',
        'title' => 'Property Region',
        'pages' => array('property_region'),
        'context' => 'normal',
        'fields' => array(),
        'local_images' => false,
        'use_with_theme' => false
    );

    $taxnow = isset($_REQUEST['taxonomy'])? $_REQUEST['taxonomy'] : '';

    $prop_city_meta =  new Tax_Meta_Class( $prop_city );
    $prop_city_meta->addImage('fave_prop_type_image',array('name'=> __('Thumbnail ','houzez')));

    if ($taxnow == 'property_region') {  
        $prop_city_meta->check_field_upload();
        $prop_city_meta->check_field_color();
        $prop_city_meta->check_field_date();
        $prop_city_meta->check_field_time();

        $plugin_path = plugins_url('houzez-theme-functionality/extensions/Tax-meta-class');
      
        wp_enqueue_style( 'tax-meta-clss', $plugin_path . '/css/Tax-meta-class.css' );

        wp_enqueue_script( 'tax-meta-clss', $plugin_path . '/js/tax-meta-clss.js', array( 'jquery' ), null, true );

    }
}

if ( !function_exists( 'houzez_get_property_lifestyle_meta' ) ):
    function houzez_get_property_lifestyle_meta( $term_id = false, $field = false ) {
        $defaults = array(
            'color_type' => 'inherit',
            'color' => '#bcbcbc',
            'ppp' => ''
        );

        if ( $term_id ) {
            $meta = get_option( '_houzez_property_lifestyle_'.$term_id );
            $meta = wp_parse_args( (array) $meta, $defaults );
        } else {
            $meta = $defaults;
        }

        if ( $field ) {
            if ( isset( $meta[$field] ) ) {
                return $meta[$field];
            } else {
                return false;
            }
        }
        return $meta;
    }
endif;

if ( !function_exists( 'houzez_property_lifestyle_add_meta_fields' ) ) :
    function houzez_property_lifestyle_add_meta_fields() {
        $houzez_meta = houzez_get_property_lifestyle_meta();
        ?>

        <div class="form-field">
            <label for="Color"><?php _e( 'Global Color', 'houzez'); ?></label><br/>
            <label><input type="radio" name="fave[color_type]" value="inherit" class="fave-radio color-type" <?php checked( $houzez_meta['color_type'], 'inherit' );?>> <?php _e( 'Inherit from default accent color', 'houzez' ); ?></label>
            <label><input type="radio" name="fave[color_type]" value="custom" class="fave-radio color-type" <?php checked( $houzez_meta['color_type'], 'custom' );?>> <?php _e( 'Custom', 'houzez' ); ?></label>
            <div id="fave_color_wrap">
                <p>
                    <input name="fave[color]" type="text" class="fave_colorpicker" value="<?php echo $houzez_meta['color']; ?>" data-default-color="<?php echo $houzez_meta['color']; ?>"/>
                </p>
                <?php if ( !empty( $colors ) ) { echo $colors; } ?>
            </div>
            <div class="clear"></div>
            <p class="howto"><?php _e( 'Choose color', 'houzez' ); ?></p>
        </div>

        <?php
    }
endif;

add_action( 'property_lifestyle_add_form_fields', 'houzez_property_lifestyle_add_meta_fields', 10, 2 );

if ( !function_exists( 'houzez_property_lifestyle_edit_meta_fields' ) ) :
    function houzez_property_lifestyle_edit_meta_fields( $term ) {
        $houzez_meta = houzez_get_property_lifestyle_meta( $term->term_id );
        ?>
        <?php

        $most_used = get_option( 'houzez_recent_colors' );

        $colors = '';

        if ( !empty( $most_used ) ) {
            $colors .= '<p>'.__( 'Recently used', 'houzez' ).': <br/>';
            foreach ( $most_used as $color ) {
                $colors .= '<a href="#" style="width: 20px; height: 20px; background: '.$color.'; float: left; margin-right:3px; border: 1px solid #aaa;" class="fave_colorpick" data-color="'.$color.'"></a>';
            }
            $colors .= '</p>';
        }

        ?>

        <tr class="form-field">
            <th scope="row" valign="top"><label><?php _e( 'Color', 'houzez' ); ?></label></th>
            <td>
                <label><input type="radio" name="fave[color_type]" value="inherit" class="fave-radio color-type" <?php checked( $houzez_meta['color_type'], 'inherit' );?>> <?php _e( 'Inherit from default accent color', 'houzez' ); ?></label> <br/>
                <label><input type="radio" name="fave[color_type]" value="custom" class="fave-radio color-type" <?php checked( $houzez_meta['color_type'], 'custom' );?>> <?php _e( 'Custom', 'houzez' ); ?></label>
                <div id="fave_color_wrap">
                    <p>
                        <input name="fave[color]" type="text" class="fave_colorpicker" value="<?php echo $houzez_meta['color']; ?>" data-default-color="<?php echo $houzez_meta['color']; ?>"/>
                    </p>
                    <?php if ( !empty( $colors ) ) { echo $colors; } ?>
                </div>
                <div class="clear"></div>
                <p class="howto"><?php _e( 'Choose color', 'houzez' ); ?></p>
            </td>
        </tr>

        <?php
    }
endif;

add_action( 'property_lifestyle_edit_form_fields', 'houzez_property_lifestyle_edit_meta_fields', 10, 2 );


if ( !function_exists( 'houzez_save_property_lifestyle_meta_fields' ) ) :
    function houzez_save_property_lifestyle_meta_fields( $term_id ) {

        if ( isset( $_POST['fave'] ) ) {

            $houzez_meta = array();

            $houzez_meta['color'] = isset( $_POST['fave']['color'] ) ? $_POST['fave']['color'] : 0;
            $houzez_meta['color_type'] = isset( $_POST['fave']['color_type'] ) ? $_POST['fave']['color_type'] : 0;

            update_option( '_houzez_property_lifestyle_'.$term_id, $houzez_meta );

            if ( $houzez_meta['color_type'] == 'custom' ) {
                houzez_update_recent_colors( $houzez_meta['color'] );
            }

            houzez_update_property_lifestyle_colors( $term_id, $houzez_meta['color'], $houzez_meta['color_type'] );
        }

    }
endif;

add_action( 'edited_property_lifestyle', 'houzez_save_property_lifestyle_meta_fields', 10, 2 );
add_action( 'create_property_lifestyle', 'houzez_save_property_lifestyle_meta_fields', 10, 2 );

if ( !function_exists( 'houzez_update_property_lifestyle_colors' ) ):
    function houzez_update_property_lifestyle_colors( $cat_id, $color, $type ) {

        $colors = (array)get_option( 'fave_lifestyle_colors' );

        if ( array_key_exists( $cat_id, $colors ) ) {

            if ( $type == 'inherit' ) {
                unset( $colors[$cat_id] );
            } elseif ( $colors[$cat_id] != $color ) {
                $colors[$cat_id] = $color;
            }

        } else {

            if ( $type != 'inherit' ) {
                $colors[$cat_id] = $color;
            }
        }

        update_option( 'houzez_property_lifestyle_colors', $colors );

    }
endif;

if ( !function_exists( 'houzez_get_property_region_meta' ) ):
    function houzez_get_property_region_meta( $term_id = false, $field = false ) {
        $defaults = array(
            'color_type' => 'inherit',
            'color' => '#bcbcbc',
            'ppp' => ''
        );

        if ( $term_id ) {
            $meta = get_option( '_houzez_property_region_'.$term_id );
            $meta = wp_parse_args( (array) $meta, $defaults );
        } else {
            $meta = $defaults;
        }

        if ( $field ) {
            if ( isset( $meta[$field] ) ) {
                return $meta[$field];
            } else {
                return false;
            }
        }
        return $meta;
    }
endif;

if ( !function_exists( 'houzez_property_region_add_meta_fields' ) ) :
    function houzez_property_region_add_meta_fields() {
        $houzez_meta = houzez_get_property_region_meta();
        ?>

        <div class="form-field">
            <label for="Color"><?php _e( 'Global Color', 'houzez'); ?></label><br/>
            <label><input type="radio" name="fave[color_type]" value="inherit" class="fave-radio color-type" <?php checked( $houzez_meta['color_type'], 'inherit' );?>> <?php _e( 'Inherit from default accent color', 'houzez' ); ?></label>
            <label><input type="radio" name="fave[color_type]" value="custom" class="fave-radio color-type" <?php checked( $houzez_meta['color_type'], 'custom' );?>> <?php _e( 'Custom', 'houzez' ); ?></label>
            <div id="fave_color_wrap">
                <p>
                    <input name="fave[color]" type="text" class="fave_colorpicker" value="<?php echo $houzez_meta['color']; ?>" data-default-color="<?php echo $houzez_meta['color']; ?>"/>
                </p>
                <?php if ( !empty( $colors ) ) { echo $colors; } ?>
            </div>
            <div class="clear"></div>
            <p class="howto"><?php _e( 'Choose color', 'houzez' ); ?></p>
        </div>

        <?php
    }
endif;

add_action( 'property_region_add_form_fields', 'houzez_property_region_add_meta_fields', 10, 2 );

if ( !function_exists( 'houzez_property_region_edit_meta_fields' ) ) :
    function houzez_property_region_edit_meta_fields( $term ) {
        $houzez_meta = houzez_get_property_region_meta( $term->term_id );
        ?>
        <?php

        $most_used = get_option( 'houzez_recent_colors' );

        $colors = '';

        if ( !empty( $most_used ) ) {
            $colors .= '<p>'.__( 'Recently used', 'houzez' ).': <br/>';
            foreach ( $most_used as $color ) {
                $colors .= '<a href="#" style="width: 20px; height: 20px; background: '.$color.'; float: left; margin-right:3px; border: 1px solid #aaa;" class="fave_colorpick" data-color="'.$color.'"></a>';
            }
            $colors .= '</p>';
        }

        ?>

        <tr class="form-field">
            <th scope="row" valign="top"><label><?php _e( 'Color', 'houzez' ); ?></label></th>
            <td>
                <label><input type="radio" name="fave[color_type]" value="inherit" class="fave-radio color-type" <?php checked( $houzez_meta['color_type'], 'inherit' );?>> <?php _e( 'Inherit from default accent color', 'houzez' ); ?></label> <br/>
                <label><input type="radio" name="fave[color_type]" value="custom" class="fave-radio color-type" <?php checked( $houzez_meta['color_type'], 'custom' );?>> <?php _e( 'Custom', 'houzez' ); ?></label>
                <div id="fave_color_wrap">
                    <p>
                        <input name="fave[color]" type="text" class="fave_colorpicker" value="<?php echo $houzez_meta['color']; ?>" data-default-color="<?php echo $houzez_meta['color']; ?>"/>
                    </p>
                    <?php if ( !empty( $colors ) ) { echo $colors; } ?>
                </div>
                <div class="clear"></div>
                <p class="howto"><?php _e( 'Choose color', 'houzez' ); ?></p>
            </td>
        </tr>

        <?php
    }
endif;

add_action( 'property_region_edit_form_fields', 'houzez_property_region_edit_meta_fields', 10, 2 );


if ( !function_exists( 'houzez_save_property_region_meta_fields' ) ) :
    function houzez_save_property_region_meta_fields( $term_id ) {

        if ( isset( $_POST['fave'] ) ) {

            $houzez_meta = array();

            $houzez_meta['color'] = isset( $_POST['fave']['color'] ) ? $_POST['fave']['color'] : 0;
            $houzez_meta['color_type'] = isset( $_POST['fave']['color_type'] ) ? $_POST['fave']['color_type'] : 0;

            update_option( '_houzez_property_region_'.$term_id, $houzez_meta );

            if ( $houzez_meta['color_type'] == 'custom' ) {
                houzez_update_recent_colors( $houzez_meta['color'] );
            }

            houzez_update_property_region_colors( $term_id, $houzez_meta['color'], $houzez_meta['color_type'] );
        }

    }
endif;

add_action( 'edited_property_region', 'houzez_save_property_region_meta_fields', 10, 2 );
add_action( 'create_property_region', 'houzez_save_property_region_meta_fields', 10, 2 );

if ( !function_exists( 'houzez_update_property_region_colors' ) ):
    function houzez_update_property_region_colors( $cat_id, $color, $type ) {

        $colors = (array)get_option( 'fave_region_colors' );

        if ( array_key_exists( $cat_id, $colors ) ) {

            if ( $type == 'inherit' ) {
                unset( $colors[$cat_id] );
            } elseif ( $colors[$cat_id] != $color ) {
                $colors[$cat_id] = $color;
            }

        } else {

            if ( $type != 'inherit' ) {
                $colors[$cat_id] = $color;
            }
        }

        update_option( 'houzez_property_region_colors', $colors );

    }
endif;

if ( ! function_exists( 'HOUZEZ_property_taxonomies_remove' ) ) {
    function HOUZEZ_property_taxonomies_remove (){
        unregister_widget( 'HOUZEZ_property_taxonomies' );
    }
    add_action( 'widgets_init', 'HOUZEZ_property_taxonomies_remove', 11 );

    require_once( get_stylesheet_directory(). '/houzez-property-taxonomies.php' );
}

function houzez_custom_menu_order() {
    global $submenu;

    $i = 0;
    $features = 0;
    $lifestyles = 0;
    $order = array();

    foreach ($submenu['edit.php?post_type=property'] as $item) {
        array_push($order, $item);

        if ($item[0] == 'Features')
            $features = $i;

        if ($item[0] == 'Lifestyles')
            $lifestyles = $i;

        $i++;
    }

    $lifestyle = $order[$lifestyles];

    for ($i = $lifestyles; $i > $features; $i--) {
        $order[$i] = $order[$i - 1];
    }

    $order[$features + 1] = $lifestyle;
    
    $submenu['edit.php?post_type=property'] = $order;
}

add_filter( 'custom_menu_order', 'houzez_custom_menu_order' );
add_filter( 'menu_order', 'houzez_custom_menu_order' );

/**
 *  Property Addon
 */
if ( !function_exists('houzez_property_addon') ) {
    function houzez_property_addon($atts, $content = null)
    {
        extract(shortcode_atts(array(
            'hz_limit_post_number' => '',
            'hz_select_addon' => ''
        ), $atts));

        ob_start();

        global $paged;
        if (is_front_page()) {
            $paged = (get_query_var('page')) ? get_query_var('page') : 1;
        }

        if ($atts['hz_select_addon'] == 'fave_week') {
            $css_classes = 'list-view';

            $args = array(
                'orderby' => 'rand',
                'post_status' => 'publish',
                'post_type' => 'property',
                'posts_per_page' => $atts['hz_limit_post_number'],
                'meta_key' => $atts['hz_select_addon'],
                'meta_value' => 1,
                'meta_compare' => '='
            );
        }

        if ($atts['hz_select_addon'] == 'fave_featured') {
            $css_classes = 'grid-view';

            $args = array(
                'order' => 'DESC',
                'orderby' => 'id',
                'post_status' => 'publish',
                'post_type' => 'property',
                'posts_per_page' => $atts['hz_limit_post_number'],
                'meta_key' => $atts['hz_select_addon'],
                'meta_value' => 1,
                'meta_compare' => '='
            );
        }

        $the_query = new WP_Query($args);
        ?>

        <div id="properties_module_section" class="houzez-module property-item-module">
            <div id="properties_module_container">
                <div id="module_properties" class="property-listing <?php echo esc_attr($css_classes);?>">

                    <?php
                        if ($the_query->have_posts()) :
                            while ($the_query->have_posts()) : $the_query->the_post();
                                get_template_part('template-parts/property-for-addon');
                            endwhile;

                            wp_reset_postdata();
                        else:
                            get_template_part('template-parts/property', 'none');
                        endif;
                    ?>

                </div>
            </div>
        </div>

        <?php
        $result = ob_get_contents();
        ob_end_clean();
        return $result;

    }

    add_shortcode('houzez-property_addon', 'houzez_property_addon');
}

vc_map( array(
    "name"  =>  esc_html__( "Property Addon", "houzez" ),
    "description"           => '',
    "base"                  => "houzez-property_addon",
    'category'              => "By Favethemes",
    "class"                 => "",
    'admin_enqueue_js'      => "",
    'admin_enqueue_css'     => "",
    "icon"                  => "icon-addon-settings",
    "params"                => array(
        array(
            "param_name" => "hz_limit_post_number",
            "type" => "textfield",
            "value" => '',
            "heading" => esc_html__("Limit post number:", "houzez" ),
            "description" => esc_html__( "Enter limit post number", "houzez" ),
            "save_always" => true
        ),
        array(
            "param_name" => "hz_select_addon",
            "type" => "dropdown",
            "value" => array( 'Featured Listing' => 'fave_featured', 'Property of the week' => 'fave_week' ),
            "heading" => esc_html__("Select Property Add On", "houzez" ),
            "save_always" => true
        ),
    )
) );

/**
 * Properties sort by features
 */
add_action('init', 'remove_parent_theme_shortcodes');

function remove_parent_theme_shortcodes() {
    remove_shortcode('houzez-properties');

    add_shortcode('houzez-properties', 'houzez_properties_sort');
}

function houzez_properties_sort($atts, $content = null)
{
    extract(shortcode_atts(array(
        'prop_grid_style' => '',
        'module_type' => '',
        'property_type' => '',
        'property_status' => '',
        'property_state' => '',
        'property_city' => '',
        'property_area' => '',
        'property_label' => '',
        'houzez_user_role' => '',
        'featured_prop' => '',
        'posts_limit' => '',
        'sort_by' => '',
        'offset' => ''
    ), $atts));

    ob_start();

    global $paged;
    if (is_front_page()) {
        $paged = (get_query_var('page')) ? get_query_var('page') : 1;
    }

    if( $module_type == "grid_3_cols" ) {
        $css_classes = "grid-view grid-view-3-col";
    } elseif( $module_type == "grid_2_cols" ) {
        $css_classes = "grid-view";
    } elseif( $module_type == "list" ) {
        $css_classes = "list-view";
    } else {
        $css_classes = "grid-view grid-view-3-col";
    }

    $arr = array();
    $featured = array();
    $week = array();
    $normal = array();

    $the_query = houzez_data_source::get_wp_query($atts, $paged);

    if ($the_query->have_posts()) :
        while ($the_query->have_posts()) : $the_query->the_post();
            $prop_featured = get_post_meta( get_the_ID(), 'fave_featured', true );
            $prop_week     = get_post_meta( get_the_ID(), 'fave_week', true );

            if ($prop_featured == 1)
                array_push($featured, get_the_ID());
            else if ($prop_week == 1)
                array_push($week, get_the_ID());
            else
                array_push($normal, get_the_ID());
        endwhile;

        $arr = array_merge($week, $featured, $normal);
        
        wp_reset_postdata();
    endif;

    if (sizeof($arr) < $atts['posts_limit']) {
        $diff = $atts['posts_limit'] - sizeof($arr);

        global $wpdb;

        $enProps = array();

        for ($i = 0; $i < sizeof($arr); $i++)
            array_push($enProps, icl_object_id( $arr[$i], 'property', false, 'en'));

        $lang = substr(home_url(), strlen(get_site_url()) + 7);

        $langProps = array();

        $properties = $wpdb->get_results( "
                SELECT id FROM wp_posts WHERE post_type = 'property' AND post_status = 'publish' ORDER BY id DESC" );

        foreach ($properties as $property) {
            if ($lang == 'de')
                $result = icl_object_id($property->id, 'property', false, 'es');

            if ($lang == 'es')
                $result = icl_object_id($property->id, 'property', false, 'de');

            if (!is_null($result) && !in_array($result, $langProps))
                array_push($langProps, $result);
        }

        $langProps = array_merge($langProps, $enProps);

        foreach ($properties as $property) {
            if ($diff > 0 && !in_array($property->id, $arr) && !in_array($property->id, $langProps)) {
                $prop_featured = get_post_meta( $property->id, 'fave_featured', true );
                $prop_week     = get_post_meta( $property->id, 'fave_week', true );

                if ($prop_featured == 1)
                    array_push($featured, $property->id);
                else if ($prop_week == 1)
                    array_push($week, $property->id);
                else
                    array_push($normal, $property->id);

                $diff--;
            }
        }

        $arr = array_merge($week, $featured, $normal);
    }

    ?>
    <div id="properties_module_section" class="houzez-module property-item-module">
        <div id="properties_module_container">
            <div id="module_properties" class="property-listing <?php echo esc_attr($css_classes);?>">

                <?php
                if( $prop_grid_style == "v_2" ) {
                    for ($i = 0; $i < sizeof($arr); $i++) {
                        $args = array('post_type' => 'property', 'p'=> $arr[$i]);

                        $the_query = new WP_Query( $args );

                        if ($the_query->have_posts()) :
                            while ($the_query->have_posts()) : $the_query->the_post();

                                get_template_part('template-parts/property-for-listing-v2');

                            endwhile;
                        else:
                            $prop_featured = get_post_meta( $arr[$i], 'fave_featured', true );
                            $prop_week     = get_post_meta( $arr[$i], 'fave_week', true );

                            $disable_favorite = houzez_option('disable_favorite');
                            $disable_photo_count = houzez_option('disable_photo_count');
                ?>
                <div id="ID-<?php echo $arr[$i]; ?>" class="item-wrap infobox_trigger prop_addon">
                    <div class="property-item-v2">
                        <div class="figure-block">
                            <figure class="item-thumb">

                                <?php if( $prop_featured == 1 ) { ?>
                                    <span class="label-featured label">
                                        <?php echo esc_html__( 'Featured', 'houzez' ); ?>
                                    </span>
                                <?php } ?>
                                <?php if( $prop_week == 1 ) { ?>
                                    <span class="label-week label">
                                        <?php echo esc_html__( 'Property of the Week', 'houzez' ); ?>
                                    </span>
                                <?php } ?>

                                <div class="label-wrap label-right">
                                <?php
                                    $term_id = '';
                                    $term_status = wp_get_post_terms( $arr[$i], 'property_status', array("fields" => "all"));
                                    $label_id = '';
                                    $term_label = wp_get_post_terms( $arr[$i], 'property_label', array("fields" => "all"));

                                    if( !empty($term_status) ) {
                                        foreach( $term_status as $status ) {
                                            $status_id = $status->term_id;
                                            $status_name = $status->name;
                                            echo '<span class="label-status label-status-'.intval($status_id).' label label-default"><a href="'.get_term_link($status_id).'">'.esc_attr($status_name).'</a></span>';
                                        }
                                    }

                                    if( !empty($term_label) ) {
                                        foreach( $term_label as $label ) {
                                            $label_id = $label->term_id;
                                            $label_name = $label->name;
                                            echo '<span class="label label-default label-color-'.intval($label_id).'"><a href="'.get_term_link($label_id).'">'.esc_attr($label_name).'</a></span>';
                                        }
                                    }
                                ?>
                                </div>

                                <a href="<?php echo get_permalink($arr[$i]); ?>" class="hover-effect">
                                    <?php
                                    if( has_post_thumbnail( $arr[$i] ) ) {
                                        echo get_the_post_thumbnail( $arr[$i], 'houzez-property-thumb-image-v2' );
                                    }else{
                                        houzez_image_placeholder( 'houzez-property-thumb-image-v2' );
                                    }
                                    ?>
                                </a>

                                <ul class="actions">

                                    <?php if( $disable_favorite != 0 ) { ?>
                                    <li>
                                        <span class="add_fav" data-placement="top" data-toggle="tooltip" data-original-title="<?php esc_html_e('Favorite', 'houzez'); ?>" data-propid="<?php echo intval( $post->ID ); ?>">
                                            <i class="fa fa-heart"></i>
                                        </span>
                                    </li>
                                    <?php } ?>

                                    <?php if( $disable_photo_count != 0 ) { ?>
                                    <li>
                                        <span data-toggle="tooltip" data-placement="top" title="(<?php echo count( $prop_images ); ?>) <?php echo $houzez_local['photos']; ?>">
                                            <i class="fa fa-camera"></i>
                                        </span>
                                    </li>
                                    <?php } ?>
                                </ul>
                            </figure>
                        </div>
                        <div class="item-body">
                            <div class="item-detail">
                                <p>
                                <?php
                                    echo substr( get_post_field('post_content', $arr[$i]), 0, 110 ); 

                                    if (strlen(get_post_field('post_content', $arr[$i])) > 110)
                                        echo ' ...';
                                ?>
                                </p>
                            </div>

                            <div class="item-title">
                                <?php
                                    echo '<h2 class="property-title">'. esc_attr( get_the_title($arr[$i]) ). '</h2>';
                                ?>
                            </div>

                            <div class="item-info">
                                <?php 
                                    $prop_bed = get_post_meta( $arr[$i], 'fave_property_bedrooms', true );
                                    $prop_bath = get_post_meta( $arr[$i], 'fave_property_bathrooms', true );
                                    $prop_size = get_post_meta( $arr[$i], 'fave_property_size', true );

                                    if (empty($prop_bed)) $prop_bed = 0;
                                    if (empty($prop_bath)) $prop_bath = 0;
                                    if (empty($prop_size)) $prop_size = 0;
                                ?>
                                <ul class="item-amenities">
                                    <li>
                                        <img src="<?php echo get_stylesheet_directory_uri(); ?>/icons/rooms.png">
                                        <span><?php echo $prop_bed; ?></span>
                                    </li>
                                    <li>
                                        <img src="<?php echo get_stylesheet_directory_uri(); ?>/icons/bathtub.png">
                                        <span><?php echo $prop_bath; ?></span>
                                    </li>
                                    <li>
                                        <img src="<?php echo get_stylesheet_directory_uri(); ?>/icons/house.png">
                                        <span><?php echo $prop_size; ?> m²</span>
                                    </li>
                                    <li>
                                        <a href="<?php echo esc_url( get_permalink($arr[$i]) ); ?>" class="btn btn btn-primary">
                                            <?php echo esc_html__( 'Details >', 'houzez' ); ?>
                                        </a>
                                    </li>
                                </ul>
                            </div>

                             <div class="item-price-block">
                                <span class="item-price">
                                <?php
                                    global $wpdb;

                                    $currency_code = get_post_meta( $arr[$i], 'fave_currency', true);

                                    $result = $wpdb->get_results(" SELECT currency_symbol FROM " . $wpdb->prefix . "houzez_currencies where currency_code='$currency_code'");

                                    if (sizeof($result) > 0)
                                        $symbol = $result[0]->currency_symbol;
                                    else
                                        $symbol = '€';

                                    $sale_price = get_post_meta( $arr[$i], 'fave_property_price', true );
                                    $sale_price = number_format( $sale_price , 0, '', ',' );
                                    
                                    $status = get_the_terms( $arr[$i], 'property_status' );
                                    
                                    if ($status[0]->slug == 'for-rent')
                                        echo $symbol . $sale_price . '/mo';
                                    else
                                        echo $symbol . $sale_price;
                                ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
                        endif;
                    }
                } else {
                    if ($the_query->have_posts()) :
                        while ($the_query->have_posts()) : $the_query->the_post();

                            get_template_part('template-parts/property-for-listing');

                        endwhile;
                        wp_reset_postdata();
                    else:
                        get_template_part('template-parts/property', 'none');
                    endif;
                }
                ?>

            </div>
            <!-- end container-content -->
        </div>
        <div class="clearfix"></div>
        <div id="fave-pagination-loadmore" class="pagination-wrap fave-load-more">
            <div class="pagination">
                <a 
                data-paged="2" 
                data-prop-limit="<?php esc_attr_e($posts_limit); ?>" 
                data-grid-style="<?php esc_attr_e($prop_grid_style); ?>" 
                data-type="<?php esc_attr_e($property_type); ?>" 
                data-status="<?php esc_attr_e($property_status); ?>" 
                data-state="<?php esc_attr_e($property_state); ?>" 
                data-city="<?php esc_attr_e($property_city); ?>" 
                data-area="<?php esc_attr_e($property_area); ?>" 
                data-label="<?php esc_attr_e($property_label); ?>" 
                data-user-role="<?php esc_attr_e($houzez_user_role); ?>" 
                data-featured-prop="<?php esc_attr_e($featured_prop); ?>" 
                data-offset="<?php esc_attr_e($offset); ?>"
                data-sortby="<?php esc_attr_e($sort_by); ?>"
                href="#">
                    <?php esc_html_e('Load More', 'houzez'); ?>     
                </a>               
            </div>
        </div>
    </div>

    <?php
    $result = ob_get_contents();
    ob_end_clean();
    return $result;

}

/**
 *  Add Regions to Houzez Grids
 */
vc_remove_element('hz-grids');

$houzez_grids_tax = array();

if (function_exists('vc_remove_param'))
    vc_remove_param('vc_row', 'font_color');
    
$houzez_grids_tax['Property Types'] = 'property_type';
$houzez_grids_tax['Property Status'] = 'property_status';
$houzez_grids_tax['Property Region'] = 'property_region';
$houzez_grids_tax['Property State'] = 'property_state';
$houzez_grids_tax['Property City'] = 'property_city';
$houzez_grids_tax['Property Neighborhood'] = 'property_area';

if( !function_exists('houzez_grid_update') ) {
    function houzez_grid_update($atts, $content = null)
    {
        extract(shortcode_atts(array(
            'houzez_grid_type' => '',
            'houzez_grid_from' => '',
            'houzez_show_child' => '',
            'orderby'           => '',
            'order'             => '',
            'houzez_hide_empty' => '',
            'no_of_terms'       => '',
            'property_type' => '',
            'property_status' => '',
            'property_area' => '',
            'property_state' => '',
            'property_city' => '',
            'property_region' => ''
        ), $atts));

        ob_start();
        $module_type = '';
        $houzez_local = houzez_get_localization();

        $slugs = '';

        if( $houzez_grid_from == 'property_city' ) {
            $slugs = $property_city;

        } else if ( $houzez_grid_from == 'property_area' ) {
            $slugs = $property_area;

        } else if ( $houzez_grid_from == 'property_region' ) {
            $slugs = $property_region;

        } else if ( $houzez_grid_from == 'property_state' ) {
            $slugs = $property_state;

        } else if ( $houzez_grid_from == 'property_status' ) {
            $slugs = $property_status;

        } else {
            $slugs = $property_type;
        }

        if ($houzez_show_child == 1) {
            $houzez_show_child = '';
        }
        if ($houzez_grid_type == 'grid_v2') {
            $module_type = 'location-module-v2';
        }

        if( $houzez_grid_from == 'property_type' ) {
            $custom_link_for = 'fave_prop_type_custom_link';
        } else {
            $custom_link_for = 'fave_prop_taxonomy_custom_link';
        }
        ?>
        <div id="location-module"
             class="houzez-module location-module <?php echo esc_attr( $module_type ); ?> grid <?php echo esc_attr( $houzez_grid_type ); ?>">
            <div class="row">
                <?php
                $tax_name = $houzez_grid_from;
                $taxonomy = get_terms(array(
                    'hide_empty' => $houzez_hide_empty,
                    'parent' => $houzez_show_child,
                    'slug' => houzez_traverse_comma_string($slugs),
                    'number' => $no_of_terms,
                    'orderby' => $orderby,
                    'order' => $order,
                    'taxonomy' => $tax_name,
                ));
                $i = 0;
                $j = 0;
                if ( !is_wp_error( $taxonomy ) ) {
                
                    foreach ($taxonomy as $term) {

                        $i++;
                        $j++;

                        if ($houzez_grid_type == 'grid_v1') {
                            if ($i == 1 || $i == 4) {
                                $col = 'col-sm-4';
                            } else {
                                $col = 'col-sm-8';
                            }
                            if ($i == 4) {
                                $i = 0;
                            }
                        } elseif ($houzez_grid_type == 'grid_v2') {
                            $col = 'col-sm-4';
                        }

                        $term_img = get_tax_meta($term->term_id, 'fave_prop_type_image');
                        $taxonomy_custom_link = get_tax_meta($term->term_id, $custom_link_for);

                        if( !empty($taxonomy_custom_link) ) {
                            $term_link = $taxonomy_custom_link;
                        } else {
                            $term_link = get_term_link($term, $tax_name);
                        }

                        ?>
                        <div class="<?php echo esc_attr($col); ?>">
                            <div class="location-block" <?php if (!empty($term_img['src'])) {
                                echo 'style="background-image: url(' . esc_url($term_img['src']) . ');"';
                            } ?>>
                                <a href="<?php echo esc_url($term_link); ?>">
                                    <div class="location-fig-caption">
                                        <h3 class="heading"><?php echo esc_attr($term->name); ?></h3>

                                        <p class="sub-heading">
                                            <?php echo esc_attr($term->count); ?>
                                            <?php
                                            if ($term->count < 2) {
                                                echo $houzez_local['property'];
                                            } else {
                                                echo $houzez_local['properties'];
                                            }
                                            ?>
                                        </p>
                                    </div>
                                </a>
                            </div>
                        </div>
                        <?php
                    }
                }
                ?>
            </div>
        </div>
        <?php
        $result = ob_get_contents();
        ob_end_clean();
        return $result;

    }

    add_shortcode('hz-grids-update', 'houzez_grid_update');
}

vc_map( array(
    "name"  =>  esc_html__( "Houzez Grids", "houzez" ),
    "description"           => 'Show Locations, Property Types, Cities, States in grid',
    "base"                  => "hz-grids-update",
    'category'              => "By Favethemes",
    "class"                 => "",
    'admin_enqueue_js'      => "",
    'admin_enqueue_css'     => "",
    "icon"                  => "icon-hz-grid",
    "params"                => array(

        array(
            "param_name" => "houzez_grid_type",
            "type" => "dropdown",
            "value" => array( 'Grid v1' => 'grid_v1', 'Grid v2' => 'grid_v2' ),
            "heading" => esc_html__("Choose Grid:", "houzez" ),
            "save_always" => true
        ),
        array(
            "param_name" => "houzez_grid_from",
            "type" => "dropdown",
            "value" => $houzez_grids_tax,
            "heading" => esc_html__("Choose Taxonomy", "houzez" ),
            "save_always" => true
        ),
        array(
            'type'          => 'houzez_get_taxonomy_list',
            'heading'       => esc_html__("Property Types", "houzez"),
            'taxonomy'      => 'property_type',
            'is_multiple'   => true,
            'is_hide_empty'   => false,
            'description'   => '',
            'param_name'    => 'property_type',
            "dependency" => Array("element" => "houzez_grid_from", "value" => array("property_type")),
            'save_always'   => true,
            'std'           => '',
        ),
        array(
            'type'          => 'houzez_get_taxonomy_list',
            'heading'       => esc_html__("Property Status", "houzez"),
            'taxonomy'      => 'property_status',
            'is_multiple'   => true,
            'is_hide_empty'   => false,
            'description'   => '',
            'param_name'    => 'property_status',
            "dependency" => Array("element" => "houzez_grid_from", "value" => array("property_status")),
            'save_always'   => true,
            'std'           => '',
        ),
        array(
            'type'          => 'houzez_get_taxonomy_list',
            'heading'       => esc_html__("Property Regions", "houzez"),
            'taxonomy'      => 'property_region',
            'is_multiple'   => true,
            'is_hide_empty'   => false,
            'description'   => '',
            'param_name'    => 'property_region',
            "dependency" => Array("element" => "houzez_grid_from", "value" => array("property_region")),
            'save_always'   => true,
            'std'           => '',
        ),
        array(
            'type'          => 'houzez_get_taxonomy_list',
            'heading'       => esc_html__("Property States", "houzez"),
            'taxonomy'      => 'property_state',
            'is_multiple'   => true,
            'is_hide_empty'   => false,
            'description'   => '',
            'param_name'    => 'property_state',
            "dependency" => Array("element" => "houzez_grid_from", "value" => array("property_state")),
            'save_always'   => true,
            'std'           => '',
        ),
        array(
            'type'          => 'houzez_get_taxonomy_list',
            'heading'       => esc_html__("Property Cities", "houzez"),
            'taxonomy'      => 'property_city',
            'is_multiple'   => true,
            'is_hide_empty'   => false,
            'description'   => '',
            'param_name'    => 'property_city',
            "dependency" => Array("element" => "houzez_grid_from", "value" => array("property_city")),
            'save_always'   => true,
            'std'           => '',
        ),

        array(
            'type'          => 'houzez_get_taxonomy_list',
            'heading'       => esc_html__("Property Areas", "houzez"),
            'taxonomy'      => 'property_area',
            'is_multiple'   => true,
            'is_hide_empty'   => false,
            'description'   => '',
            'param_name'    => 'property_area',
            "dependency" => Array("element" => "houzez_grid_from", "value" => array("property_area")),
            'save_always'   => true,
            'std'           => '',
        ),

        array(
            "param_name" => "houzez_show_child",
            "type" => "dropdown",
            "value" => array( 'No' => '0', 'Yes' => '1' ),
            "heading" => esc_html__("Show Child:", "houzez" ),
            "save_always" => true
        ),
        array(
            "param_name" => "orderby",
            "type" => "dropdown",
            "value" => array( 'Name' => 'name', 'Count' => 'count', 'ID' => 'id' ),
            "heading" => esc_html__("Order By:", "houzez" ),
            "save_always" => true
        ),
        array(
            "param_name" => "order",
            "type" => "dropdown",
            "value" => array( 'ASC' => 'ASC', 'DESC' => 'DESC' ),
            "heading" => esc_html__("Order:", "houzez" ),
            "save_always" => true
        ),
        array(
            "param_name" => "houzez_hide_empty",
            "type" => "dropdown",
            "value" => array( 'Yes' => '1', 'No' => '0' ),
            "heading" => esc_html__("Hide Empty:", "houzez" ),
            "save_always" => true
        ),
        array(
            "param_name" => "no_of_terms",
            "type" => "textfield",
            "value" => '',
            "heading" => esc_html__("Number of Items to Show:", "houzez" ),
            "save_always" => true
        )

    ) // end params
) );

/*
 * Widget Name: Property Add On: Property of the week
 */

function widget_content($args, $instance, $type) {
    global $before_widget, $after_widget, $before_title, $after_title, $post;
    extract( $args );

    $allowed_html_array = array(
        'div' => array(
            'id' => array(),
            'class' => array()
        ),
        'h3' => array(
            'class' => array()
        )
    );

    $title = apply_filters('widget_title', $instance['title'] );
    $items_num = $instance['items_num'];
    $widget_type = $instance['widget_type'];
    
    echo wp_kses( $before_widget, $allowed_html_array );

    if ($title) 
        echo wp_kses( $before_title, $allowed_html_array ) . $title . wp_kses( $after_title, $allowed_html_array );

    $wp_qry = new WP_Query(
        array(
            'post_type' => 'property',
            'posts_per_page' => $items_num,
            'meta_key' => $type,
            'meta_value' => '1',
            'ignore_sticky_posts' => 1,
            'post_status' => 'publish',
            'orderby' => 'rand'
        )
    );
    ?>
    
    <div class="widget-body">

        <?php if( $widget_type == "slider" ) { ?>
        <div class="property-widget-slider slide-animated owl-carousel owl-theme">
        <?php } else { ?>
        <div class="item-wrap infobox_trigger prop_addon">
        <?php } ?>

        <?php if ($wp_qry->have_posts()): while($wp_qry->have_posts()): $wp_qry->the_post(); ?>
            <?php $prop_featured = get_post_meta( get_the_ID(), 'fave_featured', true ); ?>
            <?php $prop_week = get_post_meta( get_the_ID(), 'fave_week', true ); ?>            
            <?php $prop_images = get_post_meta( get_the_ID(), 'fave_property_images', false ); ?>

            <?php if( $widget_type == "slider" ) { ?>
                <div class="item">
                    <div class="figure-block">
                        <figure class="item-thumb">
                            <?php if( $prop_featured != 0 ) { ?>
                                <span class="label-featured label label-success">
                                    <?php esc_html_e( 'Featured', 'houzez' ); ?>
                                </span>
                            <?php } ?>
                            <?php if( $prop_week == 1 ) { ?>
                                <span class="label-week label">
                                    <?php echo esc_html__( 'Property of the Week', 'houzez' ); ?>
                                </span>
                            <?php } ?>
                            <div class="label-wrap label-right">
                                <?php get_template_part('template-parts/listing', 'status' ); ?>
                            </div>

                            <a href="<?php the_permalink() ?>" class="hover-effect">
                                <?php
                                if( has_post_thumbnail( $post->ID ) ) {
                                    the_post_thumbnail( 'houzez-property-thumb-image' );
                                }else{
                                    houzez_image_placeholder( 'houzez-property-thumb-image' );
                                }
                                ?>
                            </a>
                            <figcaption class="thumb-caption">
                                <div class="cap-price pull-left"><?php echo houzez_listing_price(); ?></div>
                                <ul class="list-unstyled actions pull-right">
                                    <li>
                                        <span title="" data-placement="top" data-toggle="tooltip" data-original-title="<?php echo count($prop_images); ?> <?php echo esc_html__('Photos', 'houzez'); ?>">
                                            <i class="fa fa-camera"></i>
                                        </span>
                                    </li>
                                </ul>
                            </figcaption>
                        </figure>
                    </div>
                </div>
            <?php } else { ?>
                <div class="figure-block">
                    <figure class="item-thumb">
                        <?php if( $prop_featured != 0 ) { ?>
                                <span class="label-featured label label-success">
                                    <?php esc_html_e( 'Featured', 'houzez' ); ?>
                                </span>
                            <?php } ?>
                            <?php if( $prop_week == 1 ) { ?>
                                <span class="label-week label">
                                    <?php echo esc_html__( 'Property of the Week', 'houzez' ); ?>
                                </span>
                            <?php } ?>
                        <div class="label-wrap label-right">
                            <?php get_template_part('template-parts/listing', 'status' ); ?>
                        </div>

                        <a href="<?php the_permalink() ?>" class="hover-effect">
                            <?php
                            if( has_post_thumbnail( $post->ID ) ) {
                                the_post_thumbnail( 'houzez-property-thumb-image' );
                            }else {
                                houzez_image_placeholder( 'houzez-property-thumb-image' );
                            }
                            ?>
                        </a>
                        <figcaption class="thumb-caption clearfix">
                            <div class="cap-price pull-left"><?php echo houzez_listing_price(); ?></div>

                            <ul class="list-unstyled actions pull-right">
                                <li>
                                    <span title="" data-placement="top" data-toggle="tooltip" data-original-title="<?php echo count($prop_images); ?> <?php echo esc_html__('Photos', 'houzez'); ?>">
                                        <i class="fa fa-camera"></i>
                                    </span>
                                </li>
                            </ul>
                        </figcaption>
                    </figure>
                </div>
                <div class="item-body">
                    <div class="item-detail">
                        <p><?php echo wp_trim_words( get_the_content(), 20 ); ?></p>
                    </div>

                    <div class="item-title">
                        <?php
                            echo '<h2 class="property-title">'. esc_attr( get_the_title() ). '</h2>';
                        ?>
                    </div>

                    <div class="item-info">
                        <?php 
                            $propID = get_the_ID();
                            $prop_bed     = get_post_meta( get_the_ID(), 'fave_property_bedrooms', true );
                            $prop_bath     = get_post_meta( get_the_ID(), 'fave_property_bathrooms', true );
                            $prop_size     = get_post_meta( $propID, 'fave_property_size', true );

                            if (empty($prop_bed)) $prop_bed = 0;
                            if (empty($prop_bath)) $prop_bath = 0;
                            if (empty($prop_size)) $prop_size = 0;
                        ?>
                        <ul class="item-amenities">
                            <li>
                                <img src="<?php echo get_stylesheet_directory_uri(); ?>/icons/rooms.png">
                                <span><?php echo $prop_bed; ?></span>
                            </li>
                            <li>
                                <img src="<?php echo get_stylesheet_directory_uri(); ?>/icons/bathtub.png">
                                <span><?php echo $prop_bath; ?></span>
                            </li>
                            <li>
                                <img src="<?php echo get_stylesheet_directory_uri(); ?>/icons/house.png">
                                <span><?php echo $prop_size; ?> m²</span>
                            </li>
                            <li>
                                <a href="<?php echo esc_url( get_permalink() ); ?>" class="btn btn-primary btn-block">
                                    <?php echo esc_html__( 'Details >', 'houzez' ); ?>
                                </a>
                            </li>
                        </ul>
                    </div>

                     <div class="item-price-block">
                        <span class="item-price">
                            <?php echo houzez_listing_price_v1(); ?>
                        </span>
                    </div>
                </div>
            <?php } ?>
        <?php endwhile; endif; ?>

        </div>
        <?php wp_reset_postdata(); ?>
        
    </div>


<?php 
    echo wp_kses( $after_widget, $allowed_html_array );
}
 
class HOUZEZ_property_week extends WP_Widget {
    /**
     * Register widget
    **/
    public function __construct() {
        
        parent::__construct(
            'houzez_property_week', // Base ID
            esc_html__( 'HOUZEZ: Property Add On: Property of the Week', 'houzez' ), // Name
            array( 'description' => esc_html__( 'Show property of the week', 'houzez' ), ) // Args
        );
        
    }
    /**
     * Front-end display of widget
    **/
    public function widget( $args, $instance ) {
        widget_content($args, $instance, 'fave_week');
    }
    /**
     * Sanitize widget form values as they are saved
    **/
    public function update( $new_instance, $old_instance ) {
        $instance = array();

        /* Strip tags to remove HTML. For text inputs and textarea. */
        $instance['title'] = strip_tags( $new_instance['title'] );
        $instance['items_num'] = strip_tags( $new_instance['items_num'] );
        $instance['widget_type'] = strip_tags( $new_instance['widget_type'] );
        
        return $instance;
    }
    /**
     * Back-end widget form
    **/
    public function form( $instance ) {
        /* Default widget settings. */
        $defaults = array(
            'title' => 'Property of the Week',
            'items_num' => '1',
            'widget_type' => 'entries'
        );
        $instance = wp_parse_args( (array) $instance, $defaults );
        
    ?>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e('Title:', 'houzez'); ?></label>
            <input type="text" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" value="<?php echo esc_attr( $instance['title'] ); ?>" class="widefat" />
        </p>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'items_num' ) ); ?>"><?php esc_html_e('Maximum posts to show:', 'houzez'); ?></label>
            <input type="text" id="<?php echo esc_attr( $this->get_field_id( 'items_num' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'items_num' ) ); ?>" value="<?php echo esc_attr( $instance['items_num'] ); ?>" size="1" />
        </p>
        <p>
            <input type="radio" id="<?php echo esc_attr( $this->get_field_id( 'slider' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'widget_type' ) ); ?>" <?php if ($instance["widget_type"] == 'slider')  echo 'checked="checked"'; ?> value="slider" />
            <label for="<?php echo esc_attr( $this->get_field_id( 'slider' ) ); ?>"><?php esc_html_e( 'Display Properties as Slider', 'houzez' ); ?></label><br />

            <input type="radio" id="<?php echo esc_attr( $this->get_field_id( 'entries' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'widget_type' ) ); ?>" <?php if ($instance["widget_type"] == 'entries') echo 'checked="checked"'; ?> value="entries" />
            <label for="<?php echo esc_attr( $this->get_field_id( 'entries' ) ); ?>"><?php esc_html_e( 'Display Properties as List', 'houzez' ); ?></label>
        </p>
        
    <?php
    }

}

if ( ! function_exists( 'HOUZEZ_property_week_loader' ) ) {
    function HOUZEZ_property_week_loader (){
     register_widget( 'HOUZEZ_property_week' );
    }
     add_action( 'widgets_init', 'HOUZEZ_property_week_loader' );
}

/*
 * Widget Name: Property Add On: Featured Listing
 */
 
class HOUZEZ_featured_listing extends WP_Widget {
    /**
     * Register widget
    **/
    public function __construct() {
        
        parent::__construct(
            'houzez_featured_listing', // Base ID
            esc_html__( 'HOUZEZ: Property Add On: Featured Listing', 'houzez' ), // Name
            array( 'description' => esc_html__( 'Show featured listing', 'houzez' ), ) // Args
        );
        
    }
    /**
     * Front-end display of widget
    **/
    public function widget( $args, $instance ) {
        widget_content($args, $instance, 'fave_featured');
    }
    /**
     * Sanitize widget form values as they are saved
    **/
    public function update( $new_instance, $old_instance ) {
        $instance = array();

        /* Strip tags to remove HTML. For text inputs and textarea. */
        $instance['title'] = strip_tags( $new_instance['title'] );
        $instance['items_num'] = strip_tags( $new_instance['items_num'] );
        $instance['widget_type'] = strip_tags( $new_instance['widget_type'] );
        
        return $instance;
    }
    /**
     * Back-end widget form
    **/
    public function form( $instance ) {
        
        /* Default widget settings. */
        $defaults = array(
            'title' => 'Featured Listing',
            'items_num' => '5',
            'widget_type' => 'entries'
        );
        $instance = wp_parse_args( (array) $instance, $defaults );    
    ?>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e('Title:', 'houzez'); ?></label>
            <input type="text" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" value="<?php echo esc_attr( $instance['title'] ); ?>" class="widefat" />
        </p>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'items_num' ) ); ?>"><?php esc_html_e('Maximum posts to show:', 'houzez'); ?></label>
            <input type="text" id="<?php echo esc_attr( $this->get_field_id( 'items_num' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'items_num' ) ); ?>" value="<?php echo esc_attr( $instance['items_num'] ); ?>" size="1" />
        </p>
        <p>
            <input type="radio" id="<?php echo esc_attr( $this->get_field_id( 'slider' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'widget_type' ) ); ?>" <?php if ($instance["widget_type"] == 'slider')  echo 'checked="checked"'; ?> value="slider" />
            <label for="<?php echo esc_attr( $this->get_field_id( 'slider' ) ); ?>"><?php esc_html_e( 'Display Properties as Slider', 'houzez' ); ?></label><br />

            <input type="radio" id="<?php echo esc_attr( $this->get_field_id( 'entries' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'widget_type' ) ); ?>" <?php if ($instance["widget_type"] == 'entries') echo 'checked="checked"'; ?> value="entries" />
            <label for="<?php echo esc_attr( $this->get_field_id( 'entries' ) ); ?>"><?php esc_html_e( 'Display Properties as List', 'houzez' ); ?></label>
        </p>
        
    <?php
    }

}

if ( ! function_exists( 'HOUZEZ_featured_listing_loader' ) ) {
    function HOUZEZ_featured_listing_loader (){
     register_widget( 'HOUZEZ_featured_listing' );
    }
     add_action( 'widgets_init', 'HOUZEZ_featured_listing_loader' );
}

/**
 * Footer Mortgage Calculator
 */
if ( ! function_exists( 'HOUZEZ_mortgage_calculator_remove' ) ) {
    function HOUZEZ_mortgage_calculator_remove (){
        unregister_widget( 'HOUZEZ_mortgage_calculator' );
    }
    add_action( 'widgets_init', 'HOUZEZ_mortgage_calculator_remove', 11 );

    require_once( get_stylesheet_directory() . '/houzez-mortgage-calculator.php' );
}

/**
 * Footer Mortgage Sitemap
 */
/* Add 2 widgets for footer */
add_action('widgets_init', 'houzez_add_widget', 20);
if( !function_exists('houzez_add_widget') ) {
    function houzez_add_widget() {
        register_sidebar(array(
            'name' => esc_html__('Footer Area 5', 'houzez'),
            'id' => 'footer-sidebar-5',
            'description' => esc_html__('Widgets in this area will be show in footer column five', 'houzez'),
            'before_widget' => '<div id="%1$s" class="footer-widget %2$s">',
            'after_widget' => '</div>',
            'before_title' => '<div class="widget-top"><h3 class="widget-title">',
            'after_title' => '</h3></div>',
        ));

        register_sidebar(array(
            'name' => esc_html__('Footer Area 6', 'houzez'),
            'id' => 'footer-sidebar-6',
            'description' => esc_html__('Widgets in this area will be show in footer column six', 'houzez'),
            'before_widget' => '<div id="%1$s" class="footer-widget %2$s">',
            'after_widget' => '</div>',
            'before_title' => '<div class="widget-top"><h3 class="widget-title">',
            'after_title' => '</h3></div>',
        ));
    }
}

/**
 * Featured Listing/Property of the Week
 */

/* -----------------------------------------------------------------------------------------------------------
 *  Remove Property of the Week
 -------------------------------------------------------------------------------------------------------------*/
if( !function_exists('houzez_remove_prop_week') ):
    function  houzez_remove_prop_week(){
        $userID = apply_filters( 'determine_current_user', false );
        wp_set_current_user( $userID );

        $prop_id = intval( $_POST['propid'] );
        $post = get_post( $prop_id );

        if( $post->post_author == $userID ) {
            update_post_meta($prop_id, 'fave_week', 0);
        }

        return ($post->post_author == $userID);
    }
endif;

/**
 * Package Creation
 */

// Use update_custom_metabox
add_action( 'wp_ajax_nopriv_houzez_remove_payment_option', 'houzez_remove_payment_option');
add_action( 'wp_ajax_houzez_remove_payment_option', 'houzez_remove_payment_option' );

if( !function_exists('houzez_remove_payment_option') ):
    function  houzez_remove_payment_option(){
        $postID = $_POST['postID'];
        $metaKey = $_POST['metaKey'];

        delete_post_meta($postID, $metaKey);

        wp_die();
    }
endif;

/**
 * Encrypt Document Upload & Remove
 */

function houzez_doc_upload() {
    $post_id = $_POST['post_id'];

    $filename = $_FILES['file']['name'];
    $title = $_POST['title'];

    $name = pathinfo($filename, PATHINFO_FILENAME);
    $extension = pathinfo($filename, PATHINFO_EXTENSION);

    $increment = '';

    $ftp_url = "ftp://" . houzez_option('ftp_username') . ":" . houzez_option('ftp_password') 
                . "@" . houzez_option('ftp_url') . "/";

    $path = $ftp_url . $post_id;
    mkdir($path);

    $filelist = scandir($path, 1);

    if (in_array($filename, $filelist)) {
        $increment = 1;

        while (in_array($name . '_' . $increment . '.' . $extension, $fielist)) {
            $increment++;
        }

        $filename = $name . '_' . $increment . '.' . $extension;
    }
    
    $fp = fopen ( $_FILES['file']['tmp_name'], 'r' );
    $data = fread ( $fp, filesize ( $_FILES['file']['tmp_name'] ) );
    fclose ( $fp );

    $fp = fopen ( $path . "/" . $filename, 'wt' );
    if ($fp) {
        fwrite ( $fp, $data );
        fclose ( $fp );

        $flag = true;
        $i = 1;

        $key = '';

        while ($flag) {
            $doc = get_post_meta($post_id, 'doc' . $i, true);

            if ($doc == '') {
                $characters = '0123456789abcdefghijklmnopqrstuvwxyz';
                $charactersLength = strlen($characters);

                for ($j = 0; $j < 12; $j++) {
                    $key .= $characters[rand(0, $charactersLength - 1)];
                }

                add_post_meta($post_id, 'doc' . $i, $title . '/' . $filename . '/' . $key);
                $flag = false;
            }

            $i++;
        }

        $result = $filename . '/' . $key;
    } else {
        return 'fail';
    }

    return $result;
}

function houzez_doc_remove() {
    global $wpdb;

    $post_id = $_POST['post_id'];

    $title = $_POST['title'];

    $path = '/' . $post_id . '/';
    $filename = $_POST['file'];

    $ftp_server = houzez_option('ftp_url');
    $conn_id = ftp_connect($ftp_server);
    ftp_login($conn_id, houzez_option('ftp_username'), houzez_option('ftp_password'));

    $result = '';

    if (ftp_delete($conn_id, $path . $filename)) {
        for ($i = 1; $i < 6; $i++) {
            $doc = get_post_meta($post_id, 'doc' . $i, true);

            $val = explode('/', $doc);

            if ($val[0] == $title && $val[1] == $filename) {
                $wpdb->query("
                    DELETE FROM {$wpdb->prefix}postmeta WHERE post_id = " . $post_id . " AND meta_value LIKE '%" . $doc . "%'");

                delete_post_meta($post_id, 'doc' . $i);
            }
        }

        $result = 'success';
    } else {
        $result = 'fail';
    }

    ftp_close($conn_id);

    return $result;
}

function houzez_doc_share() {
    $url = houzez_get_template_link('template-user-dashboard-document.php');

    $post_id = $_POST['post_id'];
    $enc = $_POST['enc'];
    $mail = $_POST['mail'];

    add_post_meta($post_id, $mail, $enc . '/' . date('Y-m-d'));

    $users = get_users();

    $flag = false;

    foreach ($users as $user) {
        if ($user->user_email == $mail)
            $flag = true;
    }

    if (!$flag) {
        $keys = explode('/', $enc);
        $key = $keys[3];

        $parts = explode('@', $mail);
        $name = $parts[0];

        $new_enc = $key . md5($name);

        $url .= '?encrypt=' . $new_enc;
    }

    $subject = houzez_option('share_subject');
    if ($subject == '')
        $subject = 'Affordable Real Estate';

    $verbiage = houzez_option('share_verbiage');
    $content = $verbiage . "\n" . $url;

    $headers = "Reply-To: <staging@unfstaging.com>\r\n"; 
    $headers .= "Return-Path: <staging@unfstaging.com>\r\n";
    $headers .= "From: <staging@unfstaging.comm>\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/plain; charset=iso-8859-1\r\n";
    $headers .= "X-Priority: 3\r\n";
    $headers .= "X-Mailer: PHP". phpversion() ."\r\n";

    wp_mail($mail, $subject, $content, $headers);

    return true;
}

function houzez_get_rate() {
    $from = $_POST['from'];
    $to = $_POST['to'];

    if ($from == 'XBT')
        $from = 'BTC';

    if ($to == 'XBT')
        $to = 'BTC';

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, 'https://api.coinbase.com/v2/exchange-rates?currency=' . $from);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    $result = curl_exec($ch);

    curl_close($ch);

    $result = json_decode($result);
    $arr = array();

    foreach ($result->data->rates as $key => $value) {
        $arr[$key] = $value;
    }

    return $arr[$to];
}

/**
 * Membership Function
 */

function houzez_get_user_current_package( $user_id ) {

    $remaining_listings = houzez_get_remaining_listings( $user_id );
    $pack_featured_remaining_listings = houzez_get_featured_remaining_listings( $user_id );
    $package_id = houzez_get_user_package_id( $user_id );
    $packages_page_link = houzez_get_template_link('template-advanced-package.php');

    if( $remaining_listings == -1 ) {
        $remaining_listings = esc_html__('Unlimited', 'houzez');
    }

    if( !empty( $package_id ) ) {

        $seconds = 0;
        $pack_title = get_the_title( $package_id );
        $pack_listings = get_post_meta( $package_id, 'fave_package_listings', true );
        $pack_unmilited_listings = get_post_meta( $package_id, 'fave_unlimited_listings', true );
        $pack_featured_listings = get_post_meta( $package_id, 'fave_package_featured_listings', true );
        $pack_date = strtotime ( get_user_meta( $user_id, 'package_activation',true ) );

        $expired_date = date_i18n( get_option('date_format'),  $pack_date );

        echo '<div class="pkgs-status">';
        echo '<h4 class="pkgs-status-title">'.esc_html__( 'Your Current Package', 'houzez' ).'</h4>';
        echo '<ul>';
        echo '<li><strong>'.esc_attr( $pack_title ).'</strong></li>';

        if( $pack_unmilited_listings == 1 ) {
            echo '<li><span class="pkg-status-left">'.esc_html__('Listings Included: ','houzez').'</span><span class="pkg-status-right">'.esc_html__('unlimited listings ','houzez').'</span></li>';
            echo '<li><span class="pkg-status-left">'.esc_html__('Listings Remaining: ','houzez').'</span><span class="pkg-status-right">'.esc_html__('unlimited listings ','houzez').'</li>';
        } else {
            echo '<li><span class="pkg-status-left">'.esc_html__('Listings Included: ','houzez').'</span><span class="pkg-status-right">'.esc_attr( $pack_listings ).'</li>';
            echo '<li><span class="pkg-status-left">'.esc_html__('Listings Remaining: ','houzez').'</span><span class="listings_remainings pkg-status-right">'.esc_attr( $remaining_listings ).'</span></li>';
        }

        echo '<li><span class="pkg-status-left">'.esc_html__('Featured Included: ','houzez').'</span><span class="pkg-status-right">'.esc_attr( $pack_featured_listings ).'</span></li>';
        echo '<li><span class="pkg-status-left">'.esc_html__('Featured Remaining: ','houzez').'</span><span class="featured_listings_remaining pkg-status-right">'.esc_attr( $pack_featured_remaining_listings ).'</span></li>';
        echo '<li><span class="pkg-status-left">'.esc_html__('Ends On','houzez').'</span><span class="pkg-status-right">';
        echo ' '.esc_attr( $expired_date );
        echo '</span></li>';
        echo '</ul>';
        echo '</div>';

        /*if( ! is_page_template( 'template/user_dashboard_membership.php' ) ) {
            echo '<a href="' . esc_url($packages_page_link) . '" class="plan-link btn btn-primary btn-block"> ' . esc_html__('Change Membership Plan', 'houzez') . ' </a>';
        }*/

    }
}

function houzez_membership_package_update($user_id, $package_id, $payment_option) {
    $pack_listings            =   get_post_meta( $package_id, 'fave_package_listings', true );
    $pack_featured_listings   =   get_post_meta( $package_id, 'fave_package_featured_listings', true );
    $pack_unlimited_listings  =   get_post_meta( $package_id, 'fave_unlimited_listings', true );

    $user_current_posted_listings           =   houzez_get_user_num_posted_listings ( $user_id );

    $user_current_posted_featured_listings  =   houzez_get_user_num_posted_featured_listings( $user_id );

    if( houzez_check_user_existing_package_status( $user_id, $package_id ) ) {
        $new_pack_listings           =  $pack_listings - $user_current_posted_listings;
        $new_pack_featured_listings  =  $pack_featured_listings -  $user_current_posted_featured_listings;
    } else {
        $new_pack_listings           =  $pack_listings;
        $new_pack_featured_listings  =  $pack_featured_listings;
    }

    if( $new_pack_listings < 0 ) {
        $new_pack_listings = 0;
    }

    if( $new_pack_featured_listings < 0 ) {
        $new_pack_featured_listings = 0;
    }

    if ( $pack_unlimited_listings == 1 ) {
        $new_pack_listings = -1 ;
    }

    update_user_meta( $user_id, 'package_listings', $new_pack_listings) ;
    update_user_meta( $user_id, 'package_featured_listings', $new_pack_featured_listings);

    $user_submit_has_no_membership = get_the_author_meta( 'user_submit_has_no_membership', $user_id );
    if( !empty( $user_submit_has_no_membership ) ) {
        houzez_update_package_listings( $user_id );
        houzez_update_property_from_draft( $user_submit_has_no_membership );

        delete_user_meta( $user_id, 'user_submit_has_no_membership' );
    }

    switch ( $payment_option ) {
        case 'option1':
            $seconds = 60*60*24;
            break;
        case 'option2':
            $seconds = 60*60*24*7;
            break;
        case 'option3':
            $seconds = 60*60*24*30;
            break;
        case 'option4':
            $seconds = 60*60*24*30*3;
            break;
        case 'option5':
            $seconds = 60*60*24*30*6;
            break;
        case 'option6':
            $seconds = 60*60*24*365;
            break;
        case 'option7':
            $arr = array(
               'custom1' => 60*60*24,
               'custom2' => 60*60*24*7,
               'custom3' => 60*60*24*30
            );

            $value = get_post_meta( $package_id, 'fave_billing_custom_value', true );
            $option = get_post_meta( $package_id, 'fave_billing_custom_option', true );

            $seconds = $value * $arr[$option];
            break;
    }

    $date = strtotime(date('Y-m-d H:i:s'));
    $update = $date + $seconds;

    update_user_meta( $user_id, 'package_activation', date('Y-m-d H:i:s', $update) );

    update_user_meta( $user_id, 'package_id', $package_id );
}

/**
 * Membership Package Payment (Paypal, Stripe, Bitcoin, GooglePay, ApplePay)
 */
function houzez_recuring_paypal_package_payment() {
    global $current_user;
    wp_get_current_user();
    $userID = $current_user->ID;

    if ( !is_user_logged_in() ) {
        wp_die('are you kidding?');
    }

    if( $userID === 0 ) {
        wp_die('are you kidding?');
    }

    $allowed_html=array();
    $houzez_package_id    = intval($_POST['houzez_package_id']);
    $houzez_package_price = $_POST['houzez_package_price'];
    $is_package_exist     = get_posts('post_type=houzez_packages&p='.$houzez_package_id);

    if( !empty ( $is_package_exist ) ) {
        global $current_user;
        $access_token = '';

        $is_paypal_live      = houzez_option('paypal_api');
        $host                = 'https://api.sandbox.paypal.com';
        if( $is_paypal_live =='live'){
            $host = 'https://api.paypal.com';
        }
        
        $url             =   $host.'/v1/oauth2/token';
        $postArgs        =   'grant_type=client_credentials';        
        
        if(function_exists('houzez_get_paypal_access_token')){
            $access_token    =   houzez_get_paypal_access_token( $url, $postArgs );
        }

        $billing_plan = get_post_meta($houzez_package_id, 'houzez_paypal_billing_plan_'.$is_paypal_live, true);

        if( empty($billing_plan['id']) || empty($billing_plan) || !is_array($billing_plan) ) {
            houzez_create_billing_plan($houzez_package_id, $houzez_package_price, $access_token);
            $billing_plan = get_post_meta($houzez_package_id, 'houzez_paypal_billing_plan_'.$is_paypal_live, true);
        }
        
        echo houzez_create_paypal_membership($houzez_package_id, $houzez_package_price, $access_token, $billing_plan['id']);
        wp_die();
    }
    wp_die();

}

function houzez_create_billing_plan($package_id, $package_price, $access_token) {
    $blogInfo = esc_url( home_url() );
    $packPrice          =  $package_price;
    $packName           =  get_the_title($package_id);
    $billingPeriod      =  get_post_meta( $package_id, 'fave_billing_time_unit', true );
    $billingFreq        =  intval( get_post_meta( $package_id, 'fave_billing_unit', true ) );
    $submissionCurency  =  houzez_option('currency_paid_submission');
    $return_url      = houzez_get_template_link('template/template-thankyou.php');
    $cancel_url   =  houzez_get_dashboard_profile_link();
    $plan_description = $packName.' '.esc_html__('Membership payment on ','houzez').$blogInfo;

    $is_paypal_live      = houzez_option('paypal_api');
    $host                = 'https://api.sandbox.paypal.com';
    if( $is_paypal_live =='live'){
        $host = 'https://api.paypal.com';
    }

    $url             =   $host.'/v1/oauth2/token';
    $postArgs        =   'grant_type=client_credentials';
    $url                = $host.'/v1/payments/billing-plans/';

    $payment = array(
            'name' => $packName,
            'description' => $plan_description,
            'type' => 'INFINITE',
        );

    $payment['payment_definitions'][0] = array(
        'name' => 'Regular payment definition',
        'type' => 'REGULAR',
        'frequency' => $billingPeriod,
        'frequency_interval' => $billingFreq,
        'amount' => array(
            'value' => $packPrice,
            'currency' => $submissionCurency
        ),
        "cycles" => "0",
    );

    $payment['merchant_preferences'] = array(
        'return_url' => $return_url,
        'cancel_url' => $cancel_url,
        'auto_bill_amount' => 'YES',
        'initial_fail_amount_action' => 'CONTINUE',
        'max_fail_attempts' => '0'
    );

    $jsonEncode = json_encode($payment);
    $json_response = houzez_execute_paypal_request( $url, $jsonEncode, $access_token );

    if( $json_response['state']!='ACTIVE'){
        if( houzez_activate_billing_plan( $json_response['id'] ) ) {
            $billing_info = array();
            $billing_info['id']          =   $json_response['id'];
            $billing_info['name']        =   $json_response['name'];
            $billing_info['description'] =   $json_response['description'];
            $billing_info['type']        =   $json_response['type'];
            $billing_info['state']       =   "ACTIVE";
           
            update_post_meta($package_id,'houzez_paypal_billing_plan_'.$is_paypal_live, $billing_info);
            echo houzez_create_paypal_membership($package_id, $packPrice, $access_token, $json_response['id']);
            return true;
        }
    }

}

function houzez_create_paypal_membership($package_id, $package_price, $access_token, $plan_id) {
    global $current_user;
    wp_get_current_user();
    $userID = $current_user->ID;
    $blogInfo = esc_url( home_url() );

    $host = 'https://api.sandbox.paypal.com';
    $is_paypal_live = houzez_option('paypal_api');
    if( $is_paypal_live =='live'){
        $host = 'https://api.paypal.com';
    }

    $time               =  time();
    $date               =  date('Y-m-d H:i:s',$time);

    $packPrice          =  $package_price;
    $packName           =  get_the_title($package_id);
    $billingPeriod      =  get_post_meta( $package_id, 'fave_billing_time_unit', true );
    $billingFreq        =  intval( get_post_meta( $package_id, 'fave_billing_unit', true ) );

    $submissionCurency  =  houzez_option('currency_paid_submission');
    $return_url      = houzez_get_template_link('template/template-thankyou.php');
    $plan_description = $packName.' '.esc_html__('Membership payment on ','houzez').$blogInfo;
    $return_url      = houzez_get_template_link('template/template-thankyou.php');

    $url        = $host.'/v1/payments/billing-agreements/';


    $billing_agreement = array(
                        'name'          => $packName,
                        'description'   => $plan_description,
                        'start_date'    =>  gmdate("Y-m-d\TH:i:s\Z", time()+100 ),
        
    );
    
    $billing_agreement['payer'] =   array(
                        'payment_method'=>'paypal',
                        'payer_info'    => array('email'=>'payer@example.com'),
    );
     
    $billing_agreement['plan'] = array(
                        'id' => $plan_id,
    );
    
    $json       = json_encode($billing_agreement);
    $json_resp  = houzez_execute_paypal_request($url, $json,$access_token);
  
    foreach ($json_resp['links'] as $link) {
            if($link['rel'] == 'execute'){
                    $payment_execute_url = $link['href'];
                    $payment_execute_method = $link['method'];
            } else  if($link['rel'] == 'approval_url'){
                            $payment_approval_url = $link['href'];
                            $payment_approval_method = $link['method'];
                            print $payment_approval_url;
                    }
    }

    $output['payment_execute_url'] = $payment_execute_url;
    $output['access_token']        = $access_token;
    $output['package_id']          = $package_id;
    $output['recursive']         = 1;
    $output['date']              = $date;

    $save_output[$userID]   =   $output;
    update_option('houzez_paypal_package_transfer', $save_output);
    update_user_meta( $userID, 'houzez_paypal_package', $output);
    
}

function houzez_stripe_payment_membership( $pack_id, $pack_price, $title ) {

    require_once( get_template_directory() . '/framework/stripe-php/init.php' );
    $stripe_secret_key = houzez_option('stripe_secret_key');
    $stripe_publishable_key = houzez_option('stripe_publishable_key');

    $current_user = wp_get_current_user();

    $userID = $current_user->ID;
    $user_login = $current_user->user_login;
    $user_email = get_the_author_meta('user_email', $userID);

    $stripe = array(
        "secret_key" => $stripe_secret_key,
        "publishable_key" => $stripe_publishable_key
    );

    \Stripe\Stripe::setApiKey($stripe['secret_key']);

    $submission_currency = houzez_option('currency_paid_submission');

    $package_price_for_stripe = $pack_price * 100;

    print '
        <div class="houzez_stripe_membership " id="'.  sanitize_title($title).'">
            <script src="https://checkout.stripe.com/checkout.js" id="stripe_script"
            class="stripe-button"
            data-key="'. $stripe_publishable_key.'"
            data-amount="'.$package_price_for_stripe.'"
            data-email="'.$user_email.'"
            data-currency="'.$submission_currency.'"
            data-zip-code="true"
            data-locale="'.get_locale().'"
            data-billing-address="true"
            data-label="'.__('Pay with Credit Card','houzez').'"
            data-description="'.$title.' '.__('Payment','houzez').'">
            </script>
        </div>
        <input type="hidden" id="pack_id" name="pack_id" value="' . $pack_id . '">
        <input type="hidden" name="userID" value="' . $userID . '">
        <input type="hidden" id="pay_ammout" name="pay_ammout" value="' . $package_price_for_stripe . '">';
}

function houzez_googlepay_payment( $id, $price, $title, $option ) {
    require_once( get_template_directory() . '/framework/stripe-php/init.php' );

    $stripe_secret_key = houzez_option('stripe_secret_key');
    $stripe_publishable_key = houzez_option('stripe_publishable_key');

    $stripe = array(
        "secret_key" => $stripe_secret_key,
        "publishable_key" => $stripe_publishable_key
    );

    \Stripe\Stripe::setApiKey($stripe['secret_key']);

    echo '<script src="https://js.stripe.com/v3/"></script>
           <div id="google-pay-button"></div>
           <script type="text/javascript">
            var url1 = "' . houzez_get_template_link('template-advanced-thankyou.php') . '";
            var url2 = "' . houzez_get_template_link('template-addon-thankyou.php') . '";

            var param = "";

            var stripe = Stripe("' . $stripe_publishable_key . '");

            var googlePay = stripe.paymentRequest({
                country: "US",
                currency: "eur",
                total: {
                label: "' . $title . '",
                amount: ' . $price * 100 . ',
                },
                requestPayerName: true,
                requestPayerEmail: true,
            });

            var elements = stripe.elements();
            var googleButton = elements.create("paymentRequestButton", {
                paymentRequest: googlePay,
            });

            googlePay.canMakePayment().then(function(result) {
                if (result) {
                    googleButton.mount("#google-pay-button");
                } else {
                    document.getElementById("google-pay-button").closest(".method-row").style.display = "none";
                }

                document.getElementById("google-pay-button").style.display = "none";
            });

            googlePay.on("token", function(ev) {
              fetch("/", {
                method: "POST",
                body: JSON.stringify({token: ev.token.id}),
                headers: {"content-type": "application/json"}
              })
              .then(function(response) {
                if (response.ok) {
                  ev.complete("success");

                  var url = "";

                  if (param == "membership")
                    url = url1;
                  if (param == "package")
                    url = url2;

                  url += "?pay=google&option=' . $option . '&price=' . $price . '&id=' . $id . '";

                  window.location.href = url;
                } else {
                  ev.complete("fail");
                }
              });
            });

            function googlePayNow(value) {
                param = value;
                googlePay.show();
            }
           </script>
           ';
}

function houzez_applepay_payment( $id, $price, $title, $option ) {
    require_once( get_template_directory() . '/framework/stripe-php/init.php' );

    $stripe_secret_key = houzez_option('stripe_secret_key');
    $stripe_publishable_key = houzez_option('stripe_publishable_key');

    $stripe = array(
        "secret_key" => $stripe_secret_key,
        "publishable_key" => $stripe_publishable_key
    );

    \Stripe\Stripe::setApiKey($stripe['secret_key']);

    \Stripe\ApplePayDomain::create([
      'domain_name' => get_site_url()
    ]);

    echo '<script type="text/javascript" src="https://js.stripe.com/v2/"></script>
            <style>
              #apple-pay-button {
                display: none;
                background-color: black;
                background-image: -webkit-named-image(apple-pay-logo-white);
                background-size: 100% 100%;
                background-origin: content-box;
                background-repeat: no-repeat;
                width: 100%;
                height: 44px;
                padding: 10px 0;
                border-radius: 10px;
              }
            </style>
            <button id="apple-pay-button"></button>
            <script type="text/javascript">
                Stripe.setPublishableKey("' . $stripe['publishable_key'] . '");

                Stripe.applePay.checkAvailability(function(available) {
                  if (available) {
                    document.getElementById("apple-pay-button").style.display = "block";
                  } else {
                    document.getElementById("apple-pay-button").closest(".method-row").style.display = "none";
                  }
                });

                /*Stripe.applePay.buildSession({
                    countryCode: "US",
                    currencyCode: "eur",
                    total: {
                        label: "' . $title . '",
                        amount: ' . $price * 100 . '
                    }
                }, onSuccessHandler, onErrorHandler);

                function onSuccessHandler(result, completion) {
                  $.post( "/charges", { token: result.token.id }).done(function() {
                    completion(true);
                  }).fail(function() {
                    completion(false);
                  });
                }*/
            </script>
        ';
}


/**
 * Additional Package Option Payment
 */
add_action( 'wp_ajax_nopriv_houzez_paypal_option_payment', 'houzez_paypal_option_payment' );
add_action( 'wp_ajax_houzez_paypal_option_payment', 'houzez_paypal_option_payment' );

function houzez_paypal_option_payment() {
    global $current_user;
    wp_get_current_user();
    $userID = $current_user->ID;

    $allowed_html =   array();
    $blogInfo = esc_url( home_url() );
    $houzez_option_name    =   wp_kses($_POST['houzez_option_name'],$allowed_html);
    $houzez_option_price   =   $_POST['houzez_option_price'];
    $houzez_property_id      =   $_POST['houzez_property_id'];

    $currency            = houzez_option('currency_paid_submission');

    $option = array(
        'featured' => 'Featured Property',
        'week'     => 'Property of the Week'
    );

    $payment_description = esc_html__($option[$houzez_option_name].' payment on', 'houzez').$blogInfo;

    $is_paypal_live      = houzez_option('paypal_api');
    $host                = 'https://api.sandbox.paypal.com';

    if( $is_paypal_live =='live'){
        $host = 'https://api.paypal.com';
    }

    $url             =   $host.'/v1/oauth2/token';
    $postArgs        =   'grant_type=client_credentials';
    $access_token    =   houzez_get_paypal_access_token( $url, $postArgs );
    $url             =   $host.'/v1/payments/payment';
    $return_url      = houzez_get_template_link('template-addon-thankyou.php');
    $dash_profile_link   =  houzez_get_dashboard_profile_link();

    $payment = array(
        'intent' => 'sale',
        "redirect_urls" => array(
            "return_url" => $return_url,
            "cancel_url" => $dash_profile_link
        ),
        'payer' => array("payment_method" => "paypal"),
        'application_context' => array(
            "shipping_preference" => "NO_SHIPPING"
        )
    );

    $payment['transactions'][0] = array(
        'amount' => array(
            'total' => $houzez_option_price,
            'currency' => $currency,
            'details' => array(
                'subtotal' => $houzez_option_price,
                'tax' => '0.00',
                'shipping' => '0.00'
            )
        ),
        'description' => $payment_description
    );

    $payment['transactions'][0]['item_list']['items'][] = array(
        'quantity' => '1',
        'name' => esc_html__('Additional Package Payment','houzez'),
        'price' => $houzez_option_price,
        'currency' => $currency,
        'sku' => $option[$houzez_option_name],
    );

    // Convert PHP array into json format
    $jsonEncode = json_encode($payment);
    $json_response = houzez_execute_paypal_request( $url, $jsonEncode, $access_token );

    foreach ($json_response['links'] as $link) {
        if($link['rel'] == 'execute'){
            $payment_execute_url = $link['href'];
            $payment_execute_method = $link['method'];
        } else if($link['rel'] == 'approval_url'){
            $payment_approval_url = $link['href'];
            $payment_approval_method = $link['method'];
        }
    }

    // Save data in database for further use on processor page
    $output['payment_execute_url'] = $payment_execute_url;
    $output['access_token']        = $access_token;
    $output['property_id']         = $houzez_property_id;
    $output['property_option']     = $houzez_option_name;

    $save_output[$userID]   =   $output;
    update_option('houzez_paypal_addon_package', $save_output);
    update_user_meta( $userID, 'houzez_paypal_property', $output);

    print $payment_approval_url;

    wp_die();

}

function houzez_get_taxonomies_for_edit_listing( $listing_id, $taxonomy ){

    $taxonomy_id = '';
    $taxonomy_id_arr = array();

    $taxonomy_terms = get_the_terms( $listing_id, $taxonomy );

    if( !empty($taxonomy_terms) ){
        foreach( $taxonomy_terms as $term ){
            $taxonomy_id = $term->term_id;
            array_push($taxonomy_id_arr, intval($taxonomy_id));
        }
    }

    if( $taxonomy != 'property_lifestyle' ) {
        echo '<option value="-1">'.esc_html__( 'None', 'houzez').'</option>';
    }

    $parent_taxonomy = get_terms(
        array(
            $taxonomy
        ),
        array(
            'orderby'       => 'name',
            'order'         => 'ASC',
            'hide_empty'    => false,
            'parent' => 0
        )
    );

    houzez_get_taxonomies_with_id_value( $taxonomy, $parent_taxonomy, $taxonomy_id_arr );

}

function houzez_get_taxonomies_with_id_value($taxonomy, $parent_taxonomy, $taxonomy_id_arr, $prefix = " " ){

    if (!empty($parent_taxonomy)) {
        foreach ($parent_taxonomy as $term) {
            if (sizeof($taxonomy_id_arr) > 0) {
                $flag = true;

                for ($i = 0; $i < sizeof($taxonomy_id_arr); $i++) {
                    if ($taxonomy_id_arr[$i] == $term->term_id) {
                        $flag = false;   
                    }
                }

                if ($flag) {
                    echo '<option value="' . $term->term_id . '">' . $prefix . $term->name . '</option>';
                } else {
                    echo '<option value="' . $term->term_id . '" selected="selected">' . $prefix . $term->name . '</option>';
                }
            } else {
                echo '<option value="' . $term->term_id . '">' . $prefix . $term->name . '</option>';
            }
        }
    }
}

function houzez_user_has_membership( $user_id ) {
    $has_package = get_the_author_meta( 'package_id', $user_id );
    $has_listing = get_the_author_meta( 'package_listings', $user_id );
    
    if( !empty( $has_package ) && ( $has_listing != 0 || $has_listing != '' ) ) {
        return true;
    } else {
        return false;
    }
}

function houzez_submit_listing($new_property) {
    global $current_user;

    wp_get_current_user();
    $userID = $current_user->ID;
    $listings_admin_approved = houzez_option('listings_admin_approved');
    $edit_listings_admin_approved = houzez_option('edit_listings_admin_approved');
    $enable_paid_submission = houzez_option('enable_paid_submission');

    // Title
    if( isset( $_POST['prop_title']) ) {
        $new_property['post_title'] = sanitize_text_field( $_POST['prop_title'] );
    }

    if( $enable_paid_submission == 'membership' ) {
        $user_submit_has_no_membership = isset($_POST['user_submit_has_no_membership']) ? $_POST['user_submit_has_no_membership'] : '';
    } else {
        $user_submit_has_no_membership = 'no';
    }

    // Description
    if( isset( $_POST['prop_des'] ) ) {
        $new_property['post_content'] = wp_kses_post( $_POST['prop_des'] );
    }

    $new_property['post_author'] = $userID;

    $submission_action = $_POST['action'];
    $prop_id = 0;

    if( $submission_action == 'add_property' ) {

        if( $user_submit_has_no_membership == 'yes' ) {
            $new_property['post_status'] = 'draft';
        } else {
            $new_property['post_status'] = 'publish';
        }

        $prop_id = wp_insert_post( $new_property );

        if( $prop_id > 0 ) {
            $submitted_successfully = true;
            if( $enable_paid_submission == 'membership'){ // update package status
                houzez_update_package_listings( $userID );
            }
            //do_action( 'wp_insert_post', 'wp_insert_post' ); // Post the Post
        }
    }else if( $submission_action == 'update_property' ) {
        $new_property['ID'] = intval( $_POST['prop_id'] );

        if( get_post_status( intval( $_POST['prop_id'] ) ) == 'draft' ) {
            if( $enable_paid_submission == 'membership') {
                houzez_update_package_listings($userID);
            }
            if( $listings_admin_approved != 'yes' && ( $enable_paid_submission == 'no' || $enable_paid_submission == 'free_paid_listing' || $enable_paid_submission == 'membership' ) ) {
                $new_property['post_status'] = 'publish';
            }/* else {
                $new_property['post_status'] = 'pending';
            }*/
        }/* elseif( $edit_listings_admin_approved == 'yes' ) {
                $new_property['post_status'] = 'pending';
        }*/
        $prop_id = wp_update_post( $new_property );

    }

    if( $prop_id > 0 ) {
        if(class_exists('Houzez_Fields_Builder')) {
            $fields_array = Houzez_Fields_Builder::get_form_fields();
            if(!empty($fields_array)):
                foreach ( $fields_array as $value ):
                    $field_name = $value->field_id;

                    if( isset( $_POST[$field_name] ) ) {
                        update_post_meta( $prop_id, 'fave_'.$field_name, sanitize_text_field( $_POST[$field_name] ) );
                    }

                endforeach; endif;
        }

        if( $user_submit_has_no_membership == 'yes' ) {
            update_user_meta( $userID, 'user_submit_has_no_membership', $prop_id );
        }

        // Add price post meta
        if( isset( $_POST['prop_price'] ) ) {
            update_post_meta( $prop_id, 'fave_property_price', sanitize_text_field( $_POST['prop_price'] ) );

            if( isset( $_POST['prop_label'] ) ) {
                update_post_meta( $prop_id, 'fave_property_price_postfix', sanitize_text_field( $_POST['prop_label']) );
            }
        }

        //price prefix
        if( isset( $_POST['prop_price_prefix'] ) ) {
            update_post_meta( $prop_id, 'fave_property_price_prefix', sanitize_text_field( $_POST['prop_price_prefix']) );
        }

        // Second Price
        if( isset( $_POST['prop_sec_price'] ) ) {
            update_post_meta( $prop_id, 'fave_property_sec_price', sanitize_text_field( $_POST['prop_sec_price'] ) );
        }

        // currency
        if( isset( $_POST['currency'] ) ) {
            update_post_meta( $prop_id, 'fave_currency', sanitize_text_field( $_POST['currency'] ) );
            if(class_exists('Houzez_Currencies')) {
                $currencies = Houzez_Currencies::get_property_currency_2($prop_id, $_POST['currency']);

                update_post_meta( $prop_id, 'fave_currency_info', $currencies );
            }
        }

        // Area Size
        if( isset( $_POST['prop_size'] ) ) {
            update_post_meta( $prop_id, 'fave_property_size', sanitize_text_field( $_POST['prop_size'] ) );
        }

        // Area Size Prefix
        if( isset( $_POST['prop_size_prefix'] ) ) {
            update_post_meta( $prop_id, 'fave_property_size_prefix', sanitize_text_field( $_POST['prop_size_prefix'] ) );
        }

        // Land Area Size
        if( isset( $_POST['prop_land_area'] ) ) {
            update_post_meta( $prop_id, 'fave_property_land', sanitize_text_field( $_POST['prop_land_area'] ) );
        }

        // Land Area Size Prefix
        if( isset( $_POST['prop_land_area_prefix'] ) ) {
            update_post_meta( $prop_id, 'fave_property_land_postfix', sanitize_text_field( $_POST['prop_land_area_prefix'] ) );
        }

        // Bedrooms
        if( isset( $_POST['prop_beds'] ) ) {
            update_post_meta( $prop_id, 'fave_property_bedrooms', sanitize_text_field( $_POST['prop_beds'] ) );
        }

        // Bathrooms
        if( isset( $_POST['prop_baths'] ) ) {
            update_post_meta( $prop_id, 'fave_property_bathrooms', sanitize_text_field( $_POST['prop_baths'] ) );
        }

        // Garages
        if( isset( $_POST['prop_garage'] ) ) {
            update_post_meta( $prop_id, 'fave_property_garage', sanitize_text_field( $_POST['prop_garage'] ) );
        }

        // Garages Size
        if( isset( $_POST['prop_garage_size'] ) ) {
            update_post_meta( $prop_id, 'fave_property_garage_size', sanitize_text_field( $_POST['prop_garage_size'] ) );
        }

        // Virtual Tour
        if( isset( $_POST['virtual_tour'] ) ) {
            update_post_meta( $prop_id, 'fave_virtual_tour', $_POST['virtual_tour'] );
        }

        // Year Built
        if( isset( $_POST['prop_year_built'] ) ) {
            update_post_meta( $prop_id, 'fave_property_year', sanitize_text_field( $_POST['prop_year_built'] ) );
        }

        // Property ID
        $auto_property_id = houzez_option('auto_property_id');
        if( $auto_property_id != 1 ) {
            if (isset($_POST['property_id'])) {
                update_post_meta($prop_id, 'fave_property_id', sanitize_text_field($_POST['property_id']));
            }
        } else {
                update_post_meta($prop_id, 'fave_property_id', $prop_id );
        }

        // Property Video Url
        if( isset( $_POST['prop_video_url'] ) ) {
            update_post_meta( $prop_id, 'fave_video_url', sanitize_text_field( $_POST['prop_video_url'] ) );
        }

        // Property Solar Perspective
        if( isset( $_POST['prop_perspective'] ) ) {
            update_post_meta( $prop_id, 'fave_perspective', sanitize_text_field( $_POST['prop_perspective'] ) );
        }

        // property video image - in case of update
        $property_video_image = "";
        $property_video_image_id = 0;
        if( $submission_action == "update_property" ) {
            $property_video_image_id = get_post_meta( $prop_id, 'fave_video_image', true );
            if ( ! empty ( $property_video_image_id ) ) {
                $property_video_image_src = wp_get_attachment_image_src( $property_video_image_id, 'houzez-property-detail-gallery' );
                $property_video_image = $property_video_image_src[0];
            }
        }

        // clean up the old meta information related to images when property update
        if( $submission_action == "update_property" ){
            delete_post_meta( $prop_id, 'fave_property_images' );
            delete_post_meta( $prop_id, 'fave_attachments' );
            delete_post_meta( $prop_id, '_thumbnail_id' );
        }

        // Property Images
        if( isset( $_POST['propperty_image_ids'] ) ) {
            if (!empty($_POST['propperty_image_ids']) && is_array($_POST['propperty_image_ids'])) {
                $property_image_ids = array();
                foreach ($_POST['propperty_image_ids'] as $prop_img_id ) {
                    $property_image_ids[] = intval( $prop_img_id );
                    add_post_meta($prop_id, 'fave_property_images', $prop_img_id);
                }

                // featured image
                if( isset( $_POST['featured_image_id'] ) ) {
                    $featured_image_id = intval( $_POST['featured_image_id'] );
                    if( in_array( $featured_image_id, $property_image_ids ) ) {
                        update_post_meta( $prop_id, '_thumbnail_id', $featured_image_id );

                        /* if video url is provided but there is no video image then use featured image as video image */
                        if ( empty( $property_video_image ) && !empty( $_POST['prop_video_url'] ) ) {
                            update_post_meta( $prop_id, 'fave_video_image', $featured_image_id );
                        }
                    }
                } elseif ( ! empty ( $property_image_ids ) ) {
                    update_post_meta( $prop_id, '_thumbnail_id', $property_image_ids[0] );

                    /* if video url is provided but there is no video image then use featured image as video image */
                    if ( empty( $property_video_image ) && !empty( $_POST['prop_video_url'] ) ) {
                        update_post_meta( $prop_id, 'fave_video_image', $property_image_ids[0] );
                    }
                }
            }
        }

        if( isset( $_POST['propperty_attachment_ids'] ) ) {
                $property_attach_ids = array();
                foreach ($_POST['propperty_attachment_ids'] as $prop_atch_id ) {
                    $property_attach_ids[] = intval( $prop_atch_id );
                    add_post_meta($prop_id, 'fave_attachments', $prop_atch_id);
                }
        }

        // Add property type
        if( isset( $_POST['prop_type'] ) && ( $_POST['prop_type'] != '-1' ) ) {
            wp_set_object_terms( $prop_id, intval( $_POST['prop_type'] ), 'property_type' );
        }

        // Add property status
        if( isset( $_POST['prop_status'] ) && ( $_POST['prop_status'] != '-1' ) ) {
            wp_set_object_terms( $prop_id, intval( $_POST['prop_status'] ), 'property_status' );
        }

        // Add property lifestyle
        if( isset( $_POST['prop_lifestyles'] ) && sizeof($_POST['prop_lifestyles']) > 0 ) {
            for ($i = 0; $i < sizeof($_POST['prop_lifestyles']); $i++) {
                $_POST['prop_lifestyles'][$i] = intval( $_POST['prop_lifestyles'][$i] );
            }

            wp_set_object_terms( $prop_id, $_POST['prop_lifestyles'], 'property_lifestyle' );
        }

        
        $location_dropdowns = houzez_option('location_dropdowns');

        // Add property city and area
        if($location_dropdowns == 'yes' && $submission_action == 'update_property') {

            if( isset( $_POST['locality2'] ) ) {
                $property_city = sanitize_text_field( $_POST['locality2'] );
                $city_id = wp_set_object_terms( $prop_id, $property_city, 'property_city' );

                $houzez_meta = array();
                $houzez_meta['parent_state'] = isset( $_POST['administrative_area_level_1'] ) ? $_POST['administrative_area_level_1'] : '';
                if( !empty( $city_id) ) {
                    update_option('_houzez_property_city_' . $city_id[0], $houzez_meta);
                }
            }

            if( isset( $_POST['neighborhood2'] ) ) {
                $property_area = sanitize_text_field( $_POST['neighborhood2'] );
                $area_id = wp_set_object_terms( $prop_id, $property_area, 'property_area' );

                $houzez_meta = array();
                $houzez_meta['parent_city'] = isset( $_POST['locality'] ) ? $_POST['locality'] : '';
                if( !empty( $area_id) && isset( $_POST['locality'] )) {
                    update_option('_houzez_property_area_' . $area_id[0], $houzez_meta);
                }
            }

        } else {

            if( isset( $_POST['locality'] ) ) {
                $property_city = sanitize_text_field( $_POST['locality'] );
                $city_id = wp_set_object_terms( $prop_id, $property_city, 'property_city' );

                $houzez_meta = array();
                $houzez_meta['parent_state'] = isset( $_POST['administrative_area_level_1'] ) ? $_POST['administrative_area_level_1'] : '';
                if( !empty( $city_id) ) {
                    update_option('_houzez_property_city_' . $city_id[0], $houzez_meta);
                }
            }

            if( isset( $_POST['neighborhood'] ) ) {
                $property_area = sanitize_text_field( $_POST['neighborhood'] );
                $area_id = wp_set_object_terms( $prop_id, $property_area, 'property_area' );

                $houzez_meta = array();
                $houzez_meta['parent_city'] = isset( $_POST['locality'] ) ? $_POST['locality'] : '';
                if( !empty( $area_id) ) {
                    update_option('_houzez_property_area_' . $area_id[0], $houzez_meta);
                }
            }
        }


        // Add property state
        if( isset( $_POST['administrative_area_level_1'] ) ) {
            $property_state = sanitize_text_field( $_POST['administrative_area_level_1'] );
            $state_id = wp_set_object_terms( $prop_id, $property_state, 'property_state' );

            $houzez_meta = array();
            $houzez_meta['parent_country'] = isset( $_POST['country_short'] ) ? $_POST['country_short'] : '';
            if( !empty( $state_id) ) {
                update_option('_houzez_property_state_' . $state_id[0], $houzez_meta);
            }
        }

        //echo $_POST['country_short'].' '.$_POST['administrative_area_level_1'].' '.$_POST['locality'].' '.$_POST['neighborhood']; die;
       
        // Add property features
        if( isset( $_POST['prop_features'] ) ) {
            $features_array = array();
            foreach( $_POST['prop_features'] as $feature_id ) {
                $features_array[] = intval( $feature_id );
            }
            wp_set_object_terms( $prop_id, $features_array, 'property_feature' );
        }

        // additional details
        if( isset( $_POST['additional_features'] ) ) {
            $additional_features = $_POST['additional_features'];
            if( ! empty( $additional_features ) ) {
                update_post_meta( $prop_id, 'additional_features', $additional_features );
                update_post_meta( $prop_id, 'fave_additional_features_enable', 'enable' );
            }
        }

        //Floor Plans
        if( isset( $_POST['floorPlans_enable'] ) ) {
            $floorPlans_enable = $_POST['floorPlans_enable'];
            if( ! empty( $floorPlans_enable ) ) {
                update_post_meta( $prop_id, 'fave_floor_plans_enable', $floorPlans_enable );
            }
        }

        if( isset( $_POST['floor_plans'] ) ) {
            $floor_plans_post = $_POST['floor_plans'];
            if( ! empty( $floor_plans_post ) ) {
                update_post_meta( $prop_id, 'floor_plans', $floor_plans_post );
            }
        }

        //Multi-units / Sub-properties
        if( isset( $_POST['multiUnits'] ) ) {
            $multiUnits_enable = $_POST['multiUnits'];
            if( ! empty( $multiUnits_enable ) ) {
                update_post_meta( $prop_id, 'fave_multiunit_plans_enable', $multiUnits_enable );
            }
        }

        if( isset( $_POST['fave_multi_units'] ) ) {
            $fave_multi_units = $_POST['fave_multi_units'];
            if( ! empty( $fave_multi_units ) ) {
                update_post_meta( $prop_id, 'fave_multi_units', $fave_multi_units );
            }
        }

        // Make featured
        if( isset( $_POST['prop_featured'] ) ) {
            $featured = intval( $_POST['prop_featured'] );
            update_post_meta( $prop_id, 'fave_featured', $featured );
        }

        // Private Note
        if( isset( $_POST['private_note'] ) ) {
            $private_note = wp_kses_post( $_POST['private_note'] );
            update_post_meta( $prop_id, 'fave_private_note', $private_note );
        }

        //Energy Class
        if(isset($_POST['energy_class'])) {
            $energy_class = sanitize_text_field($_POST['energy_class']);
            update_post_meta( $prop_id, 'fave_energy_class', $energy_class );
        }
        if(isset($_POST['energy_global_index'])) {
            $energy_global_index = sanitize_text_field($_POST['energy_global_index']);
            update_post_meta( $prop_id, 'fave_energy_global_index', $energy_global_index );
        }
        if(isset($_POST['renewable_energy_global_index'])) {
            $renewable_energy_global_index = sanitize_text_field($_POST['renewable_energy_global_index']);
            update_post_meta( $prop_id, 'fave_renewable_energy_global_index', $renewable_energy_global_index );
        }
        if(isset($_POST['energy_performance'])) {
            $energy_performance = sanitize_text_field($_POST['energy_performance']);
            update_post_meta( $prop_id, 'fave_energy_performance', $energy_performance );
        }


        // Property Payment
        if( isset( $_POST['prop_payment'] ) ) {
            $prop_payment = sanitize_text_field( $_POST['prop_payment'] );
            update_post_meta( $prop_id, 'fave_payment_status', $prop_payment );
        }


        if( isset( $_POST['fave_agent_display_option'] ) ) {

            $prop_agent_display_option = sanitize_text_field( $_POST['fave_agent_display_option'] );
            if( $prop_agent_display_option == 'agent_info' ) {

                $prop_agent = sanitize_text_field( $_POST['fave_agents'] );
                update_post_meta( $prop_id, 'fave_agent_display_option', $prop_agent_display_option );
                update_post_meta( $prop_id, 'fave_agents', $prop_agent );
                if (houzez_is_agency()) {
                    $user_agency_id = get_user_meta( $userID, 'fave_author_agency_id', true );
                    if( !empty($user_agency_id)) {
                        update_post_meta($prop_id, 'fave_property_agency', $user_agency_id);
                    }
                }

            } elseif( $prop_agent_display_option == 'agency_info' ) {

                $user_agency_id = get_user_meta( $userID, 'fave_author_agency_id', true );
                if( !empty($user_agency_id) ) {
                    update_post_meta($prop_id, 'fave_agent_display_option', $prop_agent_display_option);
                    update_post_meta($prop_id, 'fave_property_agency', $user_agency_id);
                } else {
                    update_post_meta( $prop_id, 'fave_agent_display_option', 'author_info' );
                }

            } else {
                update_post_meta( $prop_id, 'fave_agent_display_option', $prop_agent_display_option );
            }

        } else {
            if (houzez_is_agency()) {
                $user_agency_id = get_user_meta( $userID, 'fave_author_agency_id', true );
                if( !empty($user_agency_id) ) {
                    update_post_meta($prop_id, 'fave_agent_display_option', 'agency_info');
                    update_post_meta($prop_id, 'fave_property_agency', $user_agency_id);
                } else {
                    update_post_meta( $prop_id, 'fave_agent_display_option', 'author_info' );
                }

            } elseif(houzez_is_agent()){
                $user_agent_id = get_user_meta( $userID, 'fave_author_agent_id', true );

                if ( !empty( $user_agent_id ) ) {

                    update_post_meta($prop_id, 'fave_agent_display_option', 'agent_info');
                    update_post_meta($prop_id, 'fave_agents', $user_agent_id);

                } else {
                    update_post_meta($prop_id, 'fave_agent_display_option', 'author_info');
                }

            } else {
                update_post_meta($prop_id, 'fave_agent_display_option', 'author_info');
            }
        }

        // Address
        if( isset( $_POST['property_map_address'] ) ) {
            update_post_meta( $prop_id, 'fave_property_map_address', sanitize_text_field( $_POST['property_map_address'] ) );
            update_post_meta( $prop_id, 'fave_property_address', sanitize_text_field( $_POST['property_map_address'] ) );
        }

        if( ( isset($_POST['lat']) && !empty($_POST['lat']) ) && (  isset($_POST['lng']) && !empty($_POST['lng'])  ) ) {
            $lat = sanitize_text_field( $_POST['lat'] );
            $lng = sanitize_text_field( $_POST['lng'] );
            $streetView = sanitize_text_field( $_POST['prop_google_street_view'] );
            $lat_lng = $lat.','.$lng;

            update_post_meta( $prop_id, 'houzez_geolocation_lat', $lat );
            update_post_meta( $prop_id, 'houzez_geolocation_long', $lng );
            update_post_meta( $prop_id, 'fave_property_location', $lat_lng );
            update_post_meta( $prop_id, 'fave_property_map', '1' );
            update_post_meta( $prop_id, 'fave_property_map_street_view', $streetView );

        }
        // Country
        if( isset( $_POST['country_short'] ) ) {
            update_post_meta( $prop_id, 'fave_property_country', sanitize_text_field( $_POST['country_short'] ) );
        } else {
            $default_country = houzez_option('default_country');
            update_post_meta( $prop_id, 'fave_property_country', $default_country );
        }
        // Postal Code
        if( isset( $_POST['postal_code'] ) ) {
            update_post_meta( $prop_id, 'fave_property_zip', sanitize_text_field( $_POST['postal_code'] ) );
        }

    return $prop_id;
    }
}

function houzez_email_type( $email, $email_type, $args ) {

    $value_message = houzez_option('houzez_' . $email_type, '');
    $value_subject = houzez_option('houzez_subject_' . $email_type, '');

    if (function_exists('icl_translate')) {
        $value_message = icl_translate('houzez', 'houzez_email_' . $value_message, $value_message);
        $value_subject = icl_translate('houzez', 'houzez_email_subject_' . $value_subject, $value_subject);
    }

    if (strpos($value_subject, 'Your new listing on') !== false)
        $value_message = 'Hi there,
You have submitted new listing on  %website_url!
Listing Title: %listing_title
Listing ID:  %listing_id';

    houzez_emails_filter_replace( $email, $value_message, $value_subject, $args);
}

function houzez_delete_property()
{

    $nonce = $_REQUEST['security'];
    if ( ! wp_verify_nonce( $nonce, 'delete_my_property_nonce' ) ) {
        $ajax_response = array( 'success' => false , 'reason' => esc_html__( 'Security check failed!', 'houzez' ) );
        echo json_encode( $ajax_response );
        die;
    }

    if ( !isset( $_REQUEST['prop_id'] ) ) {
        $ajax_response = array( 'success' => false , 'reason' => esc_html__( 'No Property ID found', 'houzez' ) );
        echo json_encode( $ajax_response );
        die;
    }

    $propID = $_REQUEST['prop_id'];
    $post_author = get_post_field( 'post_author', $propID );

    global $current_user;
    wp_get_current_user();
    $userID      =   $current_user->ID;

    if ( $post_author == $userID ) {
        wp_delete_post( $propID );

        $listings = get_user_meta($userID, 'package_listings', true);

        if ($listings != '' && $listings != -1)
            update_user_meta($userID, 'package_listings', intval($listings) + 1);

        $ajax_response = array( 'success' => true , 'mesg' => esc_html__( 'Property Deleted', 'houzez' ) );
        echo json_encode( $ajax_response );
        die;
    } else {
        $ajax_response = array( 'success' => false , 'reason' => esc_html__( 'Permission denied', 'houzez' ) );
        echo json_encode( $ajax_response );
        die;
    }

}

?>
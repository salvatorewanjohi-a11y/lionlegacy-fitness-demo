<?php
/**
 * Builds the Lionlegacy Fitness store inside WordPress Playground.
 * Run by the blueprint after WooCommerce and the theme are installed.
 * Products come from products.json (next to this file), photos from /wordpress/llf-images.
 */
require_once '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

// The photos are already web-sized (720×900 webp), so skip making thumbnails of each one.
// The theme reads this option and serves the photos as they are. (Real hosting can regenerate thumbnails later.)
update_option( 'llf_fast_images', 'yes' );
add_filter( 'intermediate_image_sizes_advanced', '__return_empty_array' );
add_filter( 'big_image_size_threshold', '__return_false' );
add_filter( 'woocommerce_background_image_regeneration', '__return_false' );
add_filter( 'woocommerce_resize_images', '__return_false' );

// Store settings.
foreach ( [
	'blogname'                               => 'Lionlegacy Fitness',
	'blogdescription'                        => 'Supplements, calisthenics and gym equipment – College House, Koinange Street, Nairobi',
	'timezone_string'                        => 'Africa/Nairobi',
	'woocommerce_currency'                   => 'KES',
	'woocommerce_currency_pos'               => 'left_space',
	'woocommerce_price_num_decimals'         => '0',
	'woocommerce_price_thousand_sep'         => ',',
	'woocommerce_default_country'            => 'KE:KE30',
	'woocommerce_store_address'              => 'College House, Koinange Street',
	'woocommerce_store_city'                 => 'Nairobi',
	'woocommerce_email_from_name'            => 'Lionlegacy Fitness',
	'woocommerce_manage_stock'               => 'yes',
	'woocommerce_notify_low_stock_amount'    => '2',
	'woocommerce_notify_no_stock_amount'     => '0',
	'woocommerce_allowed_countries'          => 'specific',
	'woocommerce_specific_allowed_countries' => [ 'KE' ],
	'woocommerce_ship_to_countries'          => '',
	'woocommerce_enable_reviews'             => 'yes',
	'woocommerce_review_rating_verification_label' => 'yes',
	'woocommerce_onboarding_profile'         => [ 'skipped' => true ],
	'woocommerce_task_list_hidden'           => 'yes',
	'woocommerce_coming_soon'                => 'no',
	'woocommerce_checkout_phone_field'       => 'required',
	'woocommerce_enable_coupons'             => 'yes',
	// The goal filter queries the attribute terms directly, so it works the moment a product is saved.
	'woocommerce_attribute_lookup_enabled'   => 'no',
] as $k => $v ) {
	update_option( $k, $v );
}

// Remove sample content.
foreach ( get_posts( [ 'post_type' => [ 'post', 'page' ], 'name' => 'hello-world', 'numberposts' => 1 ] ) as $p ) wp_delete_post( $p->ID, true );
$sample = get_page_by_path( 'sample-page' );
if ( $sample ) wp_delete_post( $sample->ID, true );

// Categories (menu order = order in the header and on the home page).
$cat = [];
$i   = 0;
foreach ( [
	'proteins'          => [ 'Proteins & Mass Gainers', 'Whey, isolate, casein and beef protein, plus mass gainers for hard gainers. Genuine, sealed tubs at College House, Koinange Street.' ],
	'creatine-pre'      => [ 'Creatine & Pre-Workouts', 'Creatine monohydrate from every major brand, and pre-workouts for energy, focus and pump.' ],
	'burners-aminos'    => [ 'Fat Burners & Amino Acids', 'Fat burners and L-carnitine for your cut, and EAAs, aminos and hydration for recovery.' ],
	'vitamins-health'   => [ 'Vitamins & Health', 'Multivitamins, vitamin D3, fish oil, ZMB, herbal and testosterone support for people who train.' ],
	'calisthenics-gear' => [ 'Calisthenics & Lifting Gear', 'Parallettes and dip bars made by Lionlegacy Fitness, plus lifting belts, straps, wraps, gloves and knee sleeves.' ],
	'accessories'       => [ 'Accessories & Shakers', 'Lionlegacy Fitness steel water bottles and shaker bottles.' ],
] as $slug => [ $name, $desc ] ) {
	$t = term_exists( $slug, 'product_cat' ) ?: wp_insert_term( $name, 'product_cat', [ 'slug' => $slug, 'description' => $desc ] );
	$cat[ $slug ] = (int) $t['term_id'];
	update_term_meta( $cat[ $slug ], 'order', $i++ );
}

// Training goals: a filter on the shop pages and the "Shop by goal" tiles.
if ( ! wc_attribute_taxonomy_id_by_name( 'goal' ) ) {
	wc_create_attribute( [ 'name' => 'Training goal', 'slug' => 'goal', 'type' => 'select', 'order_by' => 'menu_order', 'has_archives' => false ] );
}
$goal_tax = wc_attribute_taxonomy_name( 'goal' );
if ( ! taxonomy_exists( $goal_tax ) ) {
	register_taxonomy( $goal_tax, [ 'product' ], [ 'hierarchical' => false, 'show_ui' => false, 'query_var' => true, 'rewrite' => false ] );
}
delete_transient( 'wc_attribute_taxonomies' );
$goal_ids = [];
$i        = 0;
foreach ( [
	'muscle-gain'  => 'Muscle Gain',
	'fat-loss'     => 'Fat Loss',
	'strength'     => 'Strength & Power',
	'endurance'    => 'Endurance & Energy',
	'recovery'     => 'Recovery & Hydration',
	'wellness'     => 'Health & Wellness',
	'calisthenics' => 'Home & Calisthenics',
] as $slug => $name ) {
	$t = term_exists( $slug, $goal_tax ) ?: wp_insert_term( $name, $goal_tax, [ 'slug' => $slug ] );
	$goal_ids[ $slug ] = (int) $t['term_id'];
	update_term_meta( $goal_ids[ $slug ], 'order', $i++ );
}

// Delivery. PLACEHOLDER prices until the shop confirms them.
$zone = new WC_Shipping_Zone();
$zone->set_zone_name( 'Nairobi' );
$zone->add_location( 'KE:KE30', 'state' );
$zone->save();
// Free delivery first, so it is picked by default once the cart qualifies.
$id = $zone->add_shipping_method( 'free_shipping' );
update_option( "woocommerce_free_shipping_{$id}_settings", [ 'title' => 'Free Nairobi doorstep delivery', 'requires' => 'min_amount', 'min_amount' => '10000' ] );
$id = $zone->add_shipping_method( 'flat_rate' );
update_option( "woocommerce_flat_rate_{$id}_settings", [ 'title' => 'Same-day rider express (Nairobi doorstep)', 'cost' => '300', 'tax_status' => 'none' ] );

$rest = new WC_Shipping_Zone();
$rest->set_zone_name( 'Rest of Kenya' );
$rest->add_location( 'KE', 'country' );
$rest->save();
$id = $rest->add_shipping_method( 'flat_rate' );
update_option( "woocommerce_flat_rate_{$id}_settings", [ 'title' => 'Upcountry courier parcel (Fargo / Wells Fargo, 1–2 days)', 'cost' => '400', 'tax_status' => 'none' ] );

update_option( 'woocommerce_pickup_location_settings', [ 'enabled' => 'yes', 'title' => 'Pick up at College House (free)', 'tax_status' => 'none', 'cost' => '' ] );
update_option( 'pickup_location_pickup_locations', [ [
	'name'    => 'Lionlegacy Fitness – College House',
	'address' => [ 'address_1' => 'College House, Koinange Street', 'city' => 'Nairobi CBD', 'state' => 'KE30', 'postcode' => '', 'country' => 'KE' ],
	'details' => 'Monday–Saturday, 9:00 AM – 6:00 PM. We will call you when your order is ready to collect.',
	'enabled' => true,
] ] );

// Payment: pay on delivery or at pickup until M-Pesa Express is connected.
update_option( 'woocommerce_cod_settings', [
	'enabled'            => 'yes',
	'title'              => 'Pay on delivery or pickup (M-Pesa or cash)',
	'description'        => 'Pay by M-Pesa or cash when your order arrives, or when you collect it at College House.',
	'instructions'       => 'We will call or WhatsApp you to confirm your order, delivery and time.',
	'enable_for_methods' => [],
	'enable_for_virtual' => 'yes',
] );

// Products: from the shop's WhatsApp catalogue, built by catalogue/build.mjs.
$products = json_decode( file_get_contents( __DIR__ . '/products.json' ), true );

// The photos are all 720×900 webp files made by catalogue/build.mjs, so they are copied straight into
// uploads and registered with their known size. (media_handle_sideload opens every image, which made
// the demo take several minutes to build.)
function llf_attach_images( $slug, $name ) {
	$files = glob( "/wordpress/llf-images/$slug*.webp" ) ?: [];
	$files = array_values( array_filter( $files, fn( $f ) => preg_match( '#/' . preg_quote( $slug, '#' ) . '(-\d+)?\.webp$#', $f ) ) );
	// slug.webp is the main photo, then slug-2.webp, slug-3.webp…
	$num = fn( $f ) => preg_match( '#-(\d+)\.webp$#', substr( $f, strlen( $slug ) ), $m ) ? (int) $m[1] : 1;
	usort( $files, fn( $a, $b ) => $num( basename( $a ) ) <=> $num( basename( $b ) ) );
	$up  = wp_upload_dir();
	$ids = [];
	foreach ( $files as $i => $file ) {
		$base = basename( $file );
		$dest = trailingslashit( $up['path'] ) . $base;
		if ( ! copy( $file, $dest ) ) {
			continue;
		}
		$size = @getimagesize( $dest ) ?: [ 720, 900 ];
		$att  = wp_insert_attachment( [
			'post_mime_type' => 'image/webp',
			'post_title'     => $name . ( $i ? ' – photo ' . ( $i + 1 ) : '' ),
			'post_status'    => 'inherit',
			'guid'           => trailingslashit( $up['url'] ) . $base,
		], $dest, 0, false, false );
		if ( ! $att ) {
			continue;
		}
		wp_update_attachment_metadata( $att, [ 'width' => $size[0], 'height' => $size[1], 'file' => _wp_relative_upload_path( $dest ), 'sizes' => [], 'image_meta' => [] ] );
		update_post_meta( $att, '_wp_attachment_image_alt', $name );
		$ids[] = $att;
	}
	return $ids;
}

// Count terms once at the end instead of after every product.
wp_defer_term_counting( true );
// One transaction for the whole import: SQLite otherwise commits (and syncs to disk) after every query.
wp_suspend_cache_invalidation( true );
$wpdb->query( 'START TRANSACTION' );
$started = microtime( true );
$timing  = [ 'images' => 0, 'save' => 0, 'terms' => 0, 'options' => 0 ];
$tick    = function ( $key, $t ) use ( &$timing ) { $timing[ $key ] += microtime( true ) - $t; return microtime( true ); };

$by_slug = [];
foreach ( $products as $order => $d ) {
	$variable = ! empty( $d['variations'] );
	$t        = microtime( true );
	$imgs     = llf_attach_images( $d['slug'], $d['name'] );
	$t        = $tick( 'images', $t );
	$p = $variable ? new WC_Product_Variable() : new WC_Product_Simple();
	$p->set_name( $d['name'] );
	$p->set_slug( $d['slug'] );
	$p->set_status( 'publish' );
	$p->set_menu_order( $order );
	$p->set_description( wp_kses_post( $d['desc'] ) );
	$p->set_short_description( wp_kses_post( $d['short'] ) );
	$p->set_category_ids( array_map( fn( $s ) => $cat[ $s ], $d['cat'] ) );
	$p->set_featured( ! empty( $d['featured'] ) );
	$p->update_meta_data( '_llf_spec', $d['badge'] );
	$p->update_meta_data( '_llf_specs', $d['specs'] );
	$p->update_meta_data( '_llf_type', $d['type'] );
	$p->update_meta_data( '_llf_made_to_order', ! empty( $d['madeToOrder'] ) ? 'yes' : 'no' );

	$attributes = [];
	// Options the customer picks (flavour, design, size).
	foreach ( $d['attributes'] ?? [] as $pos => $a ) {
		$attr = new WC_Product_Attribute();
		$attr->set_name( $a['name'] );
		$attr->set_options( $a['options'] );
		$attr->set_position( $pos );
		$attr->set_visible( true );
		$attr->set_variation( true );
		$attributes[] = $attr;
	}
	// Training goals (the goal filter and the stack builder read these).
	if ( $d['goals'] ) {
		$attr = new WC_Product_Attribute();
		$attr->set_id( wc_attribute_taxonomy_id_by_name( $goal_tax ) );
		$attr->set_name( $goal_tax );
		$attr->set_options( array_map( fn( $g ) => $goal_ids[ $g ], $d['goals'] ) );
		$attr->set_position( 9 );
		$attr->set_visible( false );
		$attr->set_variation( false );
		$attributes[] = $attr;
	}
	$p->set_attributes( $attributes );

	if ( ! $variable ) {
		$p->set_regular_price( $d['regular'] );
		if ( ! empty( $d['sale'] ) ) $p->set_sale_price( $d['sale'] );
		if ( empty( $d['madeToOrder'] ) ) {
			$p->set_manage_stock( true );
			$p->set_stock_quantity( 10 ); // PLACEHOLDER stock.
		}
	}
	if ( $imgs ) {
		$p->set_image_id( $imgs[0] );
		$p->set_gallery_image_ids( array_slice( $imgs, 1 ) );
	}
	if ( $variable ) {
		// Open on the first option, so the price and photo show straight away.
		$first = $d['variations'][0]['attrs'];
		$p->set_default_attributes( array_combine( array_map( 'sanitize_title', array_keys( $first ) ), array_values( $first ) ) );
	}
	$pid = $p->save();
	$t   = $tick( 'save', $t );
	wp_set_object_terms( $pid, array_map( fn( $g ) => $goal_ids[ $g ], $d['goals'] ), $goal_tax );

	if ( $d['brand'] && taxonomy_exists( 'product_brand' ) ) {
		wp_set_object_terms( $pid, $d['brand'], 'product_brand' );
	}
	$t = $tick( 'terms', $t );

	if ( $variable ) {
		foreach ( $d['variations'] as $n => $v ) {
			$var = new WC_Product_Variation();
			$var->set_parent_id( $pid );
			$var->set_menu_order( $n );
			$var->set_attributes( array_combine( array_map( 'sanitize_title', array_keys( $v['attrs'] ) ), array_values( $v['attrs'] ) ) );
			$var->set_regular_price( $v['regular'] );
			if ( $v['price'] < $v['regular'] ) $var->set_sale_price( $v['price'] );
			$var->set_manage_stock( true );
			$var->set_stock_quantity( 5 ); // PLACEHOLDER stock.
			if ( isset( $imgs[ $v['img'] ] ) ) $var->set_image_id( $imgs[ $v['img'] ] );
			$var->save();
		}
		WC_Product_Variable::sync( $pid );
	}
	$tick( 'options', $t );
	$by_slug[ $d['slug'] ] = $pid;
}

// "Frequently bought together" suggestions.
foreach ( $products as $d ) {
	if ( empty( $d['upsells'] ) ) continue;
	// Written as meta directly: a full product save per product here doubled the import time.
	update_post_meta( $by_slug[ $d['slug'] ], '_upsell_ids', array_values( array_filter( array_map( fn( $s ) => $by_slug[ $s ] ?? 0, $d['upsells'] ) ) ) );
}

$wpdb->query( 'COMMIT' );
wp_suspend_cache_invalidation( false );
wp_defer_term_counting( false );
update_option( 'llf_setup_seconds', round( microtime( true ) - $started, 1 ) . ' ' . wp_json_encode( array_map( fn( $v ) => round( $v, 1 ), $timing ) ) );

// Pages.
function llf_page( $slug, $title, $content ) {
	$existing = get_page_by_path( $slug );
	if ( $existing ) return $existing->ID;
	return wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $title, 'post_content' => $content ] );
}
llf_page( 'wishlist', 'Your wishlist', "<!-- wp:shortcode -->\n[llf_wishlist]\n<!-- /wp:shortcode -->" );
llf_page( 'stack-builder', 'Build your supplement stack', "<!-- wp:shortcode -->\n[llf_stack_builder]\n<!-- /wp:shortcode -->" );

update_option( 'permalink_structure', '/%postname%/' );
flush_rewrite_rules();

// Skip WooCommerce's first-run redirect and setup checklist so the admin opens on the store itself.
delete_transient( '_wc_activation_redirect' );
update_option( 'woocommerce_task_list_hidden_lists', [ 'setup', 'extended' ] );
update_option( 'woocommerce_task_list_complete', 'yes' );
update_option( 'woocommerce_show_marketplace_suggestions', 'no' );
update_option( 'woocommerce_admin_install_timestamp', time() - WEEK_IN_SECONDS );

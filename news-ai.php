<?php
/**
 * Plugin Name:       News AI
 * Plugin URI:        https://github.com/mybooam/news-ai
 * Description:       Auto-post berita terbaru seputar AI dari seluruh dunia (RSS), interval per menit.
 * Version:           1.1.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            mybooam
 * License:           GPL-2.0-or-later
 * Text Domain:       news-ai
 */

namespace NewsAI;

defined( 'ABSPATH' ) || exit;

require __DIR__ . '/includes/core.php';
if ( is_admin() ) {
	require __DIR__ . '/includes/admin.php';
}

function schedule() {
	if ( ! wp_next_scheduled( HOOK ) ) {
		wp_schedule_event( time() + 30, 'news_ai', HOOK );
	}
}

add_filter( 'cron_schedules', function ( $s ) {
	$m            = max( 1, (int) opts()['interval'] );
	$s['news_ai'] = [ 'interval' => $m * MINUTE_IN_SECONDS, 'display' => "News AI ($m menit)" ];
	return $s;
} );

add_action( HOOK, __NAMESPACE__ . '\run' );
add_action( 'init', __NAMESPACE__ . '\schedule' );
add_action( 'update_option_' . OPT, function () {
	wp_clear_scheduled_hook( HOOK );
	schedule();
} );

register_activation_hook( __FILE__, __NAMESPACE__ . '\schedule' );
register_deactivation_hook( __FILE__, function () {
	wp_clear_scheduled_hook( HOOK );
	delete_transient( 'news_ai_lock' );
} );

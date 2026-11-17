<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

wp_clear_scheduled_hook( 'news_ai_fetch' );
foreach ( [ 'news_ai_settings', 'news_ai_log', 'news_ai_feed_index' ] as $o ) {
	delete_option( $o );
}
delete_transient( 'news_ai_lock' );

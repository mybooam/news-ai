<?php
namespace NewsAI;

defined( 'ABSPATH' ) || exit;

const FIELDS = [
	'enabled'       => [ 'Aktifkan auto post', 'check' ],
	'interval'      => [ 'Interval (menit)', 'number', 1, 1440 ],
	'max_per_run'   => [ 'Maks. post per siklus', 'number', 1, 5 ],
	'max_age_hours' => [ 'Usia berita maks. (jam)', 'number', 1, 168 ],
	'excerpt_words' => [ 'Panjang ringkasan (kata)', 'number', 10, 200 ],
	'post_status'   => [ 'Status post', 'select', [ 'publish' => 'Terbit', 'draft' => 'Draf', 'pending' => 'Menunggu review' ] ],
	'category'      => [ 'Kategori', 'text' ],
	'author'        => [ 'ID penulis', 'number', 1, 999999 ],
	'keywords'      => [ 'Kata kunci (pisah koma, kosong = semua)', 'area', 3 ],
	'feeds'         => [ 'RSS feed (satu URL per baris)', 'area', 12 ],
];

function sanitize( $in ) {
	$d   = defaults();
	$out = [];
	foreach ( FIELDS as $k => $f ) {
		$v = $in[ $k ] ?? '';
		switch ( $f[1] ) {
			case 'check':
				$out[ $k ] = $v ? 1 : 0;
				break;
			case 'number':
				$out[ $k ] = min( $f[3], max( $f[2], (int) $v ) );
				break;
			case 'select':
				$out[ $k ] = isset( $f[2][ (string) $v ] ) ? $v : $d[ $k ];
				break;
			case 'text':
				$out[ $k ] = sanitize_text_field( $v );
				break;
			default:
				$out[ $k ] = sanitize_textarea_field( $v );
		}
	}
	$urls = array_map( function ( $u ) {
		return esc_url_raw( trim( $u ), [ 'http', 'https' ] );
	}, explode( "\n", $out['feeds'] ) );
	$out['feeds'] = implode( "\n", array_unique( array_filter( $urls ) ) );
	return $out;
}

function input( $k, $f, $v ) {
	$n = esc_attr( OPT . "[$k]" );
	switch ( $f[1] ) {
		case 'check':
			return "<input type='checkbox' name='$n' value='1'" . checked( $v, 1, false ) . '>';
		case 'number':
			return sprintf( "<input type='number' name='%s' value='%d' min='%d' max='%d'>", $n, $v, $f[2], $f[3] );
		case 'select':
			$h = "<select name='$n'>";
			foreach ( $f[2] as $a => $b ) {
				$h .= sprintf( "<option value='%s'%s>%s</option>", esc_attr( $a ), selected( $v, $a, false ), esc_html( $b ) );
			}
			return $h . '</select>';
		case 'text':
			return sprintf( "<input type='text' class='regular-text' name='%s' value='%s'>", $n, esc_attr( $v ) );
		default:
			return sprintf( "<textarea class='large-text code' rows='%d' name='%s'>%s</textarea>", $f[2], $n, esc_textarea( $v ) );
	}
}

function page() {
	$o    = opts();
	$next = wp_next_scheduled( HOOK );
	$log  = get_option( 'news_ai_log', [] );
	?>
	<div class="wrap">
		<h1>News AI</h1>

		<?php if ( isset( $_GET['ran'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo (int) $_GET['ran']; ?> berita baru diposting.</p></div>
		<?php endif; ?>

		<p>Eksekusi berikutnya: <strong><?php echo $next ? esc_html( wp_date( 'Y-m-d H:i:s', $next ) ) : 'belum terjadwal'; ?></strong></p>

		<form method="post" action="options.php">
			<?php settings_fields( 'news_ai' ); ?>
			<table class="form-table" role="presentation">
				<?php foreach ( FIELDS as $k => $f ) : ?>
					<tr><th><?php echo esc_html( $f[0] ); ?></th><td><?php echo input( $k, $f, $o[ $k ] ); ?></td></tr>
				<?php endforeach; ?>
			</table>
			<?php submit_button(); ?>
		</form>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="news_ai_run">
			<?php wp_nonce_field( 'news_ai_run' ); ?>
			<?php submit_button( 'Jalankan sekarang', 'secondary', 'submit', false ); ?>
		</form>

		<h2>Log</h2>
		<pre style="background:#fff;border:1px solid #ccd0d4;padding:10px;max-height:240px;overflow:auto"><?php echo $log ? esc_html( implode( "\n", $log ) ) : 'Belum ada log.'; ?></pre>
	</div>
	<?php
}

function run_now() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Tidak diizinkan.' );
	}
	check_admin_referer( 'news_ai_run' );
	wp_safe_redirect( add_query_arg( [ 'page' => 'news-ai', 'ran' => run( true ) ], admin_url( 'admin.php' ) ) );
	exit;
}

add_action( 'admin_menu', function () {
	add_menu_page( 'News AI', 'News AI', 'manage_options', 'news-ai', __NAMESPACE__ . '\page', 'dashicons-rss', 58 );
} );
add_action( 'admin_init', function () {
	register_setting( 'news_ai', OPT, [ 'sanitize_callback' => __NAMESPACE__ . '\sanitize' ] );
} );
add_action( 'admin_post_news_ai_run', __NAMESPACE__ . '\run_now' );

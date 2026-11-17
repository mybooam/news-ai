<?php
namespace NewsAI;

defined( 'ABSPATH' ) || exit;

const HOOK = 'news_ai_fetch';
const OPT  = 'news_ai_settings';

function gnews( $q, $hl, $gl, $ce ) {
	return 'https://news.google.com/rss/search?q=' . urlencode( "$q when:1d" ) . "&hl=$hl&gl=$gl&ceid=$ce";
}

function default_feeds() {
	$f = [
		'https://techcrunch.com/category/artificial-intelligence/feed/',
		'https://venturebeat.com/category/ai/feed/',
		'https://www.technologyreview.com/topic/artificial-intelligence/feed',
		'https://www.theverge.com/rss/ai-artificial-intelligence/index.xml',
	];
	$g = [
		[ 'artificial intelligence', 'en-US', 'US', 'US:en' ],
		[ 'artificial intelligence', 'en-GB', 'GB', 'GB:en' ],
		[ 'artificial intelligence', 'en-IN', 'IN', 'IN:en' ],
		[ 'artificial intelligence', 'en-AU', 'AU', 'AU:en' ],
		[ 'kecerdasan buatan', 'id', 'ID', 'ID:id' ],
		[ 'Künstliche Intelligenz', 'de', 'DE', 'DE:de' ],
		[ 'intelligence artificielle', 'fr', 'FR', 'FR:fr' ],
		[ 'inteligencia artificial', 'es-419', 'MX', 'MX:es-419' ],
		[ 'inteligência artificial', 'pt-BR', 'BR', 'BR:pt-419' ],
		[ '人工知能', 'ja', 'JP', 'JP:ja' ],
		[ '인공지능', 'ko', 'KR', 'KR:ko' ],
		[ '人工智能', 'zh-CN', 'CN', 'CN:zh-Hans' ],
	];
	foreach ( $g as $a ) {
		$f[] = gnews( ...$a );
	}
	return implode( "\n", $f );
}

function defaults() {
	return [
		'enabled'       => 1,
		'interval'      => 1,
		'max_per_run'   => 1,
		'max_age_hours' => 24,
		'excerpt_words' => 60,
		'post_status'   => 'publish',
		'category'      => 'Berita AI',
		'author'        => 1,
		'keywords'      => 'AI, artificial intelligence, machine learning, LLM, OpenAI, ChatGPT, Gemini, Claude, Anthropic, kecerdasan buatan, intelligence artificielle, Künstliche Intelligenz, inteligencia artificial, inteligência artificial, 人工知能, 人工智能, 인공지능',
		'feeds'         => default_feeds(),
	];
}

function opts() {
	return wp_parse_args( get_option( OPT, [] ), defaults() );
}

function note( $m ) {
	$l = get_option( 'news_ai_log', [] );
	array_unshift( $l, current_time( 'Y-m-d H:i:s' ) . " $m" );
	update_option( 'news_ai_log', array_slice( $l, 0, 30 ), false );
}

function txt( $s ) {
	return trim( wp_strip_all_tags( html_entity_decode( (string) $s, ENT_QUOTES, 'UTF-8' ) ) );
}

// Kata ASCII pendek (mis. "AI") dicocokkan sebagai kata utuh, sisanya substring.
function hit( $text, $kws ) {
	$kws = array_filter( array_map( 'trim', explode( ',', $kws ) ) );
	if ( ! $kws ) {
		return true;
	}
	foreach ( $kws as $k ) {
		if ( strlen( $k ) <= 3 && ctype_alnum( $k ) ) {
			$ok = preg_match( '/(?<![\p{L}\p{N}])' . preg_quote( $k, '/' ) . '(?![\p{L}\p{N}])/iu', $text );
		} else {
			$ok = false !== mb_stripos( $text, $k );
		}
		if ( $ok ) {
			return true;
		}
	}
	return false;
}

function seen( $hashes ) {
	return (bool) get_posts( [
		'post_status' => 'any',
		'numberposts' => 1,
		'fields'      => 'ids',
		'meta_query'  => [ [ 'key' => '_news_ai_hash', 'value' => $hashes, 'compare' => 'IN' ] ],
	] );
}

function cat_id( $name ) {
	if ( ! $name ) {
		return 0;
	}
	$t = term_exists( $name, 'category' ) ?: wp_insert_term( $name, 'category' );
	return is_wp_error( $t ) ? 0 : (int) ( is_array( $t ) ? $t['term_id'] : $t );
}

function save( $title, $desc, $link, $src, $ts, $o ) {
	$ex   = wp_trim_words( $desc, $o['excerpt_words'], '…' );
	$body = ( $ex ? '<p>' . esc_html( $ex ) . '</p>' : '' )
		. '<p><em>Sumber: ' . esc_html( $src ) . '</em> &mdash; '
		. '<a href="' . esc_url( $link ) . '" target="_blank" rel="nofollow noopener">Baca selengkapnya</a></p>';

	$post = [
		'post_title'    => $title,
		'post_content'  => $body,
		'post_excerpt'  => $ex,
		'post_status'   => $o['post_status'],
		'post_author'   => $o['author'],
		'post_category' => array_filter( [ cat_id( $o['category'] ) ] ),
	];
	if ( $ts && $ts <= time() ) {
		$post['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', $ts );
		$post['post_date']     = get_date_from_gmt( $post['post_date_gmt'] );
	}

	$id = wp_insert_post( $post, true );
	if ( is_wp_error( $id ) ) {
		note( $id->get_error_message() );
		return 0;
	}
	return $id;
}

function pull( $url, $o, $max ) {
	$feed = fetch_feed( $url );
	if ( is_wp_error( $feed ) ) {
		note( "$url: " . $feed->get_error_message() );
		return 0;
	}

	$old = time() - $o['max_age_hours'] * HOUR_IN_SECONDS;
	$n   = 0;

	foreach ( $feed->get_items( 0, 25 ) as $it ) {
		if ( $n >= $max ) {
			break;
		}
		$link  = esc_url_raw( $it->get_permalink() );
		$title = txt( $it->get_title() );
		$ts    = (int) $it->get_date( 'U' );
		if ( ! $link || ! $title || ( $ts && $ts < $old ) ) {
			continue;
		}

		$desc = txt( $it->get_description() );
		$h    = [ md5( $link ), md5( mb_strtolower( $title ) ) ];
		if ( ! hit( "$title $desc", $o['keywords'] ) || seen( $h ) ) {
			continue;
		}

		$src  = $it->get_source();
		$name = ( $src ? $src->get_title() : '' ) ?: $feed->get_title() ?: wp_parse_url( $link, PHP_URL_HOST );

		$id = save( $title, $desc, $link, $name, $ts, $o );
		if ( $id ) {
			foreach ( $h as $x ) {
				add_post_meta( $id, '_news_ai_hash', $x );
			}
			$n++;
		}
	}
	return $n;
}

// Satu siklus: maks. 3 feed bergantian (round-robin), cache feed 5 menit.
function run( $force = false ) {
	$o    = opts();
	$urls = array_values( array_filter( array_map( 'trim', explode( "\n", $o['feeds'] ) ) ) );
	if ( ! $urls || ( ! $force && ! $o['enabled'] ) || get_transient( 'news_ai_lock' ) ) {
		return 0;
	}
	set_transient( 'news_ai_lock', 1, 120 );
	require_once ABSPATH . WPINC . '/feed.php';

	$ttl = function () {
		return 300;
	};
	add_filter( 'wp_feed_cache_transient_lifetime', $ttl );

	$n    = count( $urls );
	$i    = (int) get_option( 'news_ai_feed_index', 0 );
	$done = 0;
	for ( $k = 0; $k < min( 3, $n ) && $done < $o['max_per_run']; $k++ ) {
		$done += pull( $urls[ ( $i + $k ) % $n ], $o, $o['max_per_run'] - $done );
	}

	remove_filter( 'wp_feed_cache_transient_lifetime', $ttl );
	update_option( 'news_ai_feed_index', ( $i + $k ) % $n, false );
	delete_transient( 'news_ai_lock' );

	if ( $done ) {
		note( "$done berita baru" );
	}
	return $done;
}

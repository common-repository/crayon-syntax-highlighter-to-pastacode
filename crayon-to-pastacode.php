<?php

namespace Wabeo\C2p;

/**
 * Plugin name: Crayon Syntax Highlighter to Pastacode
 * Plugin URI: http://pastacode.wabeo.fr
 * Description: The only use of this plugin is to convert Crayon Syntax Highlighter's tags into Pastacode shortcodes.
 * Author: Willy Bahuaud
 * Author uri: https://wabeo.fr
 * Version: 1.0
 * Contributors: willybahuaud
 * Text Domain: crayon-highlighter-to-pastacode
 * Domain Path: /languages
 */

define( 'CSH_2_PASTACODE_VERSION', '1.0' );

add_action( 'plugins_loaded', '\Wabeo\C2p\load_languages' );
function load_languages() {
    load_plugin_textdomain( 'crayon-highlighter-to-pastacode', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}

/**
 * 1. Ajout de menu
 */
add_action( 'admin_menu', '\Wabeo\C2p\menu' );
function menu() {
	add_management_page( __( 'Migrate from Crayon Syntax Highlighter to Pastacode', 'crayon-highlighter-to-pastacode' ), __( 'Migrate to Pastacode', 'crayon-highlighter-to-pastacode' ), 'manage_options', 'migrate_c2p', '\Wabeo\C2p\migration_page' );
}

/**
 * 2. Formulaire d'import
 */
function migration_page() {
	$log = get_option( '_c2p_log', array() );
	echo '<div class="wrap">';
		screen_icon( 'options-general' );
		echo '<h2>' . __( 'Migrate from Crayon Syntax Highlighter to Pastacode', 'crayon-highlighter-to-pastacode' ) . '</h2>';
		echo '<form method="post" action="' . admin_url( 'admin-post.php' ) . '" id="c2p-migration">';
		echo '<input type="hidden" name="action" value="c2p-process-migration"/>';
			echo '<p>';
			submit_button( __( 'Detect and replace old tags', 'crayon-highlighter-to-pastacode' ), 'primary', 'submit', false );
		echo ' <em>(' . __( 'The operation is reversible', 'crayon-highlighter-to-pastacode' ) . ')</em></p>';
		echo '</form>';
		echo '<div class="widefat" id="c2p-content-infos"><p>'
			. nl2br( implode( PHP_EOL, $log ) )
			. '</p>' 
			. '</div>';
		echo '<p id="c2p-erase-log" ' . ( empty( $log ) ? 'style="display:none;"' : '' ) . '><a href="' . admin_url( 'admin-post.php?action=c2p-delete-logs') . '" class="button button-primary primary">' . __( 'Validate the conversion and remove the logs and backups', 'crayon-highlighter-to-pastacode' ) . '</a></p>';
	echo '</div>';

	wp_enqueue_script( 'wabeo-c2p-migration' );
	wp_localize_script( 'wabeo-c2p-migration', 'c2p_nonce', wp_create_nonce( 'migrate-since-' . $_SERVER['REMOTE_ADDR']  ) );
}

/**
 * Register scripts
 */
add_action( 'admin_enqueue_scripts', '\Wabeo\C2p\register_scripts' );
function register_scripts() {
	wp_register_script( 'wabeo-c2p-migration', plugins_url( 'script.js', __FILE__ ), array( 'jquery' ), CSH_2_PASTACODE_VERSION, true );
}

add_action( 'wp_ajax_c2p-process-migration', '\Wabeo\C2p\process_migration' );
function process_migration() {
	if ( current_user_can( 'manage_options' )
	  && wp_verify_nonce( $_POST['nonce'], 'migrate-since-' . $_SERVER['REMOTE_ADDR'] ) ) {
		$log = get_option( '_c2p_log', array() );
		$args = array(
			'suppress_filters'       => false,
			'no_found_rows'          => false,
			'update_post_term_cache' => false,
			'post_type'              => apply_filters( 'c2p_posttype_to_migrate', array( 'post', 'page' ) ),
			'posts_per_page'         => 40,
			'meta_query'             => array(
				's2p_found' => array(
					'key'     => '_c2p-found',
					'compare' => 'NOT EXISTS',
					),
				),
			);
		$contents = get_posts( $args );
		$data = fetch_post_contents( $contents );
		$log = $log + $data;
		update_option( '_c2p_log', $log );
		wp_send_json_success( $data );
	}
	wp_send_json_error();
}

add_action( 'admin_post_c2p-delete-logs', '\Wabeo\C2p\delete_logs' );
function delete_logs() {
	if ( current_user_can( 'manage_options' ) ) {
		delete_metadata( 'post', null, '_c2p-found', null, true );
		delete_metadata( 'post', null, '_cp2-old-content', null, true );
		delete_option( '_c2p_log' );
		wp_redirect( admin_url( 'tools.php?page=migrate_c2p' ) );
	}
	exit();
}

add_action( 'admin_post_restore-c2p-migration', '\Wabeo\C2p\restore_content' );
function restore_content() {
	$id = intval( $_GET['id'] );
	$log = get_option( '_c2p_log' );
	if ( $id
	  && current_user_can( 'manage_options' )
	  && in_array( $id, array_keys( $log ) )
	  && in_array( get_post_type( $id ), apply_filters( 'c2p_posttype_to_migrate', array( 'post', 'page' ) ) ) ) {

		$old_content = get_post_meta( $id, '_c2p-old-content', true );
	  	$update = wp_update_post( array(
	  	    'ID' => $id,
	  	    'post_content' => $old_content,
	  	), true );
	  	if ( ! is_wp_error( $update ) ) {
			delete_post_meta( $id, '_c2p-old-content' );
			delete_post_meta( $id, '_c2p-found' );
			$log[ $id ] = sprintf( __( '[%1$s] %2$s : old tags restored.', 'crayon-highlighter-to-pastacode' ), get_post_type( $id ), get_the_title( $id ) );
		}
		update_option( '_c2p_log', $log );
	}
	wp_redirect( admin_url( 'tools.php?page=migrate_c2p' ) );
	exit();
}

function fetch_post_contents( $contents ) {
	$out = array();

	$pastacode_pattern = get_shortcode_regex( array( 'pastacode' ) );

	foreach ( $contents as $post ) {

		// Mask Pastacode Shortcodes
		$content = preg_replace_callback( "/$pastacode_pattern/", '\Wabeo\C2p\mask_pastacode_sc', $post->post_content );

		$csh_pattern = "/<pre([^>]*)>([\\s\\S]*?)<\\/pre>/mi"; 

		if ( preg_match_all( $csh_pattern, $content, $tags ) ) {

			// Do something…
			$content = preg_replace_callback( $csh_pattern, '\Wabeo\C2p\replace_callback', $content );

			// Restore Pastacode Shortcodes
			$content = preg_replace_callback( '/CSH_2_PCSCWRAPPER(.*)CSH_2_PCSCWRAPPEREND/', '\Wabeo\C2p\restore_pastacode_sc', $content );

			$update = wp_update_post( array(
				'ID'           => $post->ID,
				'post_content' => $content,
				), true );

			$liens = ' <a href="' . get_edit_post_link( $post->ID ) . '" target="_blank">' . __( 'Verify', 'crayon-highlighter-to-pastacode' ) . '</a> ' 
				. '| <a href="' . admin_url( 'admin-post.php?action=restore-c2p-migration&id=' . $post->ID ) . '" target="_blank">' . __( 'Restore', 'crayon-highlighter-to-pastacode' ) . '</a>';

			if ( ! is_wp_error( $update ) ) {
				update_post_meta( $post->ID, '_c2p-old-content', $post->post_content );
				update_post_meta( $post->ID, '_c2p-found', 'tags' );

				$out[ $post->ID ] = sprintf( __( '[%1$s] %2$s : tags finds and replaced. %3$s', 'syntax-highlighter-to-pastacode' ), $post->post_type, $post->post_title, $liens );
			} else {
				$out[ $post->ID ] = sprintf( __( '[%1$s] %2$s : tags finds, but update failed. %3$s', 'syntax-highlighter-to-pastacode' ), $post->post_type, $post->post_title, $liens );
			}
				
		} else {
			// Do nothing…
			$out[ $post->ID ] = sprintf( __( '[%1$s] %2$s : no tag need to be converted.', 'crayon-highlighter-to-pastacode' ), $post->post_type, $post->post_title );
			update_post_meta( $post->ID, '_c2p-found', 0 );
		}
	}
	return $out;
}

function mask_pastacode_sc( $matches ) {
	$balises = 'CSH_2_PCSCWRAPPER%sCSH_2_PCSCWRAPPEREND';
	return sprintf( $balises, base64_encode( $matches[0] ) );
}

function restore_pastacode_sc( $matches ) {
	return base64_decode( $matches[1] );
}

function replace_callback( $matches ) {

	$atts = array();

	if ( preg_match( '/class="([^"]+)"/', $matches[1], $classes ) ) {
		$classes = array_filter( explode( ' ', trim( $classes[1] ) ) );
		foreach ( $classes as $class ) {
			$class = explode( ':', $class );
			$atts[ $class[0] ] = $class[1];
		}
	}

	if ( preg_match( '/data-url="([^"]+)"/', $matches[1], $url ) ) {
		$atts['url'] = $url[1];
		$sc_atts = shortcode_distant( $atts, $matches );
	} else {
		$sc_atts = shortcode_manuel( $atts, $matches[2] );
	}

	if ( is_array( $sc_atts ) ) {
		$sc_atts = array_map( function( $value, $key ) {
		    return $key . '="' . $value . '"';
		}, array_values( $sc_atts ), array_keys( $sc_atts ) );

		$pastacode = sprintf( '[pastacode %s/]', implode( ' ', $sc_atts ) );
	} else {
		$pastacode = $sc_atts;
	}

	return $pastacode;
}

function shortcode_manuel( $atts, $code ) {
	$sc_atts = array();
	$code = htmlspecialchars_decode( $code );
	$code = wrap_code( $code );
	$sc_atts['manual']   = $code;
	$sc_atts['provider'] = 'manual';
	$sc_atts['lang']     = get_lang( $atts['lang'] );

	if ( isset( $atts['mark'] ) ) {
		$sc_atts['highlight'] = esc_attr( $atts['mark'] );
	}

	if ( isset( $atts['title'] ) ) {
		$sc_atts['message'] = esc_attr( $atts['title'] );
	}

	return $sc_atts;
}

function shortcode_distant( $atts, $matches ) {
	$sc_atts = array();
	$providers = array(
		// 1: user
		// 2: path_id
		// 3: revision
		// 4: file
		'gist' => array( "/https:\\/\\/gist\\.githubusercontent\\.com\\/([^\\/]+)\\/([^\\/]+)\\/raw\\/([^\\/]+)\\/(.*)$/mi", 'gist', 'user', 'path_id', 'revision', 'file' ), 

		// 1: user
		// 2: repos
		// 3: revision
		// 4: path_id
		'github' => array( "/https:\\/\\/raw\\.githubusercontent\\.com\\/([^\\/]+)\\/([^\\/]+)\\/([^\\/]+)\\/(.*)$/mi", 'github', 'user', 'repos', 'revision', 'path_id' ),
		
		// 1: user
		// 2: repos
		// 3: revision
		// 4: path_id
		'bitbucket' => array( "/https:\\/\\/bitbucket\\.org\\/api\\/[0-9.]+\\/repositories\\/([^\\/]+)\\/([^\\/]+)\\/raw\\/([^\\/]+)\\/(.*)$/mi", 'bitbucket', 'user', 'repos', 'revision', 'path_id' ),
		'bitbucket2' => array( "/https:\\/\\/bitbucket\\.org\\/([^\\/]+)\\/([^\\/]+)\\/raw\\/([^\\/]+)\\/(.*)$/", 'bitbucket', 'user', 'repos', 'revision', 'path_id' ),

		// 1: user
		// 2: path_id
		// 3: revision
		// 4: file
		'bitbucket_snippets' => array( "/https:\\/\\/bitbucket\\.org\\/!api\\/[0-9.]+\\/snippets\\/([^\\/]+)\\/([^\\/]+)\\/([^\\/]+)\\/files\\/(.*)$/mi", 'bitbucketsnippets', 'user', 'path_id', 'revision', 'file' ),

		// 1: path_id
		'pastebin' => array( "/http:\\/\\/pastebin\\.com\\/raw\\/(.*)$/mi", 'pastebin', 'path_id' ), 
	);

	if ( isset( $atts['mark'] ) ) {
		$sc_atts['highlight'] = esc_attr( $atts['mark'] );
	}

	if ( isset( $atts['range'] ) ) {
		$sc_atts['lines'] = esc_attr( $atts['range'] );
	}

	$sc_atts['lang'] = get_lang( $atts['lang'] );

	$found = false;
	foreach ( $providers as $provider ) {
		if ( preg_match( $provider[0], $atts['url'], $infos ) ) {
			$sc_atts['provider'] = $provider[1];
			for ( $i=2; $i < count( $provider ); $i++ ) { 
				$sc_atts[ $provider[ $i ] ] = $infos[ $i-1 ];
			}
			$found = true;
			break;
		}
	}

	if ( $found ) {
		return $sc_atts;
	} else {
		return $matches[0];
	}
}

function wrap_code( $code ) {
    $revert = array( '%21'=> '!', '%2A'=> '*', '%27'=> "'", '%28'=> '(', '%29'=>')' );
    return strtr( rawurlencode( $code ), $revert );
}

function get_lang( $lang ) {
	switch ( $lang ) {
		case '':
			return 'php';
			break;
		case 'js':
		case 'jscript':
			return 'javascript';
			break;
		case 'ps':
		case 'powershell':
		case 'shell':
			return 'bash';
			break;
		case 'rails':
		case 'ror':
		case 'rb':
			return 'ruby';
			break;
		case 'xml':
		case 'xhtml':
		case 'xslt':
		case 'html':
		case 'xhtml':
		case 'plain':
			return 'markup';
			break;
		default:
			return $lang;
	}
}

<?php
/**
 * Plugin Name: Silent Publish
 * Version:     2.8
 * Plugin URI:  http://coffee2code.com/wp-plugins/silent-publish/
 * Author:      Scott Reilly
 * Author URI:  http://coffee2code.com/
 * Text Domain: silent-publish
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Description: Adds the ability to publish a post without triggering pingbacks, trackbacks, or notifying update services.
 *
 * Compatible with WordPress 4.6+ through 5.3+.
 *
 * =>> Read the accompanying readme.txt file for instructions and documentation.
 * =>> Also, visit the plugin's homepage for additional information and updates.
 * =>> Or visit: https://wordpress.org/plugins/silent-publish/
 *
 * @package Silent_Publish
 * @author  Scott Reilly
 * @version 2.8
 */

/*
	Copyright (c) 2009-2020 by Scott Reilly (aka coffee2code)

	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

defined( 'ABSPATH' ) or die();

if ( ! class_exists( 'c2c_SilentPublish' ) ) :

class c2c_SilentPublish {

	/**
	 * The name of the associated form field.
	 *
	 * @access private
	 * @var string
	 */
	private static $field = 'silent_publish';

	/**
	 * Prevents instantiation.
	 *
	 * @since 2.6
	 */
	private function __construct() {}

	/**
	 * Prevents unserializing an instance.
	 *
	 * @since 2.6
	 */
	private function __wakeup() {}

	/**
	 * Returns version of the plugin.
	 *
	 * @since 2.2.1
	 */
	public static function version() {
		return '2.8';
	}

	/**
	 * Initializer.
	 */
	public static function init() {
		// Load textdomain.
		load_plugin_textdomain( 'silent-publish' );

		// Register hooks.
		add_action( 'post_submitbox_misc_actions', array( __CLASS__, 'add_ui' ) );
		add_filter( 'save_post',                   array( __CLASS__, 'save_silent_publish_status' ), 2, 3 );
		add_action( 'publish_post',                array( __CLASS__, 'publish_post' ), 1, 1 );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_block_editor_assets' )   );
		add_filter( 'post_date_column_time',       array( __CLASS__, 'add_icon_to_post_date_column' ), 10, 4 );
		add_action( 'init',                        array( __CLASS__, 'register_meta' ) );
		add_action( 'quick_edit_custom_box',       array( __CLASS__, 'add_to_quick_edit' ), 10, 2 );
		add_action( 'admin_enqueue_scripts',       array( __CLASS__, 'admin_enqueue_scripts' ) );
	}

	/**
	 * Registers the post meta field.
	 *
	 * @since 2.7
	 */
	public static function register_meta() {
		$config = array(
			'type'              => 'boolean',
			'description'       => __( 'Publish the post silently?', 'silent-publish' ),
			'single'            => true,
			'sanitize_callback' => function ( $value ) {
				return (bool) $value;
			},
			'auth_callback'     => function() {
				return current_user_can( 'edit_posts' );
			},
			'show_in_rest'      => true,
		);

		if ( function_exists( 'register_post_meta' ) ) {
			// @todo Support non-"post" post types.
			register_post_meta( 'post', self::get_meta_key_name(), $config );
		}
		// Pre WP 4.9.8 support
		else {
			register_meta( 'post', self::get_meta_key_name(), $config );
		}
	}

	/**
	 * Outputs an icon next to post's 'Date' column field value if post was
	 * or will be silently published.
	 *
	 * @since 2.8
	 *
	 * @param string  $t_time      The published time.
	 * @param WP_Post $post        Post object.
	 * @param string  $column_name The column name.
	 * @param string  $mode        The list display mode ('excerpt' or 'list').
	 */
	public static function add_icon_to_post_date_column( $h_time, $post, $column_name, $mode ) {
		echo $h_time;

		if ( get_post_meta( $post->ID, self::get_meta_key_name(), true ) ) {
			if ( 'publish' === $post->post_status ) {
				$msg = __( 'Post was silently published.', 'silent-publish' );
			} else {
				$msg = __( 'Post will be silently published.', 'silent-publish' );
			}

			printf(
				' <span class="%s dashicons dashicons-controls-volumeoff" title="%s"></span>',
				esc_attr( self::$field ),
				esc_attr( $msg )
			);
		}
	}

	/**
	 * Enqueues JavaScript and CSS for the block editor.
	 *
	 * @since 2.7
	 */
	public static function enqueue_block_editor_assets() {
		global $post;

		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		if ( ! self::is_silent_publish_enabled( $post ) ) {
			return;
		}

		// If post is already published and was not published silently, no need to
		// show empty checkbox.
		if ( ! $post || ( 'publish' === $post->post_status && ! self::is_silent_published() ) ) {
			return;
		}

		wp_enqueue_script(
			'silent-publish-js',
			plugins_url( 'assets/js/editor.js', __FILE__ ),
			array(
				'wp-components',
				'wp-data',
				'wp-edit-post',
				'wp-editor',
				'wp-element',
				'wp-i18n',
				'wp-plugins',
			),
			self::version(),
			true
		);

		wp_enqueue_style(
			'silent-publish',
			plugins_url( 'assets/css/editor.css', __FILE__ ),
			array(),
			self::version()
		);

		//if ( function_exists( 'wp_set_script_translations' ) ) {
		//	wp_set_script_translations( 'silent-publish-js', 'silent-publish-js', \dirname( __DIR__ ) . '/languages' );
		//}
	}

	/**
	 * Returns post types that can be silently published.
	 *
	 * By default, all public post types are included (except 'attachment').
	 *
	 * @since 2.8
	 * @uses apply_filters() Calls 'c2c_silent_publish_post_types' with silently publishable post types.
	 *
	 * @return array
	 */
	public static function get_post_types() {
		$post_types = get_post_types( array( 'public' => true ) );

		unset( $post_types['attachment'] );

		/**
		 * Filters the post types that can be silently published.
		 *
		 * @since 2.8
		 *
		 * @param array $post_types Array of post type names.
		 */
		return (array) apply_filters( 'c2c_silent_publish_post_types', array_values( $post_types ) );
	}

	/**
	 * Determines if silent publishing is enabled for the given post or post
	 * type.
	 *
	 * @since 2.8
	 *
	 * @param WP_Post|int|string The ppst object, post ID, or post type name.
	 * @return bool True if silent publishing is enabled, false otherwise.
	 */
	public static function is_silent_publish_enabled( $post = null ) {
		if ( is_string( $post ) ) {
			$post_type = $post;
		} else {
			$post_type = get_post_type( $post );
		}

		if ( ! $post_type ) {
			return false;
		}

		$post_types = self::get_post_types();

		return in_array( $post_type, $post_types );
	}

	/**
	 * Determines if silent publish should be enabled for posts by default.
	 *
	 * Note: This only indicates if the silent publish checkbox should be
	 * checked by default. It does not reflect whether the post has actually
	 * been, or is set to be, silent published.
	 *
	 * @since 2.7
	 * @uses apply_filters() Calls 'c2c_silent_publish_default' with silent publish state default.
	 *
	 * @return bool True if the silent publish is enabled for a post by default,
	 *              otherwise false. Default false.
	 */
	public static function is_silent_publish_on_by_default() {
		global $post;

		/**
		 * Filters the default state for the silent publish checkbox.
		 *
		 * By default, the checkbox is not checked.
		 *
		 * @since 2.6
		 *
		 * @param bool    $default True if the silent publish checkbox should be
		 *                         checked by default, otherwise false. Default false.
		 * @param WP_Post $post    The post.
		 */
		return (bool) apply_filters( 'c2c_silent_publish_default', false, $post );
	}

	/**
	 * Determines if the post was, or will be, silent published.
	 *
	 * Note: This does not take into account whether the post is actually
	 * published or not, just whether it has been saved with silent
	 * publish checked.
	 *
	 * @since 2.8
	 *
	 * @param WP_Post|int|null The ppst object, post ID, or null (to mean the
	 *                         current post.
	 * @return bool True if post is, or will be, silent published, false otherwise.
	 */
	public static function is_silent_published( $post = null ) {
		$post = get_post ( $post );

		if ( ! $post ) {
			return false;
		}

		return (bool) get_post_meta( $post->ID, self::get_meta_key_name(), true );
	}

	/**
	 * Returns the name of the meta key.
	 *
	 * @since 2.6
	 * @uses apply_filters() Calls 'c2c_silent_publish_meta_key' with default meta key name.
	 *
	 * @return string
	 */
	public static function get_meta_key_name() {
		// Default value.
		$meta_key = '_silent-publish';

		/**
		 * Filters the name of the custom field key used by the plugin to store a
		 * post's silten publish status.
		 *
		 * @since 2.0
		 * @deprecated 2.3
		 *
		 * @param string $meta_key The name of the meta key used for storing the
		 *                         value of the post's silent publish status.
		 */
		$meta_key = apply_filters_deprecated( 'silent_publish_meta_key', array( $meta_key ), '2.3.0', 'c2c_silent_publish_meta_key' );

		/**
		 * Filters the name of the custom field key used by the plugin to store a
		 * post's silent publish status.
		 *
		 * Use a blank string to disable saving the silent publish status in a
		 * custom field.
		 *
		 * @since 2.3
		 *
		 * @param string $meta_key The name of the meta key used for storing the
		 *                         value of the post's silent publish status. If
		 *                         blank, then the status is not saved. Default
		 *                         is '_silent-publish'.
		 */
		$meta_key = apply_filters( 'c2c_silent_publish_meta_key', $meta_key );

		return $meta_key;
	}

	/**
	 * Outputs the UI to prompt user if silent publish should be enabled for the post.
	 *
	 * Displays the UI outright if the post is not published. If published, it either
	 * displays disabled when the meta field has a value, or not at all.
	 *
	 * @since 2.0
	 */
	public static function add_ui() {
		if ( ! self::is_silent_publish_enabled() ) {
			return;
		}

		$silent_published = self::is_silent_published();

		self::output_field( array(
			'disable'        => 'publish' === get_post_status(),
			'published'      => $silent_published,
			'silent_publish' => self::is_silent_publish_on_by_default() || $silent_published,
		) );
	}

	/**
	 * Outputs the markup for the input field(s).
	 *
	 * @since 2.8
	 *
	 * @param array $args {
	 *     Array of settings.
	 *
	 *     @type bool $silent_publish Should the silent publish checkbox be
	 *                                checked? Defaultfalse.
	 *     @type bool $disable        Should the silent publish field be
	 *                                disabled? Note that the actual display of
	 *                                the field if disabled may not simply be a
	 *                                disabled checkbox if post is published.
	 *                                Default false.
	 *     @type bool $published      Is the post published? If true, this
	 *                                influences if the a disabled checkbox
	 *                                instead shows a message. Default false.
	 *     @type bool $show_all       Show the silent published message *and*
	 *                                the input fields? When used for a specific
	 *                                post this is undesired, but is useful for
	 *                                the quick edit panel so that both are
	 *                                available to be reused. Default false.
	 * }
	 */
	public static function output_field( $args = array() ) {
		$defaults = array(
			'disable'        => false,
			'published'      => false,
			'show_all'       => false,
			'silent_publish' => false,
		);

		$args = wp_parse_args( (array) $args, $defaults );

		// Show a message instead of a disabled checkbox when showing all or if
		// post was silently published.
		if ( $args['show_all'] || $args['disable'] ) {
			if ( $args['show_all'] || ( $args['disable'] && $args['published'] ) ) {
				$style = $args['show_all'] ? ' style="display:none;"' : '';

				printf(
					'<div class="misc-pub-section c2c-silent-published"%s><em>%s</em></div>',
					$style,
					__( 'This post was silently published.', 'silent-publish' )
				);
			}

			// Bail unless intentionally showing both fields.
			if ( ! $args['show_all'] ) {
				return;
			}
		}

		printf(
			'<div class="misc-pub-section"><label class="selectit c2c-silent-publish%s" for="%s" title="%s">' . "\n",
			$args['disable'] ? ' c2c-silent-published-disabled' : '',
			esc_attr( self::$field ),
			esc_attr__( 'If checked, upon publication of this post do not perform any pingbacks, trackbacks, or update service notifications.', 'silent-publish' )
		);

		// Output nonce.
		printf( '<input type="hidden" name="_%1$s_nonce" value="%2$s" />', self::$field, wp_create_nonce( self::$field ) );

		// Output input field.
		printf(
			'<input id="%1$s" type="checkbox" %2$s %3$s value="1" name="%4$s" />' . "\n",
			esc_attr( self::$field ),
			disabled( $args['disable'], true, false ),
			checked( $args['silent_publish'], true, false ),
			esc_attr( self::$field )
		);

		_e( 'Silent publish?', 'silent-publish' );
		echo '</label></div>' . "\n";
	}

	/**
	 * Enqueues the admin JS.
	 *
	 * @since 2.8
	 *
	 * @param string $hook_name The hook (aka page) name.
	 */
	public static function admin_enqueue_scripts( $hook_name ) {
		if ( 'edit.php' !== $hook_name ) {
			return;
		}

		wp_enqueue_script( self::$field, plugins_url( 'assets/js/quick-edit.js', __FILE__ ), array( 'jquery' ), self::version(), true );
	}

	/**
	 * Adds the checkbox to the quick edit panel.
	 *
	 * @since 2.8
	 *
	 * @param string $column_name Name of the column being output to quick edit.
	 * @param string $post_type   The post type of the post.
	 */
	public static function add_to_quick_edit( $column_name, $post_type ) {
		if ( did_action( 'quick_edit_custom_box' ) > 1 ) {
			return;
		}

		self::output_field( array( 'show_all' => true ) );
	}

	/**
	 * Updates the value of the silent publish custom field.
	 *
	 * @since 2.0
	 *
	 * @param int     $post_ID Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an existing post being updated or not.
	 *
	 * @return array The unmodified $data.
	 */
	public static function save_silent_publish_status( $post_id, $post, $update ) {
		if ( ! self::is_silent_publish_enabled( $post ) ) {
			return;
		}

		$meta_key = self::get_meta_key_name();

		// Bail if no meta key name.
		if ( ! $meta_key ) {
			return;
		}

		// Bail if doing an autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Bail if not POST or no nonce provided.
		if ( ! $_POST || empty( $_POST[ '_' . self::$field . '_nonce' ] ) ) {
			return;
		}

		// Bail if nonce check fails.
		if ( ! wp_verify_nonce( $_POST[ '_' . self::$field . '_nonce' ], self::$field ) ) {
			return;
		}

		// Bail if a post revision.
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Bail if an attachment.
		if ( 'attachment' === get_post_type( $post ) ) {
			return;
		}

		// Bail if auto-draft or trashed post.
		if ( in_array( get_post_status( $post ), array( 'auto-draft', 'trash' ) ) ) {
			return;
		}

		// Update the value of the silent publish custom field.
		if ( ! empty( $_POST[ self::$field ] ) ) {
			update_post_meta( $post_id, $meta_key, 1 );
		} else {
			delete_post_meta( $post_id, $meta_key );
		}
	}

	/**
	 * Handles silent publishing if the associated checkbox is checked.
	 *
	 * Saves the fact this post was silently published.
	 * This does not attempt to clear this value if the post later gets republished without silent publishing.
	 * Also, this stored value is not currently used, merely saved.
	 *
	 * @since 1.0
	 *
	 * @param int $post_id Post ID.
	 */
	public static function publish_post( $post_id ) {
		if ( ! self::is_silent_publish_enabled( $post_id ) ) {
			return;
		}

		$meta_key = self::get_meta_key_name();

		// Bail if no meta key name.
		if ( ! $meta_key ) {
			return;
		}

		// Potentially handle post updates happening during publish.
		// Note: needed since post transition occurs before 'save_post' action.
		self::save_silent_publish_status( $post_id, get_post( $post_id ), true );

		// Should the post be published silently?
		if ( self::is_silent_published( $post_id ) ) {
			// Unhook the action responsible for handling pings and enclosures for post.
			remove_action( 'publish_post', '_publish_post_hook', 5, 1 );
		}
		// Potentially restore default action that may have been removed.
		elseif ( ! has_action( 'publish_post', '_publish_post_hook', 5, 1 ) ) {
			add_action( 'publish_post', '_publish_post_hook', 5, 1 );
		}
	}

} // end c2c_SilentPublish

add_action( 'plugins_loaded', array( 'c2c_SilentPublish', 'init' ) );

endif; // end if !class_exists()

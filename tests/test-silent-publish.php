<?php

defined( 'ABSPATH' ) or die();

class Silent_Publish_Test extends WP_UnitTestCase {

	protected $field    = 'silent_publish';
	protected $meta_key = '_silent-publish';
	protected $nonce    = '_silent_publish_nonce';

	private   $hooked   = -1;

	public function setUp() {
		parent::setUp();

		c2c_SilentPublish::register_meta();
	}

	public function tearDown() {
		parent::tearDown();

		$this->hooked = -1;

		add_action( 'publish_post', '_publish_post_hook', 5, 1 );

		remove_filter( 'c2c_silent_publish_meta_key', array( $this, 'c2c_silent_publish_meta_key' ) );
		remove_filter( 'c2c_silent_publish_meta_key', '__return_empty_string' );
		remove_filter( 'c2c_silent_publish_default',  '__return_true' );
		remove_action( 'publish_post',                array( $this, 'check_publish_post_hook' ), 4, 1 );
		remove_action( 'publish_post',                array( $this, 'check_publish_post_hook' ), 6, 1 );
	}


	//
	//
	// HELPER FUNCTIONS
	//
	//


	public function c2c_silent_publish_meta_key( $key ) {
		return '_new-key';
	}

	public function create_post( $status = 'publish', $silently_publish = false, $post_type = 'post' ) {
		global $post;

		$post_id = $this->factory->post->create( array( 'post_status' => $status, 'post_type' => $post_type ) );

		if ( $silently_publish ) {
			add_post_meta( $post_id, $this->meta_key, '1' );
		}

		$post = get_post( $post_id );

		return $post;
	}

	public function check_publish_post_hook( $post_id ) {
		$this->hooked = has_action( 'publish_post', '_publish_post_hook', 5, 1 ) ? 1 : 2;
	}

	/**
	 * Asserts the output matches the expected output.
	 *
	 * @param string $output            The output of the form markup for Silent Publish.
	 * @param bool   $is_silent_publish Is the value of the silent publish checkbox checked?
	 * @param bool   $is_published      Is the post published?
	 */
	public function assert_form_output( $output, $is_silent_publish, $is_published ) {
		$expected = '';

		if ( ! $is_silent_publish && $is_published ) {
			return $expected;
		}

		$expected .= sprintf(
			'<div class="misc-pub-section"><label class="selectit c2c-silent-publish" for="%1$s" title="%2$s"%3$s>' . "\n",
			esc_attr( $this->field ),
			esc_attr__( 'If checked, upon publication of this post do not perform any pingbacks, trackbacks, or update service notifications.', 'silent-publish' ),
			$is_published ? ' style="opacity:.7"' : ''
		);

		$expected .= sprintf( '<input type="hidden" name="_%1$s_nonce" value="%2$s" />', $this->field, wp_create_nonce( $this->field ) );

		// Output input field.
		$expected .= sprintf(
			'<input id="%1$s" type="checkbox" %2$s %3$s value="1" name="%4$s" />' . "\n",
			esc_attr( $this->field ),
			disabled( $is_published, true, false ),
			checked( true, (bool) $is_silent_publish, false ),
			esc_attr( $this->field )
		);

		$expected .= __( 'Silent publish?', 'silent-publish' );
		$expected .= '</label></div>' . "\n";

		$this->assertEquals( $expected, $output );
	}

	//
	//
	// TESTS
	//
	//


	public function test_class_exists() {
		$this->assertTrue( class_exists( 'c2c_SilentPublish' ) );
	}

	public function test_version() {
		$this->assertEquals( '2.7', c2c_SilentPublish::version() );
	}

	public function test_plugins_loaded_action_triggers_do_init() {
		$this->assertEquals( 10, has_filter( 'plugins_loaded', array( 'c2c_SilentPublish', 'init' ) ) );
	}

	public function test_post_submitbox_misc_action_triggers_add_ui() {
		$this->assertEquals( 10, has_action( 'post_submitbox_misc_actions', array( 'c2c_SilentPublish', 'add_ui' ) ) );
	}

	public function test_save_post_filter_triggers_save_silent_publish_status() {
		$this->assertEquals( 2, has_filter( 'save_post', array( 'c2c_SilentPublish', 'save_silent_publish_status' ) ) );
	}

	public function test_publish_post_action_triggers_publish_post() {
		$this->assertEquals( 1, has_action( 'publish_post', array( 'c2c_SilentPublish', 'publish_post' ) ) );
	}

	public function test_quick_edit_custom_box_triggers_add_to_quick_edit() {
		$this->assertEquals( 10, has_action( 'quick_edit_custom_box', array( 'c2c_SilentPublish', 'add_to_quick_edit' ) ) );
	}

	public function test_admin_enqueue_scripts_triggers_admin_enqueue_scripts() {
		$this->assertEquals( 10, has_action( 'admin_enqueue_scripts', array( 'c2c_SilentPublish', 'admin_enqueue_scripts' ) ) );
	}

	/*
	 * get_post_types()
	 */

	public function test_get_post_types( $extra_post_types = array() ) {
		$expected = array_merge( array( 'post', 'page' ), $extra_post_types );

		$this->assertEquals( $expected, c2c_SilentPublish::get_post_types() );
	}

	public function test_c2c_silent_publish_post_types_with_custom_post_type() {
		register_post_type( 'private', array( 'public' => false, 'name' => 'Private' ) );
		register_post_type( 'book', array( 'public' => true, 'name' => 'Book' )	);

		$this->test_get_post_types( array( 'book' ) );
	}

	/*
	 * filter: c2c_silent_publish_post_types
	 */

	 public function test_filter_c2c_silent_publish_post_types() {
		register_post_type( 'private', array( 'public' => false, 'name' => 'Private' ) );
		register_post_type( 'book', array( 'public' => true, 'name' => 'Book' )	);

		add_filter( 'c2c_silent_publish_post_types', function ( $p ) {
			$p[] = 'private';
			$p = array_flip( $p );
			unset( $p[ 'book' ] );
			return array_values( array_flip( $p ) );
		} );

		$this->test_get_post_types( array( 'private' ) );
	}

	public function test_non_silently_published_post_publishes_without_silencing() {
		$post_id = $this->factory->post->create( array( 'post_status' => 'draft' ) );

		wp_publish_post( $post_id );

		$this->assertFalse( metadata_exists( 'post', $post_id, $this->meta_key ) );
		$this->assertEquals( 5, has_action( 'publish_post', '_publish_post_hook', 5, 1 ) );

		return $post_id;
	}

	public function test_saving_post_set_as_silently_published_retains_meta() {
		$post_id = $this->factory->post->create();
		update_post_meta( $post_id, $this->meta_key, '1' );

		$this->assertTrue( metadata_exists( 'post', $post_id, $this->meta_key ) );

		$post = get_post( $post_id, ARRAY_A );
		$_POST[ $this->field ] = '1';
		wp_update_post( $post );

		$this->assertTrue( metadata_exists( 'post', $post_id, $this->meta_key ) );
		$this->assertEquals( '1', get_post_meta( $post_id, $this->meta_key, true ) );
	}

	public function test_saving_post_without_being_silently_published_deletes_meta() {
		$post_id = $this->factory->post->create();
		update_post_meta( $post_id, $this->meta_key, '1' );

		$this->assertTrue( metadata_exists( 'post', $post_id, $this->meta_key ) );
		$this->assertEquals( '1', get_post_meta( $post_id, $this->meta_key, true ) );

		$post = get_post( $post_id, ARRAY_A );
		// Simulate a POST.
		$_POST[ $this->nonce ] = wp_create_nonce( $this->field );
		wp_update_post( $post );

		$this->assertFalse( metadata_exists( 'post', $post_id, $this->meta_key ) );
	}

	public function test_saving_post_explicitly_not_being_silently_published_deletes_meta() {
		$post_id = $this->factory->post->create();
		update_post_meta( $post_id, $this->meta_key, '1' );

		$this->assertTrue( metadata_exists( 'post', $post_id, $this->meta_key ) );
		$this->assertEquals( '1', get_post_meta( $post_id, $this->meta_key, true ) );

		$post = get_post( $post_id, ARRAY_A );
		// Simulate a POST.
		$_POST[ $this->nonce ] = wp_create_nonce( $this->field );
		wp_update_post( $post );

		$this->assertFalse( metadata_exists( 'post', $post_id, $this->meta_key ) );
		$this->assertEquals( 5, has_action( 'publish_post', '_publish_post_hook', 5, 1 ) );
	}

	/*
	 * get_meta_key_name()
	 */

	public function test_get_meta_key_name() {
		$this->assertEquals( '_silent-publish', c2c_SilentPublish::get_meta_key_name() );
	}

	public function test_meta_key_is_registered() {
		$this->assertTrue( registered_meta_key_exists( 'post', c2c_SilentPublish::get_meta_key_name(), 'post' ) );
	}

	public function test_filtered_get_meta_key_name() {
		add_filter( 'c2c_silent_publish_meta_key', array( $this, 'c2c_silent_publish_meta_key' ) );

		$this->assertEquals( '_new-key', c2c_SilentPublish::get_meta_key_name() );
	}

	public function test_empty_get_meta_key_name() {
		add_filter( 'c2c_silent_publish_meta_key', '__return_empty_string' );

		$this->assertEmpty( c2c_SilentPublish::get_meta_key_name() );
	}

	public function test_silently_published_post_publishes_silently() {
		$post_id = $this->factory->post->create( array( 'post_status' => 'draft' ) );

		// Publishing assumes it's coming from the edit page UI where the
		// checkbox is present to set the $_POST array element to trigger
		// silent update
		$_POST[ $this->field ] = '1';
		$_POST[ $this->nonce ] = wp_create_nonce( $this->field );

		wp_publish_post( $post_id );

		$this->assertTrue( metadata_exists( 'post', $post_id, $this->meta_key ) );
		$this->assertEquals( '1', get_post_meta( $post_id, $this->meta_key, true ) );
		$this->assertFalse( has_action( 'publish_post', '_publish_post_hook', 5, 1 ) );

		return $post_id;
	}

	public function test_silently_published_post_via_meta_on_draft_publishes_silently() {
		$post_id = $this->factory->post->create( array( 'post_status' => 'draft' ) );
		update_post_meta( $post_id, $this->meta_key, '1' );

		wp_publish_post( $post_id );

		$this->assertTrue( metadata_exists( 'post', $post_id, $this->meta_key ) );
		$this->assertEquals( '1', get_post_meta( $post_id, $this->meta_key, true ) );
		$this->assertFalse( has_action( 'publish_post', '_publish_post_hook', 5, 1 ) );
	}

	public function test_previously_silently_published_post_can_be_republished_without_silence() {
		$post_id = $this->test_silently_published_post_publishes_silently();

		// Publishing assumes it's coming from the edit page UI where the
		// checkbox is present to set the $_POST array element to trigger
		// silent update
		unset( $_POST[ $this->field ] );
		$_POST[ $this->nonce ] = wp_create_nonce( $this->field );

		$post = get_post( $post_id, ARRAY_A );
		$post['post_status'] = 'draft';
		$post_id = wp_update_post( $post, true );

		wp_publish_post( $post_id );

		$this->assertFalse( metadata_exists( 'post', $post_id, $this->meta_key ) );
		$this->assertEquals( 5, has_action( 'publish_post', '_publish_post_hook', 5, 1 ) );
	}

	/*
	 * add_ui()
	 */

	public function test_form_elements_are_output_for_unpublished_post() {
		$this->create_post( 'draft', false );

		ob_start();
		c2c_SilentPublish::add_ui();
		$output = ob_get_contents();
		ob_end_clean();

		$this->assertNotEmpty( $output );
		$this->assert_form_output( $output, false, false );
	}

	public function test_form_elements_are_output_for_unpublished_post_with_meta_set() {
		$this->create_post( 'draft', true );

		ob_start();
		c2c_SilentPublish::add_ui();
		$output = ob_get_contents();
		ob_end_clean();

		$this->assertNotEmpty( $output );
		$this->assert_form_output( $output, true, false );
	}

	public function test_add_ui_when_post_not_published_but_silent_publish_enabled() {
		$this->create_post( 'draft', true );

		ob_start();
		c2c_SilentPublish::add_ui();
		$output = ob_get_contents();
		ob_end_clean();

		$this->assertNotEmpty( $output );
		$this->assert_form_output( $output, true, false );
	}

	public function test_add_ui_when_post_was_silently_published() {
		$this->create_post( 'publish', true );

		$this->expectOutputRegex(
			'~^' . preg_quote( '<div class="misc-pub-section c2c-silent-published"><em>This post was silently published.</em></div>' ) . '$~',
			c2c_SilentPublish::add_ui()
		);
	}

	public function test_add_ui_when_post_was_published_and_not_silently() {
		$this->create_post( 'publish', false );

		$this->expectOutputRegex( '/^$/', c2c_SilentPublish::add_ui() );
	}

	public function test_add_ui_when_post_was_published_and_not_silently_and_silent_default_is_true() {
		$this->test_filter_c2c_silent_publish_default();

		$this->create_post( 'publish', false );

		$this->expectOutputRegex( '/^$/', c2c_SilentPublish::add_ui() );
	}

	public function test_add_ui_when_post_type_is_not_supported() {
		register_post_type(
			'sample',
			array(
				'public'             => false,
				'label'              => 'Sample',
			)
		);
		$this->create_post( 'draft', false, 'sample' );

		$this->expectOutputRegex( '/^$/', c2c_SilentPublish::add_ui() );
	}

	/*
	 * output_field()
	 */

	public function test_output_field_default() {
		ob_start();
		c2c_SilentPublish::output_field();
		$output = ob_get_contents();
		ob_end_clean();

		$this->assertNotEmpty( $output );
		$this->assert_form_output( $output, false, false );

	}

	public function test_output_field_silent_publish_and_disable_unpublished() {
		$this->expectOutputRegex(
			'~^$~',
			c2c_SilentPublish::output_field( array( 'silent_publish' => true, 'disable' => true, 'published' => false ) )
		);
	}

	public function test_output_field_silent_publish_and_disable_published() {
		$this->expectOutputRegex(
			'~^' . preg_quote( '<div class="misc-pub-section c2c-silent-published"><em>This post was silently published.</em></div>' ) . '$~',
			c2c_SilentPublish::output_field( array( 'silent_publish' => true, 'disable' => true, 'published' => true ) )
		);
	}

	public function test_output_field_silent_publish_and_enabled() {
		$this->expectOutputRegex(
			'~' . preg_quote( '<input id="silent_publish" type="checkbox"   checked=\'checked\' value="1" name="silent_publish" />' ) . '~',
			c2c_SilentPublish::output_field( array( 'silent_publish' => true, 'disable' => false ) )
		);
	}

	public function test_output_field_no_silent_publish_and_disabled_unpublished() {
		$this->expectOutputRegex(
			'~^$~',
			c2c_SilentPublish::output_field( array( 'silent_publish' => false, 'disable' => true ) )
		);
	}

	public function test_output_field_no_silent_publish_and_disabled_published() {
		$this->expectOutputRegex(
			'~^' . preg_quote( '<div class="misc-pub-section c2c-silent-published"><em>This post was silently published.</em></div>' ) . '$~',
			c2c_SilentPublish::output_field( array( 'silent_publish' => false, 'disable' => true, 'published' => true ) )
		);
	}

	public function test_output_field_no_silent_publish_and_enabled() {
		$this->expectOutputRegex(
			'~' . preg_quote( '<input id="silent_publish" type="checkbox"   value="1" name="silent_publish" />' ) . '~',
			c2c_SilentPublish::output_field( array( 'silent_publish' => false, 'disable' => false ) )
		);
	}

	public function test_output_field_has_nonce() {
		$this->expectOutputRegex(
			'~' . preg_quote( '<input type="hidden" name="_silent_publish_nonce" value="' ) . '[a-zA-Z0-9]+' . preg_quote( '" />' ) . '~',
			c2c_SilentPublish::output_field( array( 'silent_publish' => false, 'disable' => false ) )
		);
	}

	public function test_output_field_overall_markup() {
		$this->expectOutputRegex(
			'~' . preg_quote( sprintf(
				'<div class="misc-pub-section"><label class="selectit c2c-silent-publish" for="silent_publish" title="%s">' . "\n",
				'If checked, upon publication of this post do not perform any pingbacks, trackbacks, or update service notifications.'
				) )
			// Nonce
			. '\<input type\="hidden".+/\>'
			// Input field
			. '\<input id\=".+/\>' . "\n"
			// Label text and end tags
			. preg_quote( 'Silent publish?</label></div>' . "\n" )
			. '~',
			c2c_SilentPublish::output_field()
		);
	}

	// Currently disabled checkbox cannot be output.
	//public function test_output_field_has_disabled_class_when_disabled() {
	//	$this->expectOutputRegex(
	//		'~' . preg_quote( '<div class="misc-pub-section"><label class="selectit c2c-silent-publish c2c-silent-published" for="silent_publish"' ) . '~',
	//		c2c_SilentPublish::output_field( array( 'silent_publish' => false, 'disable' => true ) )
	//	);
	//}

	/*
	 * is_silent_publish_on_by_default()
	 */

	public function test_is_silent_publish_on_by_default() {
		$this->assertFalse( c2c_SilentPublish::is_silent_publish_on_by_default() );
	}

	public function test_is_silent_publish_on_by_default_not_affected_by_silent_publish_state() {
		global $post;
		$post_id = $this->factory->post->create();
		update_post_meta( $post_id, $this->meta_key, '1' );
		$post = get_post( $post_id );

		$this->assertFalse( c2c_SilentPublish::is_silent_publish_on_by_default() );
	}

	public function test_filter_c2c_silent_publish_default() {
		add_filter( 'c2c_silent_publish_default', '__return_true' );

		$this->assertTrue( c2c_SilentPublish::is_silent_publish_on_by_default() );
	}

	/*
	 * is_silent_published()
	 */

	 public function test_is_silent_published() {
		global $post;
		$post_id = $this->factory->post->create();
		$post = get_post( $post_id );

		$this->assertFalse( c2c_SilentPublish::is_silent_published() );
	 }

	public function test_is_silent_published_for_silent_published_post() {
		global $post;
		$post_id = $this->factory->post->create();
		update_post_meta( $post_id, $this->meta_key, '1' );
		$post = get_post( $post_id );

		$this->assertTrue( c2c_SilentPublish::is_silent_published() );
	}

	public function test_is_silent_published_unaffected_by_silent_publish_default() {
		$this->test_filter_c2c_silent_publish_default();

		global $post;
		$post_id = $this->factory->post->create();
		$post = get_post( $post_id );

		$this->assertFalse( c2c_SilentPublish::is_silent_published() );
	}

	public function test_is_silent_published_with_post_id_as_arg() {
		// Create a global post that is silent published.
		$this->test_is_silent_published_for_silent_published_post();

		$post_id = $this->factory->post->create();

		$this->assertFalse( c2c_SilentPublish::is_silent_published( $post_id ) );
	}

	public function test_is_silent_published_with_post_id_as_arg_and_global_post_not_silent_published() {
		// Create a global post that is not silent published.
		$this->test_is_silent_published();

		$post_id = $this->factory->post->create();
		update_post_meta( $post_id, $this->meta_key, '1' );
		$post = get_post( $post_id );

		$this->assertTrue( c2c_SilentPublish::is_silent_published( $post ) );
	}

	public function test_is_silent_published_with_post_as_arg() {
		// Create a global post that is silent published.
		$this->test_is_silent_published_for_silent_published_post();

		$post_id = $this->factory->post->create();
		$post = get_post( $post_id );

		$this->assertFalse( c2c_SilentPublish::is_silent_published( $post ) );
	}

	public function test_is_silent_published_with_invalid_arg() {
		// Create a global post that is silent published.
		$this->test_is_silent_published_for_silent_published_post();

		$this->assertFalse( c2c_SilentPublish::is_silent_published( 'gibberish' ) );
	}

	/*
	 * add_icon_to_post_date_column()
	 */

	public function test_add_icon_to_post_date_column_on_draft_not_silent() {
		$post = $this->create_post( 'draft', false );
		$time = 'Last Modified<br>2020/01/18<span title="2020/01/18 1:50:47 am"></span>';

		$this->expectOutputRegex(
			'~^' . preg_quote( $time ) . '$~',
			c2c_SilentPublish::add_icon_to_post_date_column( $time, $post, 'Date', 'list' )
		);
	}

	public function test_add_icon_to_post_date_column_on_draft_silent() {
		$post = $this->create_post( 'draft', true );

		$time = 'Last Modified<br>2020/01/18<span title="2020/01/18 1:50:47 am"></span>';

		$this->expectOutputRegex(
			'~^' . $time . ' <span class="silent_publish dashicons dashicons-controls-volumeoff" title="Post will be silently published."></span>' . '$~',
			c2c_SilentPublish::add_icon_to_post_date_column( $time, $post, 'Date', 'list' )
		);
	}

	public function test_add_icon_to_post_date_column_on_publish_not_silent() {
		$post = $this->create_post( 'publish', false );
		$time = 'Published<br>2020/01/18<span title="2020/01/18 1:50:47 am"></span>';

		$this->expectOutputRegex(
			'~^' . preg_quote( $time ) . '$~',
			c2c_SilentPublish::add_icon_to_post_date_column( $time, $post, 'Date', 'list' )
		);
	}

	public function test_add_icon_to_post_date_column_on_publish_silent() {
		$post = $this->create_post( 'publish', true );
		$time = 'Published<br>2020/01/18<span title="2020/01/18 1:50:47 am"></span>';

		$this->expectOutputRegex(
			'~^' . $time . ' <span class="silent_publish dashicons dashicons-controls-volumeoff" title="Post was silently published."></span>' . '$~',
			c2c_SilentPublish::add_icon_to_post_date_column( $time, $post, 'Date', 'list' )
		);
	}

	/*
	 * admin_enqueue_scripts()
	 */

	public function test_admin_enqueue_scripts_not_enqueued_by_default() {
		$this->assertFalse( wp_script_is( $this->field, 'enqueued' ) );
	}

	public function test_admin_enqueue_scripts_not_enqueued_for_wrong_admin_page() {
		c2c_SilentPublish::admin_enqueue_scripts( 'plugins.php' );
		$this->assertFalse( wp_script_is( $this->field, 'enqueued' ) );
	}

	public function test_admin_enqueue_scripts_enqueued_on_proper_admin_page() {
		c2c_SilentPublish::admin_enqueue_scripts( 'edit.php' );
		$this->assertTrue( wp_script_is( $this->field, 'enqueued' ) );
	}

	/*
	 * add_to_quick_edit()
	 */

	public function test_add_to_quick_edit_outputs_on_invocation() {
		$this->expectOutputRegex( '/^\<div class="misc-pub-section c2c-silent-published".+$/m', c2c_SilentPublish::add_to_quick_edit( 'author', 'post' ) );
	}

	public function test_add_to_quick_edit_does_not_output_on_subsequent_invocation() {
		$GLOBALS['wp_actions'][ 'quick_edit_custom_box' ] = 2;
		$this->expectOutputRegex( '/^$/m', c2c_SilentPublish::add_to_quick_edit( 'author', 'post' ) );
	}

	/*
	 * Check filter gets unhooked.
	 */

	public function test_default_behavior() {
		add_action( 'publish_post', array( $this, 'check_publish_post_hook' ), 4, 1 );

		$post_id = $this->test_non_silently_published_post_publishes_without_silencing();

		do_action( 'publish_post', $post_id );

		$this->assertEquals( 1, $this->hooked );
	}

	public function test_it_works() {
		add_action( 'publish_post', array( $this, 'check_publish_post_hook' ), 4, 1 );

		$post_id = $this->test_silently_published_post_publishes_silently();

		do_action( 'publish_post', $post_id );

		$this->assertEquals( 2, $this->hooked );
	}

}

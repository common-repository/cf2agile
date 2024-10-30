<?php

abstract class cf2Agile_Field {

	protected $_tag_name = null;
	protected $_tag_title = null;

	/**
	 * Register field type
	 */
	public function __construct() {
		add_action( 'wpcf7_init', array( $this, 'add_form_tag' ) );
		add_action( 'wpcf7_admin_init', array( $this, 'add_tag_generator' ) );

		add_filter( "wpcf7_validate_{$this->_tag_name}", array( $this, 'validation_filter' ), 10, 2 );
		add_filter( "wpcf7_validate_{$this->_tag_name}*", array( $this, 'validation_filter' ), 10, 2 );
	}

	/**
	 * Register filed on frontend
	 */
	public function add_form_tag() {
		wpcf7_add_form_tag( array( $this->_tag_name, "{$this->_tag_name}*" ), array( $this, 'form_tag_handler' ), array( 'name-attr' => true ) );
	}

	/**
	 * Render filed on frontend
	 *
	 * @param WPCF7_FormTag $tag
	 * @return string
	 */
	abstract public function form_tag_handler( $tag );

	/**
	 * Validate field data
	 *
	 * @param WPCF7_Validation $result
	 * @param WPCF7_FormTag $tag
	 * @return WPCF7_Validation
	 */
	abstract public function validation_filter( $result, $tag );

	/**
	 * Add button in Admin Panel
	 */
	public function add_tag_generator() {
		WPCF7_TagGenerator::get_instance()->add( $this->_tag_name, $this->_tag_title, array( $this, 'tag_generator_callback' ) );
	}

	/**
	 * Render popup content in admin panel when click on tag button
	 *
	 * @param mixed $contact_form
	 * @param array $args
	 */
	abstract public function tag_generator_callback( $contact_form, $args = array() );

}

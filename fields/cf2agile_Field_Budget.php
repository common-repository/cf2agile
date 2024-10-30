<?php

class cf2agile_Field_Budget extends cf2Agile_Field {

	/**
	 * @inheritdoc
	 */
	public function __construct() {
		$this->_tag_name = 'cf2agile__project_budget';
		$this->_tag_title = __( 'Project Budget', 'cf2agile' );

		parent::__construct();
	}

	/**
	 * @inheritdoc
	 */
	public function form_tag_handler( $tag ) {
		if ( empty( $tag->name ) ) {
			return '';
		}

		$atts = array();
		$validation_error = wpcf7_get_validation_error( $tag->name );
		$class = wpcf7_form_controls_class( $tag->type, 'wpcf7-text' );

		if ( $validation_error ) {
			$class .= ' wpcf7-not-valid';
		}
		if ( $tag->is_required() ) {
			$atts['aria-required'] = 'true';
		}

		$atts['id'] = $tag->get_id_option();
		$atts['type'] = 'number';
		$atts['name'] = $tag->name;
		$atts['min'] = $tag->get_option( 'min', 'signed_int', true );
		$atts['max'] = $tag->get_option( 'max', 'signed_int', true );
		$atts['class'] = $tag->get_class_option( $class );
		$atts['aria-invalid'] = $validation_error ? 'true' : 'false';

		$value = (string) reset( $tag->values );

		if ( $tag->has_option( 'placeholder' ) || $tag->has_option( 'watermark' ) ) {
			$atts['placeholder'] = $value;
			$value = '';
		}

		$value = $tag->get_default_option( $value );
		$value = wpcf7_get_hangover( $tag->name, $value );
		$atts['value'] = $value;

		$atts = wpcf7_format_atts( $atts );

		return sprintf( '<span class="wpcf7-form-control-wrap %1$s"><input %2$s />%3$s</span>', sanitize_html_class( $tag->name ), $atts, $validation_error );
	}

	/**
	 * @inheritdoc
	 */
	public function validation_filter( $result, $tag ) {
		$name = $tag->name;
		$value = isset( $_POST[ $name ] ) ? trim( wp_unslash( strtr( (string) $_POST[ $name ], "\n", " " ) ) ) : '';
		$min = $tag->get_option( 'min', 'signed_int', true );
		$max = $tag->get_option( 'max', 'signed_int', true );

		if ( $tag->is_required() && '' === $value ) {
			$result->invalidate( $tag, wpcf7_get_message( 'invalid_required' ) );
		} elseif ( '' !== $value && ! wpcf7_is_number( $value ) ) {
			$result->invalidate( $tag, wpcf7_get_message( 'invalid_number' ) );
		} elseif ( '' !== $value && '' !== $min && (float) $value < (float) $min ) {
			$result->invalidate( $tag, wpcf7_get_message( 'number_too_small' ) );
		} elseif ( '' !== $value && '' !== $max && (float) $max < (float) $value ) {
			$result->invalidate( $tag, wpcf7_get_message( 'number_too_large' ) );
		}

		return $result;
	}

	/**
	 * @inheritdoc
	 */
	public function tag_generator_callback( $contact_form, $args = array() ) {
		$args = wp_parse_args( $args, array() );
		$type = $this->_tag_name;

		ob_start(); ?>

		<div class="control-box">
			<fieldset>
				<table class="form-table">
					<tbody>
					<tr>
						<th scope="row"><?php _e( 'Field type', 'cf2agile' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php _e( 'Field type', 'cf2agile' ); ?></legend>
								<label><input type="checkbox" name="required" /> <?php _e( 'Required field', 'cf2agile' ); ?></label>
							</fieldset>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="<?php echo esc_attr( $args['content'] . '-name' ); ?>"><?php _e( 'Name', 'cf2agile' ); ?></label></th>
						<td><input type="text" name="name" class="tg-name oneline" id="<?php echo esc_attr( $args['content'] . '-name' ); ?>" /></td>
					</tr>

					<tr>
						<th scope="row"><label for="<?php echo esc_attr( $args['content'] . '-values' ); ?>"><?php _e( 'Default value', 'cf2agile' ); ?></label></th>
						<td>
							<input type="text" name="values" class="oneline" id="<?php echo esc_attr( $args['content'] . '-values' ); ?>" /><br />
							<label><input type="checkbox" name="placeholder" class="option" /> <?php _e( 'Use this text as the placeholder of the field', 'cf2agile' ); ?></label>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php _e( 'Range', 'cf2agile' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php _e( 'Range', 'cf2agile' ); ?></legend>
								<label>
									<?php _e( 'Min', 'cf2agile' ); ?>
									<input type="number" name="min" class="numeric option" />
								</label>
								&ndash;
								<label>
									<?php _e( 'Max', 'cf2agile' ); ?>
									<input type="number" name="max" class="numeric option" />
								</label>
							</fieldset>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="<?php echo esc_attr( $args['content'] . '-id' ); ?>"><?php _e( 'Id attribute', 'cf2agile' ); ?></label></th>
						<td><input type="text" name="id" class="idvalue oneline option" id="<?php echo esc_attr( $args['content'] . '-id' ); ?>" /></td>
					</tr>

					<tr>
						<th scope="row"><label for="<?php echo esc_attr( $args['content'] . '-class' ); ?>"><?php _e( 'Class attribute', 'cf2agile' ); ?></label></th>
						<td><input type="text" name="class" class="classvalue oneline option" id="<?php echo esc_attr( $args['content'] . '-class' ); ?>" /></td>
					</tr>
					</tbody>
				</table>
			</fieldset>
		</div>

		<div class="insert-box">
			<input type="text" name="<?php echo $type; ?>" class="tag code" readonly="readonly" onfocus="this.select()" title="" />

			<div class="submitbox">
				<input type="button" class="button button-primary insert-tag" value="<?php _e( 'Insert Tag', 'cf2agile' ); ?>" />
			</div>

			<br class="clear" />

			<p class="description mail-tag">
				<label for="<?php echo esc_attr( $args['content'] . '-mailtag' ); ?>">
					<?php echo sprintf( __( "To use the value input through this field in a mail field, you need to insert the corresponding mail-tag (%s) into the field on the Mail tab.", 'cf2agile' ), '<strong><span class="mail-tag"></span></strong>' ); ?>
					<input type="text" class="mail-tag code hidden" readonly="readonly" id="<?php echo esc_attr( $args['content'] . '-mailtag' ); ?>" />
				</label>
			</p>
		</div>

		<?php echo ob_get_clean();
	}

}

new cf2agile_Field_Budget();

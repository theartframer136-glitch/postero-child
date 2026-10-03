<?php
/**
 * The widget of Language Switcher for Transposh
 *
 * @link       https://codingfix.com
 * @since      2.0.0
 *
 * @package    Cfx_Language_Switcher_For_Transposh
 */
class Language_Switcher_Widget extends WP_Widget {

	/**
	 * L'istanza della classe Public per accedere al rendering centralizzato.
	 *
	 * @var Cfx_Language_Switcher_For_Transposh_Public
	 */
	private $renderer;

	/**
	 * Initialize the class and set its properties.
	 */
	public function __construct() {
		parent::__construct(
			'language-switcher-for-transposh-widget',
			'Language Switcher Widget',
			array(
				'customize_selective_refresh' => true,
				'description'                 => __( 'Add a language switcher where you want', 'language-switcher-for-transposh-widget' ),
			)
		);

		if ( ! class_exists( 'Cfx_Language_Switcher_For_Transposh_Public' ) ) {
			require_once plugin_dir_path( __DIR__ ) . 'public/class-cfx-language-switcher-for-transposh-public.php';
		}

		$this->renderer = new Cfx_Language_Switcher_For_Transposh_Public( 'language-switcher-for-transposh', '2.0.0' );
	}

	/**
	 * The widget form (for the backend)
	 *
	 * @param array $instance The widget instance.
	 */
	public function form( $instance ) {
		$defaults = array( 'select' => '' );
		$instance = wp_parse_args( (array) $instance, $defaults );
		$select   = $instance['select'];
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'select' ) ); ?>"><?php esc_html_e( 'Display Style:', 'cfx-language-switcher-for-transposh' ); ?></label>
			<select name="<?php echo esc_attr( $this->get_field_name( 'select' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'select' ) ); ?>" class="widefat">
				<?php
				$options = array(
					''                        => __( 'Select a style', 'cfx-language-switcher-for-transposh' ),
					// Orizzontali.
					'lsft_horizontal_flags'   => __( 'Horizontal: Flags only', 'cfx-language-switcher-for-transposh' ),
					'lsft_horizontal_codes'   => __( 'Horizontal: Code only', 'cfx-language-switcher-for-transposh' ),
					// Verticali.
					'lsft_vertical_flags'     => __( 'Vertical: Flags only', 'cfx-language-switcher-for-transposh' ),
					'lsft_vertical_codes'     => __( 'Vertical: Code only', 'cfx-language-switcher-for-transposh' ),
					// Dropdown (JS Personalizzato).
					'lsft_dropdown_flags'     => __( 'Dropdown: Flags only', 'cfx-language-switcher-for-transposh' ),
					'lsft_dropdown_text'      => __( 'Dropdown: Text only', 'cfx-language-switcher-for-transposh' ),
					'lsft_dropdown_codes'     => __( 'Dropdown: Code only', 'cfx-language-switcher-for-transposh' ),
					'lsft_dropdown_flag_text' => __( 'Dropdown: Flags and Text', 'cfx-language-switcher-for-transposh' ),
					// Select Nativa.
					'lsft_select_text'        => __( 'Native Select (Text only)', 'cfx-language-switcher-for-transposh' ),
					'lsft_select_codes'       => __( 'Native Select (Code only)', 'cfx-language-switcher-for-transposh' ),
				);

				foreach ( $options as $key => $name ) {
					echo '<option value="' . esc_attr( $key ) . '" ' . selected( $select, $key, false ) . '>' . esc_html( $name ) . '</option>';
				}
				?>
			</select>
		</p>
		<?php
	}

	/**
	 * Update widget settings
	 *
	 * @param array $new_instance New settings.
	 * @param array $old_instance Old settings.
	 * @return array Updated settings.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance           = $old_instance;
		$instance['select'] = isset( $new_instance['select'] ) ? wp_strip_all_tags( $new_instance['select'] ) : '';
		return $instance;
	}

	/**
	 * Display the widget
	 *
	 * @param array $args Widget arguments.
	 * @param array $instance Widget instance.
	 */
	public function widget( $args, $instance ) {
		$select = isset( $instance['select'] ) ? $instance['select'] : '';

		if ( empty( $select ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $args['before_widget'];

		// Definiamo un ID univoco per il widget basato sullo stile scelto.
		$opts = array( 'id' => 'lsft-widget-' . str_replace( '_', '-', $select ) );

		/**
		 * Mapping dinamico basato sui nomi delle opzioni
		 * che ora corrispondono esattamente alla logica degli shortcodes
		 */
		switch ( $select ) {
			// ORIZZONTALI.
			case 'lsft_horizontal_flags':
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $this->renderer->render_switcher( 'flag-only', 'horizontal', $opts );
				break;
			case 'lsft_horizontal_codes':
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $this->renderer->render_switcher( 'code-only', 'horizontal', $opts );
				break;

			// VERTICALI.
			case 'lsft_vertical_flags':
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $this->renderer->render_switcher( 'flag-only', 'vertical', $opts );
				break;
			case 'lsft_vertical_code':
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $this->renderer->render_switcher( 'code-only', 'vertical', $opts );
				break;

			// DROPDOWN.
			case 'lsft_dropdown_flags':
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $this->renderer->render_switcher( 'flag-only', 'dropdown', $opts );
				break;
			case 'lsft_dropdown_text':
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $this->renderer->render_switcher( 'text-only', 'dropdown', $opts );
				break;
			case 'lsft_dropdown_codes':
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $this->renderer->render_switcher( 'code-only', 'dropdown', $opts );
				break;
			case 'lsft_dropdown_flag_text':
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $this->renderer->render_switcher( 'flag-and-text', 'dropdown', $opts );
				break;

			// NATIVE SELECT.
			case 'lsft_select_text':
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $this->renderer->render_switcher( 'text-only', 'select', $opts );
				break;
			case 'lsft_select_codes':
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $this->renderer->render_switcher( 'code-only', 'select', $opts );
				break;
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $args['after_widget'];
	}
}
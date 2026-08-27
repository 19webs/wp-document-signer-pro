<?php
/**
 * Clase de integración con Elementor.
 *
 * @package WP_Document_Signer_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPDS_Elementor_Integration {

	/**
	 * Instancia única de la clase.
	 */
	private static $instance = null;

	/**
	 * Obtener la instancia.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ), 25 );
	}

	/**
	 * Inicializa la carga del addon si Elementor está activo y habilitado en ajustes globales.
	 */
	public function init() {
		if ( ! did_action( 'elementor/loaded' ) ) {
			return;
		}

		$options = get_option( 'wpds_settings' );
		$enable_elementor = isset( $options['enable_elementor'] ) ? (bool) $options['enable_elementor'] : false;

		if ( ! $enable_elementor ) {
			return;
		}

		// Registrar categorías y widgets en Elementor
		add_action( 'elementor/elements/categories_registered', array( $this, 'register_category' ) );
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
	}

	/**
	 * Registrar categoría personalizada para los addons de 19webs.
	 */
	public function register_category( $elements_manager ) {
		$elements_manager->add_category(
			'19webs-addons',
			array(
				'title' => esc_html__( '19webs Addons', 'wp-doc-signer' ),
				'icon'  => 'fa fa-plug',
			)
		);
	}

	/**
	 * Registrar widgets en Elementor.
	 */
	public function register_widgets( $widgets_manager ) {
		require_once plugin_dir_path( __FILE__ ) . 'elementor/class-elementor-signature-widget.php';
		$widgets_manager->register( new WPDS_Elementor_Signature_Widget() );
	}
}

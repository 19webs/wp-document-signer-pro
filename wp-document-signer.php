<?php
/**
 * Plugin Name: WP Document Signer Pro
 * Description: Permite a los clientes firmar documentos legales y de consentimiento directamente desde una pantalla tactil, generando un PDF y enviandolo por email.
 * Version:     1.3.0
 * Author: 19webs
 * Author URI: https://19webs.com
 * Text Domain: wp-doc-signer
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 5.6
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Salir si se accede directamente.
}

// Definir constantes del plugin.
define( 'WPDS_VERSION', '1.3.0' );
define( 'WPDS_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPDS_URL', plugin_dir_url( __FILE__ ) );

// Cargar clases internas
require_once WPDS_PATH . 'includes/class-cpt-documents.php';
require_once WPDS_PATH . 'includes/class-admin-settings.php';
require_once WPDS_PATH . 'includes/class-shortcode-renderer.php';
require_once WPDS_PATH . 'includes/class-rest-api.php';
require_once WPDS_PATH . 'includes/class-pdf-engine.php';
require_once WPDS_PATH . 'includes/class-mailer.php';
require_once WPDS_PATH . 'includes/class-wpds-updater.php';

/**
 * Clase principal de inicializacion del plugin.
 */
class WP_Document_Signer_Pro {

	/**
	 * Instancia unica de la clase.
	 */
	private static $instance = null;

	/**
	 * Obtener instancia unica.
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
		// Inicializar componentes
		$this->init_components();
	}

	/**
	 * Inicializa los componentes individuales.
	 */
	private function init_components() {
		WPDS_CPT_Documents::get_instance();
		WPDS_Admin_Settings::get_instance();
		WPDS_Shortcode_Renderer::get_instance();
		WPDS_REST_API::get_instance();
	}
}

// Lanzar el plugin.
function wpds_run_plugin() {
	return WP_Document_Signer_Pro::get_instance();
}
add_action( 'plugins_loaded', 'wpds_run_plugin' );

/**
 * Hook de activacion del plugin.
 * Crea el directorio de almacenamiento protegido y pre-carga el documento de muestra.
 */
function wpds_activate_plugin() {
	$upload_dir = wp_upload_dir();
	$target_dir = $upload_dir['basedir'] . '/firmas-pdf';

	// Crear el directorio si no existe.
	if ( ! file_exists( $target_dir ) ) {
		wp_mkdir_p( $target_dir );
	}

	// Crear archivo .htaccess para proteccion en servidores Apache.
	$htaccess_file = $target_dir . '/.htaccess';
	if ( ! file_exists( $htaccess_file ) ) {
		$htaccess_content = "Order Deny,Allow\nDeny from all\n";
		file_put_contents( $htaccess_file, $htaccess_content );
	}

	// Crear index.php vacio para evitar listado de directorios.
	$index_file = $target_dir . '/index.php';
	if ( ! file_exists( $index_file ) ) {
		file_put_contents( $index_file, "<?php\n// Silencio es oro.\n" );
	}

	// Asegurar que el CPT este registrado en memoria antes de insertar el post
	WPDS_CPT_Documents::get_instance()->register_cpt();
	wpds_create_default_document();
}
register_activation_hook( __FILE__, 'wpds_activate_plugin' );

/**
 * Inserta el documento de prueba por defecto "Acuerdo SP Experience".
 */
function wpds_create_default_document() {
	if ( get_option( 'wpds_default_post_created' ) ) {
		return;
	}

	$post_data = array(
		'post_title'   => 'Acuerdo SP Experience',
		'post_content' => '<h3>1. SP Experience y prueba tester</h3>
<p>El SP Experience es una valoracion previa totalmente gratuita. La prueba tester tiene un precio final de 50 &euro; (IVA incluido) y constituye un servicio independiente para valorar la respuesta y el estado del cabello antes de determinados trabajos tecnicos.</p>

<h3>2. Prueba tester, reserva de cita y aplicacion de los 50 &euro;</h3>
<ul>
<li>Los 50 &euro; se abonan por la prueba tester. Una vez realizada, remuneran un servicio ya prestado y no son reembolsables, reserve o no la persona cliente un tratamiento posterior.</li>
<li>Si, tras la prueba tester, la persona cliente reserva el tratamiento, esos mismos 50 &euro; quedan vinculados a la cita y podran descontarse del tratamiento en las condiciones siguientes.</li>
<li>Si la cita se cambia con mas de 48 horas de antelacion, el descuento se mantiene para la nueva fecha. Si el salon cambia la cita, tambien se mantiene.</li>
<li>Si la persona cliente cancela definitivamente, no se presenta a la cita o solicita un cambio de fecha con 48 horas o menos de antelacion, los 50 &euro; no seran reembolsables ni quedaran pendientes como descuento para una cita futura.</li>
<li>Si la persona cliente acude y realiza el tratamiento previsto, los 50 &euro; se descontaran, como cortesia comercial, del importe final del tratamiento.</li>
<li>Si el cabello no supera la prueba tester, los 50 &euro; tampoco son reembolsables. Si la persona cliente sigue en Sara Perez Salon de Autor el proceso de recuperacion recomendado y posteriormente puede realizarse el trabajo previsto, los 50 &euro; se descontaran de dicho trabajo.</li>
<li>Este descuento solo se mantiene mientras el seguimiento y la recuperacion se realicen en Sara Perez Salon de Autor. Si se interrumpen, se realizan fuera del salon o se modifica el cabello mediante otros tratamientos, el descuento deja de estar pendiente y sera necesaria una nueva prueba tester al precio vigente.</li>
</ul>

<h3>3. Aceptacion del proyecto y evolucion del tratamiento</h3>
<p>Tras el SP Experience y, cuando proceda, la prueba tester, la persona cliente declara haber recibido explicacion suficiente del proyecto y de sus condiciones economicas antes de iniciar el tratamiento, y acepta continuar en los terminos explicados. Comprende que algunos proyectos pueden requerir varias sesiones, mantenimiento o una evolucion progresiva y que una sesion intermedia no tiene por que representar el resultado final. Las imagenes o referencias expresan preferencias, pero no garantizan una reproduccion identica, ya que el resultado depende del estado inicial y de la respuesta tecnica del cabello.</p>

<h3>4. Consecuencias de trabajos anteriores</h3>
<p>Sara Perez Salon de Autor se compromete a ejecutar el servicio con diligencia profesional y conforme a lo acordado. Un cambio posterior de opinion, arrepentimiento o una valoracion estetica subjetiva desfavorable no acredita por si solo un incumplimiento ni genera automaticamente derecho a devolucion. Todo ello, sin perjuicio de los derechos legales de la persona consumidora.</p>

<h3>5. Declaracion y firma</h3>
<p>La persona cliente declara haber leido y comprendido estas condiciones, haber recibido la informacion necesaria antes de quedar vinculada, haber podido formular preguntas y aceptar expresamente la prueba tester, la reserva y, cuando proceda, el tratamiento propuesto. Recibira o tendra a su disposicion una copia de este acuerdo.</p>',
		'post_status'  => 'publish',
		'post_type'    => 'wp_documento',
	);

	$post_id = wp_insert_post( $post_data );

	if ( ! is_wp_error( $post_id ) && $post_id > 0 ) {
		update_post_meta( $post_id, '_wpds_status', 'active' );
		update_post_meta( $post_id, '_wpds_est_titular', 'Sara Perez Gonzalez' );
		update_post_meta( $post_id, '_wpds_est_nif', '31694014Z' );
		update_post_meta( $post_id, '_wpds_est_comercial', 'Sara Perez Salon de Autor' );
		update_post_meta( $post_id, '_wpds_est_address', 'C. San Marino, 2, 11405 Jerez de la Frontera, Cadiz' );
		update_post_meta( $post_id, '_wpds_est_email', 'saraperezpeluqueriadeautor@gmail.com' );
		update_post_meta( $post_id, '_wpds_est_phone', 'Telefono fijo: +34 956 333 125 | Movil: +34 607 34 51 00' );

		update_option( 'wpds_default_post_created', true );
	}
}

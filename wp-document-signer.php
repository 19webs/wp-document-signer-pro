<?php
/**
 * Plugin Name: WP Document Signer Pro
 * Description: Permite a los clientes firmar documentos legales y de consentimiento directamente desde una pantalla tÃ¡ctil, generando un PDF y enviÃ¡ndolo por email.
 * Version:     1.0.8
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
define( 'WPDS_VERSION', '1.0.8' );
define( 'WPDS_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPDS_URL', plugin_dir_url( __FILE__ ) );

// Cargar clases internas
require_once WPDS_PATH . 'includes/class-cpt-documents.php';
require_once WPDS_PATH . 'includes/class-admin-settings.php';
require_once WPDS_PATH . 'includes/class-shortcode-renderer.php';
require_once WPDS_PATH . 'includes/class-rest-api.php';
require_once WPDS_PATH . 'includes/class-pdf-engine.php';
require_once WPDS_PATH . 'includes/class-mailer.php';

/**
 * Clase principal de inicializaciÃ³n del plugin.
 */
class WP_Document_Signer_Pro {

	/**
	 * Instancia Ãºnica de la clase.
	 */
	private static $instance = null;

	/**
	 * Obtener instancia Ãºnica.
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
 * Hook de activaciÃ³n del plugin.
 * Crea el directorio de almacenamiento protegido y pre-carga el documento de muestra.
 */
function wpds_activate_plugin() {
	$upload_dir = wp_upload_dir();
	$target_dir = $upload_dir['basedir'] . '/firmas-pdf';

	// Crear el directorio si no existe.
	if ( ! file_exists( $target_dir ) ) {
		wp_mkdir_p( $target_dir );
	}

	// Crear archivo .htaccess para protecciÃ³n en servidores Apache.
	$htaccess_file = $target_dir . '/.htaccess';
	if ( ! file_exists( $htaccess_file ) ) {
		$htaccess_content = "Order Deny,Allow\nDeny from all\n";
		file_put_contents( $htaccess_file, $htaccess_content );
	}

	// Crear index.php vacÃ­o para evitar listado de directorios.
	$index_file = $target_dir . '/index.php';
	if ( ! file_exists( $index_file ) ) {
		file_put_contents( $index_file, "<?php\n// Silencio es oro.\n" );
	}

	// Asegurar que el CPT estÃ© registrado en memoria antes de insertar el post
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
<p>El SP Experience es una valoraciÃ³n previa totalmente gratuita. La prueba tester tiene un precio final de 50 â‚¬ (IVA incluido) y constituye un servicio independiente para valorar la respuesta y el estado del cabello antes de determinados trabajos tÃ©cnicos.</p>

<h3>2. Prueba tester, reserva de cita y aplicaciÃ³n de los 50 â‚¬</h3>
<ul>
<li>Los 50 â‚¬ se abonan por la prueba tester. Una vez realizada, remuneran un servicio ya prestado y no son reembolsables, reserve o no la persona cliente un tratamiento posterior.</li>
<li>Si, tras la prueba tester, la persona cliente reserva el tratamiento, esos mismos 50 â‚¬ quedan vinculados a la cita y podrÃ¡n descontarse del tratamiento en las condiciones siguientes.</li>
<li>Si la cita se cambia con mÃ¡s de 48 horas de antelaciÃ³n, el descuento se mantiene para la nueva fecha. Si el salÃ³n cambia la cita, tambiÃ©n se mantiene.</li>
<li>Si la persona cliente cancela definitivamente, no se presenta a la cita o solicita un cambio de fecha con 48 horas o menos de antelaciÃ³n, los 50 â‚¬ no serÃ¡n reembolsables ni quedarÃ¡n pendientes como descuento para una cita futura.</li>
<li>Si la persona cliente acude y realiza el tratamiento previsto, los 50 â‚¬ se descontarÃ¡n, como cortesÃ­a comercial, del importe final del tratamiento.</li>
<li>Si el cabello no supera la prueba tester, los 50 â‚¬ tampoco son reembolsables. Si la persona cliente sigue en Sara PÃ©rez SalÃ³n de Autor el proceso de recuperaciÃ³n recomendado y posteriormente puede realizarse el trabajo previsto, los 50 â‚¬ se descontarÃ¡n de dicho trabajo.</li>
<li>Este descuento solo se mantiene mientras el seguimiento y la recuperaciÃ³n se realicen en Sara PÃ©rez SalÃ³n de Autor. Si se interrumpen, se realizan fuera del salÃ³n o se modifica el cabello mediante otros tratamientos, el descuento deja de estar pendiente y serÃ¡ necesaria una nueva prueba tester al precio vigente.</li>
</ul>

<h3>3. AceptaciÃ³n del proyecto y evoluciÃ³n del tratamiento</h3>
<p>Tras el SP Experience y, cuando proceda, la prueba tester, la persona cliente declara haber recibido explicaciÃ³n suficiente del proyecto y de sus condiciones econÃ³micas antes de iniciar el tratamiento, y acepta continuar en los tÃ©rminos explicados. Comprende que algunos proyectos pueden requerir varias sesiones, mantenimiento o una evoluciÃ³n progresiva y que una sesiÃ³n intermedia no tiene por quÃ© representar el resultado final. Las imÃ¡genes o referencias expresan preferencias, pero no garantizan una reproducciÃ³n idÃ©ntica, ya que el resultado depende del estado inicial y de la respuesta tÃ©cnica del cabello.</p>

<h3>4. Resultado, conformidad y reclamaciones</h3>
<p>Sara PÃ©rez SalÃ³n de Autor se compromete a ejecutar el servicio con diligencia profesional y conforme a lo acordado. Un cambio posterior de opiniÃ³n, arrepentimiento o una valoraciÃ³n estÃ©tica subjetiva desfavorable no acredita por sÃ­ solo un incumplimiento ni genera automÃ¡ticamente derecho a devoluciÃ³n. Todo ello, sin perjuicio de los derechos legales de la persona consumidora.</p>

<h3>5. DeclaraciÃ³n y firma</h3>
<p>La persona cliente declara haber leÃ­do y comprendido estas condiciones, haber recibido la informaciÃ³n necesaria antes de quedar vinculada, haber podido formular preguntas y aceptar expresamente la prueba tester, la reserva y, cuando proceda, el tratamiento propuesto. RecibirÃ¡ o tendrÃ¡ a su disposiciÃ³n una copia de este acuerdo.</p>',
		'post_status'  => 'publish',
		'post_type'    => 'wp_documento',
	);

	$post_id = wp_insert_post( $post_data );

	if ( ! is_wp_error( $post_id ) && $post_id > 0 ) {
		update_post_meta( $post_id, '_wpds_status', 'active' );
		update_post_meta( $post_id, '_wpds_est_titular', 'Sara PÃ©rez GonzÃ¡lez' );
		update_post_meta( $post_id, '_wpds_est_nif', '31694014Z' );
		update_post_meta( $post_id, '_wpds_est_comercial', 'Sara PÃ©rez SalÃ³n de Autor' );
		update_post_meta( $post_id, '_wpds_est_address', 'C. San Marino, 2, 11405 Jerez de la Frontera, CÃ¡diz' );
		update_post_meta( $post_id, '_wpds_est_email', 'saraperezpeluqueriadeautor@gmail.com' );
		update_post_meta( $post_id, '_wpds_est_phone', 'TelÃ©fono fijo: +34 956 333 125 | MÃ³vil: +34 607 34 51 00' );

		update_option( 'wpds_default_post_created', true );
	}
}

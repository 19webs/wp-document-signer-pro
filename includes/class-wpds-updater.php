<?php
/**
 * Clase WPDS_Updater.
 * Gestiona las actualizaciones automáticas desde el repositorio de GitHub (privado o público).
 *
 * @package WP_Document_Signer_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPDS_Updater {

	/**
	 * Instancia única de la clase.
	 */
	private static $instance = null;

	/**
	 * Nombre del plugin (carpeta/archivo).
	 */
	private $plugin_slug = '';

	/**
	 * Nombre de la carpeta del plugin.
	 */
	private $plugin_dir = '';

	/**
	 * Propietario del repositorio en GitHub.
	 */
	private $username = '19webs';

	/**
	 * Nombre del repositorio en GitHub.
	 */
	private $repository = 'wp-document-signer-pro';

	/**
	 * Datos de la última versión obtenidos de GitHub.
	 */
	private $github_response = null;

	/**
	 * Obtiene la instancia única.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor. Registra los hooks.
	 */
	private function __construct() {
		// Determinar slugs del plugin dinámicamente según su ubicación real
		$this->plugin_slug = plugin_basename( WPDS_PATH . 'wp-document-signer.php' );
		$this->plugin_dir  = basename( WPDS_PATH );

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_popup_info' ), 20, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'rename_github_source' ), 10, 4 );
		add_filter( 'http_request_args', array( $this, 'add_github_token_to_download' ), 10, 2 );
	}

	/**
	 * Obtiene las opciones de configuración de forma segura.
	 */
	private function get_wpds_settings() {
		$options = get_option( 'wpds_settings' );
		return is_array( $options ) ? $options : array();
	}

	/**
	 * Consulta la última versión en GitHub.
	 */
	public function get_latest_github_release() {
		if ( null !== $this->github_response ) {
			return $this->github_response;
		}

		// Intentar obtener de la caché transitoria por 6 horas
		$cached = get_transient( 'wpds_github_update_check' );
		if ( false !== $cached ) {
			$this->github_response = $cached;
			return $cached;
		}

		$url = "https://api.github.com/repos/{$this->username}/{$this->repository}/tags";
		
		$args = array(
			'user-agent' => 'WPDS-Updater/' . WPDS_VERSION,
			'timeout'    => 10,
			'headers'    => array(
				'Accept' => 'application/vnd.github.v3+json',
			),
		);

		$options = $this->get_wpds_settings();
		$token = isset( $options['github_token'] ) ? sanitize_text_field( $options['github_token'] ) : '';
		if ( ! empty( $token ) ) {
			$args['headers']['Authorization'] = 'token ' . $token;
		}

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || empty( $data ) || ! isset( $data[0] ) ) {
			return false;
		}

		$latest_tag = $data[0];
		if ( ! is_array( $latest_tag ) || ! isset( $latest_tag['name'] ) || ! isset( $latest_tag['zipball_url'] ) ) {
			return false;
		}

		$release = array(
			'tag_name'    => $latest_tag['name'],
			'zipball_url' => $latest_tag['zipball_url'],
			'html_url'    => "https://github.com/{$this->username}/{$this->repository}/releases/tag/" . $latest_tag['name'],
			'body'        => 'Mejoras y actualizaciones en la versión ' . $latest_tag['name'] . '.',
			'assets'      => array(),
		);

		$this->github_response = $release;

		// Guardar en caché por 6 horas
		set_transient( 'wpds_github_update_check', $release, 6 * HOUR_IN_SECONDS );

		return $release;
	}

	/**
	 * Compara versiones y notifica a WordPress si hay una nueva actualización.
	 */
	public function check_for_update( $transient ) {
		if ( isset( $_GET['force-check'] ) || isset( $_GET['check_update'] ) ) {
			delete_transient( 'wpds_github_update_check' );
			$this->github_response = null;
		}

		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_latest_github_release();
		if ( ! $release ) {
			return $transient;
		}

		$new_version = ltrim( $release['tag_name'], 'v' );

		if ( version_compare( WPDS_VERSION, $new_version, '<' ) ) {
			$package = array(
				'slug'        => $this->plugin_dir,
				'plugin'      => $this->plugin_slug,
				'new_version' => $new_version,
				'url'         => $release['html_url'],
				'package'     => $release['zipball_url'],
			);

			$transient->response[ $this->plugin_slug ] = (object) $package;
		}

		return $transient;
	}

	/**
	 * Muestra la información detallada del plugin en la ventana emergente de WordPress.
	 */
	public function plugin_popup_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( ! isset( $args->slug ) || $args->slug !== $this->plugin_dir ) {
			return $result;
		}

		$release = $this->get_latest_github_release();
		if ( ! $release ) {
			return $result;
		}

		$new_version = ltrim( $release['tag_name'], 'v' );
		$changelog   = ! empty( $release['body'] ) ? nl2br( esc_html( $release['body'] ) ) : 'Actualizaciones y mejoras de rendimiento.';

		$res = new stdClass();
		$res->name           = 'WP Document Signer Pro';
		$res->slug           = $this->plugin_dir;
		$res->version        = $new_version;
		$res->author         = '<a href="https://19webs.com" target="_blank">19webs</a>';
		$res->homepage       = 'https://19webs.com';
		$res->download_link  = $release['zipball_url'];
		
		$res->sections = array(
			'description' => 'Permite a los clientes firmar documentos legales y de consentimiento directamente desde una pantalla táctil, generando un PDF y enviándolo por email.',
			'changelog'   => $changelog,
		);

		return $res;
	}

	/**
	 * Corrige el nombre de la carpeta en el directorio temporal de actualizaciones.
	 */
	public function rename_github_source( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		global $wp_filesystem;

		if ( ! file_exists( $source . '/wp-document-signer.php' ) ) {
			return $source;
		}

		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$correct_destination = trailingslashit( dirname( $source ) ) . $this->plugin_dir;

		if ( $wp_filesystem->exists( $correct_destination ) ) {
			$wp_filesystem->delete( $correct_destination, true );
		}

		$move = $wp_filesystem->move( $source, $correct_destination, true );
		if ( $move ) {
			return $correct_destination;
		}

		return $source;
	}

	/**
	 * Adjunta el token de autorización durante la descarga del ZIP del repositorio privado.
	 */
	public function add_github_token_to_download( $args, $url ) {
		if ( strpos( $url, 'api.github.com/repos/19webs/wp-document-signer-pro/zipball' ) !== false || strpos( $url, 'codeload.github.com/19webs/wp-document-signer-pro' ) !== false ) {
			$options = $this->get_wpds_settings();
			$token = isset( $options['github_token'] ) ? sanitize_text_field( $options['github_token'] ) : '';
			if ( ! empty( $token ) ) {
				if ( ! isset( $args['headers'] ) || ! is_array( $args['headers'] ) ) {
					$args['headers'] = array();
				}
				$args['headers']['Authorization'] = 'token ' . $token;
			}
		}
		return $args;
	}
}

// Inicializar
WPDS_Updater::get_instance();

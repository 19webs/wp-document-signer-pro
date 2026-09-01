<?php
/**
 * Clase para el Endpoint REST API del Plugin.
 *
 * @package WP_Document_Signer_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPDS_REST_API {

	/**
	 * Instancia única de la clase.
	 */
	private static $instance = null;

	/**
	 * Obtener la instancia de la clase.
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
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registrar rutas de la REST API.
	 */
	public function register_routes() {
		register_rest_route(
			'wp-doc-signer/v1',
			'/submit',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_submit' ),
				'permission_callback' => '__return_true', // Permitir envío público (necesario para visitantes y páginas con caché activa)
			)
		);
	}

	/**
	 * Manejar el envío del formulario.
	 */
	public function handle_submit( $request ) {
		try {
			$document_id = intval( $request->get_param( 'wpds_document_id' ) );
			$post        = get_post( $document_id );

			// Validar que el documento existe
			if ( ! $post || 'wp_documento' !== $post->post_type ) {
				return new WP_Error( 'wpds_invalid_doc', __( 'El documento especificado no es válido.', 'wp-doc-signer' ), array( 'status' => 400 ) );
			}

			// Validar estado del documento
			$status = get_post_meta( $document_id, '_wpds_status', true );
			if ( 'paused' === $status ) {
				return new WP_Error( 'wpds_paused_doc', __( 'El documento está en pausa y no puede ser firmado.', 'wp-doc-signer' ), array( 'status' => 400 ) );
			}

			// Obtener y sanitizar parámetros
			$nombre       = sanitize_text_field( $request->get_param( 'wpds_nombre' ) );
			$telefono     = sanitize_text_field( $request->get_param( 'wpds_telefono' ) );
			$email        = sanitize_email( $request->get_param( 'wpds_email' ) );
			$dni          = sanitize_text_field( $request->get_param( 'wpds_dni' ) );
			$fecha_raw    = sanitize_text_field( $request->get_param( 'wpds_fecha' ) );
			$fecha        = ( ! empty( $fecha_raw ) && false !== strtotime( $fecha_raw ) ) ? date( 'd/m/Y', strtotime( $fecha_raw ) ) : date( 'd/m/Y' );
			$firma_1      = $request->get_param( 'wpds_firma_1' ); // Base64 PNG
			$firma_2      = $request->get_param( 'wpds_firma_2' ); // Base64 PNG
			$consentimiento = $request->get_param( 'wpds_consentimiento_imagen' );

			// Validar campos requeridos
			if ( empty( $nombre ) || empty( $telefono ) || empty( $email ) || empty( $dni ) || empty( $fecha ) || empty( $firma_1 ) ) {
				return new WP_Error( 'wpds_missing_fields', __( 'Faltan campos obligatorios para completar la firma.', 'wp-doc-signer' ), array( 'status' => 400 ) );
			}

			// Validar formato del correo
			if ( ! is_email( $email ) ) {
				return new WP_Error( 'wpds_invalid_email', __( 'Por favor, introduce una dirección de correo electrónico válida.', 'wp-doc-signer' ), array( 'status' => 400 ) );
			}

			// Validar y sanear las imágenes Base64 de las firmas
			if ( strpos( $firma_1, 'data:image/png;base64,' ) !== 0 ) {
				return new WP_Error( 'wpds_invalid_signature', __( 'El formato de la firma principal no es válido.', 'wp-doc-signer' ), array( 'status' => 400 ) );
			}

			if ( ! empty( $firma_2 ) && strpos( $firma_2, 'data:image/png;base64,' ) !== 0 ) {
				return new WP_Error( 'wpds_invalid_signature_2', __( 'El formato de la segunda firma no es válido.', 'wp-doc-signer' ), array( 'status' => 400 ) );
			}

			// Reunir datos validados
			$form_data = array(
				'nombre'         => $nombre,
				'telefono'       => $telefono,
				'email'          => $email,
				'dni'            => $dni,
				'fecha'          => $fecha,
				'firma_1'        => $firma_1,
				'firma_2'        => $firma_2,
				'consentimiento' => intval( $consentimiento ),
			);

			// Generar el PDF usando el motor PDF
			$pdf_engine = WPDS_PDF_Engine::get_instance();
			$pdf_result = $pdf_engine->generate_pdf( $document_id, $form_data );

			if ( is_wp_error( $pdf_result ) ) {
				return $pdf_result;
			}

			$pdf_path = $pdf_result['file_path'];

			// Registrar en el historial persistente de firmas en la base de datos
			$signatures_log = get_option( 'wpds_signatures_log', array() );
			$new_signature = array(
				'date'           => current_time( 'mysql' ),
				'document_id'    => $document_id,
				'document_title' => $post->post_title,
				'client_name'    => $nombre,
				'client_dni'     => $dni,
				'client_email'   => $email,
				'client_phone'   => $telefono,
				'consent_image'  => intval( $consentimiento ),
				'filename'       => isset( $pdf_result['filename'] ) ? $pdf_result['filename'] : '',
			);
			array_unshift( $signatures_log, $new_signature );
			update_option( 'wpds_signatures_log', $signatures_log, false );

			// Enviar correos con el PDF adjunto
			$mailer = WPDS_Mailer::get_instance();
			$mail_sent = $mailer->send_emails( $post, $form_data, $pdf_path );

			// Borrar el archivo local si el usuario configuró no guardar PDF en el servidor
			$settings = get_option( 'wpds_settings' );
			if ( ! is_array( $settings ) ) {
				$settings = array();
			}
			$save_local = isset( $settings['save_local'] ) ? intval( $settings['save_local'] ) : 1;
			if ( 0 === $save_local && file_exists( $pdf_path ) ) {
				unlink( $pdf_path );
			}

			if ( is_wp_error( $mail_sent ) ) {
				return $mail_sent;
			}

			return rest_ensure_response(
				array(
					'success' => true,
					'message' => __( 'Documento firmado, generado y enviado con éxito.', 'wp-doc-signer' ),
				)
			);
		} catch ( Throwable $e ) {
			return new WP_Error( 'wpds_fatal_error', $e->getMessage(), array( 'status' => 500 ) );
		}
	}
}

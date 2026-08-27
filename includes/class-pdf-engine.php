<?php
/**
 * Clase para compilar el HTML e integrar Dompdf para la generación de PDFs.
 *
 * @package WP_Document_Signer_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Cargar el autoloader de Dompdf
if ( file_exists( WPDS_PATH . 'includes/dompdf/autoload.inc.php' ) ) {
	require_once WPDS_PATH . 'includes/dompdf/autoload.inc.php';
}

use Dompdf\Dompdf;
use Dompdf\Options;

class WPDS_PDF_Engine {

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
	private function __construct() {}

	/**
	 * Retorna una firma de demostración transparente en Base64 para vistas previas.
	 */
	public function get_mock_signature_base64() {
		// PNG de una firma estilizada transparente
		return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAMgAAABQBAMAAABQ2L+7AAAABGdBTUEAALGPC/xhBQAAACBjSFJNAAB6JgAAgIQAAPoAAACA6AAAdTAAAOpgAAA6mAAAF3CculE8AAAAD1BMVEUAAAD///8QEBAYGBg4ODg402K6AAAAAXRSTlMAQObYZgAAAAFiS0dEAIgFHUgAAAAJcEhZcwAACxMAAAsTAQCanBgAAAAHdElNRQfmCBkRExFHq1kIAAAA8ElEQVRYw+2SwQ3CMAAEtZsA0gGgD1oB7aADZAIKOhgGqR0E7QBKkUogW/Afe5z6eYkIifN1vnPjXgPbtiO6+DpgWwJm+w5g+wmY2c4aYGYLwHQLwHRLwHQLwGwTMFsCZrYEzGwJmK0BmG4BmG4BmG4BmG4BmK0BmK0BmK0BmK0BmG4BmK0BmK0BmG4BmK0BmK4Be90yE52ZzLtmZp0zO9y1k13v2vWud23XW3t1Huzveba/JzY3e+Oub4f7iG83H/EfcVf8S9w7xH1PXDvfH3cfce/w72y/qP3v684u9gU2l86z/Qc8+h4237L++QAAACV0RVh0ZGF0ZTpjcmVhdGUAMjAyMi0wOC0yNVQxNzoxNzoxMSswMjowMBZ8nSwAAAAldEVYdGRhdGU6bW9kaWZ5ADIwMjItMDgtMjVUMTc6MTc6MTErMDI6MDDBTz4zAAAAAElFTkSuQmCC';
	}

	/**
	 * Genera el documento PDF y lo guarda temporal o permanentemente en el servidor.
	 *
	 * @param int   $document_id ID del CPT wp_documento.
	 * @param array $form_data   Datos sanitizados del formulario.
	 * @return array|WP_Error    Array con la ruta del archivo o un objeto WP_Error.
	 */
	public function generate_pdf( $document_id, $form_data ) {
		// Validar que Dompdf esté cargado
		if ( ! class_exists( 'Dompdf\Dompdf' ) ) {
			return new WP_Error( 'wpds_missing_dompdf', __( 'El motor de generación de PDF (Dompdf) no se encuentra disponible.', 'wp-doc-signer' ) );
		}

		$post = get_post( $document_id );
		if ( ! $post ) {
			return new WP_Error( 'wpds_pdf_invalid_doc', __( 'No se encontró el documento para compilar.', 'wp-doc-signer' ) );
		}

		// Obtener metadatos del establecimiento y textos RGPD
		$est_data = array(
			'titular'   => get_post_meta( $document_id, '_wpds_est_titular', true ),
			'nif'       => get_post_meta( $document_id, '_wpds_est_nif', true ),
			'comercial' => get_post_meta( $document_id, '_wpds_est_comercial', true ),
			'address'   => get_post_meta( $document_id, '_wpds_est_address', true ),
			'email'     => get_post_meta( $document_id, '_wpds_est_email', true ),
			'phone'     => get_post_meta( $document_id, '_wpds_est_phone', true ),
			'rgpd_finalidad'     => get_post_meta( $document_id, '_wpds_rgpd_finalidad', true ),
			'rgpd_legitimacion'  => get_post_meta( $document_id, '_wpds_rgpd_legitimacion', true ),
			'rgpd_destinatarios' => get_post_meta( $document_id, '_wpds_rgpd_destinatarios', true ),
			'rgpd_conservacion'  => get_post_meta( $document_id, '_wpds_rgpd_conservacion', true ),
			'rgpd_derechos'      => get_post_meta( $document_id, '_wpds_rgpd_derechos', true ),
			'rgpd_procedencia'   => get_post_meta( $document_id, '_wpds_rgpd_procedencia', true ),
			'rgpd_adicional'     => get_post_meta( $document_id, '_wpds_rgpd_adicional', true ),
			'consentimiento_titulo' => get_post_meta( $document_id, '_wpds_consentimiento_titulo', true ),
			'consentimiento_subtitulo' => get_post_meta( $document_id, '_wpds_consentimiento_subtitulo', true ),
			'consentimiento_texto' => get_post_meta( $document_id, '_wpds_consentimiento_texto', true ),
			'consentimiento_declaracion_titulo' => get_post_meta( $document_id, '_wpds_consentimiento_declaracion_titulo', true ),
			'consentimiento_declaracion_texto' => get_post_meta( $document_id, '_wpds_consentimiento_declaracion_texto', true ),
		);

		// Obtener y parsear el cuerpo del documento
		$document_body = $this->compile_placeholders( $post->post_content, $form_data );

		// Preparar el HTML inyectándolo en la plantilla de diseño
		$html = $this->render_template( $post->post_title, $document_body, $form_data, $est_data );

		try {
			$options = new Options();
			$options->set( 'isHtml5ParserEnabled', true );
			$options->set( 'isRemoteEnabled', true );

			$dompdf = new Dompdf( $options );
			$dompdf->loadHtml( $html );
			$dompdf->setPaper( 'A4', 'portrait' );
			$dompdf->render();

			$upload_dir = wp_upload_dir();
			$target_dir = $upload_dir['basedir'] . '/firmas-pdf';

			if ( ! file_exists( $target_dir ) ) {
				wp_mkdir_p( $target_dir );
			}

			// Nombre único que incluye el nombre del cliente para permitir búsquedas funcionales
			$clean_name  = sanitize_title( $form_data['nombre'] ); // Limpia caracteres especiales y espacios
			$random_hash = wp_hash( $document_id . '_' . time() . '_' . uniqid( '', true ) );
			$filename    = sprintf( 'documento_%d_%s_%s.pdf', $document_id, $clean_name, substr( $random_hash, 0, 8 ) );
			$file_path   = $target_dir . '/' . $filename;

			$pdf_output = $dompdf->output();
			if ( false === file_put_contents( $file_path, $pdf_output ) ) {
				return new WP_Error( 'wpds_write_error', __( 'Error al escribir el archivo PDF en el servidor.', 'wp-doc-signer' ) );
			}

			return array(
				'file_path' => $file_path,
				'filename'  => $filename,
				'url'       => $upload_dir['baseurl'] . '/firmas-pdf/' . $filename,
			);

		} catch ( Throwable $e ) {
			return new WP_Error( 'wpds_pdf_exception', __( 'Excepción al generar el PDF: ', 'wp-doc-signer' ) . $e->getMessage() );
		}
	}

	/**
	 * Reemplaza los placeholders por valores formateados si es que existen en el texto.
	 */
	private function compile_placeholders( $content, $form_data ) {
		$replacements = array(
			'{input_nombre}' => '<strong class="pdf-field-val">' . esc_html( $form_data['nombre'] ) . '</strong>',
			'{input_telefono}' => '<strong class="pdf-field-val">' . esc_html( $form_data['telefono'] ) . '</strong>',
			'{input_email}' => '<strong class="pdf-field-val">' . esc_html( $form_data['email'] ) . '</strong>',
			'{input_dni}' => '<strong class="pdf-field-val">' . esc_html( $form_data['dni'] ) . '</strong>',
			'{input_fecha}' => '<strong class="pdf-field-val">' . esc_html( $form_data['fecha'] ) . '</strong>',
		);

		return str_replace( array_keys( $replacements ), array_values( $replacements ), $content );
	}

	/**
	 * Carga la plantilla de maquetación del PDF y le inyecta el contenido.
	 */
	private function render_template( $title, $body, $form_data, $est_data ) {
		$template_path = WPDS_PATH . 'templates/pdf-default-layout.php';

		if ( ! file_exists( $template_path ) ) {
			return '<html><body><h1>' . esc_html( $title ) . '</h1><div>' . $body . '</div></body></html>';
		}

		// Asignar los metadatos del establecimiento como variables locales accesibles para la plantilla
		$est_titular   = $est_data['titular'];
		$est_nif       = $est_data['nif'];
		$est_comercial = $est_data['comercial'];
		$est_address   = $est_data['address'];
		$est_email     = $est_data['email'];
		$est_phone     = $est_data['phone'];

		// Asignar variables de textos RGPD y consentimiento con fallbacks para la plantilla
		$meta_finalidad                         = isset( $est_data['rgpd_finalidad'] ) ? $est_data['rgpd_finalidad'] : '';
		$meta_legitimacion                      = isset( $est_data['rgpd_legitimacion'] ) ? $est_data['rgpd_legitimacion'] : '';
		$meta_destinatarios                     = isset( $est_data['rgpd_destinatarios'] ) ? $est_data['rgpd_destinatarios'] : '';
		$meta_conservacion                      = isset( $est_data['rgpd_conservacion'] ) ? $est_data['rgpd_conservacion'] : '';
		$meta_derechos                          = isset( $est_data['rgpd_derechos'] ) ? $est_data['rgpd_derechos'] : '';
		$meta_procedencia                       = isset( $est_data['rgpd_procedencia'] ) ? $est_data['rgpd_procedencia'] : '';
		$meta_adicional                         = isset( $est_data['rgpd_adicional'] ) ? $est_data['rgpd_adicional'] : '';
		
		$meta_consentimiento_titulo             = isset( $est_data['consentimiento_titulo'] ) ? $est_data['consentimiento_titulo'] : '';
		$meta_consentimiento_subtitulo          = isset( $est_data['consentimiento_subtitulo'] ) ? $est_data['consentimiento_subtitulo'] : '';
		$meta_consentimiento_texto              = isset( $est_data['consentimiento_texto'] ) ? $est_data['consentimiento_texto'] : '';
		$meta_consentimiento_declaracion_titulo = isset( $est_data['consentimiento_declaracion_titulo'] ) ? $est_data['consentimiento_declaracion_titulo'] : '';
		$meta_consentimiento_declaracion_texto  = isset( $est_data['consentimiento_declaracion_texto'] ) ? $est_data['consentimiento_declaracion_texto'] : '';

		$rgpd_finalidad = ! empty( $meta_finalidad ) ? $meta_finalidad : __( 'Gestionar la reserva y la relación precontractual/contractual, prestar y documentar el servicio, gestionar pagos y cumplir obligaciones legales, así como atender o defender reclamaciones. Bases: ejecución del contrato, medidas precontractuales, obligaciones legales y, cuando proceda, interés legítimo para la defensa de reclamaciones.', 'wp-doc-signer' );

		$rgpd_legitimacion = ! empty( $meta_legitimacion ) ? $meta_legitimacion : __( 'Ejecución de un contrato, cumplimiento de obligaciones legales e interés legítimo.', 'wp-doc-signer' );

		$rgpd_destinatarios = ! empty( $meta_destinatarios ) ? $meta_destinatarios : __( 'Proveedores necesarios para la gestión del servicio y Administraciones, juzgados, tribunales, aseguradoras o asesores cuando exista obligación legal o sea necesario para gestionar o defender reclamaciones.', 'wp-doc-signer' );

		$rgpd_conservacion = ! empty( $meta_conservacion ) ? $meta_conservacion : __( 'Durante la relación con la persona cliente y, posteriormente, durante los plazos legales aplicables para atender obligaciones y posibles responsabilidades.', 'wp-doc-signer' );

		$rgpd_derechos = ! empty( $meta_derechos ) ? $meta_derechos : sprintf( __( 'Acceso, rectificación, supresión, limitación, oposición y portabilidad cuando proceda, mediante el email indicado (%s). También puede reclamar ante la Agencia Española de Protección de Datos.', 'wp-doc-signer' ), $est_email );

		$rgpd_procedencia = ! empty( $meta_procedencia ) ? $meta_procedencia : __( 'La propia persona interesada o su representante legal.', 'wp-doc-signer' );

		$rgpd_adicional = ! empty( $meta_adicional ) ? $meta_adicional : __( 'Puede consultar la información detallada sobre protección de datos en nuestra oficina o solicitándola por email.', 'wp-doc-signer' );

		$consentimiento_titulo = ! empty( $meta_consentimiento_titulo ) ? $meta_consentimiento_titulo : __( '7. Consentimiento opcional de imagen y voz', 'wp-doc-signer' );
		$consentimiento_subtitulo = ! empty( $meta_consentimiento_subtitulo ) ? $meta_consentimiento_subtitulo : __( 'Esta autorización es gratuita e independiente y solo se entenderá otorgada si se marca SÍ.', 'wp-doc-signer' );
		$consentimiento_declaracion_titulo = ! empty( $meta_consentimiento_declaracion_titulo ) ? $meta_consentimiento_declaracion_titulo : __( 'PERSONA CLIENTE', 'wp-doc-signer' );
		$consentimiento_declaracion_texto = ! empty( $meta_consentimiento_declaracion_texto ) ? $meta_consentimiento_declaracion_texto : __( 'Declaro haber recibido esta información y haber marcado libremente mi opción sobre el uso de imagen y/o voz.', 'wp-doc-signer' );

		// Procesar texto de declaración
		$custom_consent_active = ! empty( $meta_consentimiento_texto );
		$consentimiento_declaracion = '';
		if ( $custom_consent_active ) {
			$consentimiento_declaracion = str_replace(
				array( '{titular}', '{comercial}' ),
				array( $est_titular, $est_comercial ),
				$meta_consentimiento_texto
			);
		}

		// Cargar marca de agua desde los ajustes o fallback
		$options = get_option( 'wpds_settings' );
		$watermark_url = isset( $options['watermark_url'] ) ? $options['watermark_url'] : '';
		
		$watermark_path = '';
		if ( ! empty( $watermark_url ) ) {
			// Convertir URL del servidor a ruta local para evitar cURL blocking local
			$upload_dir = wp_upload_dir();
			if ( strpos( $watermark_url, $upload_dir['baseurl'] ) === 0 ) {
				$watermark_path = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $watermark_url );
			}
		}

		// Si no hay o no existe, fallback al predeterminado
		if ( empty( $watermark_path ) || ! file_exists( $watermark_path ) ) {
			$watermark_path = WPDS_PATH . 'assets/watermark_sp.jpg';
		}

		// Generar cadena Base64 transparente y segura para incrustación directa
		$watermark_base64 = '';
		if ( file_exists( $watermark_path ) ) {
			$watermark_data   = file_get_contents( $watermark_path );
			$mime_type        = 'image/jpeg';
			$ext              = strtolower( pathinfo( $watermark_path, PATHINFO_EXTENSION ) );
			if ( 'png' === $ext ) {
				$mime_type = 'image/png';
			} elseif ( 'gif' === $ext ) {
				$mime_type = 'image/gif';
			}
			$watermark_base64 = 'data:' . $mime_type . ';base64,' . base64_encode( $watermark_data );
		}

		ob_start();
		include $template_path;
		return ob_get_clean();
	}

	/**
	 * Genera un PDF de vista previa y lo envía por streaming al navegador sin guardarlo localmente.
	 */
	public function generate_preview_pdf( $document_id, $form_data ) {
		$post = get_post( $document_id );
		if ( ! $post ) {
			wp_die( esc_html__( 'Documento no válido.', 'wp-doc-signer' ) );
		}

		$est_data = array(
			'titular'   => get_post_meta( $document_id, '_wpds_est_titular', true ),
			'nif'       => get_post_meta( $document_id, '_wpds_est_nif', true ),
			'comercial' => get_post_meta( $document_id, '_wpds_est_comercial', true ),
			'address'   => get_post_meta( $document_id, '_wpds_est_address', true ),
			'email'     => get_post_meta( $document_id, '_wpds_est_email', true ),
			'phone'     => get_post_meta( $document_id, '_wpds_est_phone', true ),
			'rgpd_finalidad'     => get_post_meta( $document_id, '_wpds_rgpd_finalidad', true ),
			'rgpd_legitimacion'  => get_post_meta( $document_id, '_wpds_rgpd_legitimacion', true ),
			'rgpd_destinatarios' => get_post_meta( $document_id, '_wpds_rgpd_destinatarios', true ),
			'rgpd_conservacion'  => get_post_meta( $document_id, '_wpds_rgpd_conservacion', true ),
			'rgpd_derechos'      => get_post_meta( $document_id, '_wpds_rgpd_derechos', true ),
			'rgpd_procedencia'   => get_post_meta( $document_id, '_wpds_rgpd_procedencia', true ),
			'rgpd_adicional'     => get_post_meta( $document_id, '_wpds_rgpd_adicional', true ),
			'consentimiento_titulo' => get_post_meta( $document_id, '_wpds_consentimiento_titulo', true ),
			'consentimiento_subtitulo' => get_post_meta( $document_id, '_wpds_consentimiento_subtitulo', true ),
			'consentimiento_texto' => get_post_meta( $document_id, '_wpds_consentimiento_texto', true ),
			'consentimiento_declaracion_titulo' => get_post_meta( $document_id, '_wpds_consentimiento_declaracion_titulo', true ),
			'consentimiento_declaracion_texto' => get_post_meta( $document_id, '_wpds_consentimiento_declaracion_texto', true ),
		);

		// Fallbacks de datos de establecimiento para la vista previa
		if ( empty( $est_data['titular'] ) ) { $est_data['titular'] = 'Sara Pérez González'; }
		if ( empty( $est_data['nif'] ) ) { $est_data['nif'] = '75817812D'; }
		if ( empty( $est_data['comercial'] ) ) { $est_data['comercial'] = 'Sara Pérez Salón de Autor'; }
		if ( empty( $est_data['address'] ) ) { $est_data['address'] = 'Calle Ancha, 12, Local 2, 11402 Jerez de la Frontera, Cádiz'; }
		if ( empty( $est_data['email'] ) ) { $est_data['email'] = 'saraperezpeluqueriadeautor@gmail.com'; }
		if ( empty( $est_data['phone'] ) ) { $est_data['phone'] = '601 202 303'; }

		$document_body = $this->compile_placeholders( $post->post_content, $form_data );
		$html = $this->render_template( $post->post_title, $document_body, $form_data, $est_data );

		try {
			$options = new \Dompdf\Options();
			$options->set( 'isHtml5ParserEnabled', true );
			$options->set( 'isRemoteEnabled', true );

			$dompdf = new \Dompdf\Dompdf( $options );
			$dompdf->loadHtml( $html );
			$dompdf->setPaper( 'A4', 'portrait' );
			$dompdf->render();

			$dompdf->stream( 'preview_' . sanitize_title( $post->post_title ) . '.pdf', array( 'Attachment' => false ) );
			exit;
		} catch ( Throwable $e ) {
			wp_die( esc_html( $e->getMessage() ) );
		}
	}
}

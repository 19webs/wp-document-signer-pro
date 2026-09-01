<?php
/**
 * Clase para gestionar el envío de correos electrónicos con PDFs adjuntos.
 *
 * @package WP_Document_Signer_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPDS_Mailer {

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
	 * Envía las copias por correo electrónico al cliente y a la administración.
	 *
	 * @param WP_Post $post      El objeto Post del documento wp_documento.
	 * @param array   $form_data Datos del firmante.
	 * @param string  $pdf_path  Ruta absoluta al archivo PDF generado.
	 * @return bool|WP_Error     True si se enviaron correctamente o un WP_Error si falló.
	 */
	public function send_emails( $post, $form_data, $pdf_path ) {
		// Obtener ajustes globales
		$settings = get_option( 'wpds_settings' );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		// 1. Configurar Remitente
		$sender_name  = ! empty( $settings['sender_name'] ) ? $settings['sender_name'] : get_bloginfo( 'name' );
		$sender_email = ! empty( $settings['sender_email'] ) ? $settings['sender_email'] : get_bloginfo( 'admin_email' );

		// Cabeceras de correo electrónico HTML
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . esc_html( $sender_name ) . ' <' . sanitize_email( $sender_email ) . '>',
		);

		// Preparar adjunto
		$attachments = array( $pdf_path );

		// 2. ENVÍO AL CLIENTE
		$client_email = $form_data['email'];
		
		// Asunto cliente
		$client_subj_raw = ! empty( $settings['client_subject'] ) ? $settings['client_subject'] : __( 'Tu copia de: {nombre_documento}', 'wp-doc-signer' );
		$client_subject  = $this->parse_email_placeholders( $client_subj_raw, $post->post_title, $form_data );

		// Cuerpo cliente
		$client_body_raw = ! empty( $settings['client_body'] ) ? $settings['client_body'] : '';
		if ( empty( $client_body_raw ) ) {
			$client_body_raw = "<p>Hola {nombre_cliente},</p>\n<p>Adjunto a este correo encontrarás el documento firmado: <strong>{nombre_documento}</strong>.</p>";
		}
		$client_body = $this->parse_email_placeholders( $client_body_raw, $post->post_title, $form_data );

		$client_mail_result = wp_mail( $client_email, $client_subject, $client_body, $headers, $attachments );

		// Registrar log de cliente
		$this->log_email(
			$post->post_title,
			$client_email,
			__( 'Cliente', 'wp-doc-signer' ),
			$client_mail_result ? __( 'Éxito', 'wp-doc-signer' ) : __( 'Fallido', 'wp-doc-signer' ),
			$client_mail_result ? '' : __( 'La función wp_mail() devolvió false. Comprueba tu configuración SMTP.', 'wp-doc-signer' )
		);

		// 3. ENVÍO A LA ADMINISTRACIÓN
		// Determinar correos de administración (verificar si hay override en el documento)
		$admin_email_override = get_post_meta( $post->ID, '_wpds_email', true );
		
		if ( ! empty( $admin_email_override ) ) {
			$admin_emails = $admin_email_override;
		} else {
			$admin_emails = ! empty( $settings['admin_emails'] ) ? $settings['admin_emails'] : get_bloginfo( 'admin_email' );
		}

		// Asunto administración
		$admin_subj_raw = ! empty( $settings['admin_subject'] ) ? $settings['admin_subject'] : __( 'Firmado: {nombre_documento} - {nombre_cliente}', 'wp-doc-signer' );
		$admin_subject  = $this->parse_email_placeholders( $admin_subj_raw, $post->post_title, $form_data );

		// Cuerpo administración
		$admin_body  = '<h2>' . esc_html__( 'Nuevo Documento Firmado Recibido', 'wp-doc-signer' ) . '</h2>';
		$admin_body .= '<p>' . sprintf( esc_html__( 'Se ha firmado correctamente el documento "%s".', 'wp-doc-signer' ), esc_html( $post->post_title ) ) . '</p>';
		$admin_body .= '<h3>' . esc_html__( 'Detalles del Firmante:', 'wp-doc-signer' ) . '</h3>';
		$admin_body .= '<ul>';
		$admin_body .= '<li><strong>' . esc_html__( 'Nombre Completo:', 'wp-doc-signer' ) . '</strong> ' . esc_html( $form_data['nombre'] ) . '</li>';
		$admin_body .= '<li><strong>' . esc_html__( 'DNI / Identificación:', 'wp-doc-signer' ) . '</strong> ' . esc_html( $form_data['dni'] ) . '</li>';
		$admin_body .= '<li><strong>' . esc_html__( 'Teléfono:', 'wp-doc-signer' ) . '</strong> ' . esc_html( $form_data['telefono'] ) . '</li>';
		$admin_body .= '<li><strong>' . esc_html__( 'Correo Electrónico:', 'wp-doc-signer' ) . '</strong> ' . esc_html( $form_data['email'] ) . '</li>';
		$admin_body .= '<li><strong>' . esc_html__( 'Fecha Firma:', 'wp-doc-signer' ) . '</strong> ' . esc_html( $form_data['fecha'] ) . '</li>';
		$admin_body .= '</ul>';
		$admin_body .= '<p>' . esc_html__( 'El archivo PDF con las firmas incrustadas se encuentra adjunto a este correo.', 'wp-doc-signer' ) . '</p>';

		// Separar por comas si hay múltiples correos de admin
		$admin_emails_array = array_map( 'trim', explode( ',', $admin_emails ) );
		$admin_mail_result  = true;

		// Cabeceras específicas para administración (añadiendo Reply-To apuntando al email del cliente)
		$admin_headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . esc_html( $sender_name ) . ' <' . sanitize_email( $sender_email ) . '>',
			'Reply-To: ' . esc_html( $form_data['nombre'] ) . ' <' . sanitize_email( $form_data['email'] ) . '>',
		);

		foreach ( $admin_emails_array as $single_admin_email ) {
			if ( is_email( $single_admin_email ) ) {
				$sent = wp_mail( $single_admin_email, $admin_subject, $admin_body, $admin_headers, $attachments );
				
				// Registrar log de admin
				$this->log_email(
					$post->post_title,
					$single_admin_email,
					__( 'Administración', 'wp-doc-signer' ),
					$sent ? __( 'Éxito', 'wp-doc-signer' ) : __( 'Fallido', 'wp-doc-signer' ),
					$sent ? '' : __( 'La función wp_mail() devolvió false. Comprueba tu configuración SMTP.', 'wp-doc-signer' )
				);

				if ( ! $sent ) {
					$admin_mail_result = false;
				}
			}
		}

		// Validar si falló el envío a cliente
		if ( ! $client_mail_result ) {
			return new WP_Error( 'wpds_mail_client_failed', __( 'Error al enviar el correo con el documento al cliente.', 'wp-doc-signer' ) );
		}

		// Si falló administración, guardamos un registro de advertencia
		if ( ! $admin_mail_result ) {
			error_log( 'WPDS: Error al enviar correos de notificación a uno o más emails administrativos: ' . $admin_emails );
		}

		return true;
	}

	/**
	 * Reemplaza placeholders de correo con datos reales.
	 */
	private function parse_email_placeholders( $text, $document_title, $form_data ) {
		$replacements = array(
			'{nombre_cliente}'   => $form_data['nombre'],
			'{nombre_documento}' => $document_title,
		);

		return str_replace( array_keys( $replacements ), array_values( $replacements ), $text );
	}

	/**
	 * Registra un envío de correo electrónico en los logs.
	 */
	private function log_email( $document_title, $recipient, $type, $status, $error_msg = '' ) {
		$logs = get_option( 'wpds_email_logs', array() );
		
		$new_entry = array(
			'date'      => current_time( 'mysql' ),
			'document'  => $document_title,
			'recipient' => $recipient,
			'type'      => $type, // 'Cliente' o 'Administración'
			'status'    => $status, // 'Éxito' o 'Fallido'
			'error'     => $error_msg,
		);

		array_unshift( $logs, $new_entry );

		if ( count( $logs ) > 150 ) {
			$logs = array_slice( $logs, 0, 150 );
		}

		update_option( 'wpds_email_logs', $logs, false );
	}

	/**
	 * Busca en la base de datos el registro de firma correspondiente a un nombre de archivo PDF.
	 */
	public function get_signature_log_by_filename( $filename ) {
		$logs = get_option( 'wpds_signatures_log', array() );
		if ( ! is_array( $logs ) ) {
			return array();
		}
		$clean_target = basename( $filename );
		foreach ( $logs as $log ) {
			if ( isset( $log['filename'] ) && basename( $log['filename'] ) === $clean_target ) {
				return $log;
			}
		}
		return array();
	}

	/**
	 * Reenvía la copia del documento PDF al cliente por correo electrónico.
	 */
	public function resend_client_email( $filename, $target_email = '' ) {
		$upload_dir = wp_upload_dir();
		$file_path  = $upload_dir['basedir'] . '/firmas-pdf/' . basename( $filename );

		if ( ! file_exists( $file_path ) ) {
			return new WP_Error( 'wpds_file_not_found', __( 'El archivo PDF no se encuentra en el servidor.', 'wp-doc-signer' ) );
		}

		$log_data     = $this->get_signature_log_by_filename( basename( $filename ) );
		$client_email = ! empty( $target_email ) ? sanitize_email( $target_email ) : ( ! empty( $log_data['client_email'] ) ? $log_data['client_email'] : '' );

		if ( ! is_email( $client_email ) ) {
			return new WP_Error( 'wpds_invalid_email', __( 'Dirección de correo electrónico del cliente no válida.', 'wp-doc-signer' ) );
		}

		$document_title = ! empty( $log_data['document_title'] ) ? $log_data['document_title'] : __( 'Documento Firmado', 'wp-doc-signer' );
		
		$form_data = array(
			'nombre'   => ! empty( $log_data['client_name'] ) ? $log_data['client_name'] : __( 'Cliente', 'wp-doc-signer' ),
			'email'    => $client_email,
			'dni'      => ! empty( $log_data['client_dni'] ) ? $log_data['client_dni'] : '',
			'telefono' => ! empty( $log_data['client_phone'] ) ? $log_data['client_phone'] : '',
			'fecha'    => ! empty( $log_data['date'] ) ? date( 'd/m/Y', strtotime( $log_data['date'] ) ) : date( 'd/m/Y', filemtime( $file_path ) ),
		);

		$settings     = get_option( 'wpds_settings', array() );
		$sender_name  = ! empty( $settings['sender_name'] ) ? $settings['sender_name'] : get_bloginfo( 'name' );
		$sender_email = ! empty( $settings['sender_email'] ) ? $settings['sender_email'] : get_bloginfo( 'admin_email' );

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . esc_html( $sender_name ) . ' <' . sanitize_email( $sender_email ) . '>',
		);

		$attachments = array( $file_path );

		$client_subj_raw = ! empty( $settings['client_subject'] ) ? $settings['client_subject'] : __( 'Tu copia de: {nombre_documento}', 'wp-doc-signer' );
		$client_subject  = $this->parse_email_placeholders( $client_subj_raw, $document_title, $form_data );

		$client_body_raw = ! empty( $settings['client_body'] ) ? $settings['client_body'] : '';
		if ( empty( $client_body_raw ) ) {
			$client_body_raw = "<p>Hola {nombre_cliente},</p>\n<p>Adjunto a este correo encontrarás la copia del documento firmado: <strong>{nombre_documento}</strong>.</p>";
		}
		$client_body = $this->parse_email_placeholders( $client_body_raw, $document_title, $form_data );

		$sent = wp_mail( $client_email, $client_subject, $client_body, $headers, $attachments );

		$this->log_email(
			$document_title,
			$client_email,
			__( 'Reenvío Cliente', 'wp-doc-signer' ),
			$sent ? __( 'Éxito', 'wp-doc-signer' ) : __( 'Fallido', 'wp-doc-signer' ),
			$sent ? '' : __( 'Error en wp_mail() al reenviar al cliente.', 'wp-doc-signer' )
		);

		if ( ! $sent ) {
			return new WP_Error( 'wpds_mail_failed', __( 'No se pudo reenviar el correo al cliente. Verifica la configuración SMTP.', 'wp-doc-signer' ) );
		}

		return $client_email;
	}

	/**
	 * Reenvía la copia del documento PDF a la dirección de correo de administración.
	 */
	public function resend_admin_email( $filename, $target_email = '' ) {
		$upload_dir = wp_upload_dir();
		$file_path  = $upload_dir['basedir'] . '/firmas-pdf/' . basename( $filename );

		if ( ! file_exists( $file_path ) ) {
			return new WP_Error( 'wpds_file_not_found', __( 'El archivo PDF no se encuentra en el servidor.', 'wp-doc-signer' ) );
		}

		$log_data       = $this->get_signature_log_by_filename( basename( $filename ) );
		$post_id        = ! empty( $log_data['document_id'] ) ? intval( $log_data['document_id'] ) : 0;
		$document_title = ! empty( $log_data['document_title'] ) ? $log_data['document_title'] : __( 'Documento Firmado', 'wp-doc-signer' );

		$form_data = array(
			'nombre'   => ! empty( $log_data['client_name'] ) ? $log_data['client_name'] : __( 'Cliente', 'wp-doc-signer' ),
			'email'    => ! empty( $log_data['client_email'] ) ? $log_data['client_email'] : '',
			'dni'      => ! empty( $log_data['client_dni'] ) ? $log_data['client_dni'] : '',
			'telefono' => ! empty( $log_data['client_phone'] ) ? $log_data['client_phone'] : '',
			'fecha'    => ! empty( $log_data['date'] ) ? date( 'd/m/Y', strtotime( $log_data['date'] ) ) : date( 'd/m/Y', filemtime( $file_path ) ),
		);

		$settings     = get_option( 'wpds_settings', array() );
		$sender_name  = ! empty( $settings['sender_name'] ) ? $settings['sender_name'] : get_bloginfo( 'name' );
		$sender_email = ! empty( $settings['sender_email'] ) ? $settings['sender_email'] : get_bloginfo( 'admin_email' );

		if ( ! empty( $target_email ) ) {
			$admin_emails = $target_email;
		} else {
			$admin_email_override = $post_id ? get_post_meta( $post_id, '_wpds_email', true ) : '';
			if ( ! empty( $admin_email_override ) ) {
				$admin_emails = $admin_email_override;
			} else {
				$admin_emails = ! empty( $settings['admin_emails'] ) ? $settings['admin_emails'] : get_bloginfo( 'admin_email' );
			}
		}

		$admin_subj_raw = ! empty( $settings['admin_subject'] ) ? $settings['admin_subject'] : __( 'Firmado: {nombre_documento} - {nombre_cliente}', 'wp-doc-signer' );
		$admin_subject  = $this->parse_email_placeholders( $admin_subj_raw, $document_title, $form_data );

		$admin_body  = '<h2>' . esc_html__( 'Reenvío de Documento Firmado', 'wp-doc-signer' ) . '</h2>';
		$admin_body .= '<p>' . sprintf( esc_html__( 'Se adjunta la copia del documento "%s".', 'wp-doc-signer' ), esc_html( $document_title ) ) . '</p>';
		$admin_body .= '<h3>' . esc_html__( 'Detalles del Firmante:', 'wp-doc-signer' ) . '</h3>';
		$admin_body .= '<ul>';
		$admin_body .= '<li><strong>' . esc_html__( 'Nombre Completo:', 'wp-doc-signer' ) . '</strong> ' . esc_html( $form_data['nombre'] ) . '</li>';
		$admin_body .= '<li><strong>' . esc_html__( 'DNI / Identificación:', 'wp-doc-signer' ) . '</strong> ' . esc_html( $form_data['dni'] ) . '</li>';
		$admin_body .= '<li><strong>' . esc_html__( 'Teléfono:', 'wp-doc-signer' ) . '</strong> ' . esc_html( $form_data['telefono'] ) . '</li>';
		$admin_body .= '<li><strong>' . esc_html__( 'Correo Electrónico:', 'wp-doc-signer' ) . '</strong> ' . esc_html( $form_data['email'] ) . '</li>';
		$admin_body .= '<li><strong>' . esc_html__( 'Fecha Firma:', 'wp-doc-signer' ) . '</strong> ' . esc_html( $form_data['fecha'] ) . '</li>';
		$admin_body .= '</ul>';
		$admin_body .= '<p>' . esc_html__( 'El archivo PDF con las firmas incrustadas se encuentra adjunto a este correo.', 'wp-doc-signer' ) . '</p>';

		$admin_emails_array = array_map( 'trim', explode( ',', $admin_emails ) );
		$sent_count         = 0;
		$attachments        = array( $file_path );

		$admin_headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . esc_html( $sender_name ) . ' <' . sanitize_email( $sender_email ) . '>',
		);

		if ( ! empty( $form_data['email'] ) ) {
			$admin_headers[] = 'Reply-To: ' . esc_html( $form_data['nombre'] ) . ' <' . sanitize_email( $form_data['email'] ) . '>';
		}

		foreach ( $admin_emails_array as $single_admin_email ) {
			if ( is_email( $single_admin_email ) ) {
				$sent = wp_mail( $single_admin_email, $admin_subject, $admin_body, $admin_headers, $attachments );
				$this->log_email(
					$document_title,
					$single_admin_email,
					__( 'Reenvío Administración', 'wp-doc-signer' ),
					$sent ? __( 'Éxito', 'wp-doc-signer' ) : __( 'Fallido', 'wp-doc-signer' ),
					$sent ? '' : __( 'Error en wp_mail() al reenviar a administración.', 'wp-doc-signer' )
				);
				if ( $sent ) {
					$sent_count++;
				}
			}
		}

		if ( 0 === $sent_count ) {
			return new WP_Error( 'wpds_mail_admin_failed', __( 'No se pudo reenviar el correo a los destinatarios administrativos.', 'wp-doc-signer' ) );
		}

		return implode( ', ', $admin_emails_array );
	}
}

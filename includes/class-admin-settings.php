<?php
/**
 * Clase para el Panel de Ajustes Generales y el Historial de Documentos Firmados.
 *
 * @package WP_Document_Signer_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPDS_Admin_Settings {

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
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'handle_zip_download' ) );
		add_action( 'admin_init', array( $this, 'handle_pdf_view' ) );
		add_action( 'admin_init', array( $this, 'handle_file_delete' ) );
		add_action( 'admin_init', array( $this, 'handle_bulk_actions' ) );
		add_action( 'admin_init', array( $this, 'handle_force_update_check' ) );
		add_action( 'admin_init', array( $this, 'handle_csv_export' ) );
		add_action( 'wp_ajax_wpds_ajax_check_update', array( $this, 'handle_ajax_check_update' ) );
		add_action( 'wp_ajax_wpds_ajax_upgrade_plugin', array( $this, 'handle_ajax_upgrade_plugin' ) );
		add_action( 'wp_ajax_wpds_ajax_filter_signed_docs', array( $this, 'handle_ajax_filter_signed_docs' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Registrar páginas del menú bajo el CPT wp_documento.
	 */
	public function add_settings_page() {
		// 1. Nueva sección dedicada exclusivamente al Historial de PDFs Firmados
		add_submenu_page(
			'edit.php?post_type=wp_documento',
			__( 'Documentos Firmados', 'wp-doc-signer' ),
			__( 'Docs. Firmados', 'wp-doc-signer' ),
			'manage_options',
			'wpds-signed-docs',
			array( $this, 'render_signed_docs_page' )
		);

		// 2. Sección de Ajustes Globales (ahora de ancho completo, más limpia)
		add_submenu_page(
			'edit.php?post_type=wp_documento',
			__( 'Ajustes de Firma', 'wp-doc-signer' ),
			__( 'Ajustes Globales', 'wp-doc-signer' ),
			'manage_options',
			'wpds-settings',
			array( $this, 'render_settings_page' )
		);

		// 3. Registro de Envíos de Correo
		add_submenu_page(
			'edit.php?post_type=wp_documento',
			__( 'Registro de Envíos', 'wp-doc-signer' ),
			__( 'Registro de Envíos', 'wp-doc-signer' ),
			'manage_options',
			'wpds-email-log',
			array( $this, 'render_email_log_page' )
		);
	}

	/**
	 * Obtiene las opciones de configuración de forma segura asegurando que siempre retorne un array.
	 */
	private function get_wpds_settings() {
		$options = get_option( 'wpds_settings' );
		return is_array( $options ) ? $options : array();
	}

	/**
	 * Cargar assets de administración.
	 */
	public function enqueue_admin_assets( $hook ) {
		$screen = get_current_screen();
		if ( $screen && ( 'wp_documento' === $screen->post_type || 'wp_documento_page_wpds-settings' === $hook || 'wp_documento_page_wpds-signed-docs' === $hook || 'wp_documento_page_wpds-email-log' === $hook ) ) {
			wp_enqueue_style( 'wpds-admin-style', WPDS_URL . 'assets/css/admin-style.css', array(), WPDS_VERSION );
			
			// Encolar media library para selector de marca de agua
			if ( 'wp_documento_page_wpds-settings' === $hook ) {
				wp_enqueue_media();
			}
		}
	}

	/**
	 * Registra secciones y campos de configuración.
	 */
	public function register_settings() {
		register_setting( 'wpds_settings_group', 'wpds_settings', array( $this, 'sanitize_settings' ) );

		// Sección de Configuración de Correo
		add_settings_section(
			'wpds_email_section',
			__( 'Configuración de Envíos de Correo (Notificaciones)', 'wp-doc-signer' ),
			array( $this, 'render_email_section_description' ),
			'wpds-settings'
		);

		add_settings_field(
			'admin_emails',
			__( 'Email(s) Administrativos', 'wp-doc-signer' ),
			array( $this, 'render_admin_emails_field' ),
			'wpds-settings',
			'wpds_email_section'
		);

		add_settings_field(
			'sender_name',
			__( 'Nombre del Remitente', 'wp-doc-signer' ),
			array( $this, 'render_sender_name_field' ),
			'wpds-settings',
			'wpds_email_section'
		);

		add_settings_field(
			'sender_email',
			__( 'Email del Remitente ("From")', 'wp-doc-signer' ),
			array( $this, 'render_sender_email_field' ),
			'wpds-settings',
			'wpds_email_section'
		);

		add_settings_field(
			'client_subject',
			__( 'Asunto Email Cliente', 'wp-doc-signer' ),
			array( $this, 'render_client_subject_field' ),
			'wpds-settings',
			'wpds_email_section'
		);

		add_settings_field(
			'admin_subject',
			__( 'Asunto Email Salón / Negocio', 'wp-doc-signer' ),
			array( $this, 'render_admin_subject_field' ),
			'wpds-settings',
			'wpds_email_section'
		);

		add_settings_field(
			'client_body',
			__( 'Cuerpo del Email para el Cliente', 'wp-doc-signer' ),
			array( $this, 'render_client_body_field' ),
			'wpds-settings',
			'wpds_email_section'
		);

		// Sección de Configuración de Archivos y Marca de Agua
		add_settings_section(
			'wpds_files_section',
			__( 'Configuración de Almacenamiento y Diseño', 'wp-doc-signer' ),
			array( $this, 'render_files_section_description' ),
			'wpds-settings'
		);

		add_settings_field(
			'watermark_url',
			__( 'Marca de Agua Personalizada (PDF)', 'wp-doc-signer' ),
			array( $this, 'render_watermark_url_field' ),
			'wpds-settings',
			'wpds_files_section'
		);

		add_settings_field(
			'save_local',
			__( 'Guardar copias PDF en el servidor', 'wp-doc-signer' ),
			array( $this, 'render_save_local_field' ),
			'wpds-settings',
			'wpds_files_section'
		);

		// Sección de Actualizaciones Automáticas (GitHub) - Oculta por defecto para marca blanca
		if ( isset( $_GET['show_git'] ) ) {
			add_settings_section(
				'wpds_github_section',
				__( 'Configuración de Actualizaciones Automáticas (GitHub)', 'wp-doc-signer' ),
				array( $this, 'render_github_section_description' ),
				'wpds-settings'
			);

			add_settings_field(
				'github_token',
				__( 'Token de Acceso Personal (PAT)', 'wp-doc-signer' ),
				array( $this, 'render_github_token_field' ),
				'wpds-settings',
				'wpds_github_section'
			);
		}
	}

	/**
	 * Sanitizar y validar los campos de configuración.
	 */
	public function sanitize_settings( $input ) {
		$output = array();
		$old_options = $this->get_wpds_settings();

		// Emails administrativos
		if ( isset( $input['admin_emails'] ) ) {
			$emails_raw = sanitize_text_field( $input['admin_emails'] );
			$emails_array = explode( ',', $emails_raw );
			$emails_clean = array();
			foreach ( $emails_array as $email ) {
				$email = trim( $email );
				if ( is_email( $email ) ) {
					$emails_clean[] = $email;
				}
			}
			$output['admin_emails'] = implode( ', ', $emails_clean );
		}

		// Nombre de remitente
		$output['sender_name'] = isset( $input['sender_name'] ) ? sanitize_text_field( $input['sender_name'] ) : get_bloginfo( 'name' );

		// Email de remitente
		if ( isset( $input['sender_email'] ) && is_email( $input['sender_email'] ) ) {
			$output['sender_email'] = sanitize_email( $input['sender_email'] );
		} else {
			$output['sender_email'] = get_bloginfo( 'admin_email' );
		}

		// Asuntos
		$output['client_subject'] = isset( $input['client_subject'] ) ? sanitize_text_field( $input['client_subject'] ) : __( 'Copia de tu documento firmado', 'wp-doc-signer' );
		$output['admin_subject']  = isset( $input['admin_subject'] ) ? sanitize_text_field( $input['admin_subject'] ) : __( 'Nuevo documento firmado recibido', 'wp-doc-signer' );

		// Cuerpo del correo
		$output['client_body'] = isset( $input['client_body'] ) ? wp_kses_post( $input['client_body'] ) : '';

		// URL de Marca de agua
		$output['watermark_url'] = isset( $input['watermark_url'] ) ? esc_url_raw( $input['watermark_url'] ) : '';

		// Guardado local
		$output['save_local'] = isset( $input['save_local'] ) ? 1 : 0;

		// Preservar Token de GitHub si no se envía en el formulario (por estar oculto)
		if ( isset( $input['github_token'] ) ) {
			$output['github_token'] = sanitize_text_field( $input['github_token'] );
		} elseif ( isset( $old_options['github_token'] ) ) {
			$output['github_token'] = $old_options['github_token'];
		}

		return $output;
	}

	public function render_email_section_description() {
		echo '<p>' . esc_html__( 'Configura los correos a donde se enviarán las notificaciones tras completar un acuerdo y los parámetros de envío.', 'wp-doc-signer' ) . '</p>';
		echo '<div class="notice notice-info inline" style="margin: 10px 0 20px 0; padding: 15px; border-left-color: #7227cb; background: #f6f0ff; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">';
		echo '<p style="margin: 0; font-size: 13.5px; font-weight: 500; color: #3c1e70; line-height: 1.4;">';
		echo '💡 <strong>¿Problemas con la entrega de correos?</strong> ';
		echo esc_html__( 'Si los emails de las firmas no llegan a las bandejas de entrada (especialmente a cuentas de Gmail o Outlook), se debe a filtros SPF o DMARC de tu hosting. Puedes resolverlo de inmediato activando y configurando el apartado de SMTP en tu plugin companion ', 'wp-doc-signer' );
		echo '<strong>WP Agency Toolkit</strong>';
		echo esc_html__( ' para enrutar de forma segura todos los envíos de WordPress.', 'wp-doc-signer' );
		echo '</p>';
		echo '</div>';
	}

	public function render_files_section_description() {
		echo '<p>' . esc_html__( 'Configura el comportamiento de almacenamiento local en el servidor y la imagen que aparecerá de fondo en los PDF.', 'wp-doc-signer' ) . '</p>';
	}

	public function render_github_section_description() {
		echo '<p>' . esc_html__( 'Si tu repositorio de GitHub es privado, debes ingresar un Token de Acceso Personal (classic PAT) con permisos de lectura para que WordPress pueda comprobar e instalar actualizaciones de forma automática.', 'wp-doc-signer' ) . '</p>';
	}

	public function render_admin_emails_field() {
		$options = $this->get_wpds_settings();
		$value = isset( $options['admin_emails'] ) ? $options['admin_emails'] : get_bloginfo( 'admin_email' );
		echo '<input type="text" name="wpds_settings[admin_emails]" class="large-text" value="' . esc_attr( $value ) . '" placeholder="admin@tuservicio.com, copias@tuservicio.com" />';
		echo '<p class="description">' . esc_html__( 'Ingresa los correos de administración separados por comas.', 'wp-doc-signer' ) . '</p>';
	}

	public function render_sender_name_field() {
		$options = $this->get_wpds_settings();
		$value = isset( $options['sender_name'] ) ? $options['sender_name'] : get_bloginfo( 'name' );
		echo '<input type="text" name="wpds_settings[sender_name]" class="regular-text" value="' . esc_attr( $value ) . '" />';
	}

	public function render_sender_email_field() {
		$options = $this->get_wpds_settings();
		$value = isset( $options['sender_email'] ) ? $options['sender_email'] : get_bloginfo( 'admin_email' );
		echo '<input type="email" name="wpds_settings[sender_email]" class="regular-text" value="' . esc_attr( $value ) . '" />';
		echo '<p class="description" style="color: #b32d2e; font-weight: 500;">' . esc_html__( '⚠️ IMPORTANTE: Utiliza un correo con el mismo dominio de tu web (ej. noreply@saraperezstudio.com o info@saraperezstudio.com) en lugar de una dirección de Gmail, Hotmail o Yahoo. Si envías correos desde tu servidor simulando ser de @gmail.com, Google bloqueará la entrega inmediatamente debido a políticas SPF y DMARC.', 'wp-doc-signer' ) . '</p>';
	}

	public function render_client_subject_field() {
		$options = $this->get_wpds_settings();
		$value = isset( $options['client_subject'] ) ? $options['client_subject'] : __( 'Tu copia de: {nombre_documento}', 'wp-doc-signer' );
		echo '<input type="text" name="wpds_settings[client_subject]" class="large-text" value="' . esc_attr( $value ) . '" />';
	}

	public function render_admin_subject_field() {
		$options = $this->get_wpds_settings();
		$value = isset( $options['admin_subject'] ) ? $options['admin_subject'] : __( 'Firmado: {nombre_documento} - {nombre_cliente}', 'wp-doc-signer' );
		echo '<input type="text" name="wpds_settings[admin_subject]" class="large-text" value="' . esc_attr( $value ) . '" />';
	}

	public function render_client_body_field() {
		$options = $this->get_wpds_settings();
		$value = isset( $options['client_body'] ) ? $options['client_body'] : __( "<p>Hola {nombre_cliente},</p>\n<p>Gracias por tu firma. Adjunto a este correo encontrarás la copia en PDF firmada digitalmente de tu documento: <strong>{nombre_documento}</strong>.</p>\n<p>Saludos cordiales.</p>", 'wp-doc-signer' );

		$settings = array(
			'textarea_name' => 'wpds_settings[client_body]',
			'textarea_rows' => 8,
			'media_buttons' => false,
			'tinymce'       => true,
		);
		wp_editor( $value, 'wpds_client_body_editor', $settings );
	}

	public function render_watermark_url_field() {
		$options = $this->get_wpds_settings();
		$value = isset( $options['watermark_url'] ) ? $options['watermark_url'] : '';
		?>
		<div class="wpds-media-uploader-row">
			<input type="text" name="wpds_settings[watermark_url]" id="wpds_watermark_url" class="large-text" value="<?php echo esc_url( $value ); ?>" placeholder="https://..." style="width: 70%; display: inline-block; vertical-align: middle;" />
			<button type="button" id="wpds_upload_watermark_btn" class="button" style="display: inline-block; vertical-align: middle; margin-left: 10px;"><?php esc_html_e( 'Subir / Seleccionar Imagen', 'wp-doc-signer' ); ?></button>
		</div>
		<p class="description"><?php esc_html_e( 'Carga el logotipo que aparecerá de fondo en cada página del PDF generado. Se recomienda una imagen cuadrada y ligera con colores oscuros o escala de grises. Si se deja vacío, se utilizará la imagen predeterminada del plugin.', 'wp-doc-signer' ); ?></p>
		
		<script>
			jQuery(document).ready(function($) {
				$('#wpds_upload_watermark_btn').click(function(e) {
					e.preventDefault();
					var custom_uploader = wp.media({
						title: '<?php esc_html_e( 'Seleccionar Marca de Agua', 'wp-doc-signer' ); ?>',
						button: {
							text: '<?php esc_html_e( 'Usar Imagen', 'wp-doc-signer' ); ?>'
						},
						multiple: false
					}).on('select', function() {
						var attachment = custom_uploader.state().get('selection').first().toJSON();
						$('#wpds_watermark_url').val(attachment.url);
					}).open();
				});
			});
		</script>
		<?php
	}

	public function render_save_local_field() {
		$options = $this->get_wpds_settings();
		$checked = isset( $options['save_local'] ) ? $options['save_local'] : 1;
		echo '<label><input type="checkbox" name="wpds_settings[save_local]" value="1" ' . checked( 1, $checked, false ) . ' /> ' . esc_html__( 'Guardar archivos firmados en la carpeta del servidor /wp-content/uploads/firmas-pdf/', 'wp-doc-signer' ) . '</label>';
		echo '<p class="description" style="color: #646970;">' . esc_html__( 'Los archivos se protegerán automáticamente mediante restricciones de acceso .htaccess en servidores basados en Apache.', 'wp-doc-signer' ) . '</p>';
	}

	public function render_github_token_field() {
		$options = $this->get_wpds_settings();
		$value = isset( $options['github_token'] ) ? $options['github_token'] : '';
		echo '<input type="password" name="wpds_settings[github_token]" class="regular-text" value="' . esc_attr( $value ) . '" placeholder="ghp_..." />';
		echo '<p class="description">' . esc_html__( 'Genera tu token de acceso (PAT) clásico en GitHub con permisos de "repo" para repositorios privados.', 'wp-doc-signer' ) . '</p>';
	}



	/**
	 * Maneja la descarga masiva en ZIP de todos los PDF firmados.
	 */
	public function handle_zip_download() {
		if ( isset( $_GET['page'] ) && ( 'wpds-settings' === $_GET['page'] || 'wpds-signed-docs' === $_GET['page'] ) && isset( $_GET['download_zip'] ) ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Acceso denegado.', 'wp-doc-signer' ) );
			}

			$upload_dir = wp_upload_dir();
			$target_dir = $upload_dir['basedir'] . '/firmas-pdf';

			if ( ! file_exists( $target_dir ) ) {
				wp_die( esc_html__( 'El directorio de firmas no existe todavía.', 'wp-doc-signer' ) );
			}

			$files = glob( $target_dir . '/*.pdf' );
			if ( empty( $files ) ) {
				wp_die( esc_html__( 'No hay documentos PDF firmados guardados para descargar.', 'wp-doc-signer' ) );
			}

			if ( ! class_exists( 'ZipArchive' ) ) {
				wp_die( esc_html__( 'La extensión ZipArchive de PHP no está disponible en este servidor.', 'wp-doc-signer' ) );
			}

			$zip = new ZipArchive();
			$zip_filename = 'documentos_firmados_' . date( 'Ymd_His' ) . '.zip';
			$zip_filepath = get_temp_dir() . $zip_filename;

			if ( $zip->open( $zip_filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE ) === true ) {
				foreach ( $files as $file ) {
					$zip->addFile( $file, basename( $file ) );
				}
				$zip->close();

				if ( file_exists( $zip_filepath ) ) {
					header( 'Content-Type: application/zip' );
					header( 'Content-Disposition: attachment; filename="' . $zip_filename . '"' );
					header( 'Content-Length: ' . filesize( $zip_filepath ) );
					readfile( $zip_filepath );
					unlink( $zip_filepath );
					exit;
				}
			}
			wp_die( esc_html__( 'Error al generar el archivo ZIP.', 'wp-doc-signer' ) );
		}
	}

	/**
	 * Transmite y muestra de forma segura el archivo PDF para administradores autorizados.
	 * Bypassea la restricción del .htaccess del directorio de subidas.
	 */
	public function handle_pdf_view() {
		if ( isset( $_GET['page'] ) && ( 'wpds-settings' === $_GET['page'] || 'wpds-signed-docs' === $_GET['page'] ) && isset( $_GET['view_pdf'] ) ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Acceso denegado.', 'wp-doc-signer' ) );
			}

			$file_name = sanitize_text_field( $_GET['view_pdf'] );
			$upload_dir = wp_upload_dir();
			$target_dir = $upload_dir['basedir'] . '/firmas-pdf';
			$file_path  = $target_dir . '/' . basename( $file_name );

			if ( file_exists( $file_path ) && 'pdf' === pathinfo( $file_path, PATHINFO_EXTENSION ) ) {
				$disposition = ( isset( $_GET['download'] ) && '1' === $_GET['download'] ) ? 'attachment' : 'inline';
				
				header( 'Content-Type: application/pdf' );
				header( 'Content-Disposition: ' . $disposition . '; filename="' . basename( $file_path ) . '"' );
				header( 'Content-Length: ' . filesize( $file_path ) );
				readfile( $file_path );
				exit;
			} else {
				wp_die( esc_html__( 'El archivo solicitado no existe o no es un PDF válido.', 'wp-doc-signer' ) );
			}
		}
	}

	/**
	 * Maneja la eliminación individual de archivos firmados.
	 */
	public function handle_file_delete() {
		if ( isset( $_GET['page'] ) && ( 'wpds-settings' === $_GET['page'] || 'wpds-signed-docs' === $_GET['page'] ) && isset( $_GET['delete_file'] ) ) {
			$file_to_delete = sanitize_text_field( $_GET['delete_file'] );
			$nonce          = isset( $_GET['wpds_del_nonce'] ) ? sanitize_text_field( $_GET['wpds_del_nonce'] ) : '';

			if ( ! wp_verify_nonce( $nonce, 'wpds_delete_file_action_' . $file_to_delete ) ) {
				wp_die( esc_html__( 'Error de seguridad o sesión expirada.', 'wp-doc-signer' ) );
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Acceso denegado.', 'wp-doc-signer' ) );
			}

			$upload_dir = wp_upload_dir();
			$target_dir = $upload_dir['basedir'] . '/firmas-pdf';
			$file_path  = $target_dir . '/' . basename( $file_to_delete );

			if ( file_exists( $file_path ) && 'pdf' === pathinfo( $file_path, PATHINFO_EXTENSION ) ) {
				unlink( $file_path );
				wp_redirect( admin_url( 'edit.php?post_type=wp_documento&page=' . sanitize_key( $_GET['page'] ) . '&deleted=1' ) );
				exit;
			} else {
				wp_die( esc_html__( 'El archivo no existe o no es un PDF válido.', 'wp-doc-signer' ) );
			}
		}
	}

	/**
	 * Maneja las acciones en lote (descarga ZIP y eliminación múltiple).
	 */
	public function handle_bulk_actions() {
		if ( isset( $_POST['wpds_bulk_action_nonce'] ) && wp_verify_nonce( $_POST['wpds_bulk_action_nonce'], 'wpds_bulk_action' ) ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Acceso denegado.', 'wp-doc-signer' ) );
			}

			$action = isset( $_POST['bulk_action'] ) ? sanitize_text_field( $_POST['bulk_action'] ) : '';
			$files  = isset( $_POST['bulk_files'] ) ? array_map( 'sanitize_text_field', $_POST['bulk_files'] ) : array();

			if ( empty( $action ) || '-1' === $action || empty( $files ) ) {
				return;
			}

			$upload_dir = wp_upload_dir();
			$target_dir = $upload_dir['basedir'] . '/firmas-pdf';

			if ( 'download_zip' === $action ) {
				if ( ! class_exists( 'ZipArchive' ) ) {
					wp_die( esc_html__( 'La extensión ZipArchive de PHP no está disponible en este servidor.', 'wp-doc-signer' ) );
				}

				$zip = new ZipArchive();
				$zip_filename = 'documentos_seleccionados_' . date( 'Ymd_His' ) . '.zip';
				$zip_filepath = get_temp_dir() . $zip_filename;

				if ( $zip->open( $zip_filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE ) === true ) {
					foreach ( $files as $file ) {
						$file_path = $target_dir . '/' . basename( $file );
						if ( file_exists( $file_path ) && 'pdf' === pathinfo( $file_path, PATHINFO_EXTENSION ) ) {
							$zip->addFile( $file_path, basename( $file_path ) );
						}
					}
					$zip->close();

					if ( file_exists( $zip_filepath ) ) {
						header( 'Content-Type: application/zip' );
						header( 'Content-Disposition: attachment; filename="' . $zip_filename . '"' );
						header( 'Content-Length: ' . filesize( $zip_filepath ) );
						readfile( $zip_filepath );
						unlink( $zip_filepath );
						exit;
					}
				}
				wp_die( esc_html__( 'Error al generar el archivo ZIP.', 'wp-doc-signer' ) );
			}

			if ( 'delete' === $action ) {
				$deleted_count = 0;
				foreach ( $files as $file ) {
					$file_path = $target_dir . '/' . basename( $file );
					if ( file_exists( $file_path ) && 'pdf' === pathinfo( $file_path, PATHINFO_EXTENSION ) ) {
						unlink( $file_path );
						$deleted_count++;
					}
				}
				wp_redirect( admin_url( 'edit.php?post_type=wp_documento&page=wpds-signed-docs&bulk_deleted=' . $deleted_count ) );
				exit;
			}
		}
	}



	/**
	 * Renderizar la página de opciones del panel (Ajustes Globales - Ahora de ancho completo).
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_GET['settings-updated'] ) ) {
			add_settings_error( 'wpds_messages', 'wpds_message', __( 'Configuración guardada correctamente.', 'wp-doc-signer' ), 'updated' );
		}

		settings_errors( 'wpds_messages' );

		$latest_version = $this->get_latest_github_version();
		$current_version = WPDS_VERSION;
		?>
		<div class="wrap wpds-admin-wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<!-- Indicación de Versión y Actualizaciones -->
			<div id="wpds-version-notice-container" style="min-height: 48px;">
				<?php if ( 'error_api_connection' === $latest_version ) : ?>
					<div style="margin: 15px 0 10px 0; font-size: 13px; color: #d63638; font-weight: 500; display: flex; align-items: center; gap: 6px; background: #fff; padding: 12px 15px; border-radius: 6px; border: 1px solid #d63638; max-width: 830px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
						<span style="color: #d63638; font-size: 14px;">⚠️</span> 
						<span><?php echo esc_html__( 'No se pudo comprobar si hay actualizaciones disponibles en este momento.', 'wp-doc-signer' ); ?></span>
						<button type="button" id="wpds-check-update-btn" class="button button-secondary button-small" style="margin-left: 15px; vertical-align: middle;"><?php esc_html_e( 'Comprobar ahora', 'wp-doc-signer' ); ?></button>
					</div>
				<?php elseif ( $latest_version && version_compare( $latest_version, $current_version, '>' ) ) : ?>
					<div class="notice notice-warning inline" style="margin: 15px 0; padding: 15px; border-left-color: #ffb900; background: #fffdf6; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: block; max-width: 830px;">
						<p style="margin: 0; font-size: 14px; font-weight: 600; color: #7a5f00; line-height: 1.5;">
							🎉 <?php echo sprintf( esc_html__( '¡Nueva versión disponible! Tienes instalada la versión %s y la versión más reciente es la %s.', 'wp-doc-signer' ), '<strong>' . esc_html( $current_version ) . '</strong>', '<strong>v' . esc_html( $latest_version ) . '</strong>' ); ?>
							<br/>
							<span style="font-weight: normal; font-size: 12.5px; color: #66521a; display: inline-block; margin-top: 5px;"><?php esc_html_e( 'Una nueva versión de actualización está disponible para su instalación.', 'wp-doc-signer' ); ?></span>
							<button type="button" id="wpds-upgrade-plugin-btn" class="button button-primary button-small" style="margin-left: 15px; vertical-align: middle;"><?php esc_html_e( 'Actualizar ahora', 'wp-doc-signer' ); ?></button>
							<button type="button" id="wpds-check-update-btn" class="button button-secondary button-small" style="margin-left: 10px; vertical-align: middle;"><?php esc_html_e( 'Comprobar ahora', 'wp-doc-signer' ); ?></button>
						</p>
					</div>
				<?php else : ?>
					<div style="margin: 15px 0 10px 0; font-size: 13px; color: #475569; font-weight: 500; display: flex; align-items: center; gap: 6px; background: #fff; padding: 12px 15px; border-radius: 6px; border: 1px solid #c3c4c7; max-width: 830px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
						<span style="color: #22c55e; font-size: 14px;">●</span> 
						<span><?php echo sprintf( esc_html__( 'Versión instalada: %s (El plugin está al día)', 'wp-doc-signer' ), esc_html( $current_version ) ); ?></span>
						<button type="button" id="wpds-check-update-btn" class="button button-secondary button-small" style="margin-left: 15px; vertical-align: middle;"><?php esc_html_e( 'Comprobar ahora', 'wp-doc-signer' ); ?></button>
					</div>
				<?php endif; ?>
			</div>
			
			<div class="wpds-settings-layout" style="max-width: 860px; margin-top: 20px; padding: 25px; border-radius: 8px; border: 1px solid #c3c4c7; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
				<form action="options.php" method="post">
					<?php
					settings_fields( 'wpds_settings_group' );
					do_settings_sections( 'wpds-settings' );
					submit_button( __( 'Guardar Cambios', 'wp-doc-signer' ) );
					?>
				</form>
			</div>
		</div>

		<!-- Script de jQuery para comprobar y actualizar de forma asíncrona -->
		<script>
		jQuery(document).ready(function($) {
			// Comprobación manual
			$(document).on('click', '#wpds-check-update-btn', function(e) {
				e.preventDefault();
				var $btn = $(this);
				var originalText = $btn.text();
				$btn.prop('disabled', true).text('<?php esc_html_e( "Comprobando...", "wp-doc-signer" ); ?>');

				$.post(ajaxurl, {
					action: 'wpds_ajax_check_update',
					nonce: '<?php echo esc_js( wp_create_nonce( "wpds_admin_nonce" ) ); ?>'
				}, function(response) {
					if (response.success) {
						$('#wpds-version-notice-container').html(response.data.html_version);
					} else {
						alert(response.data.message || 'Error al comprobar actualizaciones.');
					}
					$btn.prop('disabled', false).text(originalText);
				}).fail(function(xhr, status, error) {
					console.error('AJAX Error:', error, xhr.responseText);
					alert('Error de conexión con el servidor.');
					$btn.prop('disabled', false).text(originalText);
				});
			});

			// Actualización inline sin parpadeos
			$(document).on('click', '#wpds-upgrade-plugin-btn', function(e) {
				e.preventDefault();
				var $btn = $(this);
				var originalText = $btn.text();
				if (!confirm('¿Estás seguro de que deseas actualizar el plugin ahora?')) {
					return;
				}
				$btn.prop('disabled', true).text('<?php esc_html_e( "Actualizando...", "wp-doc-signer" ); ?>');

				$.post(ajaxurl, {
					action: 'wpds_ajax_upgrade_plugin',
					nonce: '<?php echo esc_js( wp_create_nonce( "wpds_admin_nonce" ) ); ?>'
				}, function(response) {
					if (response.success) {
						alert(response.data.message);
						location.reload();
					} else {
						alert(response.data.message || 'Error al realizar la actualización.');
						$btn.prop('disabled', false).text(originalText);
					}
				}).fail(function(xhr, status, error) {
					console.error('AJAX Error:', error, xhr.responseText);
					alert('Error al conectar con el servidor.');
					$btn.prop('disabled', false).text(originalText);
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Forzar la eliminación de la caché de versión de GitHub al solicitarlo.
	 */
	public function handle_force_update_check() {
		if ( isset( $_GET['page'] ) && 'wpds-settings' === $_GET['page'] && isset( $_GET['check_update'] ) ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Acceso denegado.', 'wp-doc-signer' ) );
			}
			delete_site_transient( 'update_plugins' );
			delete_transient( 'wpds_github_update_check' );
			delete_transient( 'wpds_latest_github_version' );
			wp_redirect( remove_query_arg( 'check_update' ) );
			exit;
		}
	}

	/**
	 * Realiza la comprobación de versión de GitHub de forma asíncrona mediante AJAX.
	 */
	public function handle_ajax_check_update() {
		check_ajax_referer( 'wpds_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Acceso denegado.', 'wp-doc-signer' ) ) );
		}

		delete_site_transient( 'update_plugins' );
		delete_transient( 'wpds_github_update_check' );
		delete_transient( 'wpds_latest_github_version' );

		$latest_version = $this->get_latest_github_version();
		$current_version = WPDS_VERSION;

		ob_start();
		if ( 'error_api_connection' === $latest_version ) {
			?>
			<div style="margin: 15px 0 10px 0; font-size: 13px; color: #d63638; font-weight: 500; display: flex; align-items: center; gap: 6px; background: #fff; padding: 12px 15px; border-radius: 6px; border: 1px solid #d63638; max-width: 830px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
				<span style="color: #d63638; font-size: 14px;">⚠️</span> 
				<span><?php echo esc_html__( 'No se pudo comprobar si hay actualizaciones disponibles en este momento.', 'wp-doc-signer' ); ?></span>
				<button type="button" id="wpds-check-update-btn" class="button button-secondary button-small" style="margin-left: 15px; vertical-align: middle;"><?php esc_html_e( 'Comprobar ahora', 'wp-doc-signer' ); ?></button>
			</div>
			<?php
		} elseif ( $latest_version && version_compare( $latest_version, $current_version, '>' ) ) {
			?>
			<div class="notice notice-warning inline" style="margin: 15px 0; padding: 15px; border-left-color: #ffb900; background: #fffdf6; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: block; max-width: 830px;">
				<p style="margin: 0; font-size: 14px; font-weight: 600; color: #7a5f00; line-height: 1.5;">
					🎉 <?php echo sprintf( esc_html__( '¡Nueva versión disponible! Tienes instalada la versión %s y la versión más reciente es la %s.', 'wp-doc-signer' ), '<strong>' . esc_html( $current_version ) . '</strong>', '<strong>v' . esc_html( $latest_version ) . '</strong>' ); ?>
					<br/>
					<span style="font-weight: normal; font-size: 12.5px; color: #66521a; display: inline-block; margin-top: 5px;"><?php esc_html_e( 'Una nueva versión de actualización está disponible para su instalación.', 'wp-doc-signer' ); ?></span>
					<button type="button" id="wpds-upgrade-plugin-btn" class="button button-primary button-small" style="margin-left: 15px; vertical-align: middle;"><?php esc_html_e( 'Actualizar ahora', 'wp-doc-signer' ); ?></button>
					<button type="button" id="wpds-check-update-btn" class="button button-secondary button-small" style="margin-left: 10px; vertical-align: middle;"><?php esc_html_e( 'Comprobar ahora', 'wp-doc-signer' ); ?></button>
				</p>
			</div>
			<?php
		} else {
			?>
			<div style="margin: 15px 0 10px 0; font-size: 13px; color: #475569; font-weight: 500; display: flex; align-items: center; gap: 6px; background: #fff; padding: 12px 15px; border-radius: 6px; border: 1px solid #c3c4c7; max-width: 830px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
				<span style="color: #22c55e; font-size: 14px;">●</span> 
				<span><?php echo sprintf( esc_html__( 'Versión instalada: %s (El plugin está al día)', 'wp-doc-signer' ), esc_html( $current_version ) ); ?></span>
				<button type="button" id="wpds-check-update-btn" class="button button-secondary button-small" style="margin-left: 15px; vertical-align: middle;"><?php esc_html_e( 'Comprobar ahora', 'wp-doc-signer' ); ?></button>
			</div>
			<?php
		}
		$html_version = ob_get_clean();

		wp_send_json_success( array(
			'html_version'    => $html_version,
		) );
	}

	/**
	 * Actualiza el plugin de forma inline mediante AJAX sin parpadeos de pantalla.
	 */
	public function handle_ajax_upgrade_plugin() {
		check_ajax_referer( 'wpds_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_send_json_error( array( 'message' => __( 'No tienes permisos suficientes para actualizar plugins.', 'wp-doc-signer' ) ) );
		}

		// Limpiar las cachés de actualización de WordPress y GitHub
		delete_site_transient( 'update_plugins' );
		delete_transient( 'wpds_github_update_check' );

		// Forzar a WordPress a comprobar actualizaciones para inyectar la URL del zip más reciente
		wp_update_plugins();

		include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		include_once ABSPATH . 'wp-admin/includes/plugin.php';

		// Usar un skin silencioso para no imprimir HTML
		$skin     = new Automatic_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );
		$plugin   = plugin_basename( WPDS_PATH . 'wp-document-signer.php' );
		
		// Ejecutar la actualización
		$result = $upgrader->upgrade( $plugin );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		} elseif ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'La actualización falló sin error reportado. Verifica los permisos de escritura.', 'wp-doc-signer' ) ) );
		}

		// Reactivar el plugin si se desactivó durante la actualización
		$reactivate = activate_plugin( $plugin );
		if ( is_wp_error( $reactivate ) ) {
			wp_send_json_error( array( 'message' => __( 'Plugin actualizado, pero no se pudo reactivar: ', 'wp-doc-signer' ) . $reactivate->get_error_message() ) );
		}

		// Limpiar de nuevo transients post-actualización
		delete_site_transient( 'update_plugins' );
		delete_transient( 'wpds_github_update_check' );

		wp_send_json_success( array(
			'message' => __( 'Plugin actualizado y reactivado con éxito.', 'wp-doc-signer' ),
		) );
	}

	public function get_latest_github_version() {
		$release = WPDS_Updater::get_instance()->get_latest_github_release();
		if ( $release && isset( $release['tag_name'] ) ) {
			return ltrim( $release['tag_name'], 'v' );
		}
		return 'error_api_connection';
	}



	/**
	 * Obtiene el listado filtrado de archivos PDF.
	 */
	private function get_filtered_signed_files( $search_query = '' ) {
		$upload_dir = wp_upload_dir();
		$target_dir = $upload_dir['basedir'] . '/firmas-pdf';
		$files      = array();

		if ( file_exists( $target_dir ) ) {
			$files = glob( $target_dir . '/*.pdf' );
		}

		if ( ! empty( $files ) ) {
			usort( $files, function( $a, $b ) {
				return filemtime( $b ) - filemtime( $a );
			});
		}

		if ( ! empty( $search_query ) && ! empty( $files ) ) {
			$files = array_filter( $files, function( $file ) use ( $search_query ) {
				return strpos( strtolower( basename( $file ) ), strtolower( $search_query ) ) !== false;
			});
		}

		return $files;
	}

	/**
	 * Filtra los documentos firmados mediante AJAX.
	 */
	public function handle_ajax_filter_signed_docs() {
		check_ajax_referer( 'wpds_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Acceso denegado.', 'wp-doc-signer' ) ) );
		}

		$search_query = isset( $_POST['s'] ) ? sanitize_text_field( $_POST['s'] ) : '';
		$current_page = isset( $_POST['paged'] ) ? max( 1, intval( $_POST['paged'] ) ) : 1;

		$files = $this->get_filtered_signed_files( $search_query );

		$total_items    = count( $files );
		$items_per_page = 20;
		$total_pages    = ceil( $total_items / $items_per_page );

		if ( ! empty( $files ) ) {
			$files_sliced = array_slice( $files, ( $current_page - 1 ) * $items_per_page, $items_per_page );
		} else {
			$files_sliced = array();
		}

		// Renderizar tbody
		ob_start();
		if ( empty( $files_sliced ) ) {
			?>
			<tr class="no-items">
				<td class="colspanchange" colspan="5" style="text-align: center; padding: 40px; color: #646970; font-style: italic;">
					<?php if ( ! empty( $search_query ) ) : ?>
						<?php esc_html_e( 'No se encontraron PDFs firmados que coincidan con la búsqueda.', 'wp-doc-signer' ); ?>
					<?php else : ?>
						<?php esc_html_e( 'No hay documentos PDF firmados en el servidor actualmente.', 'wp-doc-signer' ); ?>
					<?php endif; ?>
				</td>
			</tr>
			<?php
		} else {
			foreach ( $files_sliced as $file_path ) {
				$filename = basename( $file_path );
				$file_url      = admin_url( 'edit.php?post_type=wp_documento&page=wpds-signed-docs&view_pdf=' . urlencode( $filename ) );
				$download_url  = admin_url( 'edit.php?post_type=wp_documento&page=wpds-signed-docs&view_pdf=' . urlencode( $filename ) . '&download=1' );
				$del_url       = admin_url( 'edit.php?post_type=wp_documento&page=wpds-signed-docs&delete_file=' . urlencode( $filename ) . '&wpds_del_nonce=' . wp_create_nonce( 'wpds_delete_file_action_' . $filename ) );
				?>
				<tr>
					<th scope="row" class="check-column wpds-checkbox-cell" style="width: 2.2em; padding: 12px 10px; text-align: center; vertical-align: middle;">
						<input type="checkbox" name="bulk_files[]" value="<?php echo esc_attr( $filename ); ?>">
					</th>
					<td class="title column-title column-primary page-title" style="padding: 12px 10px; vertical-align: middle;">
						<strong>
							<a class="row-title" href="<?php echo esc_url( $file_url ); ?>" target="_blank" style="text-decoration: none; color: #2271b1;" title="<?php esc_attr_e( 'Visualizar PDF', 'wp-doc-signer' ); ?>">
								<span class="dashicons dashicons-pdf" style="vertical-align: middle; margin-right: 5px; color: #d9383a; font-size: 19px; width: 19px; height: 19px;"></span> 
								<?php echo esc_html( $filename ); ?>
							</a>
						</strong>
					</td>
					<td class="date column-date" style="padding: 12px 10px; vertical-align: middle; color: #50575e;">
						<?php echo esc_html( date( 'd/m/Y H:i:s', filemtime( $file_path ) ) ); ?>
					</td>
					<td class="size column-size" style="padding: 12px 10px; vertical-align: middle; text-align: center; color: #50575e;">
						<?php echo esc_html( size_format( filesize( $file_path ) ) ); ?>
					</td>
					<td class="actions column-actions" style="padding: 12px 10px; vertical-align: middle; text-align: right;">
						<a href="<?php echo esc_url( $file_url ); ?>" class="button wpds-action-btn wpds-view-btn" target="_blank" title="<?php esc_attr_e( 'Visualizar PDF en nueva pestaña', 'wp-doc-signer' ); ?>">
							<span class="dashicons dashicons-visibility"></span>
						</a>
						<a href="<?php echo esc_url( $download_url ); ?>" class="button wpds-action-btn wpds-download-btn" title="<?php esc_attr_e( 'Descargar archivo PDF', 'wp-doc-signer' ); ?>">
							<span class="dashicons dashicons-download"></span>
						</a>
						<a href="<?php echo esc_url( $del_url ); ?>" class="button wpds-action-btn wpds-delete-btn" onclick="return confirm('¿Está seguro de que desea eliminar permanentemente este archivo del servidor?');" title="<?php esc_attr_e( 'Eliminar permanentemente del servidor', 'wp-doc-signer' ); ?>">
							<span class="dashicons dashicons-trash"></span>
						</a>
					</td>
				</tr>
				<?php
			}
		}
		$html_tbody = ob_get_clean();

		// Renderizar bloque de paginación
		ob_start();
		if ( $total_pages > 1 ) {
			?>
			<span class="displaying-num" style="margin-right: 10px; font-style: italic; color: #646970;"><?php echo sprintf( _n( '%s elemento', '%s elementos', $total_items, 'wp-doc-signer' ), number_format_i18n( $total_items ) ); ?></span>
			<?php
			echo paginate_links( array(
				'base'      => '#%#%',
				'format'    => '',
				'total'     => $total_pages,
				'current'   => $current_page,
				'prev_text' => __( '&laquo; Anterior', 'wp-doc-signer' ),
				'next_text' => __( 'Siguiente &raquo;', 'wp-doc-signer' ),
			) );
		} else {
			?>
			<span class="displaying-num" style="font-style: italic; color: #646970;"><?php echo sprintf( _n( '%s elemento', '%s elementos', $total_items, 'wp-doc-signer' ), number_format_i18n( $total_items ) ); ?></span>
			<?php
		}
		$html_pagination = ob_get_clean();

		wp_send_json_success( array(
			'html_tbody'      => $html_tbody,
			'html_pagination' => $html_pagination,
		) );
	}

	/**
	 * Renderizar la página dedicada para el Historial de Documentos Firmados (Docs. Firmados).
	 * Incluye paginación nativa y buscador funcional de archivos locales.
	 */
	public function render_signed_docs_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Alertas de eliminación individual
		if ( isset( $_GET['deleted'] ) ) {
			add_settings_error( 'wpds_messages', 'wpds_message', __( 'El archivo PDF seleccionado ha sido eliminado del servidor.', 'wp-doc-signer' ), 'updated' );
		}

		// Alertas de eliminación masiva en lote
		if ( isset( $_GET['bulk_deleted'] ) ) {
			$count = intval( $_GET['bulk_deleted'] );
			add_settings_error( 'wpds_messages', 'wpds_message', sprintf( _n( 'Se ha eliminado %s archivo del servidor.', 'Se han eliminado %s archivos del servidor.', $count, 'wp-doc-signer' ), $count ), 'updated' );
		}

		settings_errors( 'wpds_messages' );

		// Obtener listado de archivos guardados localmente
		$upload_dir = wp_upload_dir();
		$target_dir = $upload_dir['basedir'] . '/firmas-pdf';
		$files      = array();

		if ( file_exists( $target_dir ) ) {
			$files = glob( $target_dir . '/*.pdf' );
		}

		// Ordenar por fecha de modificación descendente (más nuevos primero)
		if ( ! empty( $files ) ) {
			usort( $files, function( $a, $b ) {
				return filemtime( $b ) - filemtime( $a );
			});
		}

		// Filtrar por búsqueda si existe
		$search_query = isset( $_REQUEST['s'] ) ? sanitize_text_field( $_REQUEST['s'] ) : '';
		if ( ! empty( $search_query ) && ! empty( $files ) ) {
			$files = array_filter( $files, function( $file ) use ( $search_query ) {
				return strpos( strtolower( basename( $file ) ), strtolower( $search_query ) ) !== false;
			});
		}

		$total_items    = count( $files );
		$items_per_page = 20;
		$current_page   = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
		$total_pages    = ceil( $total_items / $items_per_page );

		// Cortar los elementos correspondientes a la página actual
		if ( ! empty( $files ) ) {
			$files_sliced = array_slice( $files, ( $current_page - 1 ) * $items_per_page, $items_per_page );
		} else {
			$files_sliced = array();
		}
		?>
		<div class="wrap wpds-admin-wrap" id="wpds-signed-docs-container">
			<h1 class="wp-heading-inline" style="margin-bottom: 15px;"><?php esc_html_e( 'Documentos Firmados (PDF)', 'wp-doc-signer' ); ?></h1>
			
			<!-- Buscador dinámico de archivos sin recarga -->
			<div class="wpds-search-form-container" style="display: flex; justify-content: flex-end; margin-bottom: 15px; margin-top: 5px;">
				<div style="display: flex; gap: 6px; align-items: center; position: relative;">
					<label class="screen-reader-text" for="wpds-search-input"><?php esc_html_e( 'Buscar PDFs:', 'wp-doc-signer' ); ?></label>
					<input type="search" id="wpds-search-input" value="<?php echo esc_attr( $search_query ); ?>" placeholder="<?php esc_attr_e( 'Buscar por nombre o firmante...', 'wp-doc-signer' ); ?>" style="height: 30px; margin: 0; min-width: 260px;" />
					<button type="button" id="wpds-search-submit" class="button"><?php esc_html_e( 'Buscar', 'wp-doc-signer' ); ?></button>
				</div>
			</div>

			<!-- Formulario para acciones masivas -->
			<form method="post" action="">
				<?php wp_nonce_field( 'wpds_bulk_action', 'wpds_bulk_action_nonce' ); ?>

				<!-- Barra de navegación y acciones de tabla (Clase flex propia para evitar colisiones CSS de WordPress) -->
				<div class="wpds-tablenav-flex" style="clear: both;">
					<div class="alignleft actions bulkactions" style="margin: 0; display: flex; gap: 6px; align-items: center;">
						<select name="bulk_action" id="bulk-action-selector-top" style="height: 30px; line-height: 28px; padding: 2px 24px 2px 8px;">
							<option value="-1"><?php esc_html_e( 'Acciones en lote', 'wp-doc-signer' ); ?></option>
							<option value="download_zip"><?php esc_html_e( 'Descargar seleccionados (.ZIP)', 'wp-doc-signer' ); ?></option>
							<option value="delete"><?php esc_html_e( 'Eliminar del servidor', 'wp-doc-signer' ); ?></option>
						</select>
						<input type="submit" id="doaction" class="button action" value="<?php esc_attr_e( 'Aplicar', 'wp-doc-signer' ); ?>" />
						
						<?php if ( $total_items > 0 ) : ?>
							<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wp_documento&page=wpds-signed-docs&download_zip=1' ) ); ?>" class="button button-secondary" style="margin-left: 10px; display: inline-flex; align-items: center; gap: 4px; height: 30px;">
								<span class="dashicons dashicons-download" style="font-size: 16px; width: 16px; height: 16px; line-height: 16px; margin: 0;"></span> 
								<?php esc_html_e( 'Descargar Historial Completo (.ZIP)', 'wp-doc-signer' ); ?>
							</a>
							<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wp_documento&page=wpds-signed-docs&export_csv=1' ) ); ?>" class="button button-secondary" style="margin-left: 10px; display: inline-flex; align-items: center; gap: 4px; height: 30px;">
								<span class="dashicons dashicons-media-spreadsheet" style="font-size: 16px; width: 16px; height: 16px; line-height: 16px; margin: 0;"></span> 
								<?php esc_html_e( 'Exportar Historial (.CSV)', 'wp-doc-signer' ); ?>
							</a>
						<?php endif; ?>
					</div>

					<div class="tablenav-pages" style="margin: 0;">
						<?php if ( $total_pages > 1 ) : ?>
							<span class="displaying-num" style="margin-right: 10px; font-style: italic; color: #646970;"><?php echo sprintf( _n( '%s elemento', '%s elementos', $total_items, 'wp-doc-signer' ), number_format_i18n( $total_items ) ); ?></span>
							<?php
							echo paginate_links( array(
								'base'      => '#%#%',
								'format'    => '',
								'total'     => $total_pages,
								'current'   => $current_page,
								'prev_text' => __( '&laquo; Anterior', 'wp-doc-signer' ),
								'next_text' => __( 'Siguiente &raquo;', 'wp-doc-signer' ),
							) );
							?>
						<?php else : ?>
							<span class="displaying-num" style="font-style: italic; color: #646970;"><?php echo sprintf( _n( '%s elemento', '%s elementos', $total_items, 'wp-doc-signer' ), number_format_i18n( $total_items ) ); ?></span>
						<?php endif; ?>
					</div>
				</div>

				<!-- Contenedor responsivo de la tabla para visualización en móvil -->
				<div class="wpds-table-responsive">
					<table class="wp-list-table widefat fixed striped table-view-list posts" style="box-shadow: 0 1px 3px rgba(0,0,0,0.04); border-radius: 4px;">
						<thead>
							<tr>
								<td id="cb" class="manage-column column-cb check-column wpds-checkbox-cell" style="width: 2.2em; padding: 12px 10px;"><input id="cb-select-all-1" type="checkbox"></td>
								<th scope="col" id="title" class="manage-column column-title column-primary" style="padding: 12px 10px; font-weight: 700;"><?php esc_html_e( 'Nombre del Documento / Hash único', 'wp-doc-signer' ); ?></th>
								<th scope="col" id="date" class="manage-column column-date" style="padding: 12px 10px; width: 220px; font-weight: 700;"><?php esc_html_e( 'Fecha y Hora de Firma', 'wp-doc-signer' ); ?></th>
								<th scope="col" id="size" class="manage-column column-size" style="padding: 12px 10px; width: 130px; text-align: center; font-weight: 700;"><?php esc_html_e( 'Tamaño del PDF', 'wp-doc-signer' ); ?></th>
								<th scope="col" id="actions" class="manage-column column-actions" style="padding: 12px 10px; width: 160px; text-align: right; font-weight: 700;"><?php esc_html_e( 'Acciones', 'wp-doc-signer' ); ?></th>
							</tr>
						</thead>
						<tbody id="the-list">
							<?php if ( empty( $files_sliced ) ) : ?>
								<tr class="no-items">
									<td class="colspanchange" colspan="5" style="text-align: center; padding: 40px; color: #646970; font-style: italic;">
										<?php if ( ! empty( $search_query ) ) : ?>
											<?php esc_html_e( 'No se encontraron PDFs firmados que coincidan con la búsqueda.', 'wp-doc-signer' ); ?>
										<?php else : ?>
											<?php esc_html_e( 'No hay documentos PDF firmados en el servidor actualmente.', 'wp-doc-signer' ); ?>
										<?php endif; ?>
									</td>
								</tr>
							<?php else : ?>
								<?php foreach ( $files_sliced as $file_path ) : 
									$filename = basename( $file_path );
									$file_url      = admin_url( 'edit.php?post_type=wp_documento&page=wpds-signed-docs&view_pdf=' . urlencode( $filename ) );
									$download_url  = admin_url( 'edit.php?post_type=wp_documento&page=wpds-signed-docs&view_pdf=' . urlencode( $filename ) . '&download=1' );
									$del_url       = admin_url( 'edit.php?post_type=wp_documento&page=wpds-signed-docs&delete_file=' . urlencode( $filename ) . '&wpds_del_nonce=' . wp_create_nonce( 'wpds_delete_file_action_' . $filename ) );
									?>
									<tr>
										<th scope="row" class="check-column wpds-checkbox-cell" style="width: 2.2em; padding: 12px 10px; text-align: center; vertical-align: middle;">
											<input type="checkbox" name="bulk_files[]" value="<?php echo esc_attr( $filename ); ?>">
										</th>
										<td class="title column-title column-primary page-title" style="padding: 12px 10px; vertical-align: middle;">
											<strong>
												<a class="row-title" href="<?php echo esc_url( $file_url ); ?>" target="_blank" style="text-decoration: none; color: #2271b1;" title="<?php esc_attr_e( 'Visualizar PDF', 'wp-doc-signer' ); ?>">
													<span class="dashicons dashicons-pdf" style="vertical-align: middle; margin-right: 5px; color: #d9383a; font-size: 19px; width: 19px; height: 19px;"></span> 
													<?php echo esc_html( $filename ); ?>
												</a>
											</strong>
										</td>
										<td class="date column-date" style="padding: 12px 10px; vertical-align: middle; color: #50575e;">
											<?php echo esc_html( date( 'd/m/Y H:i:s', filemtime( $file_path ) ) ); ?>
										</td>
										<td class="size column-size" style="padding: 12px 10px; vertical-align: middle; text-align: center; color: #50575e;">
											<?php echo esc_html( size_format( filesize( $file_path ) ) ); ?>
										</td>
										<td class="actions column-actions" style="padding: 12px 10px; vertical-align: middle; text-align: right;">
											<!-- Ver PDF inline -->
											<a href="<?php echo esc_url( $file_url ); ?>" class="button wpds-action-btn wpds-view-btn" target="_blank" title="<?php esc_attr_e( 'Visualizar PDF en nueva pestaña', 'wp-doc-signer' ); ?>">
												<span class="dashicons dashicons-visibility"></span>
											</a>
											<!-- Descargar PDF directo -->
											<a href="<?php echo esc_url( $download_url ); ?>" class="button wpds-action-btn wpds-download-btn" title="<?php esc_attr_e( 'Descargar archivo PDF', 'wp-doc-signer' ); ?>">
												<span class="dashicons dashicons-download"></span>
											</a>
											<!-- Eliminar PDF individual -->
											<a href="<?php echo esc_url( $del_url ); ?>" class="button wpds-action-btn wpds-delete-btn" onclick="return confirm('¿Está seguro de que desea eliminar permanentemente este archivo del servidor?');" title="<?php esc_attr_e( 'Eliminar permanentemente del servidor', 'wp-doc-signer' ); ?>">
												<span class="dashicons dashicons-trash"></span>
											</a>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
						<tfoot>
							<tr>
								<td class="manage-column column-cb check-column wpds-checkbox-cell" style="width: 2.2em; padding: 12px 10px;"><input id="cb-select-all-2" type="checkbox"></td>
								<th scope="col" class="manage-column column-title column-primary" style="padding: 12px 10px; font-weight: 700;"><?php esc_html_e( 'Nombre del Documento / Hash único', 'wp-doc-signer' ); ?></th>
								<th scope="col" class="manage-column column-date" style="padding: 12px 10px; width: 220px; font-weight: 700;"><?php esc_html_e( 'Fecha y Hora de Firma', 'wp-doc-signer' ); ?></th>
								<th scope="col" class="manage-column column-size" style="padding: 12px 10px; width: 130px; text-align: center; font-weight: 700;"><?php esc_html_e( 'Tamaño del PDF', 'wp-doc-signer' ); ?></th>
								<th scope="col" class="manage-column column-actions" style="padding: 12px 10px; width: 160px; text-align: right; font-weight: 700;"><?php esc_html_e( 'Acciones', 'wp-doc-signer' ); ?></th>
							</tr>
						</tfoot>
					</table>
				</div>
			</form>
		</div>

		<!-- Script de jQuery para el select-all y búsqueda/paginación asíncrona (AJAX) -->
		<script>
		jQuery(document).ready(function($) {
			var searchNonce = '<?php echo esc_js( wp_create_nonce( "wpds_admin_nonce" ) ); ?>';

			// Función para recargar la tabla vía AJAX
			function loadSignedDocs(searchQuery, pageNum) {
				var $container = $('#wpds-signed-docs-container');
				$container.css('opacity', '0.55');

				$.post(ajaxurl, {
					action: 'wpds_ajax_filter_signed_docs',
					nonce: searchNonce,
					s: searchQuery,
					paged: pageNum
				}, function(response) {
					if (response.success) {
						$('#the-list').html(response.data.html_tbody);
						$('.tablenav-pages').html(response.data.html_pagination);
						
						// Sincronizar select-all
						$('#cb-select-all-1, #cb-select-all-2').prop('checked', false);
					} else {
						alert(response.data.message || 'Error al filtrar documentos.');
					}
					$container.css('opacity', '1');
				}).fail(function() {
					alert('Error de conexión con el servidor.');
					$container.css('opacity', '1');
				});
			}

			// Búsqueda instantánea al teclear (con retardo para no colapsar el servidor)
			var searchTimeout;
			$(document).on('keyup input', '#wpds-search-input', function() {
				clearTimeout(searchTimeout);
				var query = $(this).val();
				searchTimeout = setTimeout(function() {
					loadSignedDocs(query, 1);
				}, 450);
			});

			// Búsqueda manual al hacer clic
			$(document).on('click', '#wpds-search-submit', function(e) {
				e.preventDefault();
				var query = $('#wpds-search-input').val();
				loadSignedDocs(query, 1);
			});

			// Interceptar enlaces de paginación para AJAX
			$(document).on('click', '.tablenav-pages a', function(e) {
				e.preventDefault();
				var href = $(this).attr('href');
				var pageNum = 1;

				if (href) {
					if (href.indexOf('#') !== -1) {
						var parts = href.split('#');
						pageNum = parseInt(parts[parts.length - 1]) || 1;
					} else {
						var match = href.match(/paged=(\d+)/);
						if (match) {
							pageNum = parseInt(match[1]) || 1;
						}
					}
				}

				var query = $('#wpds-search-input').val();
				loadSignedDocs(query, pageNum);
			});

			// Sincronizar checkboxes de cabecera
			$(document).on('change', '#cb-select-all-1, #cb-select-all-2', function() {
				var checked = $(this).is(':checked');
				$('tbody input[name="bulk_files[]"]').prop('checked', checked);
				$('#cb-select-all-1, #cb-select-all-2').prop('checked', checked);
			});
			
			// Desmarcar select-all si se desmarca uno manual
			$(document).on('change', 'tbody input[name="bulk_files[]"]', function() {
				var total = $('tbody input[name="bulk_files[]"]').length;
				var checked = $('tbody input[name="bulk_files[]"]:checked').length;
				$('#cb-select-all-1, #cb-select-all-2').prop('checked', total === checked);
			});
		});
		</script>
		<?php
	}

	/**
	 * Exporta el historial de firmas en formato CSV.
	 */
	public function handle_csv_export() {
		if ( isset( $_GET['page'] ) && 'wpds-signed-docs' === $_GET['page'] && isset( $_GET['export_csv'] ) ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Acceso denegado.', 'wp-doc-signer' ) );
			}

			$signatures = get_option( 'wpds_signatures_log', array() );

			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="historial-firmas-' . date( 'Y-m-d' ) . '.csv"' );

			$output = fopen( 'php://output', 'w' );
			
			// UTF-8 BOM for Excel compatibility
			fprintf( $output, chr(0xEF).chr(0xBB).chr(0xBF) );

			// Cabeceras de las columnas
			fputcsv( $output, array(
				__( 'Fecha y Hora', 'wp-doc-signer' ),
				__( 'Documento', 'wp-doc-signer' ),
				__( 'Cliente', 'wp-doc-signer' ),
				__( 'DNI / Identificación', 'wp-doc-signer' ),
				__( 'Correo Electrónico', 'wp-doc-signer' ),
				__( 'Teléfono', 'wp-doc-signer' ),
				__( 'Consentimiento Imagen/Voz', 'wp-doc-signer' ),
				__( 'Archivo PDF', 'wp-doc-signer' )
			), ';' );

			foreach ( $signatures as $sig ) {
				$consent_text = '';
				$consent_val = isset( $sig['consent_image'] ) ? intval( $sig['consent_image'] ) : -1;
				if ( 1 === $consent_val ) {
					$consent_text = __( 'SÍ', 'wp-doc-signer' );
				} elseif ( 0 === $consent_val ) {
					$consent_text = __( 'NO', 'wp-doc-signer' );
				} else {
					$consent_text = __( 'N/A', 'wp-doc-signer' );
				}

				fputcsv( $output, array(
					isset( $sig['date'] ) ? $sig['date'] : '',
					isset( $sig['document_title'] ) ? $sig['document_title'] : '',
					isset( $sig['client_name'] ) ? $sig['client_name'] : '',
					isset( $sig['client_dni'] ) ? $sig['client_dni'] : '',
					isset( $sig['client_email'] ) ? $sig['client_email'] : '',
					isset( $sig['client_phone'] ) ? $sig['client_phone'] : '',
					$consent_text,
					isset( $sig['filename'] ) ? $sig['filename'] : ''
				), ';' );
			}

			fclose( $output );
			exit;
		}
	}

	/**
	 * Renderizar la página dedicada para el Registro de Envíos de Correo.
	 */
	public function render_email_log_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Opción para vaciar logs
		if ( isset( $_POST['wpds_clear_logs'] ) ) {
			check_admin_referer( 'wpds_clear_email_logs_action', 'wpds_clear_email_logs_nonce' );
			update_option( 'wpds_email_logs', array() );
			add_settings_error( 'wpds_messages', 'wpds_message', __( 'Se ha vaciado el historial de envíos correctamente.', 'wp-doc-signer' ), 'updated' );
		}

		settings_errors( 'wpds_messages' );

		$logs = get_option( 'wpds_email_logs', array() );
		$total_items = count( $logs );
		$items_per_page = 20;
		$current_page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
		$total_pages = ceil( $total_items / $items_per_page );

		if ( ! empty( $logs ) ) {
			$logs_sliced = array_slice( $logs, ( $current_page - 1 ) * $items_per_page, $items_per_page );
		} else {
			$logs_sliced = array();
		}
		?>
		<div class="wrap wpds-admin-wrap">
			<h1 class="wp-heading-inline" style="margin-bottom: 15px;"><?php esc_html_e( 'Registro de Envíos de Correo', 'wp-doc-signer' ); ?></h1>

			<form method="post" action="" style="float: right; margin-top: 5px;" onsubmit="return confirm('¿Está seguro de que desea vaciar todos los registros del historial de correos? Esta acción no se puede deshacer.');">
				<?php wp_nonce_field( 'wpds_clear_email_logs_action', 'wpds_clear_email_logs_nonce' ); ?>
				<input type="submit" name="wpds_clear_logs" class="button button-secondary" value="<?php esc_attr_e( 'Vaciar Historial de Envíos', 'wp-doc-signer' ); ?>" />
			</form>

			<p class="description" style="margin-bottom: 20px;"><?php esc_html_e( 'Historial con el estado de las notificaciones enviadas por correo electrónico al firmar un acuerdo (últimos 150 registros).', 'wp-doc-signer' ); ?></p>

			<div class="wpds-tablenav-flex" style="clear: both; margin-top: 15px;">
				<div class="alignleft"></div>
				<div class="tablenav-pages">
					<?php if ( $total_pages > 1 ) : ?>
						<span class="displaying-num" style="margin-right: 10px; font-style: italic; color: #646970;"><?php echo sprintf( _n( '%s elemento', '%s elementos', $total_items, 'wp-doc-signer' ), number_format_i18n( $total_items ) ); ?></span>
						<?php
						echo paginate_links( array(
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'total'     => $total_pages,
							'current'   => $current_page,
							'prev_text' => __( '&laquo; Anterior', 'wp-doc-signer' ),
							'next_text' => __( 'Siguiente &raquo;', 'wp-doc-signer' ),
						) );
						?>
					<?php else : ?>
						<span class="displaying-num" style="font-style: italic; color: #646970;"><?php echo sprintf( _n( '%s elemento', '%s elementos', $total_items, 'wp-doc-signer' ), number_format_i18n( $total_items ) ); ?></span>
					<?php endif; ?>
				</div>
			</div>

			<div class="wpds-table-responsive">
				<table class="wp-list-table widefat fixed striped table-view-list posts" style="box-shadow: 0 1px 3px rgba(0,0,0,0.04); border-radius: 4px;">
					<thead>
						<tr>
							<th scope="col" style="padding: 12px 10px; width: 180px; font-weight: 700;"><?php esc_html_e( 'Fecha y Hora', 'wp-doc-signer' ); ?></th>
							<th scope="col" style="padding: 12px 10px; font-weight: 700;"><?php esc_html_e( 'Documento', 'wp-doc-signer' ); ?></th>
							<th scope="col" style="padding: 12px 10px; width: 220px; font-weight: 700;"><?php esc_html_e( 'Destinatario', 'wp-doc-signer' ); ?></th>
							<th scope="col" style="padding: 12px 10px; width: 130px; font-weight: 700;"><?php esc_html_e( 'Tipo', 'wp-doc-signer' ); ?></th>
							<th scope="col" style="padding: 12px 10px; width: 120px; text-align: center; font-weight: 700;"><?php esc_html_e( 'Estado', 'wp-doc-signer' ); ?></th>
							<th scope="col" style="padding: 12px 10px; width: 200px; font-weight: 700;"><?php esc_html_e( 'Observación / Detalle', 'wp-doc-signer' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $logs_sliced ) ) : ?>
							<tr>
								<td colspan="6" style="text-align: center; padding: 40px; color: #646970; font-style: italic;">
									<?php esc_html_e( 'No hay registros de envíos de correo actualmente.', 'wp-doc-signer' ); ?>
								</td>
							</tr>
						<?php else : ?>
							<?php foreach ( $logs_sliced as $log ) : ?>
								<tr>
									<td style="padding: 12px 10px; vertical-align: middle; color: #50575e;">
										<?php echo esc_html( date( 'd/m/Y H:i:s', strtotime( $log['date'] ) ) ); ?>
									</td>
									<td style="padding: 12px 10px; vertical-align: middle; font-weight: 600; color: #1d2327;">
										<?php echo esc_html( $log['document'] ); ?>
									</td>
									<td style="padding: 12px 10px; vertical-align: middle; color: #50575e;">
										<?php echo esc_html( $log['recipient'] ); ?>
									</td>
									<td style="padding: 12px 10px; vertical-align: middle; color: #50575e;">
										<?php echo esc_html( $log['type'] ); ?>
									</td>
									<td style="padding: 12px 10px; vertical-align: middle; text-align: center;">
										<?php if ( __( 'Éxito', 'wp-doc-signer' ) === $log['status'] ) : ?>
											<span style="background: #e6f4ea; color: #137333; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; text-transform: uppercase;"><?php echo esc_html( $log['status'] ); ?></span>
										<?php else : ?>
											<span style="background: #fce8e6; color: #c5221f; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; text-transform: uppercase;"><?php echo esc_html( $log['status'] ); ?></span>
										<?php endif; ?>
									</td>
									<td style="padding: 12px 10px; vertical-align: middle; color: #d63638; font-size: 12px; font-style: italic;">
										<?php echo esc_html( $log['error'] ); ?>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}
}

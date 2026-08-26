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
	}

	/**
	 * Cargar assets de administración.
	 */
	public function enqueue_admin_assets( $hook ) {
		$screen = get_current_screen();
		if ( $screen && ( 'wp_documento' === $screen->post_type || 'wp_documento_page_wpds-settings' === $hook || 'wp_documento_page_wpds-signed-docs' === $hook ) ) {
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
	}

	/**
	 * Sanitizar y validar los campos de configuración.
	 */
	public function sanitize_settings( $input ) {
		$output = array();

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

	public function render_admin_emails_field() {
		$options = get_option( 'wpds_settings' );
		$value = isset( $options['admin_emails'] ) ? $options['admin_emails'] : get_bloginfo( 'admin_email' );
		echo '<input type="text" name="wpds_settings[admin_emails]" class="large-text" value="' . esc_attr( $value ) . '" placeholder="admin@tuservicio.com, copias@tuservicio.com" />';
		echo '<p class="description">' . esc_html__( 'Ingresa los correos de administración separados por comas.', 'wp-doc-signer' ) . '</p>';
	}

	public function render_sender_name_field() {
		$options = get_option( 'wpds_settings' );
		$value = isset( $options['sender_name'] ) ? $options['sender_name'] : get_bloginfo( 'name' );
		echo '<input type="text" name="wpds_settings[sender_name]" class="regular-text" value="' . esc_attr( $value ) . '" />';
	}

	public function render_sender_email_field() {
		$options = get_option( 'wpds_settings' );
		$value = isset( $options['sender_email'] ) ? $options['sender_email'] : get_bloginfo( 'admin_email' );
		echo '<input type="email" name="wpds_settings[sender_email]" class="regular-text" value="' . esc_attr( $value ) . '" />';
		echo '<p class="description" style="color: #b32d2e; font-weight: 500;">' . esc_html__( '⚠️ IMPORTANTE: Utiliza un correo con el mismo dominio de tu web (ej. noreply@saraperezstudio.com o info@saraperezstudio.com) en lugar de una dirección de Gmail, Hotmail o Yahoo. Si envías correos desde tu servidor simulando ser de @gmail.com, Google bloqueará la entrega inmediatamente debido a políticas SPF y DMARC.', 'wp-doc-signer' ) . '</p>';
	}

	public function render_client_subject_field() {
		$options = get_option( 'wpds_settings' );
		$value = isset( $options['client_subject'] ) ? $options['client_subject'] : __( 'Tu copia de: {nombre_documento}', 'wp-doc-signer' );
		echo '<input type="text" name="wpds_settings[client_subject]" class="large-text" value="' . esc_attr( $value ) . '" />';
	}

	public function render_admin_subject_field() {
		$options = get_option( 'wpds_settings' );
		$value = isset( $options['admin_subject'] ) ? $options['admin_subject'] : __( 'Firmado: {nombre_documento} - {nombre_cliente}', 'wp-doc-signer' );
		echo '<input type="text" name="wpds_settings[admin_subject]" class="large-text" value="' . esc_attr( $value ) . '" />';
	}

	public function render_client_body_field() {
		$options = get_option( 'wpds_settings' );
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
		$options = get_option( 'wpds_settings' );
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
		$options = get_option( 'wpds_settings' );
		$checked = isset( $options['save_local'] ) ? $options['save_local'] : 1;
		echo '<label><input type="checkbox" name="wpds_settings[save_local]" value="1" ' . checked( 1, $checked, false ) . ' /> ' . esc_html__( 'Guardar archivos firmados en la carpeta del servidor /wp-content/uploads/firmas-pdf/', 'wp-doc-signer' ) . '</label>';
		echo '<p class="description" style="color: #646970;">' . esc_html__( 'Los archivos se protegerán automáticamente mediante restricciones de acceso .htaccess en servidores basados en Apache.', 'wp-doc-signer' ) . '</p>';
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
				header( 'Content-Type: application/pdf' );
				header( 'Content-Disposition: inline; filename="' . basename( $file_path ) . '"' );
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
		?>
		<div class="wrap wpds-admin-wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			
			<div class="wpds-settings-layout" style="max-width: 860px; margin-top: 15px; padding: 25px; border-radius: 8px; border: 1px solid #c3c4c7; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
				<form action="options.php" method="post">
					<?php
					settings_fields( 'wpds_settings_group' );
					do_settings_sections( 'wpds-settings' );
					submit_button( __( 'Guardar Cambios', 'wp-doc-signer' ) );
					?>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Renderizar la página dedicada para el Historial de Documentos Firmados (Docs. Firmados).
	 * Incluye paginación nativa y buscador funcional de archivos locales.
	 */
	public function render_signed_docs_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_GET['deleted'] ) ) {
			add_settings_error( 'wpds_messages', 'wpds_message', __( 'El archivo PDF seleccionado ha sido eliminado del servidor.', 'wp-doc-signer' ), 'updated' );
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
		<div class="wrap wpds-admin-wrap">
			<h1 class="wp-heading-inline" style="margin-bottom: 15px;"><?php esc_html_e( 'Documentos Firmados (PDF)', 'wp-doc-signer' ); ?></h1>
			
			<!-- Buscador nativo de WordPress -->
			<?php if ( $total_items > 0 || ! empty( $search_query ) ) : ?>
				<form method="get" class="search-form wp-filter" style="float: right; margin-top: 5px;">
					<input type="hidden" name="post_type" value="wp_documento" />
					<input type="hidden" name="page" value="wpds-signed-docs" />
					<p class="search-box" style="position: relative; margin: 0;">
						<label class="screen-reader-text" for="post-search-input"><?php esc_html_e( 'Buscar PDFs:', 'wp-doc-signer' ); ?></label>
						<input type="search" id="post-search-input" name="s" value="<?php echo esc_attr( $search_query ); ?>" placeholder="<?php esc_attr_e( 'Buscar por nombre...', 'wp-doc-signer' ); ?>" />
						<input type="submit" id="search-submit" class="button" value="<?php esc_attr_e( 'Buscar', 'wp-doc-signer' ); ?>" />
					</p>
				</form>
			<?php endif; ?>

			<!-- Barra de navegación y acciones de tabla -->
			<div class="tablenav top" style="display: flex; justify-content: space-between; align-items: center; margin: 15px 0 10px 0; clear: left;">
				<div class="alignleft actions bulkactions" style="margin: 0;">
					<?php if ( $total_items > 0 ) : ?>
						<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wp_documento&page=wpds-signed-docs&download_zip=1' ) ); ?>" class="button button-primary">
							<span class="dashicons dashicons-download" style="vertical-align: middle; margin-top: -3px; font-size: 16px;"></span> <?php esc_html_e( 'Descargar Todo en ZIP', 'wp-doc-signer' ); ?>
						</a>
					<?php endif; ?>
				</div>

				<?php if ( $total_pages > 1 ) : ?>
					<div class="tablenav-pages" style="margin: 0;">
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
					</div>
				<?php else : ?>
					<div class="tablenav-pages one-page" style="margin: 0;">
						<span class="displaying-num" style="font-style: italic; color: #646970;"><?php echo sprintf( _n( '%s elemento', '%s elementos', $total_items, 'wp-doc-signer' ), number_format_i18n( $total_items ) ); ?></span>
					</div>
				<?php endif; ?>
			</div>

			<!-- Tabla de listado con estilos nativos de WP -->
			<table class="wp-list-table widefat fixed striped table-view-list posts" style="box-shadow: 0 1px 3px rgba(0,0,0,0.04); border-radius: 4px;">
				<thead>
					<tr>
						<th scope="col" id="title" class="manage-column column-title column-primary" style="padding: 12px 10px; font-weight: 700;"><?php esc_html_e( 'Nombre del Documento / Hash único', 'wp-doc-signer' ); ?></th>
						<th scope="col" id="date" class="manage-column column-date" style="padding: 12px 10px; width: 220px; font-weight: 700;"><?php esc_html_e( 'Fecha y Hora de Firma', 'wp-doc-signer' ); ?></th>
						<th scope="col" id="size" class="manage-column column-size" style="padding: 12px 10px; width: 130px; text-align: center; font-weight: 700;"><?php esc_html_e( 'Tamaño del PDF', 'wp-doc-signer' ); ?></th>
						<th scope="col" id="actions" class="manage-column column-actions" style="padding: 12px 10px; width: 160px; text-align: right; font-weight: 700;"><?php esc_html_e( 'Acciones', 'wp-doc-signer' ); ?></th>
					</tr>
				</thead>
				<tbody id="the-list">
					<?php if ( empty( $files_sliced ) ) : ?>
						<tr class="no-items">
							<td class="colspanchange" colspan="4" style="text-align: center; padding: 40px; color: #646970; font-style: italic;">
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
							$file_url = admin_url( 'edit.php?post_type=wp_documento&page=wpds-signed-docs&view_pdf=' . urlencode( $filename ) );
							$del_url  = admin_url( 'edit.php?post_type=wp_documento&page=wpds-signed-docs&delete_file=' . urlencode( $filename ) . '&wpds_del_nonce=' . wp_create_nonce( 'wpds_delete_file_action_' . $filename ) );
							?>
							<tr>
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
									<a href="<?php echo esc_url( $file_url ); ?>" class="button button-small" target="_blank" style="vertical-align: middle;" title="<?php esc_attr_e( 'Abrir PDF en ventana nueva', 'wp-doc-signer' ); ?>">
										<span class="dashicons dashicons-visibility" style="font-size: 15px; margin-top: 3px; height: 15px; width: 15px;"></span> <?php esc_html_e( 'Ver', 'wp-doc-signer' ); ?>
									</a>
									<a href="<?php echo esc_url( $del_url ); ?>" class="button button-small" style="color: #d63638; border-color: #d63638; background: #fff8f8; vertical-align: middle; margin-left: 4px;" onclick="return confirm('¿Está seguro de que desea eliminar permanentemente este archivo del servidor?');" title="<?php esc_attr_e( 'Eliminar del Servidor', 'wp-doc-signer' ); ?>">
										<span class="dashicons dashicons-trash" style="font-size: 15px; margin-top: 3px; height: 15px; width: 15px;"></span>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<!-- Paginación inferior -->
			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav bottom" style="display: flex; justify-content: flex-end; margin-top: 15px;">
					<div class="tablenav-pages">
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
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}

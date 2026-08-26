<?php
/**
 * Clase para registrar y gestionar el Custom Post Type "Documentos para Firma".
 *
 * @package WP_Document_Signer_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPDS_CPT_Documents {

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
		add_action( 'init', array( $this, 'register_cpt' ) );
		add_action( 'init', array( $this, 'enable_elementor_support' ), 20 );
		add_action( 'add_meta_boxes', array( $this, 'add_document_metaboxes' ) );
		add_action( 'save_post_wp_documento', array( $this, 'save_document_meta' ) );

		// Customizar columnas en el listado de administración
		add_filter( 'manage_wp_documento_posts_columns', array( $this, 'set_custom_columns' ) );
		add_action( 'manage_wp_documento_posts_custom_column', array( $this, 'fill_custom_columns' ), 10, 2 );

		// AJAX hooks para vistas previas
		add_action( 'wp_ajax_wpds_preview_form', array( $this, 'ajax_preview_form' ) );
		add_action( 'wp_ajax_wpds_preview_pdf', array( $this, 'ajax_preview_pdf' ) );
	}

	/**
	 * Registra el Custom Post Type 'wp_documento'.
	 */
	public function register_cpt() {
		$labels = array(
			'name'                  => _x( 'Documentos', 'Post Type General Name', 'wp-doc-signer' ),
			'singular_name'         => _x( 'Documento', 'Post Type Singular Name', 'wp-doc-signer' ),
			'menu_name'             => __( 'Firma Documentos', 'wp-doc-signer' ),
			'name_admin_bar'        => __( 'Documento para Firma', 'wp-doc-signer' ),
			'all_items'             => __( 'Todos los Documentos', 'wp-doc-signer' ),
			'add_new_item'          => __( 'Añadir Nuevo Documento', 'wp-doc-signer' ),
			'add_new'               => __( 'Añadir Nuevo', 'wp-doc-signer' ),
			'edit_item'             => __( 'Editar Documento', 'wp-doc-signer' ),
			'update_item'           => __( 'Actualizar Documento', 'wp-doc-signer' ),
			'view_item'             => __( 'Ver Documento', 'wp-doc-signer' ),
			'search_items'          => __( 'Buscar Documento', 'wp-doc-signer' ),
			'not_found'             => __( 'No se encontraron documentos', 'wp-doc-signer' ),
			'not_found_in_trash'    => __( 'No se encontraron documentos en la papelera', 'wp-doc-signer' ),
		);

		$args = array(
			'label'               => __( 'Documento para Firma', 'wp-doc-signer' ),
			'description'         => __( 'Documentos legales y consentimientos para firmar', 'wp-doc-signer' ),
			'labels'              => $labels,
			'supports'            => array( 'title', 'editor' ),
			'hierarchical'        => false,
			'public'              => false, // Privado para evitar acceso directo no autorizado
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_position'       => 28,
			'menu_icon'           => 'dashicons-media-document',
			'show_in_nav_menus'   => false,
			'can_export'          => true,
			'has_archive'         => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'rewrite'             => false,
			'capability_type'     => 'post',
			'show_in_rest'        => true, // Habilitar editor de bloques moderno
		);

		register_post_type( 'wp_documento', $args );
	}

	/**
	 * Activa programáticamente el soporte de Elementor para nuestro CPT de documentos.
	 */
	public function enable_elementor_support() {
		if ( ! post_type_exists( 'wp_documento' ) ) {
			return;
		}

		$allowed_post_types = get_option( 'elementor_active_post_types', array( 'page', 'post' ) );
		if ( is_array( $allowed_post_types ) && ! in_array( 'wp_documento', $allowed_post_types, true ) ) {
			$allowed_post_types[] = 'wp_documento';
			update_option( 'elementor_active_post_types', $allowed_post_types );
		}
	}

	/**
	 * Añadir metaboxes al panel de documentos.
	 */
	public function add_document_metaboxes() {
		// Metabox de Configuración y Vista Previa
		add_meta_box(
			'wpds_document_meta',
			__( 'Configuración y Vista Previa', 'wp-doc-signer' ),
			array( $this, 'render_settings_metabox' ),
			'wp_documento',
			'side',
			'default'
		);

		// Metabox de Datos del Establecimiento
		add_meta_box(
			'wpds_establishment_meta',
			__( 'Datos del Establecimiento (Cabecera del Documento)', 'wp-doc-signer' ),
			array( $this, 'render_establishment_metabox' ),
			'wp_documento',
			'normal',
			'high'
		);

		// Metabox de Textos RGPD Personalizados
		add_meta_box(
			'wpds_rgpd_custom_meta',
			__( 'Textos de Protección de Datos (RGPD) Personalizados', 'wp-doc-signer' ),
			array( $this, 'render_rgpd_custom_metabox' ),
			'wp_documento',
			'normal',
			'default'
		);
	}

	/**
	 * Renderizar metabox de configuración y botones de vista previa.
	 */
	public function render_settings_metabox( $post ) {
		wp_nonce_field( 'wpds_save_document_meta_action', 'wpds_document_meta_nonce' );

		// Obtener metadatos actuales
		$status = get_post_meta( $post->ID, '_wpds_status', true );
		if ( empty( $status ) ) {
			$status = 'active';
		}

		$notification_email = get_post_meta( $post->ID, '_wpds_email', true );
		
		// Crear enlaces de vista previa
		$preview_form_url = admin_url( 'admin-ajax.php?action=wpds_preview_form&post_id=' . $post->ID . '&nonce=' . wp_create_nonce( 'wpds_preview_form_' . $post->ID ) );
		$preview_pdf_url  = admin_url( 'admin-ajax.php?action=wpds_preview_pdf&post_id=' . $post->ID . '&nonce=' . wp_create_nonce( 'wpds_preview_pdf_' . $post->ID ) );
		?>
		<div class="wpds-meta-group" style="margin-bottom: 15px;">
			<label for="wpds_status" style="display: block; font-weight: bold; margin-bottom: 5px;"><?php esc_html_e( 'Estado:', 'wp-doc-signer' ); ?></label>
			<select name="wpds_status" id="wpds_status" class="widefat">
				<option value="active" <?php selected( $status, 'active' ); ?>><?php esc_html_e( 'Activo (Disponible para firmar)', 'wp-doc-signer' ); ?></option>
				<option value="paused" <?php selected( $status, 'paused' ); ?>><?php esc_html_e( 'Pausado (Inactivo)', 'wp-doc-signer' ); ?></option>
			</select>
		</div>

		<div class="wpds-meta-group" style="margin-bottom: 15px;">
			<label for="wpds_email" style="display: block; font-weight: bold; margin-bottom: 5px;"><?php esc_html_e( 'Email Notificación Específico:', 'wp-doc-signer' ); ?></label>
			<input type="email" name="wpds_email" id="wpds_email" class="widefat" value="<?php echo esc_attr( $notification_email ); ?>" placeholder="<?php esc_attr_e( 'Usar email global', 'wp-doc-signer' ); ?>" />
		</div>

		<div class="wpds-meta-group" style="margin-bottom: 15px;">
			<label style="display: block; font-weight: bold; margin-bottom: 5px;"><?php esc_html_e( 'Shortcode:', 'wp-doc-signer' ); ?></label>
			<code style="display: block; padding: 6px; background: #eaeaea; border: 1px solid #ccc; font-size: 11px; user-select: all; text-align: center; border-radius: 4px;" title="Doble clic para seleccionar todo">
				[firmar_documento id="<?php echo esc_attr( $post->ID ); ?>"]
			</code>
		</div>

		<hr style="border: 0; border-top: 1px solid #dfdfdf; margin: 15px 0;" />

		<div class="wpds-meta-group" style="margin-bottom: 10px;">
			<a href="<?php echo esc_url( $preview_form_url ); ?>" class="button button-large widefat" target="_blank" style="text-align: center; margin-bottom: 8px;">
				<span class="dashicons dashicons-visibility" style="vertical-align: middle; margin-right: 5px;"></span><?php esc_html_e( 'Vista Previa Pantalla', 'wp-doc-signer' ); ?>
			</a>
			<a href="<?php echo esc_url( $preview_pdf_url ); ?>" class="button button-primary button-large widefat" target="_blank" style="text-align: center;">
				<span class="dashicons dashicons-pdf" style="vertical-align: middle; margin-right: 5px;"></span><?php esc_html_e( 'Vista Previa PDF', 'wp-doc-signer' ); ?>
			</a>
			<p class="description" style="margin-top: 8px; font-size: 11px; text-align: center;"><?php esc_html_e( 'La vista previa PDF generará un documento A4 simulado con firmas de prueba y la marca de agua del salón.', 'wp-doc-signer' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Renderizar metabox para configurar los datos del establecimiento de cabecera.
	 */
	public function render_establishment_metabox( $post ) {
		// Obtener metadatos con valores por defecto del salón
		$titular    = get_post_meta( $post->ID, '_wpds_est_titular', true );
		$nif        = get_post_meta( $post->ID, '_wpds_est_nif', true );
		$comercial  = get_post_meta( $post->ID, '_wpds_est_comercial', true );
		$address    = get_post_meta( $post->ID, '_wpds_est_address', true );
		$email      = get_post_meta( $post->ID, '_wpds_est_email', true );
		$phone      = get_post_meta( $post->ID, '_wpds_est_phone', true );

		// Fallback para valores por defecto iniciales si están vacíos (basados en el PDF real)
		if ( empty( $titular ) && $post->post_date === $post->post_modified ) {
			$titular   = 'Sara Pérez González';
			$nif       = '31694014Z';
			$comercial = 'Sara Pérez Salón de Autor';
			$address   = 'C. San Marino, 2, 11405 Jerez de la Frontera, Cádiz';
			$email     = 'saraperezpeluqueriadeautor@gmail.com';
			$phone     = 'Teléfono fijo: +34 956 333 125 | Móvil: +34 607 34 51 00';
		}
		?>
		<table class="form-table">
			<tr>
				<th scope="row"><label for="wpds_est_titular"><?php esc_html_e( 'Nombre del Titular / Responsable', 'wp-doc-signer' ); ?></label></th>
				<td><input type="text" name="wpds_est_titular" id="wpds_est_titular" value="<?php echo esc_attr( $titular ); ?>" class="regular-text" required /></td>
			</tr>
			<tr>
				<th scope="row"><label for="wpds_est_nif"><?php esc_html_e( 'NIF / CIF', 'wp-doc-signer' ); ?></label></th>
				<td><input type="text" name="wpds_est_nif" id="wpds_est_nif" value="<?php echo esc_attr( $nif ); ?>" class="regular-text" required /></td>
			</tr>
			<tr>
				<th scope="row"><label for="wpds_est_comercial"><?php esc_html_e( 'Nombre Comercial', 'wp-doc-signer' ); ?></label></th>
				<td><input type="text" name="wpds_est_comercial" id="wpds_est_comercial" value="<?php echo esc_attr( $comercial ); ?>" class="regular-text" required /></td>
			</tr>
			<tr>
				<th scope="row"><label for="wpds_est_address"><?php esc_html_e( 'Dirección Física', 'wp-doc-signer' ); ?></label></th>
				<td><input type="text" name="wpds_est_address" id="wpds_est_address" value="<?php echo esc_attr( $address ); ?>" class="large-text" required /></td>
			</tr>
			<tr>
				<th scope="row"><label for="wpds_est_email"><?php esc_html_e( 'Email Público Establecimiento', 'wp-doc-signer' ); ?></label></th>
				<td><input type="email" name="wpds_est_email" id="wpds_est_email" value="<?php echo esc_attr( $email ); ?>" class="regular-text" required /></td>
			</tr>
			<tr>
				<th scope="row"><label for="wpds_est_phone"><?php esc_html_e( 'Teléfono(s)', 'wp-doc-signer' ); ?></label></th>
				<td><input type="text" name="wpds_est_phone" id="wpds_est_phone" value="<?php echo esc_attr( $phone ); ?>" class="regular-text" required /></td>
			</tr>
		</table>
		<p class="description"><?php esc_html_e( 'Estos datos se maquetarán de forma automatizada en el bloque izquierdo de la cabecera del documento PDF generado.', 'wp-doc-signer' ); ?></p>
		<?php
	}

	/**
	 * Guardar metadatos del documento.
	 */
	public function save_document_meta( $post_id ) {
		if ( ! isset( $_POST['wpds_document_meta_nonce'] ) || ! wp_verify_nonce( $_POST['wpds_document_meta_nonce'], 'wpds_save_document_meta_action' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Guardar Estado e Email de notificaciones
		if ( isset( $_POST['wpds_status'] ) ) {
			update_post_meta( $post_id, '_wpds_status', sanitize_text_field( $_POST['wpds_status'] ) );
		}
		if ( isset( $_POST['wpds_email'] ) ) {
			update_post_meta( $post_id, '_wpds_email', sanitize_email( $_POST['wpds_email'] ) );
		}

		// Guardar Metadatos del Establecimiento y del RGPD
		$fields = array(
			'wpds_est_titular',
			'wpds_est_nif',
			'wpds_est_comercial',
			'wpds_est_address',
			'wpds_est_email',
			'wpds_est_phone',
			'wpds_rgpd_finalidad',
			'wpds_rgpd_destinatarios',
			'wpds_rgpd_conservacion',
			'wpds_rgpd_derechos',
			'wpds_consentimiento_texto'
		);
		foreach ( $fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				update_post_meta( $post_id, '_' . $field, wp_kses_post( $_POST[ $field ] ) );
			}
		}
	}

	/**
	 * Renderizar metabox para configurar los textos de protección de datos (RGPD) personalizados.
	 */
	public function render_rgpd_custom_metabox( $post ) {
		$finalidad     = get_post_meta( $post->ID, '_wpds_rgpd_finalidad', true );
		$destinatarios = get_post_meta( $post->ID, '_wpds_rgpd_destinatarios', true );
		$conservacion  = get_post_meta( $post->ID, '_wpds_rgpd_conservacion', true );
		$derechos      = get_post_meta( $post->ID, '_wpds_rgpd_derechos', true );
		$consentimiento_texto = get_post_meta( $post->ID, '_wpds_consentimiento_texto', true );
		?>
		<table class="form-table">
			<tr>
				<th scope="row"><label for="wpds_rgpd_finalidad"><?php esc_html_e( 'Finalidades y Base Jurídica', 'wp-doc-signer' ); ?></label></th>
				<td>
					<textarea name="wpds_rgpd_finalidad" id="wpds_rgpd_finalidad" rows="3" class="large-text" placeholder="<?php esc_attr_e( 'Por defecto: Gestionar la reserva y la relación precontractual/contractual...', 'wp-doc-signer' ); ?>"><?php echo esc_textarea( $finalidad ); ?></textarea>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wpds_rgpd_destinatarios"><?php esc_html_e( 'Destinatarios', 'wp-doc-signer' ); ?></label></th>
				<td>
					<textarea name="wpds_rgpd_destinatarios" id="wpds_rgpd_destinatarios" rows="3" class="large-text" placeholder="<?php esc_attr_e( 'Por defecto: Proveedores necesarios para la gestión del servicio...', 'wp-doc-signer' ); ?>"><?php echo esc_textarea( $destinatarios ); ?></textarea>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wpds_rgpd_conservacion"><?php esc_html_e( 'Conservación', 'wp-doc-signer' ); ?></label></th>
				<td>
					<textarea name="wpds_rgpd_conservacion" id="wpds_rgpd_conservacion" rows="3" class="large-text" placeholder="<?php esc_attr_e( 'Por defecto: Durante la relación con la persona cliente...', 'wp-doc-signer' ); ?>"><?php echo esc_textarea( $conservacion ); ?></textarea>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wpds_rgpd_derechos"><?php esc_html_e( 'Derechos', 'wp-doc-signer' ); ?></label></th>
				<td>
					<textarea name="wpds_rgpd_derechos" id="wpds_rgpd_derechos" rows="3" class="large-text" placeholder="<?php esc_attr_e( 'Por defecto: Acceso, rectificación, supresión, limitación... mediante email...', 'wp-doc-signer' ); ?>"><?php echo esc_textarea( $derechos ); ?></textarea>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wpds_consentimiento_texto"><?php esc_html_e( 'Texto Declaración de Consentimiento (Imagen/Voz)', 'wp-doc-signer' ); ?></label></th>
				<td>
					<textarea name="wpds_consentimiento_texto" id="wpds_consentimiento_texto" rows="4" class="large-text" placeholder="<?php esc_attr_e( 'Por defecto: Autorizo a {titular} / {comercial} a captar y utilizar gratuitamente mi imagen y/o voz...', 'wp-doc-signer' ); ?>"><?php echo esc_textarea( $consentimiento_texto ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Puedes usar los tags {titular} y {comercial} para cargarlos dinámicamente.', 'wp-doc-signer' ); ?></p>
				</td>
			</tr>
		</table>
		<p class="description"><?php esc_html_e( 'Deja estos campos vacíos para usar los textos legales estándar de Sara Pérez Salón de Autor por defecto.', 'wp-doc-signer' ); ?></p>
		<?php
	}

	/**
	 * Añadir columnas personalizadas al listado.
	 */
	public function set_custom_columns( $columns ) {
		$new_columns = array();
		foreach ( $columns as $key => $title ) {
			if ( 'date' === $key ) {
				$new_columns['wpds_status']    = __( 'Estado', 'wp-doc-signer' );
				$new_columns['wpds_shortcode'] = __( 'Shortcode', 'wp-doc-signer' );
			}
			$new_columns[ $key ] = $title;
		}
		return $new_columns;
	}

	/**
	 * Rellenar columnas personalizadas.
	 */
	public function fill_custom_columns( $column, $post_id ) {
		switch ( $column ) {
			case 'wpds_status':
				$status = get_post_meta( $post_id, '_wpds_status', true );
				if ( 'paused' === $status ) {
					echo '<span class="status-paused">' . esc_html__( 'Pausado', 'wp-doc-signer' ) . '</span>';
				} else {
					echo '<span class="status-active">' . esc_html__( 'Activo', 'wp-doc-signer' ) . '</span>';
				}
				break;

			case 'wpds_shortcode':
				echo '<code style="user-select:all; background:#eaeaea; padding:3px 6px; border:1px solid #ccc; border-radius:3px; font-size:11px;">[firmar_documento id="' . esc_attr( $post_id ) . '"]</code>';
				break;
		}
	}

	/**
	 * Callback para la Vista Previa del Formulario en Frontend (Modo Quiosco).
	 */
	public function ajax_preview_form() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Acceso denegado. Permisos insuficientes.', 'wp-doc-signer' ) );
		}

		$post_id = isset( $_GET['post_id'] ) ? intval( $_GET['post_id'] ) : 0;
		$nonce   = isset( $_GET['nonce'] ) ? sanitize_text_field( $_GET['nonce'] ) : '';

		if ( ! wp_verify_nonce( $nonce, 'wpds_preview_form_' . $post_id ) ) {
			wp_die( esc_html__( 'Error de seguridad o sesión expirada.', 'wp-doc-signer' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post || 'wp_documento' !== $post->post_type ) {
			wp_die( esc_html__( 'Documento inválido.', 'wp-doc-signer' ) );
		}

		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
			<title><?php echo sprintf( esc_html__( 'Vista Previa: %s', 'wp-doc-signer' ), esc_html( $post->post_title ) ); ?></title>
			<?php wp_head(); ?>
			<style>
				body {
					background-color: #f1f5f9;
					margin: 0;
					padding: 20px;
					box-sizing: border-box;
				}
				.wpds-preview-banner {
					max-width: 780px;
					margin: 10px auto 20px auto;
					background: #1e293b;
					color: #ffffff;
					padding: 12px 20px;
					border-radius: 12px;
					font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
					font-size: 13px;
					display: flex;
					justify-content: space-between;
					align-items: center;
					box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
				}
				.wpds-preview-close {
					background: #475569;
					color: #ffffff;
					border: none;
					padding: 6px 12px;
					border-radius: 6px;
					cursor: pointer;
					font-weight: 600;
					transition: background 0.2s;
				}
				.wpds-preview-close:hover {
					background: #334155;
				}
			</style>
		</head>
		<body>
			<div class="wpds-preview-banner">
				<span><strong><?php esc_html_e( 'Modo Vista Previa de Formulario:', 'wp-doc-signer' ); ?></strong> <?php esc_html_e( 'Así es como el cliente visualizará y firmará este documento en un iPad, Tablet o Web.', 'wp-doc-signer' ); ?></span>
				<button class="wpds-preview-close" onclick="window.close()"><?php esc_html_e( 'Cerrar Vista Previa', 'wp-doc-signer' ); ?></button>
			</div>

			<?php echo do_shortcode( '[firmar_documento id="' . $post_id . '"]' ); ?>

			<?php wp_footer(); ?>
		</body>
		</html>
		<?php
		exit;
	}

	/**
	 * Callback para la Vista Previa del PDF de Muestra.
	 */
	public function ajax_preview_pdf() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Acceso denegado. Permisos insuficientes.', 'wp-doc-signer' ) );
		}

		$post_id = isset( $_GET['post_id'] ) ? intval( $_GET['post_id'] ) : 0;
		$nonce   = isset( $_GET['nonce'] ) ? sanitize_text_field( $_GET['nonce'] ) : '';

		if ( ! wp_verify_nonce( $nonce, 'wpds_preview_pdf_' . $post_id ) ) {
			wp_die( esc_html__( 'Error de seguridad o sesión expirada.', 'wp-doc-signer' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post || 'wp_documento' !== $post->post_type ) {
			wp_die( esc_html__( 'Documento inválido.', 'wp-doc-signer' ) );
		}

		// Obtener datos ficticios de demostración
		$mock_data = array(
			'nombre'         => 'Juan Pérez Gómez (FIRMADO DE PRUEBA)',
			'telefono'       => '+34 600 123 456',
			'email'          => 'juan.perez.prueba@ejemplo.com',
			'dni'            => '12345678Z',
			'fecha'          => date( 'd/m/Y' ),
			'consentimiento' => 1,
			'firma_1'        => WPDS_PDF_Engine::get_instance()->get_mock_signature_base64(),
			'firma_2'        => WPDS_PDF_Engine::get_instance()->get_mock_signature_base64(),
		);

		// Generar PDF
		$pdf_result = WPDS_PDF_Engine::get_instance()->generate_pdf( $post_id, $mock_data );

		if ( is_wp_error( $pdf_result ) ) {
			wp_die( esc_html( $pdf_result->get_error_message() ) );
		}

		$file_path = $pdf_result['file_path'];

		if ( file_exists( $file_path ) ) {
			// Stream el PDF directamente al navegador
			header( 'Content-Type: application/pdf' );
			header( 'Content-Disposition: inline; filename="MUESTRA_' . sanitize_title( $post->post_title ) . '.pdf"' );
			header( 'Content-Length: ' . filesize( $file_path ) );
			readfile( $file_path );
			
			// Borrar inmediatamente el archivo PDF temporal generado
			unlink( $file_path );
			exit;
		} else {
			wp_die( esc_html__( 'No se pudo generar el archivo de muestra en el servidor.', 'wp-doc-signer' ) );
		}
	}
}

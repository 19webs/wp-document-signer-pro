<?php
/**
 * Registra y gestiona el Custom Post Type "wp_documento" y sus Metaboxes.
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
		add_action( 'init', array( $this, 'register_cpt_documento' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_document_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_document_meta' ) );
		
		// Columnas personalizadas en el listado
		add_filter( 'manage_wp_documento_posts_columns', array( $this, 'set_custom_columns' ) );
		add_action( 'manage_wp_documento_posts_custom_column', array( $this, 'render_custom_columns' ), 10, 2 );

		// Registrar acciones AJAX para vistas previas (evita el retorno de "0")
		add_action( 'wp_ajax_wpds_preview_form', array( $this, 'handle_preview_form' ) );
		add_action( 'wp_ajax_wpds_preview_pdf', array( $this, 'handle_preview_pdf' ) );
	}

	/**
	 * Registrar el CPT "wp_documento".
	 */
	public function register_cpt_documento() {
		$labels = array(
			'name'                  => _x( 'Documentos', 'Post type general name', 'wp-doc-signer' ),
			'singular_name'         => _x( 'Documento', 'Post type singular name', 'wp-doc-signer' ),
			'menu_name'             => _x( 'Firmador Pro', 'Admin Menu text', 'wp-doc-signer' ),
			'name_admin_bar'        => _x( 'Documento', 'Add New on Toolbar', 'wp-doc-signer' ),
			'add_new'               => __( 'Añadir nuevo', 'wp-doc-signer' ),
			'add_new_item'          => __( 'Añadir nuevo Documento', 'wp-doc-signer' ),
			'new_item'              => __( 'Nuevo Documento', 'wp-doc-signer' ),
			'edit_item'             => __( 'Editar Documento', 'wp-doc-signer' ),
			'view_item'             => __( 'Ver Documento', 'wp-doc-signer' ),
			'all_items'             => __( 'Todos los Documentos', 'wp-doc-signer' ),
			'search_items'          => __( 'Buscar Documentos', 'wp-doc-signer' ),
			'not_found'             => __( 'No se encontraron documentos.', 'wp-doc-signer' ),
			'not_found_in_trash'    => __( 'No se encontraron documentos en la papelera.', 'wp-doc-signer' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'documentos-firma' ),
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => false,
			'menu_position'      => 25,
			'menu_icon'          => 'dashicons-media-document',
			'supports'           => array( 'title', 'editor' ),
		);

		register_post_type( 'wp_documento', $args );
	}

	/**
	 * Registrar el CPT de forma manual en activación (evita fallos de inserción inicial).
	 */
	public function register_cpt() {
		$this->register_cpt_documento();
	}

	/**
	 * Añadir metaboxes de configuración del documento.
	 */
	public function add_document_meta_boxes() {
		add_meta_box(
			'wpds_document_settings',
			__( 'Acciones y Estado del Documento', 'wp-doc-signer' ),
			array( $this, 'render_settings_metabox' ),
			'wp_documento',
			'side',
			'high'
		);

		add_meta_box(
			'wpds_establishment_details',
			__( 'Datos del Establecimiento (Cabecera PDF)', 'wp-doc-signer' ),
			array( $this, 'render_establishment_metabox' ),
			'wp_documento',
			'normal',
			'high'
		);

		add_meta_box(
			'wpds_rgpd_details',
			__( 'Textos de Protección de Datos (RGPD) y Consentimiento Personalizados', 'wp-doc-signer' ),
			array( $this, 'render_rgpd_custom_metabox' ),
			'wp_documento',
			'normal',
			'default'
		);
	}

	/**
	 * Renderizar el panel lateral de estado y vistas previas.
	 */
	public function render_settings_metabox( $post ) {
		wp_nonce_field( 'wpds_save_document_meta_action', 'wpds_document_meta_nonce' );

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
		$titular    = get_post_meta( $post->ID, '_wpds_est_titular', true );
		$nif        = get_post_meta( $post->ID, '_wpds_est_nif', true );
		$comercial  = get_post_meta( $post->ID, '_wpds_est_comercial', true );
		$address    = get_post_meta( $post->ID, '_wpds_est_address', true );
		$email      = get_post_meta( $post->ID, '_wpds_est_email', true );
		$phone      = get_post_meta( $post->ID, '_wpds_est_phone', true );

		// Fallbacks preestablecidos de Sara Pérez
		if ( empty( $titular ) ) { $titular = 'Sara Pérez González'; }
		if ( empty( $nif ) ) { $nif = '75817812D'; }
		if ( empty( $comercial ) ) { $comercial = 'Sara Pérez Salón de Autor'; }
		if ( empty( $address ) ) { $address = 'Calle Ancha, 12, Local 2, 11402 Jerez de la Frontera, Cádiz'; }
		if ( empty( $email ) ) { $email = 'saraperezpeluqueriadeautor@gmail.com'; }
		if ( empty( $phone ) ) { $phone = '601 202 303'; }
		?>
		<table class="form-table">
			<tr>
				<th scope="row"><label for="wpds_est_titular"><?php esc_html_e( 'Nombre del Titular / Empresa', 'wp-doc-signer' ); ?></label></th>
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
				<th scope="row"><label for="wpds_est_address"><?php esc_html_e( 'Dirección Física Completa', 'wp-doc-signer' ); ?></label></th>
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

		// Guardar Metadatos del Establecimiento y del RGPD/Consentimiento
		$fields = array(
			'wpds_est_titular',
			'wpds_est_nif',
			'wpds_est_comercial',
			'wpds_est_address',
			'wpds_est_email',
			'wpds_est_phone',
			'wpds_rgpd_finalidad',
			'wpds_rgpd_legitimacion',
			'wpds_rgpd_destinatarios',
			'wpds_rgpd_conservacion',
			'wpds_rgpd_derechos',
			'wpds_rgpd_procedencia',
			'wpds_rgpd_adicional',
			'wpds_consentimiento_titulo',
			'wpds_consentimiento_subtitulo',
			'wpds_consentimiento_texto',
			'wpds_consentimiento_declaracion_titulo',
			'wpds_consentimiento_declaracion_texto',
		);
		foreach ( $fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				update_post_meta( $post_id, '_' . $field, wp_kses_post( $_POST[ $field ] ) );
			}
		}
		update_post_meta( $post_id, '_wpds_rgpd_customized', '1' );
	}

	/**
	 * Renderizar metabox para configurar los textos de protección de datos (RGPD) y Consentimiento personalizados.
	 */
	public function render_rgpd_custom_metabox( $post ) {
		$customized    = get_post_meta( $post->ID, '_wpds_rgpd_customized', true );

		$finalidad     = get_post_meta( $post->ID, '_wpds_rgpd_finalidad', true );
		$legitimacion  = get_post_meta( $post->ID, '_wpds_rgpd_legitimacion', true );
		$destinatarios = get_post_meta( $post->ID, '_wpds_rgpd_destinatarios', true );
		$conservacion  = get_post_meta( $post->ID, '_wpds_rgpd_conservacion', true );
		$derechos      = get_post_meta( $post->ID, '_wpds_rgpd_derechos', true );
		$procedencia   = get_post_meta( $post->ID, '_wpds_rgpd_procedencia', true );
		$adicional     = get_post_meta( $post->ID, '_wpds_rgpd_adicional', true );

		// Pre-cargar valores por defecto si es una nueva página de edición y nunca se ha guardado
		if ( ! $customized && 'auto-draft' === $post->post_status ) {
			$finalidad     = __( 'Gestionar la reserva y la relación precontractual/contractual, prestar y documentar el servicio, gestionar pagos y cumplir obligaciones legales, así como atender o defender reclamaciones. Bases: ejecución del contrato, medidas precontractuales, obligaciones legales y, cuando proceda, interés legítimo para la defensa de reclamaciones.', 'wp-doc-signer' );
			$legitimacion  = __( 'Ejecución de un contrato, cumplimiento de obligaciones legales e interés legítimo.', 'wp-doc-signer' );
			$destinatarios = __( 'Proveedores necesarios para la gestión del servicio y Administraciones, juzgados, tribunales, aseguradoras o asesores cuando exista obligación legal o sea necesario para gestionar o defender reclamaciones.', 'wp-doc-signer' );
			$conservacion  = __( 'Durante la relación con la persona cliente y, posteriormente, durante los plazos legales aplicables para atender obligaciones y posibles responsabilidades.', 'wp-doc-signer' );
			$derechos      = __( 'Acceso, rectificación, supresión, limitación, oposición y portabilidad cuando proceda, mediante el email indicado.', 'wp-doc-signer' );
			$procedencia   = __( 'La propia persona interesada o su representante legal.', 'wp-doc-signer' );
			$adicional     = __( 'Puede consultar la información detallada sobre protección de datos en nuestra oficina o solicitándola por email.', 'wp-doc-signer' );
		}
		
		// Campos de personalización de sección de Consentimiento
		$consentimiento_titulo             = get_post_meta( $post->ID, '_wpds_consentimiento_titulo', true );
		$consentimiento_subtitulo          = get_post_meta( $post->ID, '_wpds_consentimiento_subtitulo', true );
		$consentimiento_texto              = get_post_meta( $post->ID, '_wpds_consentimiento_texto', true );
		$consentimiento_declaracion_titulo = get_post_meta( $post->ID, '_wpds_consentimiento_declaracion_titulo', true );
		$consentimiento_declaracion_texto  = get_post_meta( $post->ID, '_wpds_consentimiento_declaracion_texto', true );
		?>
		<h4 style="border-bottom: 1px solid #dfdfdf; padding-bottom: 10px; margin-top: 20px; font-weight: bold; color: #1d2327; font-size: 14px;"><?php esc_html_e( '1. Tabla de Información Básica (RGPD)', 'wp-doc-signer' ); ?></h4>
		<table class="form-table">
			<tr>
				<th scope="row"><label for="wpds_rgpd_finalidad"><?php esc_html_e( 'Finalidades / Fines del tratamiento', 'wp-doc-signer' ); ?></label></th>
				<td>
					<textarea name="wpds_rgpd_finalidad" id="wpds_rgpd_finalidad" rows="3" class="large-text" placeholder="<?php esc_attr_e( 'Por defecto: Gestionar la reserva y la relación precontractual/contractual...', 'wp-doc-signer' ); ?>"><?php echo esc_textarea( $finalidad ); ?></textarea>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wpds_rgpd_legitimacion"><?php esc_html_e( 'Legitimación / Base Legal', 'wp-doc-signer' ); ?></label></th>
				<td>
					<textarea name="wpds_rgpd_legitimacion" id="wpds_rgpd_legitimacion" rows="2" class="large-text" placeholder="<?php esc_attr_e( 'Por defecto: Ejecución del contrato, consentimiento de la persona interesada y cumplimiento de obligaciones legales...', 'wp-doc-signer' ); ?>"><?php echo esc_textarea( $legitimacion ); ?></textarea>
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
				<th scope="row"><label for="wpds_rgpd_procedencia"><?php esc_html_e( 'Procedencia / Origen de los datos', 'wp-doc-signer' ); ?></label></th>
				<td>
					<textarea name="wpds_rgpd_procedencia" id="wpds_rgpd_procedencia" rows="2" class="large-text" placeholder="<?php esc_attr_e( 'Por defecto: Facilitados por la propia persona interesada al solicitar la cita o el servicio...', 'wp-doc-signer' ); ?>"><?php echo esc_textarea( $procedencia ); ?></textarea>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wpds_rgpd_adicional"><?php esc_html_e( 'Información Adicional / Detalles', 'wp-doc-signer' ); ?></label></th>
				<td>
					<textarea name="wpds_rgpd_adicional" id="wpds_rgpd_adicional" rows="2" class="large-text" placeholder="<?php esc_attr_e( 'Por defecto: Puede consultar la información adicional y detallada en nuestra política de privacidad web...', 'wp-doc-signer' ); ?>"><?php echo esc_textarea( $adicional ); ?></textarea>
				</td>
			</tr>
		</table>

		<h4 style="border-bottom: 1px solid #dfdfdf; padding-bottom: 10px; margin-top: 30px; font-weight: bold; color: #1d2327; font-size: 14px;"><?php esc_html_e( '2. Consentimiento Opcional de Imagen y Voz', 'wp-doc-signer' ); ?></h4>
		<table class="form-table">
			<tr>
				<th scope="row"><label for="wpds_consentimiento_titulo"><?php esc_html_e( 'Título de la Sección', 'wp-doc-signer' ); ?></label></th>
				<td>
					<input type="text" name="wpds_consentimiento_titulo" id="wpds_consentimiento_titulo" value="<?php echo esc_attr( $consentimiento_titulo ); ?>" class="large-text" placeholder="<?php esc_attr_e( 'Por defecto: 7. Consentimiento opcional de imagen y voz', 'wp-doc-signer' ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wpds_consentimiento_subtitulo"><?php esc_html_e( 'Subtítulo / Instrucciones', 'wp-doc-signer' ); ?></label></th>
				<td>
					<textarea name="wpds_consentimiento_subtitulo" id="wpds_consentimiento_subtitulo" rows="2" class="large-text" placeholder="<?php esc_attr_e( 'Por defecto: Esta autorización es gratuita e independiente y solo se entenderá otorgada si se marca SÍ.', 'wp-doc-signer' ); ?>"><?php echo esc_textarea( $consentimiento_subtitulo ); ?></textarea>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wpds_consentimiento_texto"><?php esc_html_e( 'Párrafos de Cláusula de Consentimiento', 'wp-doc-signer' ); ?></label></th>
				<td>
					<textarea name="wpds_consentimiento_texto" id="wpds_consentimiento_texto" rows="6" class="large-text" placeholder="<?php esc_attr_e( 'Introduce aquí las cláusulas del consentimiento. Puedes usar saltos de línea para separar párrafos. Admite variables {titular} y {comercial}. Si se deja vacío, cargará los tres párrafos predeterminados de Sara Pérez.', 'wp-doc-signer' ); ?>"><?php echo esc_textarea( $consentimiento_texto ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Si dejas este campo vacío, se usarán los tres párrafos del salón por defecto (Autorización a salón y SP Academy, fines formativos, retirada, etc.).', 'wp-doc-signer' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wpds_consentimiento_declaracion_titulo"><?php esc_html_e( 'Título de Declaración (Firma)', 'wp-doc-signer' ); ?></label></th>
				<td>
					<input type="text" name="wpds_consentimiento_declaracion_titulo" id="wpds_consentimiento_declaracion_titulo" value="<?php echo esc_attr( $consentimiento_declaracion_titulo ); ?>" class="large-text" placeholder="<?php esc_attr_e( 'Por defecto: PERSONA CLIENTE', 'wp-doc-signer' ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wpds_consentimiento_declaracion_texto"><?php esc_html_e( 'Texto de Declaración (Firma)', 'wp-doc-signer' ); ?></label></th>
				<td>
					<textarea name="wpds_consentimiento_declaracion_texto" id="wpds_consentimiento_declaracion_texto" rows="2" class="large-text" placeholder="<?php esc_attr_e( 'Por defecto: Declaro haber recibido esta información y haber marcado libremente mi opción sobre el uso de imagen y/o voz.', 'wp-doc-signer' ); ?>"><?php echo esc_textarea( $consentimiento_declaracion_texto ); ?></textarea>
				</td>
			</tr>
		</table>
		<p class="description" style="margin-top: 15px;"><?php esc_html_e( 'Deja cualquier campo vacío para usar los textos legales estándar de Sara Pérez Salón de Autor por defecto.', 'wp-doc-signer' ); ?></p>
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
	 * Renderizar columnas personalizadas en el listado.
	 */
	public function render_custom_columns( $column, $post_id ) {
		switch ( $column ) {
			case 'wpds_status':
				$status = get_post_meta( $post_id, '_wpds_status', true );
				if ( 'paused' === $status ) {
					echo '<span class="post-state" style="color:#d63638; font-weight:bold;">' . esc_html__( 'Pausado', 'wp-doc-signer' ) . '</span>';
				} else {
					echo '<span class="post-state" style="color:#2271b1; font-weight:bold;">' . esc_html__( 'Activo', 'wp-doc-signer' ) . '</span>';
				}
				break;

			case 'wpds_shortcode':
				echo '<code style="background:#f0f0f1; padding:3px 6px; border-radius:3px; font-size:11px;">[firmar_documento id="' . esc_attr( $post_id ) . '"]</code>';
				break;
		}
	}

	/**
	 * Maneja la visualización en pantalla de la vista previa del documento.
	 */
	public function handle_preview_form() {
		$post_id = isset( $_GET['post_id'] ) ? intval( $_GET['post_id'] ) : 0;
		$nonce   = isset( $_GET['nonce'] ) ? sanitize_text_field( $_GET['nonce'] ) : '';

		if ( ! wp_verify_nonce( $nonce, 'wpds_preview_form_' . $post_id ) ) {
			wp_die( esc_html__( 'Sesión expirada o enlace inválido.', 'wp-doc-signer' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Acceso denegado.', 'wp-doc-signer' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post || 'wp_documento' !== $post->post_type ) {
			wp_die( esc_html__( 'Documento no válido.', 'wp-doc-signer' ) );
		}

		// Encolar los assets necesarios
		wp_enqueue_style( 'wpds-signer-style', WPDS_URL . 'assets/css/style.css', array(), WPDS_VERSION );
		wp_enqueue_script( 'wpds-signature-pad', WPDS_URL . 'assets/js/signature_pad.umd.min.js', array(), WPDS_VERSION, true );
		wp_enqueue_script( 'wpds-signer-script', WPDS_URL . 'assets/js/script.js', array( 'jquery' ), WPDS_VERSION, true );

		// Localizar script AJAX
		wp_localize_script(
			'wpds-signer-script',
			'wpds_ajax_obj',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'rest_url' => esc_url_raw( rest_url( 'wp-doc-signer/v1/submit' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
			)
		);

		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title><?php echo sprintf( esc_html__( 'Vista Previa: %s', 'wp-doc-signer' ), esc_html( $post->post_title ) ); ?></title>
			<?php wp_head(); ?>
			<style>
				body {
					background-color: #f1f5f9;
					padding: 40px 20px;
					font-family: 'Outfit', sans-serif;
				}
				.wpds-preview-container {
					max-width: 960px;
					margin: 0 auto;
					background: #fff;
					padding: 35px;
					border-radius: 12px;
					box-shadow: 0 4px 20px rgba(0,0,0,0.06);
				}
				.wpds-preview-header {
					text-align: center;
					margin-bottom: 30px;
					border-bottom: 2px solid #f1f5f9;
					padding-bottom: 20px;
				}
				.wpds-preview-header h1 {
					margin: 0;
					font-size: 26px;
					color: #0f172a;
					font-weight: 700;
				}
				.wpds-preview-header p {
					margin: 5px 0 0 0;
					color: #64748b;
					font-size: 14px;
				}
			</style>
		</head>
		<body>
			<div class="wpds-preview-container">
				<div class="wpds-preview-header">
					<h1><?php esc_html_e( 'VISTA PREVIA DEL ASISTENTE DE FIRMAS', 'wp-doc-signer' ); ?></h1>
					<p><?php echo sprintf( esc_html__( 'Estás visualizando el documento "%s" en modo de simulación.', 'wp-doc-signer' ), esc_html( $post->post_title ) ); ?></p>
				</div>
				<?php echo do_shortcode( '[firmar_documento id="' . $post_id . '"]' ); ?>
			</div>
			<?php wp_footer(); ?>
		</body>
		</html>
		<?php
		exit;
	}

	/**
	 * Genera un PDF compilado de prueba y lo sirve directamente en el navegador.
	 */
	public function handle_preview_pdf() {
		$post_id = isset( $_GET['post_id'] ) ? intval( $_GET['post_id'] ) : 0;
		$nonce   = isset( $_GET['nonce'] ) ? sanitize_text_field( $_GET['nonce'] ) : '';

		if ( ! wp_verify_nonce( $nonce, 'wpds_preview_pdf_' . $post_id ) ) {
			wp_die( esc_html__( 'Sesión expirada o enlace inválido.', 'wp-doc-signer' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Acceso denegado.', 'wp-doc-signer' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post || 'wp_documento' !== $post->post_type ) {
			wp_die( esc_html__( 'Documento no válido.', 'wp-doc-signer' ) );
		}

		// Datos ficticios completos de prueba
		$form_data = array(
			'nombre'         => 'Cliente de Prueba',
			'telefono'       => '600 112 233',
			'email'          => 'prueba@19webs.com',
			'dni'            => '12345678Z',
			'fecha'          => date( 'd/m/Y' ),
			'firma_1'        => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAJYAAAA8CAYAAACGOMuXAAAACXBIWXMAAAsTAAALEwEAmpwYAAAC80lEQVR4nO3bQW7bMBAF0J9k1c0BcoDuuuvkQD1Aj9AN0ht0i/QGPkCP0A0a1C2qbnKADpAb1E0yQAIkXexqS6IlaZCSbYkyHwARaZEiRer/qDEhEBERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERFQD/wL/xH4F3UqAEYAAAAASUVORK5CYII=',
			'firma_2'        => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAJYAAAA8CAYAAACGOMuXAAAACXBIWXMAAAsTAAALEwEAmpwYAAAC80lEQVR4nO3bQW7bMBAF0J9k1c0BcoDuuuvkQD1Aj9AN0ht0i/QGPkCP0A0a1C2qbnKADpAb1E0yQAIkXexqS6IlaZCSbYkyHwARaZEiRer/qDEhEBERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERFQD/wL/xH4F3UqAEYAAAAASUVORK5CYII=',
			'consentimiento' => 1,
		);

		$pdf_engine = WPDS_PDF_Engine::get_instance();
		$pdf_engine->generate_preview_pdf( $post_id, $form_data );
		exit;
	}
}

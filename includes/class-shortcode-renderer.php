<?php
/**
 * Clase para renderizar el shortcode del formulario de firma en el frontend.
 *
 * @package WP_Document_Signer_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPDS_Shortcode_Renderer {

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
		add_shortcode( 'firmar_documento', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Renderizar el shortcode.
	 */
	public function render_shortcode( $atts ) {
		// Normalizar atributos
		$args = shortcode_atts(
			array(
				'id'   => 0,
				'slug' => '',
			),
			$atts,
			'firmar_documento'
		);

		$post = null;

		// Buscar documento
		if ( ! empty( $args['id'] ) ) {
			$post = get_post( intval( $args['id'] ) );
		} elseif ( ! empty( $args['slug'] ) ) {
			$posts = get_posts(
				array(
					'name'           => sanitize_title( $args['slug'] ),
					'post_type'      => 'wp_documento',
					'post_status'    => 'publish',
					'posts_per_page' => 1,
				)
			);
			if ( ! empty( $posts ) ) {
				$post = $posts[0];
			}
		}

		if ( ! $post || 'wp_documento' !== $post->post_type ) {
			return '<div class="wpds-alert wpds-alert-error">' . esc_html__( 'Error: Documento no encontrado.', 'wp-doc-signer' ) . '</div>';
		}

		$status = get_post_meta( $post->ID, '_wpds_status', true );
		if ( 'paused' === $status ) {
			return '<div class="wpds-alert wpds-alert-warning">' . esc_html__( 'Este documento no está disponible para firma en este momento.', 'wp-doc-signer' ) . '</div>';
		}

		// Encolar assets
		$this->enqueue_frontend_assets( $post->ID );

		// Obtener metadatos del establecimiento
		$est_titular   = get_post_meta( $post->ID, '_wpds_est_titular', true );
		$est_nif       = get_post_meta( $post->ID, '_wpds_est_nif', true );
		$est_comercial = get_post_meta( $post->ID, '_wpds_est_comercial', true );
		$est_address   = get_post_meta( $post->ID, '_wpds_est_address', true );
		$est_email     = get_post_meta( $post->ID, '_wpds_est_email', true );
		$est_phone     = get_post_meta( $post->ID, '_wpds_est_phone', true );

		// Obtener metadatos de protección de datos y consentimiento personalizados
		$meta_finalidad            = get_post_meta( $post->ID, '_wpds_rgpd_finalidad', true );
		$meta_legitimacion         = get_post_meta( $post->ID, '_wpds_rgpd_legitimacion', true );
		$meta_destinatarios        = get_post_meta( $post->ID, '_wpds_rgpd_destinatarios', true );
		$meta_conservacion         = get_post_meta( $post->ID, '_wpds_rgpd_conservacion', true );
		$meta_derechos             = get_post_meta( $post->ID, '_wpds_rgpd_derechos', true );
		$meta_procedencia          = get_post_meta( $post->ID, '_wpds_rgpd_procedencia', true );
		$meta_adicional            = get_post_meta( $post->ID, '_wpds_rgpd_adicional', true );
		
		$meta_consentimiento_titulo             = get_post_meta( $post->ID, '_wpds_consentimiento_titulo', true );
		$meta_consentimiento_subtitulo          = get_post_meta( $post->ID, '_wpds_consentimiento_subtitulo', true );
		$meta_consentimiento_texto              = get_post_meta( $post->ID, '_wpds_consentimiento_texto', true );
		$meta_consentimiento_declaracion_titulo = get_post_meta( $post->ID, '_wpds_consentimiento_declaracion_titulo', true );
		$meta_consentimiento_declaracion_texto  = get_post_meta( $post->ID, '_wpds_consentimiento_declaracion_texto', true );

		// Textos definitivos con fallbacks legales por defecto
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

		$custom_consent_active = ! empty( $meta_consentimiento_texto );
		if ( $custom_consent_active ) {
			$consentimiento_declaracion = str_replace(
				array( '{titular}', '{comercial}' ),
				array( $est_titular, $est_comercial ),
				$meta_consentimiento_texto
			);
		} else {
			$consentimiento_declaracion = '';
		}

		// Obtener contenido del documento (las cláusulas)
		$content = apply_filters( 'the_content', $post->post_content );

		ob_start();
		?>
		<style>
			/* Reset definitivo e incontestable de espaciado y mayúsculas en TODO el formulario y modal */
			body .wpds-signer-container h1,
			body .wpds-signer-container h2,
			body .wpds-signer-container h3,
			body .wpds-signer-container h4,
			body .wpds-signer-container h5,
			body .wpds-signer-container h6,
			body .wpds-signer-container span,
			body .wpds-signer-container p,
			body .wpds-signer-container td,
			body .wpds-signer-container th,
			body .wpds-signer-container div,
			body .wpds-signer-container label,
			body .wpds-signer-container button,
			body .wpds-signer-container input,
			body .wpds-modal h1,
			body .wpds-modal h2,
			body .wpds-modal h3,
			body .wpds-modal h4,
			body .wpds-modal h5,
			body .wpds-modal h6,
			body .wpds-modal p,
			body .wpds-modal span,
			body .wpds-modal div,
			body .wpds-modal button {
				letter-spacing: 0px !important;
				letter-spacing: normal !important;
				text-transform: none !important;
				word-spacing: normal !important;
				font-family: 'Outfit', -apple-system, BlinkMacSystemFont, sans-serif !important;
			}

			/* Títulos estructurales del wizard y del establecimiento sí van en mayúsculas limpias y tamaño controlado */
			body .wpds-signer-container h2.wpds-section-title {
				text-transform: uppercase !important;
				letter-spacing: 0.5px !important;
				font-size: 1.6rem !important;
				font-weight: 800 !important;
				line-height: 1.3 !important;
				margin: 0 0 0.5rem 0 !important;
				text-align: center !important;
				color: #0f172a !important;
				border: none !important;
			}
			body .wpds-signer-container h4.wpds-card-title {
				text-transform: uppercase !important;
				letter-spacing: 1px !important;
				font-size: 0.85rem !important;
				font-weight: 700 !important;
				color: #475569 !important;
				margin: 0 0 1rem 0 !important;
				padding: 0 0 8px 0 !important;
				border-bottom: 1px dashed #cbd5e1 !important;
			}
			body .wpds-signer-container h3.wpds-section-sub-title {
				font-size: 1.2rem !important;
				font-weight: 700 !important;
				color: #0f172a !important;
				margin: 2rem 0 0.8rem 0 !important;
			}

			/* Estilos de títulos de las cláusulas internas del acuerdo (cuerpo del documento) */
			body .wpds-signer-container .wpds-document-content-block h3 {
				font-size: 1.15rem !important;
				font-weight: bold !important;
				color: #0f172a !important;
				margin: 1.5rem 0 0.5rem 0 !important;
				line-height: 1.4 !important;
			}

			/* Modal y título del Modal corregido (no descuadrado) */
			body .wpds-modal h3.wpds-modal-title {
				text-transform: uppercase !important;
				letter-spacing: 0.5px !important;
				font-size: 1.3rem !important;
				font-weight: 700 !important;
				color: #0f172a !important;
				margin: 0 0 0.75rem 0 !important;
				line-height: 1.3 !important;
				text-align: center !important;
			}
			body .wpds-modal p {
				font-size: 0.95rem !important;
				color: #64748b !important;
				margin: 0 0 1.5rem 0 !important;
				line-height: 1.5 !important;
				text-align: center !important;
			}
			body .wpds-modal-content {
				padding: 2.5rem !important;
				max-width: 440px !important;
				width: 90% !important;
				text-align: center !important;
				box-sizing: border-box !important;
			}
		</style>
		<div class="wpds-signer-container" id="wpds-signer-container-<?php echo esc_attr( $post->ID ); ?>">
			<!-- Indicador de Progreso de Pasos (Páginas) -->
			<div class="wpds-steps-header">
				<div class="wpds-step-indicator active" id="wpds-step-ind-1">
					<span class="wpds-step-num">1</span>
					<span class="wpds-step-text"><?php esc_html_e( 'Acuerdo de Servicio', 'wp-doc-signer' ); ?></span>
				</div>
				<div class="wpds-step-line"></div>
				<div class="wpds-step-indicator" id="wpds-step-ind-2">
					<span class="wpds-step-num">2</span>
					<span class="wpds-step-text"><?php esc_html_e( 'Protección de Datos', 'wp-doc-signer' ); ?></span>
				</div>
			</div>

			<form id="wpds-signing-form" method="POST" class="wpds-signing-form">
				<?php wp_nonce_field( 'wpds_sign_document_' . $post->ID, 'wpds_nonce' ); ?>
				<input type="hidden" name="wpds_document_id" value="<?php echo esc_attr( $post->ID ); ?>" />

				<!-- PÁGINA 1: ACUERDO DE ACEPTACIÓN -->
				<div class="wpds-step-section active" id="wpds-step-section-1">
					<h2 class="wpds-section-title"><?php echo esc_html( $post->post_title ); ?></h2>
					
					<!-- Cabecera de Identificación (Establecimiento vs Cliente) -->
					<div class="wpds-identification-grid">
						<div class="wpds-id-card wpds-establishment-card">
							<h4 class="wpds-card-title"><?php esc_html_e( 'RESPONSABLE / ESTABLECIMIENTO', 'wp-doc-signer' ); ?></h4>
							<p><strong><?php echo esc_html( $est_titular ); ?></strong></p>
							<p><?php echo sprintf( esc_html__( 'NIF: %s', 'wp-doc-signer' ), esc_html( $est_nif ) ); ?></p>
							<p><?php echo sprintf( esc_html__( 'Nombre comercial: %s', 'wp-doc-signer' ), esc_html( $est_comercial ) ); ?></p>
							<p><?php echo esc_html( $est_address ); ?></p>
							<p><?php echo esc_html( $est_email ); ?></p>
							<p><?php echo esc_html( $est_phone ); ?></p>
						</div>

						<div class="wpds-id-card wpds-client-card">
							<h4 class="wpds-card-title"><?php esc_html_e( 'PERSONA CLIENTE', 'wp-doc-signer' ); ?></h4>
							
							<div class="wpds-input-group">
								<label for="wpds_nombre"><?php esc_html_e( 'Nombre y apellidos:', 'wp-doc-signer' ); ?> <span class="wpds-required">*</span></label>
								<input type="text" name="wpds_nombre" id="wpds_nombre" required class="wpds-input" placeholder="<?php esc_attr_e( 'Nombre completo del firmante', 'wp-doc-signer' ); ?>" />
							</div>

							<div class="wpds-input-group">
								<label for="wpds_dni"><?php esc_html_e( 'DNI / NIE / Pasaporte:', 'wp-doc-signer' ); ?> <span class="wpds-required">*</span></label>
								<input type="text" name="wpds_dni" id="wpds_dni" required class="wpds-input" placeholder="<?php esc_attr_e( 'Documento de identidad', 'wp-doc-signer' ); ?>" />
							</div>

							<div class="wpds-input-group">
								<label for="wpds_telefono"><?php esc_html_e( 'Teléfono:', 'wp-doc-signer' ); ?> <span class="wpds-required">*</span></label>
								<input type="tel" name="wpds_telefono" id="wpds_telefono" required class="wpds-input" placeholder="<?php esc_attr_e( 'Ej: +34 600 000 000', 'wp-doc-signer' ); ?>" />
							</div>

							<div class="wpds-input-group">
								<label for="wpds_email"><?php esc_html_e( 'Email:', 'wp-doc-signer' ); ?> <span class="wpds-required">*</span></label>
								<input type="email" name="wpds_email" id="wpds_email" required class="wpds-input" placeholder="<?php esc_attr_e( 'nombre@correo.com', 'wp-doc-signer' ); ?>" />
							</div>

							<div class="wpds-input-group">
								<label for="wpds_fecha"><?php esc_html_e( 'Fecha:', 'wp-doc-signer' ); ?> <span class="wpds-required">*</span></label>
								<input type="date" name="wpds_fecha" id="wpds_fecha" required class="wpds-input" value="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>" />
							</div>
						</div>
					</div>

					<p class="wpds-intro-declaration">
						<?php esc_html_e( 'La persona cliente declara haber recibido, antes de contratar la prueba tester y antes de iniciar cualquier tratamiento, información clara sobre el SP Experience, el precio y condiciones de la prueba tester, la reserva de cita y el servicio propuesto. Ha podido formular preguntas y acepta este acuerdo.', 'wp-doc-signer' ); ?>
					</p>

					<!-- Cláusulas del documento (Editable desde Elementor/Editor de bloques) -->
					<div class="wpds-document-content-block">
						<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>

					<!-- Sección de firmas Página 1 -->
					<div class="wpds-signatures-flex">
						<div class="wpds-signature-box">
							<label class="wpds-sig-label"><?php esc_html_e( 'FIRMA PERSONA CLIENTE', 'wp-doc-signer' ); ?> <span class="wpds-required">*</span></label>
							<div class="wpds-canvas-container">
								<canvas id="wpds_canvas_1" class="wpds-signature-canvas"></canvas>
								<button type="button" class="wpds-clear-canvas" data-target="wpds_canvas_1"><?php esc_html_e( 'Limpiar', 'wp-doc-signer' ); ?></button>
							</div>
							<input type="hidden" name="wpds_firma_1" id="wpds_firma_1" required />
						</div>

						<div class="wpds-signature-box wpds-establishment-signature-display">
							<label class="wpds-sig-label"><?php esc_html_e( 'SARA PÉREZ SALÓN DE AUTOR', 'wp-doc-signer' ); ?></label>
							<div class="wpds-est-stamp">
								<div class="wpds-est-stamp-city"><?php esc_html_e( 'Jerez de la Frontera, Cádiz', 'wp-doc-signer' ); ?></div>
								<div class="wpds-est-stamp-name"><?php echo esc_html( $est_titular ); ?></div>
								<div class="wpds-est-stamp-nif"><?php echo sprintf( esc_html__( 'NIF: %s', 'wp-doc-signer' ), esc_html( $est_nif ) ); ?></div>
								<div class="wpds-est-stamp-com"><?php echo esc_html( $est_comercial ); ?></div>
								<div class="wpds-est-stamp-seal"><?php esc_html_e( 'FIRMADO DIGITALMENTE', 'wp-doc-signer' ); ?></div>
							</div>
						</div>
					</div>

					<div class="wpds-navigation-row">
						<button type="button" id="wpds-next-step-btn" class="wpds-nav-button wpds-button-next">
							<?php esc_html_e( 'Continuar a Protección de Datos', 'wp-doc-signer' ); ?>
							<span class="dashicons dashicons-arrow-right-alt" style="vertical-align: middle; margin-left: 5px;"></span>
						</button>
					</div>
				</div>

				<!-- PÁGINA 2: PROTECCIÓN DE DATOS Y RGPD -->
				<div class="wpds-step-section" id="wpds-step-section-2" style="display: none;">
					<h2 class="wpds-section-title"><?php esc_html_e( 'INFORMACIÓN SOBRE PROTECCIÓN DE DATOS', 'wp-doc-signer' ); ?></h2>
					<p class="wpds-section-subtitle"><?php esc_html_e( 'Información sobre el consentimiento de imagen', 'wp-doc-signer' ); ?></p>

					<!-- Tabla RGPD Completa -->
					<div class="wpds-rgpd-table-wrapper">
						<table class="wpds-rgpd-table">
							<tr>
								<td class="wpds-rgpd-header-cell"><?php esc_html_e( 'RESPONSABLE', 'wp-doc-signer' ); ?></td>
								<td><?php echo sprintf( esc_html__( '%s - NIF %s - %s. %s. Contacto: %s.', 'wp-doc-signer' ), esc_html( $est_titular ), esc_html( $est_nif ), esc_html( $est_comercial ), esc_html( $est_address ), esc_html( $est_email ) ); ?></td>
							</tr>
							<tr>
								<td class="wpds-rgpd-header-cell"><?php esc_html_e( 'FINALIDADES', 'wp-doc-signer' ); ?></td>
								<td><?php echo esc_html( $rgpd_finalidad ); ?></td>
							</tr>
							<tr>
								<td class="wpds-rgpd-header-cell"><?php esc_html_e( 'LEGITIMACIÓN', 'wp-doc-signer' ); ?></td>
								<td><?php echo esc_html( $rgpd_legitimacion ); ?></td>
							</tr>
							<tr>
								<td class="wpds-rgpd-header-cell"><?php esc_html_e( 'DESTINATARIOS', 'wp-doc-signer' ); ?></td>
								<td><?php echo esc_html( $rgpd_destinatarios ); ?></td>
							</tr>
							<tr>
								<td class="wpds-rgpd-header-cell"><?php esc_html_e( 'CONSERVACIÓN', 'wp-doc-signer' ); ?></td>
								<td><?php echo esc_html( $rgpd_conservacion ); ?></td>
							</tr>
							<tr>
								<td class="wpds-rgpd-header-cell"><?php esc_html_e( 'DERECHOS', 'wp-doc-signer' ); ?></td>
								<td><?php echo esc_html( $rgpd_derechos ); ?></td>
							</tr>
							<tr>
								<td class="wpds-rgpd-header-cell"><?php esc_html_e( 'PROCEDENCIA', 'wp-doc-signer' ); ?></td>
								<td><?php echo esc_html( $rgpd_procedencia ); ?></td>
							</tr>
							<tr>
								<td class="wpds-rgpd-header-cell"><?php esc_html_e( 'INFORMACIÓN ADICIONAL', 'wp-doc-signer' ); ?></td>
								<td><?php echo esc_html( $rgpd_adicional ); ?></td>
							</tr>
						</table>
					</div>

					<h3 class="wpds-section-sub-title" style="margin-top: 2rem;"><?php echo esc_html( $consentimiento_titulo ); ?></h3>
					<p class="description" style="margin-bottom: 1.5rem;"><?php echo esc_html( $consentimiento_subtitulo ); ?></p>

					<!-- Selector de Consentimiento Imagen SÍ / NO -->
					<div class="wpds-consent-interactive-block">
						<div class="wpds-consent-options-selector">
							<button type="button" class="wpds-consent-btn" data-value="1">
								<span class="wpds-consent-check"></span>
								<span class="wpds-consent-btn-txt"><?php esc_html_e( 'SÍ ACEPTO', 'wp-doc-signer' ); ?></span>
							</button>
							<button type="button" class="wpds-consent-btn" data-value="0">
								<span class="wpds-consent-check"></span>
								<span class="wpds-consent-btn-txt"><?php esc_html_e( 'NO ACEPTO', 'wp-doc-signer' ); ?></span>
							</button>
							<input type="hidden" name="wpds_consentimiento_imagen" id="wpds_consentimiento_imagen" value="" required />
						</div>

						<div class="wpds-consent-statement-card">
							<p><strong><?php esc_html_e( 'DECLARACIÓN:', 'wp-doc-signer' ); ?></strong></p>
							<?php if ( $custom_consent_active ) : ?>
								<?php echo wp_kses_post( wpautop( $consentimiento_declaracion ) ); ?>
							<?php else : ?>
								<p><?php echo sprintf( esc_html__( 'Autorizo a %s / %s y a SP EXPERIENCE ACADEMY, S.L. a captar y utilizar gratuitamente mi imagen y/o voz para la difusión de trabajos realizados por %s en redes sociales y materiales formativos propios.', 'wp-doc-signer' ), esc_html( $est_titular ), esc_html( $est_comercial ), esc_html( $est_titular ) ); ?></p>
								<p><?php esc_html_e( 'Para el uso formativo autorizado, SP EXPERIENCE ACADEMY, S.L. (CIF B22608962, Calle Mar del Norte, 5, 11405 Jerez de la Frontera, Cádiz; mismo email de contacto) podrá tratar la imagen como responsable de sus propios materiales formativos. La difusión en redes sociales implica publicación en plataformas de terceros, cuyo tratamiento posterior se rige por sus propias políticas.', 'wp-doc-signer' ); ?></p>
								<p><?php esc_html_e( 'La autorización puede retirarse en cualquier momento. La retirada no afecta a la licitud de los usos anteriores. Desde su recepción cesarán los nuevos usos y, cuando proceda retirar contenidos de perfiles o canales bajo control directo de los responsables, se tramitará sin dilación indebida y en un plazo máximo de 15 días hábiles. Respecto de copias o redistribuciones realizadas por terceros fuera de su control directo, se adoptarán las medidas razonables legalmente exigibles.', 'wp-doc-signer' ); ?></p>
							<?php endif; ?>
						</div>
					</div>

					<div class="wpds-consent-declaracion-wrapper" style="margin-top: 2rem;">
						<p class="wpds-intro-declaration">
							<strong><?php echo esc_html( $consentimiento_declaracion_titulo ); ?>:</strong><br/>
							<?php echo esc_html( $consentimiento_declaracion_texto ); ?>
						</p>
					</div>

					<!-- Sección de firmas Página 2 -->
					<div class="wpds-signatures-flex">
						<div class="wpds-signature-box">
							<label class="wpds-sig-label"><?php esc_html_e( 'FIRMA PERSONA CLIENTE (CONSENTIMIENTO)', 'wp-doc-signer' ); ?> <span class="wpds-required">*</span></label>
							<div class="wpds-canvas-container">
								<canvas id="wpds_canvas_2" class="wpds-signature-canvas"></canvas>
								<button type="button" class="wpds-clear-canvas" data-target="wpds_canvas_2"><?php esc_html_e( 'Limpiar', 'wp-doc-signer' ); ?></button>
							</div>
							<input type="hidden" name="wpds_firma_2" id="wpds_firma_2" required />
						</div>
						<div class="wpds-signature-box wpds-empty-flex-box" style="visibility:hidden; opacity:0;"></div>
					</div>

					<!-- Botones navegación -->
					<div class="wpds-navigation-row wpds-two-buttons">
						<button type="button" id="wpds-prev-step-btn" class="wpds-nav-button wpds-button-prev">
							<span class="dashicons dashicons-arrow-left-alt" style="vertical-align: middle; margin-right: 5px;"></span>
							<?php esc_html_e( 'Volver a Condiciones', 'wp-doc-signer' ); ?>
						</button>
						<button type="submit" id="wpds-submit-btn" class="wpds-submit-button">
							<span class="wpds-btn-text"><?php esc_html_e( 'Firmar y Finalizar Acuerdo', 'wp-doc-signer' ); ?></span>
							<span class="wpds-btn-spinner" style="display: none;"></span>
						</button>
					</div>
				</div>
			</form>

			<!-- Modal de Feedback de Carga / Éxito / Error -->
			<div id="wpds-feedback-modal" class="wpds-modal" style="display: none;">
				<div class="wpds-modal-content">
					<div class="wpds-modal-icon-container">
						<div class="wpds-spinner" id="wpds-modal-spinner"></div>
						<div class="wpds-success-icon" id="wpds-modal-success" style="display: none;">
							<svg viewBox="0 0 52 52"><circle class="wpds-checkmark-circle" cx="26" cy="26" r="25" fill="none"/><path class="wpds-checkmark-check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/></svg>
						</div>
						<div class="wpds-error-icon" id="wpds-modal-error" style="display: none;">
							<svg viewBox="0 0 52 52"><circle class="wpds-error-circle" cx="26" cy="26" r="25" fill="none"/><path class="wpds-error-x" fill="none" d="M16 16 36 36 M36 16 16 36"/></svg>
						</div>
					</div>
					<h3 id="wpds-modal-title"><?php esc_html_e( 'Procesando firma...', 'wp-doc-signer' ); ?></h3>
					<p id="wpds-modal-message"><?php esc_html_e( 'Generando el archivo PDF y enviando copias por correo electrónico.', 'wp-doc-signer' ); ?></p>
					<button type="button" id="wpds-modal-close-btn" class="wpds-modal-btn" style="display: none;"><?php esc_html_e( 'Cerrar', 'wp-doc-signer' ); ?></button>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Encolar los archivos CSS y JS para el frontend.
	 */
	private function enqueue_frontend_assets( $document_id ) {
		wp_enqueue_style( 'wp-icons' );
		wp_enqueue_style( 'wpds-frontend-css', WPDS_URL . 'assets/css/frontend-signer.css', array(), WPDS_VERSION );
		wp_enqueue_script( 'wpds-signature-pad', WPDS_URL . 'assets/js/signature_pad.umd.min.js', array(), '5.1.4', true );
		wp_enqueue_script( 'wpds-frontend-js', WPDS_URL . 'assets/js/frontend-signer.js', array( 'wpds-signature-pad' ), WPDS_VERSION, true );

		$local_vars = array(
			'api_url'  => esc_url_raw( rest_url( 'wp-doc-signer/v1/submit' ) ),
			'nonce'    => wp_create_nonce( 'wpds_submit_nonce' ),
			'messages' => array(
				'validation_error'   => __( 'Por favor, rellene todos los campos obligatorios antes de continuar.', 'wp-doc-signer' ),
				'signature_error_1'  => __( 'Por favor, firme en el cuadro del Acuerdo (Firma 1) antes de continuar.', 'wp-doc-signer' ),
				'signature_error_2'  => __( 'Por favor, firme en el cuadro de Protección de Datos (Firma 2) antes de finalizar.', 'wp-doc-signer' ),
				'consent_error'      => __( 'Debe elegir una de las opciones de consentimiento de imagen (SÍ / NO).', 'wp-doc-signer' ),
				'submit_error'       => __( 'Ha ocurrido un error al procesar el documento. Inténtelo de nuevo.', 'wp-doc-signer' ),
				'success_title'      => __( '¡Acuerdo Firmado!', 'wp-doc-signer' ),
				'success_message'    => __( 'El documento se ha generado correctamente. Se ha enviado una copia en PDF por correo electrónico.', 'wp-doc-signer' ),
				'processing'         => __( 'Generando PDF de alta calidad y enviando notificaciones...', 'wp-doc-signer' ),
			),
		);

		wp_localize_script( 'wpds-frontend-js', 'wpds_vars', $local_vars );
	}
}

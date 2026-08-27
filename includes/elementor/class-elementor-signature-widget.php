<?php
/**
 * Widget de Elementor para el Firmador de Documentos.
 *
 * @package WP_Document_Signer_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPDS_Elementor_Signature_Widget extends \Elementor\Widget_Base {

	/**
	 * Retorna el nombre único del widget.
	 */
	public function get_name() {
		return 'wpds_signature_widget';
	}

	/**
	 * Retorna el título del widget para el panel de Elementor.
	 */
	public function get_title() {
		return esc_html__( 'Firmador Pro', 'wp-doc-signer' );
	}

	/**
	 * Retorna el icono del widget.
	 */
	public function get_icon() {
		return 'eicon-document-file';
	}

	/**
	 * Retorna las categorías asociadas al widget.
	 */
	public function get_categories() {
		return array( '19webs-addons' );
	}

	/**
	 * Registrar controles y campos de configuración en el panel de Elementor.
	 */
	protected function register_controls() {
		
		// SECCIÓN: Contenido
		$this->start_controls_section(
			'content_section',
			array(
				'label' => esc_html__( 'Configuración de Firma', 'wp-doc-signer' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		// Obtener listado de documentos del Custom Post Type
		$documents = get_posts( array(
			'post_type'      => 'wp_documento',
			'posts_per_page' => -1,
		) );

		$options = array( '' => esc_html__( 'Selecciona un documento...', 'wp-doc-signer' ) );
		if ( ! empty( $documents ) ) {
			foreach ( $documents as $doc ) {
				$options[ $doc->ID ] = $doc->post_title;
			}
		}

		$this->add_control(
			'document_id',
			array(
				'label'   => esc_html__( 'Seleccionar Documento', 'wp-doc-signer' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '',
				'options' => $options,
			)
		);

		$this->end_controls_section();

		// SECCIÓN: Estilos visuales
		$this->start_controls_section(
			'style_section',
			array(
				'label' => esc_html__( 'Estilos del Asistente', 'wp-doc-signer' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		// Color Primario (afecta botones y elementos interactivos)
		$this->add_control(
			'primary_color',
			array(
				'label'     => esc_html__( 'Color Primario', 'wp-doc-signer' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wpds-next-btn, {{WRAPPER}} .wpds-consent-btn.wpds-active' => 'background-color: {{VALUE}} !important; border-color: {{VALUE}} !important;',
					'{{WRAPPER}} .wpds-consent-btn.wpds-active .wpds-consent-check' => 'background-color: {{VALUE}} !important; border-color: {{VALUE}} !important;',
					'{{WRAPPER}} .wpds-signature-canvas-container' => 'border-color: {{VALUE}} !important;',
					'{{WRAPPER}} .wpds-step-indicator span.active' => 'background-color: {{VALUE}} !important; color: #fff !important; border-color: {{VALUE}} !important;',
				),
			)
		);

		// Color de los textos
		$this->add_control(
			'text_color',
			array(
				'label'     => esc_html__( 'Color de los Textos', 'wp-doc-signer' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wpds-step-section' => 'color: {{VALUE}} !important;',
					'{{WRAPPER}} .wpds-section-title' => 'color: {{VALUE}} !important;',
					'{{WRAPPER}} .wpds-section-subtitle' => 'color: {{VALUE}} !important;',
					'{{WRAPPER}} label' => 'color: {{VALUE}} !important;',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Renderiza el widget en el frontend.
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();
		$document_id = isset( $settings['document_id'] ) ? intval( $settings['document_id'] ) : 0;

		// Evitar errores visuales en el editor si no se ha seleccionado documento
		if ( empty( $document_id ) ) {
			echo '<div style="padding: 30px; background: #f8fafc; border: 2px dashed #cbd5e1; text-align: center; color: #64748b; font-family: \'Outfit\', sans-serif; border-radius: 8px;">';
			echo '<span class="dashicons dashicons-edit" style="font-size: 24px; width: 24px; height: 24px; margin-bottom: 8px; display: block; margin: 0 auto;"></span>';
			echo '<strong>' . esc_html__( 'Firmador Pro: Ningún documento seleccionado', 'wp-doc-signer' ) . '</strong><br/>';
			echo esc_html__( 'Por favor, selecciona un documento legal en el panel lateral de ajustes del widget.', 'wp-doc-signer' );
			echo '</div>';
			return;
		}

		// Cargar los scripts y estilos necesarios del firmador
		wp_enqueue_style( 'wpds-signer-style' );
		wp_enqueue_script( 'wpds-signature-pad' );
		wp_enqueue_script( 'wpds-signer-script' );

		// Renderizar shortcode de firma
		echo do_shortcode( '[firmar_documento id="' . $document_id . '"]' );
	}
}

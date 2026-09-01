<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<title><?php echo esc_html( $title ); ?></title>
	<style>
		@page {
			margin: 2.2cm 2cm 2.2cm 2cm;
		}
		body {
			font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
			font-size: 9.5pt;
			line-height: 1.5;
			color: #1a202c;
		}
		/* Marca de Agua */
		.watermark {
			position: fixed;
			top: 25%;
			left: 20%;
			width: 60%;
			opacity: 0.25;
			z-index: -1;
		}
		/* Encabezado */
		.header {
			position: fixed;
			top: -1.3cm;
			left: 0;
			right: 0;
			height: 0.8cm;
			border-bottom: 1px solid #e2e8f0;
			font-size: 7.5pt;
			color: #718096;
			text-align: right;
		}
		/* Pie de página */
		.footer {
			position: fixed;
			bottom: -1.3cm;
			left: 0;
			right: 0;
			height: 0.8cm;
			border-top: 1px solid #e2e8f0;
			font-size: 7.5pt;
			color: #718096;
			text-align: center;
			padding-top: 4px;
		}
		.footer .page-number:after {
			content: "Página " counter(page);
		}
		
		/* Títulos */
		h1.main-title {
			font-size: 15pt;
			font-weight: bold;
			color: #0f172a;
			text-align: center;
			margin: 0 0 4px 0;
			text-transform: uppercase;
			letter-spacing: 0.5px;
		}
		h2.subtitle {
			font-size: 11pt;
			font-style: italic;
			color: #4a5568;
			text-align: center;
			margin: 0 0 15px 0;
		}
		h3.section-title {
			font-size: 10.5pt;
			font-weight: bold;
			color: #0f172a;
			margin-top: 15px;
			margin-bottom: 8px;
			border-bottom: 1px solid #e2e8f0;
			padding-bottom: 3px;
		}

		/* Grid de Identificación (Tablas) */
		.info-table {
			width: 100%;
			border-collapse: collapse;
			margin-bottom: 15px;
		}
		.info-table td {
			border: 1px solid #cbd5e1;
			padding: 6px 10px;
			vertical-align: top;
			font-size: 8.5pt;
		}
		.info-table th {
			background-color: #f1f5f9;
			border: 1px solid #cbd5e1;
			padding: 5px 10px;
			font-weight: bold;
			font-size: 8.5pt;
			text-align: left;
			color: #1e293b;
		}
		.info-label {
			font-weight: bold;
			color: #475569;
			width: 30%;
		}

		/* Declaración Inicial */
		.declaration-text {
			font-size: 8.5pt;
			font-style: italic;
			color: #334155;
			text-align: justify;
			background-color: #f8fafc;
			padding: 8px 12px;
			border-left: 3px solid #6366f1;
			margin-bottom: 15px;
		}

		/* Contenido de Cláusulas */
		.clauses-content {
			text-align: justify;
			font-size: 9pt;
			line-height: 1.45;
			margin-bottom: 20px;
		}
		.clauses-content p {
			margin-top: 0;
			margin-bottom: 8px;
		}
		.clauses-content ul {
			margin-top: 0;
			margin-bottom: 8px;
			padding-left: 20px;
		}
		.clauses-content li {
			margin-bottom: 4px;
		}

		/* Estructura de Firmas en Pie (Página 1) */
		.signatures-row-table {
			width: 100%;
			border-collapse: collapse;
			margin-top: 15px;
		}
		.signatures-row-table td {
			width: 50%;
			border: 1px solid #cbd5e1;
			padding: 10px;
			vertical-align: top;
		}
		.sig-client-block {
			min-height: 110px;
		}
		.sig-client-title {
			font-weight: bold;
			font-size: 8.5pt;
			color: #1e293b;
			margin-bottom: 6px;
			text-transform: uppercase;
		}
		.sig-image {
			width: 180px;
			height: 70px;
			display: block;
			margin-top: 5px;
		}
		.sig-establishment-stamp {
			font-size: 8.5pt;
			line-height: 1.4;
		}
		.stamp-title {
			font-weight: bold;
			color: #0f172a;
			text-transform: uppercase;
			margin-bottom: 5px;
		}

		/* Estilos RGPD (Página 2) */
		.rgpd-table {
			width: 100%;
			border-collapse: collapse;
			margin-bottom: 20px;
		}
		.rgpd-table td {
			border: 1px solid #cbd5e1;
			padding: 6px 10px;
			font-size: 8.5pt;
			vertical-align: middle;
		}
		.rgpd-label-cell {
			font-weight: bold;
			background-color: #f8fafc;
			color: #1e293b;
			width: 25%;
		}

		/* Consentimiento Imagen */
		.consent-box-table {
			width: 100%;
			border-collapse: collapse;
			margin-bottom: 20px;
		}
		.consent-box-table td {
			border: 1px solid #cbd5e1;
			padding: 10px;
			vertical-align: top;
		}
		.consent-option-cell {
			width: 25%;
			text-align: center;
			vertical-align: middle !important;
			background-color: #f8fafc;
		}
		.consent-badge-selected {
			display: inline-block;
			border: 2px solid #6366f1;
			background-color: #e0e7ff;
			color: #4338ca;
			font-weight: bold;
			padding: 6px 12px;
			border-radius: 4px;
			font-size: 10pt;
		}
		.consent-badge-unselected {
			display: inline-block;
			border: 1px solid #cbd5e1;
			background-color: #ffffff;
			color: #94a3b8;
			padding: 6px 12px;
			border-radius: 4px;
			font-size: 9pt;
		}
		.consent-text-p {
			font-size: 8.5pt;
			margin: 0 0 6px 0;
			text-align: justify;
		}
		.consent-text-p:last-of-type {
			margin-bottom: 0;
		}

		/* Separador de Página */
		.page-break {
			page-break-after: always;
		}
	</style>
</head>
<body>

	<!-- Imagen de Marca de Agua -->
	<?php if ( ! empty( $watermark_base64 ) ) : ?>
		<img src="<?php echo $watermark_base64; ?>" class="watermark" alt="Watermark" />
	<?php endif; ?>

	<!-- Cabecera de Página (Dompdf) -->
	<div class="header">
		<?php echo esc_html( $est_comercial ); ?> | <?php echo esc_html( $title ); ?>
	</div>

	<!-- Pie de Página (Dompdf) -->
	<div class="footer">
		<div class="page-number"></div>
		<div style="font-size: 6.5pt; color: #a0aec0; margin-top: 2px;">
			<?php echo sprintf( esc_html__( 'Acuerdo %s - %s | Documento firmado electrónicamente con validez jurídica.', 'wp-doc-signer' ), esc_html( $title ), esc_html( $est_comercial ) ); ?>
		</div>
	</div>

	<!-- ==================== PÁGINA 1: ACUERDO DE ACEPTACIÓN ==================== -->
	<div class="page-container">
		
		<h1 class="main-title"><?php echo esc_html( $title ); ?></h1>
		<h2 class="subtitle"><?php esc_html_e( 'Acuerdo SP Experience', 'wp-doc-signer' ); ?></h2>

		<!-- Tabla de Información (Establecimiento y Cliente) -->
		<table class="info-table">
			<thead>
				<tr>
					<th style="width: 50%;"><?php esc_html_e( 'RESPONSABLE / ESTABLECIMIENTO', 'wp-doc-signer' ); ?></th>
					<th style="width: 50%;"><?php esc_html_e( 'PERSONA CLIENTE', 'wp-doc-signer' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td>
						<strong><?php echo sprintf( esc_html__( 'Responsable: %s', 'wp-doc-signer' ), esc_html( $est_titular ) ); ?></strong><br />
						<?php echo sprintf( esc_html__( 'NIF: %s', 'wp-doc-signer' ), esc_html( $est_nif ) ); ?><br />
						<?php echo sprintf( esc_html__( 'Nombre comercial: %s', 'wp-doc-signer' ), esc_html( $est_comercial ) ); ?><br />
						<?php echo sprintf( esc_html__( 'Dirección: %s', 'wp-doc-signer' ), esc_html( $est_address ) ); ?><br />
						<?php echo sprintf( esc_html__( 'Email: %s', 'wp-doc-signer' ), esc_html( $est_email ) ); ?><br />
						<?php echo sprintf( esc_html__( 'Teléfono: %s', 'wp-doc-signer' ), esc_html( $est_phone ) ); ?>
					</td>
					<td>
						<table style="width: 100%; border: none; border-collapse: collapse;">
							<tr>
								<td style="border: none; padding: 2px 0; font-weight: bold; width: 35%;"><?php esc_html_e( 'Nombre y apellidos:', 'wp-doc-signer' ); ?></td>
								<td style="border: none; padding: 2px 0;"><?php echo esc_html( $form_data['nombre'] ); ?></td>
							</tr>
							<tr>
								<td style="border: none; padding: 2px 0; font-weight: bold;"><?php esc_html_e( 'DNI:', 'wp-doc-signer' ); ?></td>
								<td style="border: none; padding: 2px 0;"><?php echo esc_html( $form_data['dni'] ); ?></td>
							</tr>
							<tr>
								<td style="border: none; padding: 2px 0; font-weight: bold;"><?php esc_html_e( 'Teléfono:', 'wp-doc-signer' ); ?></td>
								<td style="border: none; padding: 2px 0;"><?php echo esc_html( $form_data['telefono'] ); ?></td>
							</tr>
							<tr>
								<td style="border: none; padding: 2px 0; font-weight: bold;"><?php esc_html_e( 'Email:', 'wp-doc-signer' ); ?></td>
								<td style="border: none; padding: 2px 0;"><?php echo esc_html( $form_data['email'] ); ?></td>
							</tr>
						</table>
					</td>
				</tr>
			</tbody>
		</table>

		<div class="declaration-text">
			<?php esc_html_e( 'La persona cliente declara haber recibido, antes de contratar la prueba tester y antes de iniciar cualquier tratamiento, información clara sobre el SP Experience, el precio y condiciones de la prueba tester, la reserva de cita y el servicio propuesto. Ha podido formular preguntas y acepta este acuerdo.', 'wp-doc-signer' ); ?>
		</div>

		<!-- Cláusulas del Contrato -->
		<div class="clauses-content">
			<?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>

		<!-- Bloque de Firmas Página 1 -->
		<table class="signatures-row-table">
			<tr>
				<td>
					<div class="sig-client-block">
						<div class="sig-client-title"><?php esc_html_e( 'PERSONA CLIENTE', 'wp-doc-signer' ); ?></div>
						<div style="font-size: 8pt; color: #475569;">
							<?php 
							$pdf_fecha_display = ! empty( $form_data['fecha'] ) ? ( strtotime( $form_data['fecha'] ) ? date( 'd/m/Y', strtotime( $form_data['fecha'] ) ) : $form_data['fecha'] ) : date( 'd/m/Y' );
							?>
							<strong><?php esc_html_e( 'Fecha:', 'wp-doc-signer' ); ?></strong> <?php echo esc_html( $pdf_fecha_display ); ?>
						</div>
						<div style="margin-top: 10px;">
							<div style="font-size: 7.5pt; color: #64748b; font-style: italic; margin-bottom: 2px;"><?php esc_html_e( 'Firma:', 'wp-doc-signer' ); ?></div>
							<img src="<?php echo esc_attr( $form_data['firma_1'] ); ?>" class="sig-image" alt="Firma Cliente" />
						</div>
					</div>
				</td>
				<td>
					<div class="sig-client-block sig-establishment-stamp">
						<div class="stamp-title"><?php echo esc_html( $est_comercial ); ?></div>
						<div style="color: #475569; font-size: 8pt; margin-bottom: 6px;">
							<?php esc_html_e( 'Jerez de la Frontera, Cádiz', 'wp-doc-signer' ); ?>
						</div>
						<div style="margin-top: 12px; font-weight: bold; color: #0f172a;">
							<?php echo esc_html( $est_titular ); ?><br />
							NIF: <?php echo esc_html( $est_nif ); ?><br />
							<?php echo esc_html( $est_comercial ); ?>
						</div>
						<div style="margin-top: 15px; font-size: 7.5pt; color: #16a34a; font-weight: bold; letter-spacing: 0.5px;">
							[ FIRMADO ELECTRÓNICAMENTE ]
						</div>
					</div>
				</td>
			</tr>
		</table>

	</div>

	<!-- Salto de página para el consentimiento RGPD -->
	<div class="page-break"></div>

	<!-- ==================== PÁGINA 2: PROTECCIÓN DE DATOS ==================== -->
	<div class="page-container">
		
		<h1 class="main-title"><?php esc_html_e( 'INFORMACIÓN SOBRE PROTECCIÓN DE DATOS', 'wp-doc-signer' ); ?></h1>
		<h2 class="subtitle"><?php esc_html_e( 'Información sobre el consentimiento de imagen', 'wp-doc-signer' ); ?></h2>

		<h3 class="section-title"><?php esc_html_e( '6. Información básica de protección de datos', 'wp-doc-signer' ); ?></h3>

		<!-- Tabla RGPD Básica -->
		<table class="rgpd-table">
			<tr>
				<td class="rgpd-label-cell"><?php esc_html_e( 'RESPONSABLE', 'wp-doc-signer' ); ?></td>
				<td><?php echo sprintf( esc_html__( '%s - NIF %s - %s. %s. Contacto: %s.', 'wp-doc-signer' ), esc_html( $est_titular ), esc_html( $est_nif ), esc_html( $est_comercial ), esc_html( $est_address ), esc_html( $est_email ) ); ?></td>
			</tr>
			<?php if ( ! empty( $rgpd_finalidad ) ) : ?>
				<tr>
					<td class="rgpd-label-cell"><?php esc_html_e( 'FINALIDADES', 'wp-doc-signer' ); ?></td>
					<td><?php echo esc_html( $rgpd_finalidad ); ?></td>
				</tr>
			<?php endif; ?>
			<?php if ( ! empty( $rgpd_legitimacion ) ) : ?>
				<tr>
					<td class="rgpd-label-cell"><?php esc_html_e( 'LEGITIMACIÓN', 'wp-doc-signer' ); ?></td>
					<td><?php echo esc_html( $rgpd_legitimacion ); ?></td>
				</tr>
			<?php endif; ?>
			<?php if ( ! empty( $rgpd_destinatarios ) ) : ?>
				<tr>
					<td class="rgpd-label-cell"><?php esc_html_e( 'DESTINATARIOS', 'wp-doc-signer' ); ?></td>
					<td><?php echo esc_html( $rgpd_destinatarios ); ?></td>
				</tr>
			<?php endif; ?>
			<?php if ( ! empty( $rgpd_conservacion ) ) : ?>
				<tr>
					<td class="rgpd-label-cell"><?php esc_html_e( 'CONSERVACIÓN', 'wp-doc-signer' ); ?></td>
					<td><?php echo esc_html( $rgpd_conservacion ); ?></td>
				</tr>
			<?php endif; ?>
			<?php if ( ! empty( $rgpd_derechos ) ) : ?>
				<tr>
					<td class="rgpd-label-cell"><?php esc_html_e( 'DERECHOS', 'wp-doc-signer' ); ?></td>
					<td><?php echo esc_html( $rgpd_derechos ); ?></td>
				</tr>
			<?php endif; ?>
			<?php if ( ! empty( $rgpd_procedencia ) ) : ?>
				<tr>
					<td class="rgpd-label-cell"><?php esc_html_e( 'PROCEDENCIA', 'wp-doc-signer' ); ?></td>
					<td><?php echo esc_html( $rgpd_procedencia ); ?></td>
				</tr>
			<?php endif; ?>
			<?php if ( ! empty( $rgpd_adicional ) ) : ?>
				<tr>
					<td class="rgpd-label-cell"><?php esc_html_e( 'INFORMACIÓN ADICIONAL', 'wp-doc-signer' ); ?></td>
					<td><?php echo esc_html( $rgpd_adicional ); ?></td>
				</tr>
			<?php endif; ?>
		</table>

		<h3 class="section-title"><?php echo esc_html( $consentimiento_titulo ); ?></h3>
		<p style="font-size: 8.5pt; color: #4a5568; margin-top: 0; margin-bottom: 10px; font-style: italic;">
			<?php echo esc_html( $consentimiento_subtitulo ); ?>
		</p>

		<!-- Tabla de Consentimiento Toggles/Checks -->
		<table class="consent-box-table">
			<tr>
				<td class="consent-option-cell">
					<div style="margin-bottom: 15px;">
						<?php if ( 1 === intval( $form_data['consentimiento'] ) ) : ?>
							<span class="consent-badge-selected">[X] SÍ ACEPTO</span>
						<?php else : ?>
							<span class="consent-badge-unselected">[ ] SÍ ACEPTO</span>
						<?php endif; ?>
					</div>
					<div>
						<?php if ( 0 === intval( $form_data['consentimiento'] ) ) : ?>
							<span class="consent-badge-selected">[X] NO ACEPTO</span>
						<?php else : ?>
							<span class="consent-badge-unselected">[ ] NO ACEPTO</span>
						<?php endif; ?>
					</div>
				</td>
				<td>
					<?php if ( $custom_consent_active ) : ?>
						<?php echo wp_kses_post( wpautop( $consentimiento_declaracion ) ); ?>
					<?php else : ?>
						<p class="consent-text-p">
							<?php echo sprintf( esc_html__( 'Autorizo a %s / %s y a SP EXPERIENCE ACADEMY, S.L. a captar y utilizar gratuitamente mi imagen y/o voz para la difusión de trabajos realizados por %s en redes sociales y materiales formativos propios.', 'wp-doc-signer' ), esc_html( $est_titular ), esc_html( $est_comercial ), esc_html( $est_titular ) ); ?>
						</p>
						<p class="consent-text-p">
							<?php echo sprintf( esc_html__( 'Para el uso formativo autorizado, SP EXPERIENCE ACADEMY, S.L. (CIF B22608962, Calle Mar del Norte, 5, 11405 Jerez de la Frontera, Cádiz; mismo email de contacto) podrá tratar la imagen como responsable de sus propios materiales formativos. La difusión en redes sociales implica publicación en plataformas de terceros, cuyo tratamiento posterior se rige por sus propias políticas.', 'wp-doc-signer' ) ); ?>
						</p>
						<p class="consent-text-p">
							<?php esc_html_e( 'La autorización puede retirarse en cualquier momento. La retirada no afecta a la licitud de los usos anteriores. Desde su recepción cesarán los nuevos usos y, cuando proceda retirar contenidos de perfiles o canales bajo control directo de los responsables, se tramitará sin dilación indebida y en un plazo máximo de 15 días hábiles. Respecto de copias o redistribuciones realizadas por terceros fuera de su control directo, se adoptarán las medidas razonables legalmente exigibles.', 'wp-doc-signer' ); ?>
						</p>
					<?php endif; ?>
				</td>
			</tr>
		</table>

		<!-- Bloque de Firma Página 2 -->
		<table class="signatures-row-table" style="margin-top: 25px;">
			<tr>
				<td colspan="2" style="background-color: #f8fafc; padding: 12px; border-bottom: none;">
					<strong><?php echo esc_html( $consentimiento_declaracion_titulo ); ?></strong>
					<p style="margin: 5px 0 0 0; font-size: 8.5pt; color: #475569;">
						<?php echo esc_html( $consentimiento_declaracion_texto ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<td style="width: 50%; border-top: none;">
					<div style="font-size: 8pt; color: #475569; margin-bottom: 5px;">
						<strong><?php esc_html_e( 'Fecha:', 'wp-doc-signer' ); ?></strong> <?php echo esc_html( $pdf_fecha_display ); ?>
					</div>
					<div style="font-size: 8pt; color: #475569;">
						<strong><?php esc_html_e( 'Firmante:', 'wp-doc-signer' ); ?></strong> <?php echo esc_html( $form_data['nombre'] ); ?>
					</div>
				</td>
				<td style="width: 50%; border-top: none;">
					<div style="font-size: 7.5pt; color: #64748b; font-style: italic; margin-bottom: 2px;"><?php esc_html_e( 'Firma Consentimiento:', 'wp-doc-signer' ); ?></div>
					<img src="<?php echo esc_attr( $form_data['firma_2'] ); ?>" class="sig-image" alt="Firma Consentimiento" />
				</td>
			</tr>
		</table>

	</div>

</body>
</html>

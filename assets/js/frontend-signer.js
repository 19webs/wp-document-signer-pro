/**
 * Lógica del Frontend para el Wizard de Firma y Envío del Formulario WP Document Signer Pro.
 */
document.addEventListener('DOMContentLoaded', function () {
	const form = document.getElementById('wpds-signing-form');
	if (!form) return;

	// Elementos del Wizard (Pasos)
	const step1Section = document.getElementById('wpds-step-section-1');
	const step2Section = document.getElementById('wpds-step-section-2');
	const ind1 = document.getElementById('wpds-step-ind-1');
	const ind2 = document.getElementById('wpds-step-ind-2');

	const btnNext = document.getElementById('wpds-next-step-btn');
	const btnPrev = document.getElementById('wpds-prev-step-btn');

	// Elementos del Modal y Botones
	const modal = document.getElementById('wpds-feedback-modal');
	const modalTitle = document.getElementById('wpds-modal-title');
	const modalMessage = document.getElementById('wpds-modal-message');
	const modalSpinner = document.getElementById('wpds-modal-spinner');
	const modalSuccess = document.getElementById('wpds-modal-success');
	const modalError = document.getElementById('wpds-modal-error');
	const modalCloseBtn = document.getElementById('wpds-modal-close-btn');
	const submitBtn = document.getElementById('wpds-submit-btn');

	const pads = {};

	// 1. Inicializar Signature Pad 1 (Acuerdo)
	const canvas1 = document.getElementById('wpds_canvas_1');
	let pad1 = null;
	if (canvas1) {
		pad1 = new SignaturePad(canvas1, {
			backgroundColor: 'rgba(255, 255, 255, 0)',
			penColor: 'rgb(15, 23, 42)' // Gris oscuro
		});
		pads['wpds_canvas_1'] = pad1;
	}

	// Inicializar Signature Pad 2 (RGPD)
	const canvas2 = document.getElementById('wpds_canvas_2');
	let pad2 = null;
	if (canvas2) {
		pad2 = new SignaturePad(canvas2, {
			backgroundColor: 'rgba(255, 255, 255, 0)',
			penColor: 'rgb(15, 23, 42)'
		});
		pads['wpds_canvas_2'] = pad2;
	}

	// 2. Función de re-escalado Retina para Canvas
	function resizeCanvas(canvas, pad) {
		if (!canvas || !pad) return;

		const signatureData = pad.toData();
		const ratio = Math.max(window.devicePixelRatio || 1, 1);
		
		canvas.width = canvas.offsetWidth * ratio;
		canvas.height = canvas.offsetHeight * ratio;
		canvas.getContext("2d").scale(ratio, ratio);

		pad.clear();
		pad.fromData(signatureData);
	}

	// Re-escalar canvas 1 inicialmente
	if (canvas1 && pad1) {
		resizeCanvas(canvas1, pad1);
	}

	// Escuchar redimensionamiento global
	let resizeTimeout;
	window.addEventListener('resize', function () {
		clearTimeout(resizeTimeout);
		resizeTimeout = setTimeout(function () {
			if (canvas1 && pad1 && step1Section.style.display !== 'none') {
				resizeCanvas(canvas1, pad1);
			}
			if (canvas2 && pad2 && step2Section.style.display !== 'none') {
				resizeCanvas(canvas2, pad2);
			}
		}, 200);
	});

	// 3. Botones Limpiar individuales
	const clearButtons = document.querySelectorAll('.wpds-clear-canvas');
	clearButtons.forEach(button => {
		button.addEventListener('click', function () {
			const targetCanvasId = this.getAttribute('data-target');
			const pad = pads[targetCanvasId];
			if (pad) {
				pad.clear();
				const hiddenInputId = targetCanvasId.replace('canvas_', 'firma_');
				const hiddenInput = document.getElementById(hiddenInputId);
				if (hiddenInput) {
					hiddenInput.value = '';
				}
			}
		});
	});

	// 4. Lógica de los Botones de Consentimiento (SÍ / NO)
	const consentBtns = document.querySelectorAll('.wpds-consent-btn');
	const hiddenConsentInput = document.getElementById('wpds_consentimiento_imagen');

	consentBtns.forEach(btn => {
		btn.addEventListener('click', function () {
			// Remover clase activo de los hermanos
			consentBtns.forEach(b => b.classList.remove('active'));
			
			// Activar actual
			this.classList.add('active');
			
			// Actualizar el valor en el input oculto
			const val = this.getAttribute('data-value');
			hiddenConsentInput.value = val;
		});
	});

	// 5. Navegación del Wizard
	// Paso 1 -> Paso 2
	if (btnNext) {
		btnNext.addEventListener('click', function () {
			// Validar inputs obligatorios del cliente (Página 1)
			const requiredInputs = step1Section.querySelectorAll('input[required]');
			let isValid = true;
			
			requiredInputs.forEach(input => {
				if (!input.checkValidity()) {
					input.reportValidity();
					isValid = false;
				}
			});

			if (!isValid) return;

			// Validar firma 1
			if (pad1 && pad1.isEmpty()) {
				alert(wpds_vars.messages.signature_error_1);
				return;
			}

			// Pasar a Paso 2
			step1Section.style.display = 'none';
			step2Section.style.display = 'block';
			
			ind1.classList.remove('active');
			ind2.classList.add('active');

			// Importante: Re-dimensionar canvas 2 una vez se muestre la sección (ya que anteriormente estaba oculto y media 0x0)
			if (canvas2 && pad2) {
				setTimeout(() => {
					resizeCanvas(canvas2, pad2);
				}, 50);
			}
			
			// Subir scroll arriba para mejor experiencia en tablets
			window.scrollTo({ top: form.offsetTop - 50, behavior: 'smooth' });
		});
	}

	// Paso 2 -> Paso 1
	if (btnPrev) {
		btnPrev.addEventListener('click', function () {
			step2Section.style.display = 'none';
			step1Section.style.display = 'block';
			
			ind2.classList.remove('active');
			ind1.classList.add('active');

			// Re-dimensionar canvas 1 por seguridad
			if (canvas1 && pad1) {
				setTimeout(() => {
					resizeCanvas(canvas1, pad1);
				}, 50);
			}
			
			window.scrollTo({ top: form.offsetTop - 50, behavior: 'smooth' });
		});
	}

	// 6. Cerrar modal de feedback
	if (modalCloseBtn) {
		modalCloseBtn.addEventListener('click', function () {
			modal.classList.remove('wpds-active');
		});
	}

	// 7. Enviar Formulario Final (Paso 2)
	form.addEventListener('submit', function (e) {
		e.preventDefault();

		// Validar consentimiento RGPD (SÍ / NO)
		if (hiddenConsentInput.value === '') {
			alert(wpds_vars.messages.consent_error);
			return;
		}

		// Validar firma 2 (Consentimiento) obligatoria
		if (pad2 && pad2.isEmpty()) {
			alert(wpds_vars.messages.signature_error_2);
			return;
		}

		// Rellenar los inputs hidden con los PNG de firmas
		const hiddenFirma1 = document.getElementById('wpds_firma_1');
		if (pad1 && hiddenFirma1) {
			hiddenFirma1.value = pad1.toDataURL('image/png');
		}

		const hiddenFirma2 = document.getElementById('wpds_firma_2');
		if (pad2 && hiddenFirma2) {
			hiddenFirma2.value = pad2.toDataURL('image/png');
		}

		// Deshabilitar botón
		submitBtn.disabled = true;
		const btnSpinner = submitBtn.querySelector('.wpds-btn-spinner');
		if (btnSpinner) btnSpinner.style.display = 'inline-block';

		// Mostrar modal de carga
		showModalState('processing', wpds_vars.messages.processing);

		const formData = new FormData(form);
		formData.append('wpds_nonce', wpds_vars.nonce);

		// Envío fetch
		fetch(wpds_vars.api_url, {
			method: 'POST',
			headers: {
				'X-WPDS-Nonce': wpds_vars.nonce
			},
			body: formData
		})
		.then(response => {
			return response.json().then(data => {
				if (!response.ok) {
					throw new Error(data.message || wpds_vars.messages.submit_error);
				}
				return data;
			});
		})
		.then(data => {
			// Éxito
			showModalState('success', wpds_vars.messages.success_message, wpds_vars.messages.success_title);
			form.reset();
			if (pad1) pad1.clear();
			if (pad2) pad2.clear();
			consentBtns.forEach(b => b.classList.remove('active'));
			hiddenConsentInput.value = '';

			// Redirigir al Paso 1 visualmente
			step2Section.style.display = 'none';
			step1Section.style.display = 'block';
			ind2.classList.remove('active');
			ind1.classList.add('active');
			if (canvas1 && pad1) resizeCanvas(canvas1, pad1);
			submitBtn.disabled = false;
			if (btnSpinner) btnSpinner.style.display = 'none';
		})
		.catch(error => {
			// Error
			showModalState('error', error.message || wpds_vars.messages.submit_error, 'Error');
			submitBtn.disabled = false;
			if (btnSpinner) btnSpinner.style.display = 'none';
		});
	});

	function showModalState(state, message, title = '') {
		modal.classList.add('wpds-active');
		modalMessage.textContent = message;

		modalSpinner.style.display = 'none';
		modalSuccess.style.display = 'none';
		modalError.style.display = 'none';
		modalCloseBtn.style.display = 'none';

		if (state === 'processing') {
			modalSpinner.style.display = 'block';
			modalTitle.textContent = title || 'Procesando...';
		} else if (state === 'success') {
			modalSuccess.style.display = 'block';
			modalTitle.textContent = title || 'Éxito';
			modalCloseBtn.style.display = 'block';
		} else if (state === 'error') {
			modalError.style.display = 'block';
			modalTitle.textContent = title || 'Error';
			modalCloseBtn.style.display = 'block';
		}
	}
});

(function ($) {
	'use strict';

	if (typeof rmInlineEdit === 'undefined') return;

	const config = rmInlineEdit;
	let isEditMode = false;
	let hasChanges = false;
	let originalValues = {};
	let changedFields = {};
	let mediaFrames = {};

	/**
	 * Get the placeholder text for a field (descriptive > label > generic).
	 */
	function getPlaceholder(fieldName) {
		var field = config.fields[fieldName];
		return (field && field.placeholder) || (field && field.label) || config.i18n.emptyField;
	}

	// =========================================================================
	// INITIALIZATION
	// =========================================================================

	function init() {
		createToolbar();
		discoverEditableElements();
		hydrateRepeaterDisplays();
		showInitialPlaceholders();
		bindEvents();

		// Auto-enter edit mode if profile is empty (new coach onboarding)
		if (config.isProfileEmpty) {
			enterEditMode();
			showWelcomeBanner();
		}
	}

	/**
	 * Inject placeholders for empty text/textarea/wysiwyg fields on page load
	 * so visitors see grey hints even outside edit mode.
	 */
	function showInitialPlaceholders() {
		document.querySelectorAll('[data-rm-field]').forEach(function (el) {
			var type = el.dataset.rmType;
			var fieldName = el.dataset.rmField;

			// Text-like fields: check if displayed content is empty
			if (type === 'text' || type === 'number' || type === 'textarea' || type === 'wysiwyg') {
				var textEl = findTextNode(el);
				if (!textEl) return;

				// Treat &nbsp; (\u00a0) as empty
				var rawText = textEl.textContent.trim().replace(/\u00a0/g, '');
				if (rawText) return; // field has real content

				// Clear &nbsp; residue
				textEl.innerHTML = '';
				el.classList.add('rm-empty-field');

				var ph = document.createElement('span');
				ph.className = 'rm-placeholder';
				ph.textContent = getPlaceholder(fieldName);
				textEl.appendChild(ph);

				// Ensure the widget container stays visible
				forceEmptyFieldVisible(el, textEl);
				return;
			}

			// Taxonomy fields: show placeholder if no terms are selected
			if (type === 'taxonomy') {
				var fieldCfg = config.fields[fieldName];
				var taxonomy = fieldCfg && fieldCfg.taxonomy;
				if (!taxonomy) return;
				var taxData = config.taxonomies[taxonomy];
				var selected = taxData ? taxData.selected : [];
				if (selected && selected.length > 0) return;

				var textEl = findTextNode(el);
				if (!textEl) return;
				if (el.querySelector('.rm-placeholder')) return;

				textEl.innerHTML = '';
				el.classList.add('rm-empty-field');

				var ph = document.createElement('span');
				ph.className = 'rm-placeholder';
				ph.textContent = getPlaceholder(fieldName);
				textEl.appendChild(ph);
				forceEmptyFieldVisible(el, textEl);
				return;
			}

			// Repeater fields: show placeholder if no items
			if (type === 'repeater') {
				var items = config.repeaters[fieldName] || [];
				if (items.length > 0) return;

				var textEl = findTextNode(el);
				if (!textEl) return;
				if (el.querySelector('.rm-placeholder')) return;

				el.classList.add('rm-empty-field');

				var ph = document.createElement('span');
				ph.className = 'rm-placeholder';
				ph.textContent = getPlaceholder(fieldName);
				textEl.appendChild(ph);
				forceEmptyFieldVisible(el, textEl);
			}
		});
	}

	/**
	 * Store original repeater HTML for cancel/restore.
	 * JetEngine renders nested data correctly, so no text replacement needed.
	 */
	function hydrateRepeaterDisplays() {
		document.querySelectorAll('[data-rm-field]').forEach(function (el) {
			if (el.dataset.rmType !== 'repeater') return;
			var fieldName = el.dataset.rmField;

			// Just store the current server-rendered HTML for cancel/restore
			el._rmOriginalHtml = el.innerHTML;
			originalValues[fieldName] = { html: el.innerHTML };
		});
	}

	/**
	 * Find all elements with rm-edit-{field_name} classes.
	 * Store references and original values.
	 */
	function discoverEditableElements() {
		document.querySelectorAll('[class*="rm-edit-"]').forEach(function (el) {
			const fieldName = getFieldName(el);
			if (!fieldName || !config.fields[fieldName]) return;

			el.dataset.rmField = fieldName;
			el.dataset.rmType = config.fields[fieldName].type;

			// Store original content for cancel
			storeOriginalValue(el, fieldName);
		});
	}

	/**
	 * Extract field name from CSS class: rm-edit-coach_bio → coach_bio
	 */
	function getFieldName(el) {
		const classes = el.className.split(/\s+/);
		for (let i = 0; i < classes.length; i++) {
			if (classes[i].startsWith('rm-edit-')) {
				return classes[i].replace('rm-edit-', '');
			}
		}
		return null;
	}

	/**
	 * Store original value of an element for cancel/restore.
	 *
	 * We store per-ELEMENT (el._rmOriginalHtml) for accurate cancel/restore,
	 * and per-FIELD-NAME (originalValues[fieldName]) for save comparison and
	 * format detection.  This prevents cross-contamination when multiple DOM
	 * elements share the same rm-edit-{field} class (e.g. duplicated Elementor
	 * buttons).
	 */
	function storeOriginalValue(el, fieldName) {
		const type = config.fields[fieldName].type;

		// Clone and strip plugin UI elements so they are never stored
		var cleanHtml = (function () {
			var clone = el.cloneNode(true);
			clone.querySelectorAll('.rm-wysiwyg-toolbar, .rm-placeholder, .rm-image-overlay, .rm-image-placeholder, .rm-tax-edit-btn, .rm-gallery-overlay, .rm-date-input, .rm-date-range-container, .rm-number-input, #rm-edit-fab, .rm-edit-fab').forEach(function (n) { n.remove(); });
			return clone.innerHTML;
		})();

		// Per-element original HTML (used by cancelChanges for accurate restore)
		el._rmOriginalHtml = cleanHtml;

		if (type === 'image' || type === 'featured_image') {
			const img = el.querySelector('img');
			originalValues[fieldName] = {
				html: cleanHtml,
				id: config.images[fieldName] ? config.images[fieldName].id : 0,
				url: img ? img.src : '',
			};
		} else if (type === 'gallery') {
			originalValues[fieldName] = {
				html: cleanHtml,
				images: config.galleries[fieldName] ? [...config.galleries[fieldName]] : [],
			};
		} else if (type === 'date') {
			originalValues[fieldName] = {
				html: cleanHtml,
				date: config.dates[fieldName] || '',
			};
		} else if (type === 'date_range') {
			originalValues[fieldName] = {
				html: cleanHtml,
				dates: config.dates[fieldName] || { start: '', end: '' },
			};
		} else if (type === 'taxonomy') {
			const taxonomy = config.fields[fieldName].taxonomy;
			originalValues[fieldName] = {
				html: cleanHtml,
				selected: config.taxonomies[taxonomy]
					? [...config.taxonomies[taxonomy].selected]
					: [],
			};
		} else if (type === 'cpt_select') {
			originalValues[fieldName] = {
				html: cleanHtml,
				selected: config.cptOptions && config.cptOptions[fieldName]
					? config.cptOptions[fieldName].selected
					: 0,
			};
		} else if (type === 'repeater') {
			originalValues[fieldName] = {
				html: cleanHtml,
			};
		} else {
			// Text, textarea, wysiwyg, number, url
			const textEl = findTextNode(el);
			originalValues[fieldName] = {
				html: cleanHtml,
				text: textEl ? textEl.textContent.trim().replace(/\u00a0/g, '') : '',
			};
		}
	}

	/**
	 * Find the deepest element that contains text (drill through Elementor wrappers).
	 * For multi-paragraph content (e.g. post_content), returns the parent container
	 * so all paragraphs are captured for editing.
	 */
	function findTextNode(el) {
		// For Elementor heading widget
		const heading = el.querySelector('h1, h2, h3, h4, h5, h6');
		if (heading) return heading;

		// For Elementor text-editor widget (contains all paragraphs)
		const editor = el.querySelector('.elementor-text-editor');
		if (editor) return editor;

		// For JetEngine dynamic field content container
		const jetContent = el.querySelector('.jet-listing-dynamic-field__content');
		if (jetContent) return jetContent;

		// Check for multiple paragraphs — return parent container instead of single <p>
		const paragraphs = el.querySelectorAll('p');
		if (paragraphs.length > 1) {
			var parent = paragraphs[0].parentElement;
			if (parent && parent !== el) return parent;
			return el;
		}

		// For single paragraph
		const p = el.querySelector('p');
		if (p) return p;

		// For spans with text
		const span = el.querySelector('span');
		if (span && span.textContent.trim()) return span;

		// Fallback: the element itself
		return el;
	}

	// =========================================================================
	// TOOLBAR (floating edit/save/cancel bar)
	// =========================================================================

	function createToolbar() {
		// Edit button (FAB - floating action button)
		const fab = document.createElement('button');
		fab.id = 'rm-edit-fab';
		fab.className = 'rm-edit-fab';
		fab.innerHTML = `
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
				<path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>
				<path d="m15 5 4 4"/>
			</svg>
			<span>${config.i18n.editProfile}</span>
		`;
		fab.addEventListener('click', enterEditMode);

		// Place FAB inside a visual container:
		// - Coach context: single FAB inside cover photo
		// - Camp context: two FABs — one in gallery (top-left) + one sticky (bottom-right, content-aligned)
		var coverContainer = document.querySelector('.rm-edit-coach_cover_photo');
		var galleryContainer = document.querySelector('.rm-edit-camp_gallery');
		if (coverContainer) {
			coverContainer.style.position = 'relative';
			coverContainer.appendChild(fab);
			fab.classList.add('rm-edit-fab--in-cover');
		} else if (galleryContainer) {
			// Gallery FAB (top-left inside gallery)
			var galleryFab = document.createElement('button');
			galleryFab.className = 'rm-edit-fab rm-edit-fab--in-gallery';
			galleryFab.innerHTML = fab.innerHTML;
			galleryFab.addEventListener('click', enterEditMode);
			galleryContainer.style.position = 'relative';
			galleryContainer.appendChild(galleryFab);

			// Sticky FAB (bottom-right, aligned with content container)
			fab.classList.add('rm-edit-fab--sticky');
			document.body.appendChild(fab);
		} else {
			document.body.appendChild(fab);
		}

		// Save/Cancel toolbar (bottom bar)
		const toolbar = document.createElement('div');
		toolbar.id = 'rm-edit-toolbar';
		toolbar.className = 'rm-edit-toolbar rm-hidden';
		toolbar.innerHTML = `
			<div class="rm-toolbar-inner">
				<div class="rm-toolbar-status" id="rm-toolbar-status"></div>
				<div class="rm-toolbar-actions">
					<button type="button" class="rm-btn rm-btn-cancel" id="rm-btn-cancel">
						${config.i18n.cancel}
					</button>
					<button type="button" class="rm-btn rm-btn-save" id="rm-btn-save">
						${config.i18n.save}
					</button>
				</div>
			</div>
		`;
		document.body.appendChild(toolbar);

		document.getElementById('rm-btn-save').addEventListener('click', saveChanges);
		document.getElementById('rm-btn-cancel').addEventListener('click', cancelChanges);
	}

	// =========================================================================
	// WELCOME BANNER (for new coach onboarding)
	// =========================================================================

	function showWelcomeBanner() {
		// Don't show twice
		if (document.getElementById('rm-welcome-banner')) return;

		var banner = document.createElement('div');
		banner.id = 'rm-welcome-banner';
		banner.className = 'rm-welcome-banner';
		banner.innerHTML =
			'<div class="rm-welcome-banner-inner">' +
				'<div class="rm-welcome-banner-content">' +
					'<div class="rm-welcome-banner-title">' + escapeHtml(config.i18n.welcomeTitle) + '</div>' +
					'<div class="rm-welcome-banner-text">' + escapeHtml(config.i18n.welcomeText) + '</div>' +
				'</div>' +
				'<button type="button" class="rm-welcome-banner-close">&times;</button>' +
			'</div>';

		// Insert at the top of the page content
		var firstField = document.querySelector('[data-rm-field]');
		if (firstField) {
			var section = firstField.closest('.e-con, .elementor-section');
			if (section) {
				section.parentNode.insertBefore(banner, section);
			} else {
				firstField.parentNode.insertBefore(banner, firstField);
			}
		} else {
			document.body.insertBefore(banner, document.body.firstChild);
		}

		banner.querySelector('.rm-welcome-banner-close').addEventListener('click', function () {
			banner.remove();
		});
	}

	function removeWelcomeBanner() {
		var banner = document.getElementById('rm-welcome-banner');
		if (banner) banner.remove();
	}

	// =========================================================================
	// EDIT MODE
	// =========================================================================

	function enterEditMode() {
		isEditMode = true;
		hasChanges = false;
		changedFields = {};
		document.body.classList.add('rm-edit-mode');

		// Hide FAB(s), show toolbar
		document.getElementById('rm-edit-fab').classList.add('rm-hidden');
		var gFab = document.querySelector('.rm-edit-fab--in-gallery');
		if (gFab) gFab.classList.add('rm-hidden');
		document.getElementById('rm-edit-toolbar').classList.remove('rm-hidden');

		// Activate each editable element
		document.querySelectorAll('[data-rm-field]').forEach(function (el) {
			const fieldName = el.dataset.rmField;
			const type = el.dataset.rmType;

			el.classList.add('rm-editable');

			switch (type) {
				case 'text':
					setupTextEditable(el, fieldName);
					break;
				case 'number':
					setupNumberEditable(el, fieldName);
					break;
				case 'url':
					setupUrlEditable(el, fieldName);
					break;
				case 'textarea':
					setupTextareaEditable(el, fieldName);
					break;
				case 'wysiwyg':
					setupWysiwygEditable(el, fieldName);
					break;
				case 'image':
				case 'featured_image':
					setupImageEditable(el, fieldName);
					break;
				case 'repeater':
					setupRepeaterEditable(el, fieldName);
					break;
				case 'taxonomy':
					setupTaxonomyEditable(el, fieldName);
					break;
				case 'date':
					setupDateEditable(el, fieldName);
					break;
				case 'date_range':
					setupDateRangeEditable(el, fieldName);
					break;
				case 'gallery':
					setupGalleryEditable(el, fieldName);
					break;
				case 'cpt_select':
					setupCptSelectEditable(el, fieldName);
					break;
			}
		});

		setStatus('');
	}

	function exitEditMode() {
		isEditMode = false;
		hasChanges = false;
		changedFields = {};
		document.body.classList.remove('rm-edit-mode');

		// Show FAB(s), hide toolbar
		document.getElementById('rm-edit-fab').classList.remove('rm-hidden');
		var gFab = document.querySelector('.rm-edit-fab--in-gallery');
		if (gFab) gFab.classList.remove('rm-hidden');
		document.getElementById('rm-edit-toolbar').classList.add('rm-hidden');

		// Remove welcome banner
		removeWelcomeBanner();

		// Deactivate all editable elements
		document.querySelectorAll('[data-rm-field]').forEach(function (el) {
			el.classList.remove('rm-editable', 'rm-editable-hover');
			var type = el.dataset.rmType;

			// Remove contenteditable
			const textEl = findTextNode(el);
			if (textEl) {
				textEl.contentEditable = 'false';
				textEl.removeAttribute('contenteditable');
			}

			// Remove image overlay and placeholder
			const overlay = el.querySelector('.rm-image-overlay');
			if (overlay) overlay.remove();
			const imgPh = el.querySelector('.rm-image-placeholder');
			if (imgPh) imgPh.remove();

			// Remove taxonomy/repeater edit button
			const editBtn = el.querySelector('.rm-tax-edit-btn');
			if (editBtn) editBtn.remove();

			// Remove ALL WYSIWYG toolbars inside el
			el.querySelectorAll('.rm-wysiwyg-toolbar').forEach(function (bar) { bar.remove(); });

			// Cleanup date fields (flatpickr)
			if (type === 'date') {
				if (el._rmFlatpickr) {
					el._rmFlatpickr.destroy();
					el._rmFlatpickr = null;
				}
				var dateInput = el.querySelector('.rm-date-input');
				if (dateInput) dateInput.remove();
				// Restore original text element visibility
				var dateTextEl = findTextNode(el);
				if (dateTextEl && dateTextEl.style) {
					dateTextEl.style.removeProperty('display');
				}
			}

			// Cleanup date range fields (flatpickr pair)
			if (type === 'date_range') {
				if (el._rmFlatpickrs) {
					el._rmFlatpickrs.forEach(function (fp) { fp.destroy(); });
					el._rmFlatpickrs = null;
				}
				var rangeContainer = el.querySelector('.rm-date-range-container');
				if (rangeContainer) rangeContainer.remove();
				var drTextEl = findTextNode(el);
				if (drTextEl && drTextEl.style) {
					drTextEl.style.removeProperty('display');
				}
			}

			// Cleanup number fields (input overlay)
			if (type === 'number') {
				var numInput = el.querySelector('.rm-number-input');
				if (numInput) numInput.remove();
				var numTextEl = findTextNode(el);
				if (numTextEl && numTextEl.style) {
					numTextEl.style.removeProperty('display');
				}
			}

			// Cleanup gallery overlay
			if (type === 'gallery') {
				var galOverlay = el.querySelector('.rm-gallery-overlay');
				if (galOverlay) galOverlay.remove();
			}

			// Re-inject placeholders for still-empty text fields (keep visible outside edit mode)
			if (type === 'text' || type === 'number' || type === 'textarea' || type === 'wysiwyg') {
				var fieldTextEl = findTextNode(el);
				var existingPh = el.querySelector('.rm-placeholder');
				var rawContent = fieldTextEl ? fieldTextEl.textContent.trim().replace(/\u00a0/g, '') : '';
				// If placeholder exists, strip its text from the raw content check
				if (existingPh) {
					rawContent = rawContent.replace(existingPh.textContent.trim(), '').trim();
				}
				if (!rawContent && fieldTextEl) {
					// Field is still empty — ensure placeholder is present
					if (!existingPh) {
						fieldTextEl.innerHTML = '';
						var ph = document.createElement('span');
						ph.className = 'rm-placeholder';
						ph.textContent = getPlaceholder(el.dataset.rmField);
						fieldTextEl.appendChild(ph);
					}
					el.classList.add('rm-empty-field');
					forceEmptyFieldVisible(el, fieldTextEl);
				} else {
					// Field has content — remove placeholder if present
					el.classList.remove('rm-empty-field');
					if (existingPh) existingPh.remove();
					cleanupForcedVisibility(el);
				}
			} else {
				// Non-text fields: clean up forced visibility and remove placeholders
				cleanupForcedVisibility(el);
				el.classList.remove('rm-empty-field');
				var placeholder = el.querySelector('.rm-placeholder');
				if (placeholder) placeholder.remove();
			}
		});

		// Remove ALL WYSIWYG toolbars document-wide (catches any orphaned siblings)
		document.querySelectorAll('.rm-wysiwyg-toolbar').forEach(function (bar) { bar.remove(); });

		// Remove all fallback editor cards
		document.querySelectorAll('.rm-fallback-editor').forEach(function (card) { card.remove(); });

		// Remove any open popups
		document.querySelectorAll('.rm-tax-popup, .rm-url-popup, .rm-repeater-popup, .rm-cpt-popup').forEach(function (p) {
			p.remove();
		});

		// Re-inject placeholders for still-empty taxonomy/repeater fields
		// (the cleanup above removes them, but we need them visible outside edit mode)
		showInitialPlaceholders();
	}

	// =========================================================================
	// TEXT FIELDS (contenteditable)
	// =========================================================================

	function setupTextEditable(el, fieldName) {
		const textEl = findTextNode(el);
		if (!textEl) return;

		const isEmpty = !textEl.textContent.trim();

		if (isEmpty) {
			textEl.textContent = '';
			el.classList.add('rm-empty-field');
			const ph = document.createElement('span');
			ph.className = 'rm-placeholder';
			ph.textContent = getPlaceholder(fieldName);
			textEl.appendChild(ph);
		}

		textEl.contentEditable = 'true';
		textEl.dataset.rmTextField = fieldName;

		textEl.addEventListener('focus', function () {
			// Remove placeholder on focus
			const ph = textEl.querySelector('.rm-placeholder');
			if (ph) {
				ph.remove();
				textEl.textContent = '';
				el.classList.remove('rm-empty-field');
			}
		});

		textEl.addEventListener('blur', function () {
			const val = textEl.textContent.trim();
			if (!val) {
				el.classList.add('rm-empty-field');
				const ph = document.createElement('span');
				ph.className = 'rm-placeholder';
				ph.textContent = getPlaceholder(fieldName);
				textEl.appendChild(ph);
			}
		});

		textEl.addEventListener('input', function () {
			markChanged(fieldName);
		});

		// Prevent Enter for single-line text fields
		textEl.addEventListener('keydown', function (e) {
			if (e.key === 'Enter') {
				e.preventDefault();
				textEl.blur();
			}
		});
	}

	function setupTextareaEditable(el, fieldName) {
		const textEl = findTextNode(el);
		if (!textEl) return;

		const isEmpty = !textEl.textContent.trim();

		if (isEmpty) {
			el.classList.add('rm-empty-field');
			textEl.innerHTML = '<span class="rm-placeholder">' + escapeHtml(getPlaceholder(fieldName)) + '</span>';
			forceEmptyFieldVisible(el, textEl);
		}

		textEl.contentEditable = 'true';
		textEl.dataset.rmTextField = fieldName;

		textEl.addEventListener('focus', function () {
			const ph = textEl.querySelector('.rm-placeholder');
			if (ph) {
				ph.remove();
				el.classList.remove('rm-empty-field');
			}
		});

		textEl.addEventListener('blur', function () {
			if (!textEl.textContent.trim()) {
				el.classList.add('rm-empty-field');
				textEl.innerHTML = '<span class="rm-placeholder">' + escapeHtml(getPlaceholder(fieldName)) + '</span>';
			}
		});

		textEl.addEventListener('input', function () {
			markChanged(fieldName);
		});
	}

	// =========================================================================
	// NUMBER FIELDS (input overlay instead of contenteditable)
	// =========================================================================

	function setupNumberEditable(el, fieldName) {
		var textEl = findTextNode(el);
		if (!textEl) return;

		// Extract numeric value from displayed text (e.g. "1 500 €" → "1500", "10 Slots Available" → "10")
		var displayText = textEl.textContent.trim();
		var numericValue = displayText.replace(/[^0-9.-]/g, '');

		// Create number input
		var input = document.createElement('input');
		input.type = 'number';
		input.className = 'rm-number-input';
		input.value = numericValue;
		var fieldConfig = config.fields[fieldName];
		if (fieldConfig.min !== undefined) input.min = fieldConfig.min;
		if (fieldConfig.max !== undefined) input.max = fieldConfig.max;
		input.placeholder = getPlaceholder(fieldName);

		// Hide original text, insert input in same container for inline alignment
		textEl.style.display = 'none';
		textEl.parentNode.insertBefore(input, textEl.nextSibling);

		input.addEventListener('input', function () {
			changedFields[fieldName] = input.value;
			markChanged(fieldName);
		});
	}

	// =========================================================================
	// WYSIWYG FIELDS (contenteditable with formatting toolbar)
	// =========================================================================

	function setupWysiwygEditable(el, fieldName) {
		const textEl = findTextNode(el);
		if (!textEl) return;

		// Treat &nbsp; (\u00a0) as empty — the PHP filter injects &nbsp; for
		// empty WYSIWYG fields so Elementor always renders the widget.
		var rawText = textEl.textContent.trim().replace(/\u00a0/g, '');
		var isEmpty = !rawText;

		if (isEmpty) {
			// Clear any &nbsp; residue and show placeholder
			textEl.innerHTML = '';
			el.classList.add('rm-empty-field');
			var ph = document.createElement('span');
			ph.className = 'rm-placeholder';
			ph.textContent = getPlaceholder(fieldName);
			textEl.appendChild(ph);

			// Force the widget container to be visible (Elementor may give it
			// minimal height when the dynamic content was just &nbsp;)
			forceEmptyFieldVisible(el, textEl);
		}

		// Make editable
		textEl.contentEditable = 'true';
		textEl.dataset.rmTextField = fieldName;

		// Remove any existing toolbar from previous edit sessions
		el.querySelectorAll('.rm-wysiwyg-toolbar').forEach(function (t) { t.remove(); });
		document.querySelectorAll('.rm-wysiwyg-toolbar[data-rm-toolbar-for="' + fieldName + '"]').forEach(function (t) { t.remove(); });

		// Create formatting toolbar
		var toolbar = createWysiwygToolbar(fieldName);

		// Insert toolbar BEFORE el (outside the Elementor widget) so it's never
		// clipped by overflow:hidden or restrictive Elementor container styling.
		el.parentNode.insertBefore(toolbar, el);

		// Toolbar button handlers
		bindWysiwygToolbar(toolbar, fieldName);

		// Focus/blur handlers
		textEl.addEventListener('focus', function () {
			var ph = textEl.querySelector('.rm-placeholder');
			if (ph) {
				ph.remove();
				textEl.innerHTML = '';
				el.classList.remove('rm-empty-field');
			}
		});

		textEl.addEventListener('blur', function () {
			var blurText = textEl.textContent.trim().replace(/\u00a0/g, '');
			if (!blurText) {
				el.classList.add('rm-empty-field');
				textEl.innerHTML = '<span class="rm-placeholder">' + escapeHtml(getPlaceholder(fieldName)) + '</span>';
			}
		});

		textEl.addEventListener('input', function () {
			markChanged(fieldName);
		});

		textEl.addEventListener('keyup', function () {
			updateWysiwygToolbarState(toolbar);
		});
		textEl.addEventListener('mouseup', function () {
			updateWysiwygToolbarState(toolbar);
		});
	}

	/**
	 * Create the toolbar HTML element for a WYSIWYG field.
	 */
	function createWysiwygToolbar(fieldName) {
		var toolbar = document.createElement('div');
		toolbar.className = 'rm-wysiwyg-toolbar';
		toolbar.dataset.rmToolbarFor = fieldName;
		toolbar.innerHTML =
			'<button type="button" data-cmd="bold" title="Bold"><b>B</b></button>' +
			'<button type="button" data-cmd="italic" title="Italic"><i>I</i></button>' +
			'<button type="button" data-cmd="underline" title="Underline"><u>U</u></button>' +
			'<span class="rm-wysiwyg-sep"></span>' +
			'<button type="button" data-cmd="insertUnorderedList" title="Bullet list">' +
				'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><circle cx="3" cy="6" r="1" fill="currentColor"/><circle cx="3" cy="12" r="1" fill="currentColor"/><circle cx="3" cy="18" r="1" fill="currentColor"/></svg>' +
			'</button>' +
			'<button type="button" data-cmd="insertOrderedList" title="Numbered list">' +
				'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="10" y1="6" x2="21" y2="6"/><line x1="10" y1="12" x2="21" y2="12"/><line x1="10" y1="18" x2="21" y2="18"/><text x="1" y="8" font-size="8" fill="currentColor" stroke="none">1</text><text x="1" y="14" font-size="8" fill="currentColor" stroke="none">2</text><text x="1" y="20" font-size="8" fill="currentColor" stroke="none">3</text></svg>' +
			'</button>';
		return toolbar;
	}

	/**
	 * Bind click handlers to a WYSIWYG toolbar's buttons.
	 */
	function bindWysiwygToolbar(toolbar, fieldName) {
		toolbar.querySelectorAll('button').forEach(function (btn) {
			btn.addEventListener('mousedown', function (e) { e.preventDefault(); });
			btn.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				document.execCommand(btn.dataset.cmd, false, null);
				markChanged(fieldName);
				updateWysiwygToolbarState(toolbar);
			});
		});
	}

	/**
	 * Create a standalone fallback editor card for an empty field.
	 * Inserts it directly inside the Elementor section card (after the heading)
	 * so it's always visible regardless of whether Elementor hides the widget.
	 */
	function createSingleFallbackEditor(el, fieldName) {
		var card = document.createElement('div');
		card.className = 'rm-fallback-editor rm-editable';
		card.dataset.rmFallbackFor = fieldName;

		var label = config.fields[fieldName].label || fieldName;
		var toolbar = createWysiwygToolbar(fieldName + '-fallback');

		var editArea = document.createElement('div');
		editArea.className = 'rm-fallback-editor-area';
		editArea.contentEditable = 'true';
		editArea.dataset.placeholder = getPlaceholder(fieldName);

		var labelEl = document.createElement('div');
		labelEl.className = 'rm-fallback-editor-label';
		labelEl.textContent = label;

		card.appendChild(labelEl);
		card.appendChild(toolbar);
		card.appendChild(editArea);

		// Insert strategy: walk up from el to find the nearest visible
		// Elementor section/container, and append the card inside it.
		var insertParent = el.closest('.e-con, .elementor-section, .elementor-widget-wrap, .elementor-column-wrap');
		if (insertParent) {
			insertParent.appendChild(card);
		} else {
			// Fallback: insert after el's highest visible ancestor
			var target = el;
			while (target.parentElement &&
				   target.parentElement !== document.body &&
				   target.parentElement.getBoundingClientRect().height > 0) {
				target = target.parentElement;
			}
			target.parentNode.insertBefore(card, target.nextSibling);
		}

		// Bind toolbar
		bindWysiwygToolbar(toolbar, fieldName);

		// Bind edit area
		editArea.addEventListener('input', function () {
			changedFields[fieldName] = editArea.innerHTML;
			markChanged(fieldName);
		});
		editArea.addEventListener('keyup', function () { updateWysiwygToolbarState(toolbar); });
		editArea.addEventListener('mouseup', function () { updateWysiwygToolbarState(toolbar); });
	}

	function updateWysiwygToolbarState(toolbar) {
		toolbar.querySelectorAll('button[data-cmd]').forEach(function (btn) {
			var cmd = btn.dataset.cmd;
			if (document.queryCommandState(cmd)) {
				btn.classList.add('rm-wysiwyg-active');
			} else {
				btn.classList.remove('rm-wysiwyg-active');
			}
		});
	}

	// =========================================================================
	// URL FIELDS (popup input - prevents link navigation)
	// =========================================================================

	function setupUrlEditable(el, fieldName) {
		// Only bind once (persists across edit mode toggles)
		if (el.dataset.rmUrlBound) return;
		el.dataset.rmUrlBound = '1';

		// Handle empty URL field - show placeholder
		addEmptyPlaceholder(el, fieldName);

		// Intercept all clicks in capture phase to prevent link navigation
		el.addEventListener('click', function (e) {
			if (!isEditMode) return;
			// Don't intercept clicks inside an open popup
			if (e.target.closest('.rm-url-popup')) return;

			e.preventDefault();
			e.stopPropagation();
			openUrlPopup(el, fieldName);
		}, true);
	}

	/**
	 * Add a placeholder to an empty field so it remains clickable.
	 */
	function addEmptyPlaceholder(el, fieldName) {
		var textEl = findTextNode(el);
		if (!textEl) return;

		var hasContent = false;
		var type = config.fields[fieldName].type;

		if (type === 'url') {
			var link = el.querySelector('a');
			hasContent = link && link.href && link.href !== '#' && !link.href.endsWith('/#') && link.textContent.trim();
			if (!hasContent) hasContent = !!textEl.textContent.trim();
		} else {
			hasContent = !!textEl.textContent.trim();
		}

		if (!hasContent) {
			el.classList.add('rm-empty-field');
			if (!el.querySelector('.rm-placeholder')) {
				var ph = document.createElement('span');
				ph.className = 'rm-placeholder';
				ph.textContent = getPlaceholder(fieldName);
				textEl.appendChild(ph);
			}
		}
	}

	function openUrlPopup(el, fieldName) {
		// Close any existing popups
		document.querySelectorAll('.rm-url-popup, .rm-tax-popup, .rm-repeater-popup, .rm-cpt-popup').forEach(function (p) {
			p.remove();
		});

		// Get current URL value
		var currentUrl = '';
		if (changedFields[fieldName] !== undefined) {
			currentUrl = changedFields[fieldName];
		} else {
			var link = el.querySelector('a');
			if (link && link.href && link.href !== '#' && !link.href.endsWith('/#')) {
				currentUrl = link.href;
			}
		}

		var popup = document.createElement('div');
		popup.className = 'rm-url-popup';
		popup.innerHTML =
			'<div class="rm-url-popup-header">' +
				'<span>' + (config.fields[fieldName].label || 'URL') + '</span>' +
				'<button type="button" class="rm-url-popup-close">&times;</button>' +
			'</div>' +
			'<div class="rm-url-popup-body">' +
				'<input type="url" class="rm-url-input" placeholder="' + escapeHtml(getPlaceholder(fieldName)) + '" />' +
			'</div>' +
			'<div class="rm-url-popup-footer">' +
				'<button type="button" class="rm-btn rm-btn-done">' + config.i18n.done + '</button>' +
			'</div>';

		el.style.position = 'relative';
		el.appendChild(popup);

		var input = popup.querySelector('.rm-url-input');
		input.value = currentUrl;
		input.focus();
		input.select();

		// AUTO-SAVE: store value on every keystroke so it's never lost
		input.addEventListener('input', function () {
			changedFields[fieldName] = input.value.trim();
			markChanged(fieldName);
		});

		// Prevent clicks inside popup from bubbling to parent handler
		popup.addEventListener('click', function (e) {
			e.stopPropagation();
		});

		// Close button - save current value before closing
		popup.querySelector('.rm-url-popup-close').addEventListener('click', function () {
			changedFields[fieldName] = input.value.trim();
			if (input.value.trim()) markChanged(fieldName);
			popup.remove();
		});

		// Done button
		popup.querySelector('.rm-btn-done').addEventListener('click', function () {
			changedFields[fieldName] = input.value.trim();
			markChanged(fieldName);
			popup.remove();
		});

		// Enter to confirm, Escape to close
		input.addEventListener('keydown', function (e) {
			e.stopPropagation();
			if (e.key === 'Enter') {
				popup.querySelector('.rm-btn-done').click();
			}
			if (e.key === 'Escape') {
				changedFields[fieldName] = input.value.trim();
				if (input.value.trim()) markChanged(fieldName);
				popup.remove();
			}
		});

		// Close on outside click - save current value first
		setTimeout(function () {
			document.addEventListener('click', function closeUrlPopup(e) {
				if (!popup.contains(e.target) && !el.contains(e.target)) {
					changedFields[fieldName] = input.value.trim();
					if (input.value.trim()) markChanged(fieldName);
					popup.remove();
					document.removeEventListener('click', closeUrlPopup);
				}
			});
		}, 100);
	}

	// =========================================================================
	// IMAGE FIELDS (WP Media Uploader)
	// =========================================================================

	function setupImageEditable(el, fieldName) {
		var img = el.querySelector('img');
		var hasBgImage = false;

		// Check for background-image
		if (!img) {
			var targets = [el].concat(Array.from(el.querySelectorAll('*')));
			for (var i = 0; i < targets.length; i++) {
				var bg = window.getComputedStyle(targets[i]).backgroundImage;
				if (bg && bg !== 'none' && bg.indexOf('url(') !== -1) {
					hasBgImage = true;
					break;
				}
			}
		}

		var hasImage = !!img || hasBgImage;

		if (hasImage) {
			// Image exists: show change overlay
			var overlay = document.createElement('div');
			overlay.className = 'rm-image-overlay';
			overlay.innerHTML =
				'<button type="button" class="rm-image-btn">' +
					'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
						'<path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>' +
					'</svg> ' +
					escapeHtml(config.i18n.changeImage) +
				'</button>';

			el.style.position = 'relative';
			el.appendChild(overlay);

			overlay.querySelector('.rm-image-btn').addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				openMediaUploader(el, fieldName);
			});
		} else {
			// No image: show placeholder zone
			var placeholder = document.createElement('div');
			placeholder.className = 'rm-image-placeholder';
			placeholder.innerHTML =
				'<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">' +
					'<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>' +
					'<circle cx="8.5" cy="8.5" r="1.5"/>' +
					'<polyline points="21 15 16 10 5 21"/>' +
				'</svg>' +
				'<span>' + escapeHtml(getPlaceholder(fieldName)) + '</span>';

			placeholder.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				openMediaUploader(el, fieldName);
			});

			el.style.position = 'relative';
			el.appendChild(placeholder);
			el.classList.add('rm-empty-field');
		}
	}

	function openMediaUploader(el, fieldName) {
		// Reuse existing frame to avoid duplicate event listeners
		if (mediaFrames[fieldName]) {
			mediaFrames[fieldName].open();
			return;
		}

		var frame = wp.media({
			title: config.fields[fieldName].label || config.i18n.selectImage,
			multiple: false,
			library: { type: 'image' },
			button: { text: config.i18n.selectImage },
		});

		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();

			if (!attachment || !attachment.id) {
				return;
			}

			var imgUrl = attachment.sizes && attachment.sizes.large
				? attachment.sizes.large.url
				: attachment.url;

			// Remove placeholder if present
			var imgPh = el.querySelector('.rm-image-placeholder');
			if (imgPh) imgPh.remove();
			el.classList.remove('rm-empty-field');

			// Update the image visually
			updateImageVisual(el, imgUrl);

			// Store the new attachment ID
			changedFields[fieldName] = attachment.id;
			markChanged(fieldName);
		});

		mediaFrames[fieldName] = frame;
		frame.open();
	}

	// =========================================================================
	// DATE FIELDS (flatpickr inline date picker)
	// =========================================================================

	function setupDateEditable(el, fieldName) {
		var currentValue = config.dates[fieldName] || '';
		if (changedFields[fieldName] !== undefined) {
			currentValue = changedFields[fieldName];
		}

		var textEl = findTextNode(el);

		// Create a date input alongside the existing text
		var input = document.createElement('input');
		input.type = 'text';
		input.className = 'rm-date-input';
		input.value = currentValue;
		input.placeholder = getPlaceholder(fieldName);

		// Hide the original text element
		if (textEl) {
			textEl.style.display = 'none';
		}

		el.appendChild(input);

		// Initialize flatpickr if available
		if (typeof flatpickr !== 'undefined') {
			var fp = flatpickr(input, {
				dateFormat: 'Y-m-d',
				altInput: true,
				altFormat: 'F j, Y',
				defaultDate: currentValue || null,
				onChange: function (selectedDates, dateStr) {
					changedFields[fieldName] = dateStr;
					markChanged(fieldName);
				},
			});
			el._rmFlatpickr = fp;
		} else {
			// Fallback: plain date input
			input.type = 'date';
			input.addEventListener('change', function () {
				changedFields[fieldName] = input.value;
				markChanged(fieldName);
			});
		}
	}

	// =========================================================================
	// DATE RANGE FIELDS (two flatpickr inputs for start + end date)
	// =========================================================================

	function setupDateRangeEditable(el, fieldName) {
		var currentDates = config.dates[fieldName] || { start: '', end: '' };
		if (changedFields[fieldName] !== undefined) {
			currentDates = changedFields[fieldName];
		}

		var textEl = findTextNode(el);

		// Create container with two date inputs
		var container = document.createElement('div');
		container.className = 'rm-date-range-container';

		var startLabel = document.createElement('label');
		startLabel.className = 'rm-date-range-label';
		startLabel.textContent = 'Start';
		var startInput = document.createElement('input');
		startInput.type = 'text';
		startInput.className = 'rm-date-input rm-date-start';
		startInput.value = currentDates.start || '';
		startInput.placeholder = 'Start date';

		var separator = document.createElement('span');
		separator.className = 'rm-date-range-sep';
		separator.textContent = '\u2192'; // →

		var endLabel = document.createElement('label');
		endLabel.className = 'rm-date-range-label';
		endLabel.textContent = 'End';
		var endInput = document.createElement('input');
		endInput.type = 'text';
		endInput.className = 'rm-date-input rm-date-end';
		endInput.value = currentDates.end || '';
		endInput.placeholder = 'End date';

		var startWrap = document.createElement('div');
		startWrap.className = 'rm-date-range-field';
		startWrap.appendChild(startLabel);
		startWrap.appendChild(startInput);

		var endWrap = document.createElement('div');
		endWrap.className = 'rm-date-range-field';
		endWrap.appendChild(endLabel);
		endWrap.appendChild(endInput);

		container.appendChild(startWrap);
		container.appendChild(separator);
		container.appendChild(endWrap);

		// Hide original text, insert container in same position for inline alignment
		if (textEl) {
			textEl.style.display = 'none';
			textEl.parentNode.insertBefore(container, textEl.nextSibling);
		} else {
			el.appendChild(container);
		}

		// Shared date values object
		var dateValues = {
			start: currentDates.start || '',
			end: currentDates.end || '',
		};

		// Initialize flatpickr if available
		if (typeof flatpickr !== 'undefined') {
			var fpStart = flatpickr(startInput, {
				dateFormat: 'Y-m-d',
				altInput: true,
				altFormat: 'F j, Y',
				defaultDate: dateValues.start || null,
				onChange: function (selectedDates, dateStr) {
					dateValues.start = dateStr;
					changedFields[fieldName] = { start: dateValues.start, end: dateValues.end };
					markChanged(fieldName);
				},
			});
			var fpEnd = flatpickr(endInput, {
				dateFormat: 'Y-m-d',
				altInput: true,
				altFormat: 'F j, Y',
				defaultDate: dateValues.end || null,
				onChange: function (selectedDates, dateStr) {
					dateValues.end = dateStr;
					changedFields[fieldName] = { start: dateValues.start, end: dateValues.end };
					markChanged(fieldName);
				},
			});
			el._rmFlatpickrs = [fpStart, fpEnd];
		} else {
			// Fallback: plain date inputs
			startInput.type = 'date';
			endInput.type = 'date';
			startInput.addEventListener('change', function () {
				dateValues.start = startInput.value;
				changedFields[fieldName] = { start: dateValues.start, end: dateValues.end };
				markChanged(fieldName);
			});
			endInput.addEventListener('change', function () {
				dateValues.end = endInput.value;
				changedFields[fieldName] = { start: dateValues.start, end: dateValues.end };
				markChanged(fieldName);
			});
		}
	}

	// =========================================================================
	// GALLERY FIELDS (WP Media Uploader - multiple images)
	// =========================================================================

	function setupGalleryEditable(el, fieldName) {
		var overlay = document.createElement('div');
		overlay.className = 'rm-gallery-overlay';
		overlay.innerHTML =
			'<button type="button" class="rm-gallery-edit-btn">' +
				'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
					'<path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>' +
				'</svg> ' +
				escapeHtml(config.i18n.editGallery || 'Edit Gallery') +
			'</button>';

		el.style.position = 'relative';
		el.appendChild(overlay);

		overlay.querySelector('.rm-gallery-edit-btn').addEventListener('click', function (e) {
			e.preventDefault();
			e.stopPropagation();
			openGalleryUploader(el, fieldName);
		});
	}

	function openGalleryUploader(el, fieldName) {
		// Reuse existing frame
		if (mediaFrames['gallery_' + fieldName]) {
			mediaFrames['gallery_' + fieldName].open();
			return;
		}

		var frame = wp.media({
			title: config.i18n.editGallery || 'Edit Gallery',
			multiple: 'add',
			library: { type: 'image' },
			button: { text: config.i18n.selectImages || 'Select Images' },
		});

		// Pre-select current gallery images when frame opens
		frame.on('open', function () {
			var selection = frame.state().get('selection');
			var currentImages = config.galleries[fieldName] || [];

			// If there are pending changes, use those IDs
			if (changedFields[fieldName] !== undefined) {
				var ids = String(changedFields[fieldName]).split(',').map(Number).filter(Boolean);
				ids.forEach(function (id) {
					var attachment = wp.media.attachment(id);
					attachment.fetch();
					selection.add(attachment);
				});
			} else {
				currentImages.forEach(function (img) {
					var attachment = wp.media.attachment(img.id);
					attachment.fetch();
					selection.add(attachment);
				});
			}
		});

		frame.on('select', function () {
			var attachments = frame.state().get('selection').toJSON();
			var ids = [];
			var urls = [];

			attachments.forEach(function (att) {
				ids.push(att.id);
				var url = att.sizes && att.sizes.large ? att.sizes.large.url : att.url;
				urls.push(url);
			});

			changedFields[fieldName] = ids.join(',');
			markChanged(fieldName);

			// Update gallery display
			updateGalleryVisual(el, urls);
		});

		mediaFrames['gallery_' + fieldName] = frame;
		frame.open();
	}

	/**
	 * Update gallery images in the DOM.
	 */
	function updateGalleryVisual(el, urls) {
		var imgs = el.querySelectorAll('img');
		urls.forEach(function (url, i) {
			if (imgs[i]) {
				imgs[i].src = url;
				imgs[i].srcset = '';
				if (imgs[i].dataset.lazySrc) imgs[i].dataset.lazySrc = url;
				if (imgs[i].dataset.src) imgs[i].dataset.src = url;
			}
		});
		// Remove extra images if the new selection has fewer
		for (var i = urls.length; i < imgs.length; i++) {
			imgs[i].closest('li, .woocommerce-product-gallery__image, .jet-woo-product-gallery__image-item')?.remove();
		}
	}

	// =========================================================================
	// REPEATER FIELDS (popup list editor)
	// =========================================================================

	function setupRepeaterEditable(el, fieldName) {
		// Show placeholder if empty
		var values = config.repeaters[fieldName] || [];
		var hasValues = changedFields[fieldName] !== undefined
			? (Array.isArray(changedFields[fieldName]) && changedFields[fieldName].length > 0)
			: values.length > 0;

		if (!hasValues) {
			addEmptyPlaceholder(el, fieldName);
		}

		const editBtn = document.createElement('button');
		editBtn.type = 'button';
		editBtn.className = 'rm-tax-edit-btn';
		editBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>';
		editBtn.addEventListener('click', function (e) {
			e.preventDefault();
			e.stopPropagation();
			openRepeaterPopup(el, fieldName);
		});

		el.style.position = 'relative';
		el.appendChild(editBtn);
	}

	function openRepeaterPopup(el, fieldName) {
		// Close any existing popups
		document.querySelectorAll('.rm-tax-popup, .rm-url-popup, .rm-repeater-popup, .rm-cpt-popup').forEach(function (p) {
			p.remove();
		});

		// Get current items: either from pending changes or from config
		// Config now provides flat strings: ['test', 'dfdgd']
		var currentItems;
		if (changedFields[fieldName] !== undefined) {
			currentItems = changedFields[fieldName];
		} else {
			var rawData = config.repeaters[fieldName] || [];
			currentItems = rawData.map(function (item) {
				return String(item || '');
			});
		}
		// Filter out empty strings
		currentItems = currentItems.filter(function (v) { return v !== ''; });

		var popup = document.createElement('div');
		popup.className = 'rm-repeater-popup';

		var itemsHtml = '';
		if (currentItems.length > 0) {
			currentItems.forEach(function (val, idx) {
				itemsHtml +=
					'<div class="rm-repeater-item" data-index="' + idx + '">' +
						'<span class="rm-repeater-num">' + (idx + 1) + '</span>' +
						'<input type="text" class="rm-repeater-input" value="' + escapeHtml(val) + '" />' +
						'<button type="button" class="rm-repeater-remove" title="Remove">&times;</button>' +
					'</div>';
			});
		}

		popup.innerHTML =
			'<div class="rm-repeater-popup-header">' +
				'<span>' + escapeHtml(config.fields[fieldName].label) + '</span>' +
				'<button type="button" class="rm-repeater-popup-close">&times;</button>' +
			'</div>' +
			'<div class="rm-repeater-popup-body">' +
				'<div class="rm-repeater-items">' + itemsHtml + '</div>' +
				'<button type="button" class="rm-repeater-add">' + config.i18n.addItem + '</button>' +
			'</div>' +
			'<div class="rm-repeater-popup-footer">' +
				'<button type="button" class="rm-btn rm-btn-done">' + config.i18n.done + '</button>' +
			'</div>';

		el.appendChild(popup);

		// Reposition if popup goes off-screen
		adjustPopupViewport(popup);

		// Bind remove buttons for existing items
		var itemsContainer = popup.querySelector('.rm-repeater-items');
		popup.querySelectorAll('.rm-repeater-remove').forEach(function (btn) {
			btn.addEventListener('click', function (e) {
				e.stopPropagation(); // Prevent outside-click handler from closing popup
				btn.closest('.rm-repeater-item').remove();
				renumberRepeaterItems(itemsContainer);
			});
		});

		// Add item button
		popup.querySelector('.rm-repeater-add').addEventListener('click', function () {
			var container = popup.querySelector('.rm-repeater-items');
			var count = container.querySelectorAll('.rm-repeater-item').length;
			var div = document.createElement('div');
			div.className = 'rm-repeater-item';
			div.innerHTML =
				'<span class="rm-repeater-num">' + (count + 1) + '</span>' +
				'<input type="text" class="rm-repeater-input" value="" />' +
				'<button type="button" class="rm-repeater-remove" title="Remove">&times;</button>';
			div.querySelector('.rm-repeater-remove').addEventListener('click', function (e) {
				e.stopPropagation(); // Prevent outside-click handler from closing popup
				div.remove();
				renumberRepeaterItems(container);
			});
			container.appendChild(div);
			div.querySelector('.rm-repeater-input').focus();
		});

		// Close button
		popup.querySelector('.rm-repeater-popup-close').addEventListener('click', function () {
			popup.remove();
		});

		// Done button - collect values and save
		popup.querySelector('.rm-btn-done').addEventListener('click', function () {
			var values = [];
			popup.querySelectorAll('.rm-repeater-input').forEach(function (input) {
				var val = String(input.value || '').trim();
				if (val) values.push(val);
			});
			changedFields[fieldName] = values;
			markChanged(fieldName);
			// Remove popup BEFORE updating display so findTextNode doesn't match popup elements
			popup.remove();
			updateRepeaterDisplay(el, fieldName, values);
		});

		// Close on click outside
		setTimeout(function () {
			document.addEventListener('click', function closePopup(e) {
				if (!popup.contains(e.target) && !e.target.closest('.rm-tax-edit-btn')) {
					popup.remove();
					document.removeEventListener('click', closePopup);
				}
			});
		}, 100);
	}

	function updateRepeaterDisplay(el, fieldName, values) {
		var textEl = findTextNode(el);
		if (!textEl) return;

		if (values.length > 0) {
			// Remove placeholder if present
			var ph = el.querySelector('.rm-placeholder');
			if (ph) ph.remove();

			// Try to match the original Elementor display format
			// If there was a <ul> list, keep using <ul>. Otherwise use comma-separated or list.
			var originalHtml = originalValues[fieldName] ? originalValues[fieldName].html : '';
			if (originalHtml.indexOf('<ul') !== -1 || originalHtml.indexOf('<li') !== -1) {
				textEl.innerHTML = '<ul>' + values.map(function (v) {
					return '<li>' + escapeHtml(v) + '</li>';
				}).join('') + '</ul>';
			} else {
				// Default: comma-separated display
				textEl.textContent = values.join(', ');
			}
			el.classList.remove('rm-empty-field');
		} else {
			textEl.textContent = '';
			el.classList.add('rm-empty-field');
		}
	}

	// =========================================================================
	// TAXONOMY FIELDS (checkbox popup)
	// =========================================================================

	function setupTaxonomyEditable(el, fieldName) {
		const taxonomy = config.fields[fieldName].taxonomy;
		if (!config.taxonomies[taxonomy]) return;

		// Show placeholder if no terms selected
		var selected = config.taxonomies[taxonomy].selected || [];
		if (selected.length === 0) {
			addEmptyPlaceholder(el, fieldName);
		}

		const editBtn = document.createElement('button');
		editBtn.type = 'button';
		editBtn.className = 'rm-tax-edit-btn';
		editBtn.innerHTML = `
			<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
				<path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>
			</svg>
		`;
		editBtn.addEventListener('click', function (e) {
			e.preventDefault();
			e.stopPropagation();
			openTaxonomyPopup(el, fieldName, taxonomy);
		});

		el.style.position = 'relative';
		el.appendChild(editBtn);
	}

	function openTaxonomyPopup(el, fieldName, taxonomy) {
		// Close any existing popup
		document.querySelectorAll('.rm-tax-popup, .rm-url-popup, .rm-repeater-popup, .rm-cpt-popup').forEach(function (p) {
			p.remove();
		});

		const taxData = config.taxonomies[taxonomy];
		if (!taxData) {
			console.warn('[RM Inline Edit] No taxonomy data for "' + taxonomy + '". Available:', Object.keys(config.taxonomies));
			return;
		}
		const currentSelected = changedFields[fieldName] || [...taxData.selected];

		const popup = document.createElement('div');
		popup.className = 'rm-tax-popup';

		let checkboxesHtml = '';
		if (taxData.terms.length === 0) {
			checkboxesHtml = '<div class="rm-tax-empty">No items available for "' + taxonomy + '"</div>';
		} else {
			taxData.terms.forEach(function (term) {
				const checked = currentSelected.indexOf(term.id) !== -1 ? 'checked' : '';
				checkboxesHtml += `
					<label class="rm-tax-option">
						<input type="checkbox" value="${term.id}" ${checked}>
						<span>${term.name}</span>
					</label>
				`;
			});
		}

		popup.innerHTML = `
			<div class="rm-tax-popup-header">
				<span>${config.fields[fieldName].label}</span>
				<button type="button" class="rm-tax-popup-close">&times;</button>
			</div>
			<div class="rm-tax-popup-body">
				${checkboxesHtml}
			</div>
			<div class="rm-tax-popup-footer">
				<button type="button" class="rm-btn rm-btn-done">${config.i18n.done}</button>
			</div>
		`;

		el.appendChild(popup);

		// Reposition if popup goes off-screen
		adjustPopupViewport(popup);

		// Close button
		popup.querySelector('.rm-tax-popup-close').addEventListener('click', function () {
			popup.remove();
		});

		// Done button
		popup.querySelector('.rm-btn-done').addEventListener('click', function () {
			const selected = [];
			const selectedNames = [];
			popup.querySelectorAll('input[type="checkbox"]:checked').forEach(function (cb) {
				selected.push(parseInt(cb.value));
				selectedNames.push(cb.parentElement.querySelector('span').textContent);
			});

			changedFields[fieldName] = selected;
			markChanged(fieldName);

			// Update displayed tags visually
			updateTaxonomyDisplay(el, fieldName, selectedNames);

			popup.remove();
		});

		// Close on click outside
		setTimeout(function () {
			document.addEventListener('click', function closePopup(e) {
				if (!popup.contains(e.target) && !e.target.closest('.rm-tax-edit-btn')) {
					popup.remove();
					document.removeEventListener('click', closePopup);
				}
			});
		}, 100);
	}

	function updateTaxonomyDisplay(el, fieldName, names) {
		// Try to find the text content element
		const textEl = findTextNode(el);
		if (textEl) {
			// Remove placeholder if present
			var ph = el.querySelector('.rm-placeholder');
			if (ph) ph.remove();

			if (names.length > 0) {
				textEl.textContent = names.join(', ');
				el.classList.remove('rm-empty-field');
			} else {
				textEl.textContent = '';
				el.classList.add('rm-empty-field');
			}
		}
	}

	// =========================================================================
	// CPT SELECT FIELDS (radio popup for JetEngine relation CPT)
	// =========================================================================

	function setupCptSelectEditable(el, fieldName) {
		var cptData = config.cptOptions && config.cptOptions[fieldName];
		if (!cptData) return;

		// Show placeholder if no selection
		if (!cptData.selected) {
			addEmptyPlaceholder(el, fieldName);
		}

		var editBtn = document.createElement('button');
		editBtn.type = 'button';
		editBtn.className = 'rm-tax-edit-btn';
		editBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>';
		editBtn.addEventListener('click', function (e) {
			e.preventDefault();
			e.stopPropagation();
			openCptSelectPopup(el, fieldName, editBtn);
		});

		el.style.position = 'relative';
		el.appendChild(editBtn);
	}

	function openCptSelectPopup(el, fieldName, triggerBtn) {
		// Close any existing popups
		document.querySelectorAll('.rm-tax-popup, .rm-url-popup, .rm-repeater-popup, .rm-cpt-popup').forEach(function (p) {
			p.remove();
		});

		var cptData = config.cptOptions[fieldName];
		if (!cptData) return;

		var currentSelected = changedFields[fieldName] !== undefined
			? parseInt(changedFields[fieldName])
			: cptData.selected;

		var popup = document.createElement('div');
		popup.className = 'rm-cpt-popup';

		var optionsHtml = '';
		if (cptData.options.length === 0) {
			optionsHtml = '<div class="rm-tax-empty">No ' + (config.fields[fieldName].label || 'items') + ' available</div>';
		} else {
			cptData.options.forEach(function (opt) {
				var checked = opt.id === currentSelected ? 'checked' : '';
				optionsHtml +=
					'<label class="rm-cpt-option">' +
						'<input type="radio" name="rm-cpt-' + fieldName + '" value="' + opt.id + '" ' + checked + '>' +
						'<span>' + escapeHtml(opt.title) + '</span>' +
					'</label>';
			});
		}

		popup.innerHTML =
			'<div class="rm-tax-popup-header">' +
				'<span>' + escapeHtml(config.fields[fieldName].label) + '</span>' +
				'<button type="button" class="rm-tax-popup-close">&times;</button>' +
			'</div>' +
			'<div class="rm-tax-popup-body">' +
				optionsHtml +
			'</div>' +
			'<div class="rm-tax-popup-footer">' +
				'<button type="button" class="rm-btn rm-btn-done">' + config.i18n.done + '</button>' +
			'</div>';

		el.appendChild(popup);

		// Reposition if popup goes off-screen
		adjustPopupViewport(popup);

		// Close button
		popup.querySelector('.rm-tax-popup-close').addEventListener('click', function () {
			popup.remove();
		});

		// Done button
		popup.querySelector('.rm-btn-done').addEventListener('click', function () {
			var selected = popup.querySelector('input[type="radio"]:checked');
			if (selected) {
				var selectedId = parseInt(selected.value);
				var selectedName = selected.parentElement.querySelector('span').textContent;
				changedFields[fieldName] = selectedId;
				markChanged(fieldName);
				updateCptSelectDisplay(el, fieldName, selectedName);
			}
			popup.remove();
		});

		// Close on click outside
		setTimeout(function () {
			document.addEventListener('click', function closePopup(e) {
				if (!popup.contains(e.target) && !e.target.closest('.rm-tax-edit-btn')) {
					popup.remove();
					document.removeEventListener('click', closePopup);
				}
			});
		}, 100);
	}

	function updateCptSelectDisplay(el, fieldName, name) {
		var textEl = findTextNode(el);
		if (!textEl) return;

		var ph = el.querySelector('.rm-placeholder');
		if (ph) ph.remove();

		if (name) {
			textEl.textContent = name;
			el.classList.remove('rm-empty-field');
		} else {
			textEl.textContent = '';
			el.classList.add('rm-empty-field');
		}
	}

	// =========================================================================
	// SAVE / CANCEL
	// =========================================================================

	function saveChanges() {
		// Close any open popups first and capture their values
		document.querySelectorAll('.rm-url-popup').forEach(function (p) {
			var input = p.querySelector('.rm-url-input');
			var parentEl = p.closest('[data-rm-field]');
			if (input && parentEl) {
				var fn = parentEl.dataset.rmField;
				changedFields[fn] = input.value.trim();
			}
			p.remove();
		});

		if (!hasChanges) {
			exitEditMode();
			return;
		}

		const saveBtn = document.getElementById('rm-btn-save');
		saveBtn.disabled = true;
		saveBtn.textContent = config.i18n.saving;
		setStatus(config.i18n.saving);

		// Collect values from editable elements
		const fieldsToSave = {};

		document.querySelectorAll('[data-rm-field]').forEach(function (el) {
			const fieldName = el.dataset.rmField;
			const type = el.dataset.rmType;

			// Check if this field has pending changes stored (images, taxonomies, urls, repeaters, dates, galleries)
			if (changedFields[fieldName] !== undefined) {
				fieldsToSave[fieldName] = changedFields[fieldName];
				return;
			}

			// For number fields, get value from the number input
			if (type === 'number') {
				var numInput = el.querySelector('.rm-number-input');
				if (numInput) {
					var numVal = numInput.value.trim();
					var origText = originalValues[fieldName] ? originalValues[fieldName].text : '';
					var origNum = origText.replace(/[^0-9.-]/g, '');
					if (numVal !== origNum) {
						fieldsToSave[fieldName] = numVal;
					}
				}
				return;
			}

			// For text/textarea fields, get the current contenteditable value
			if (type === 'text' || type === 'textarea') {
				const textEl = findTextNode(el);
				if (!textEl) return;

				// Skip if placeholder is showing
				if (textEl.querySelector('.rm-placeholder')) {
					fieldsToSave[fieldName] = '';
					return;
				}

				let value;
				if (type === 'textarea') {
					value = htmlToText(textEl.innerHTML);
				} else {
					value = textEl.textContent.trim();
				}

				// Only include if actually changed
				const orig = originalValues[fieldName] ? originalValues[fieldName].text : '';
				if (value !== orig) {
					fieldsToSave[fieldName] = value;
				}
			}

			// For wysiwyg fields, send HTML content
			if (type === 'wysiwyg') {
				const textEl = findTextNode(el);
				if (!textEl) return;

				if (textEl.querySelector('.rm-placeholder')) {
					fieldsToSave[fieldName] = '';
					return;
				}

				// Clone the node so we can safely strip any plugin UI elements
				var clone = textEl.cloneNode(true);
				// Remove any toolbar or plugin elements that might have leaked in
				clone.querySelectorAll('.rm-wysiwyg-toolbar, .rm-placeholder').forEach(function (n) { n.remove(); });
				var htmlValue = clone.innerHTML;

				// Strip &nbsp; from comparison (PHP filter injects it for empty fields)
				var currentText = textEl.textContent.trim().replace(/\u00a0/g, '');
				const orig = originalValues[fieldName] ? originalValues[fieldName].text : '';
				if (currentText !== orig) {
					fieldsToSave[fieldName] = htmlValue;
				}
			}
		});

		if (Object.keys(fieldsToSave).length === 0) {
			saveBtn.disabled = false;
			saveBtn.textContent = config.i18n.save;
			exitEditMode();
			return;
		}

		$.ajax({
			url: config.ajaxUrl,
			type: 'POST',
			data: {
				action: 'rm_inline_save',
				nonce: config.nonce,
				post_id: config.postId,
				context: config.context || 'coach',
				fields: fieldsToSave,
			},
			success: function (response) {
				console.log('[RM Inline Edit] Server response:', response);
				saveBtn.disabled = false;
				saveBtn.textContent = config.i18n.save;

				if (!response || typeof response !== 'object') {
					console.error('[RM Inline Edit] Invalid response (not JSON):', response);
					setStatus(config.i18n.error, 'error');
					return;
				}

				if (response.success) {
					setStatus(config.i18n.saved, 'success');

					// Show errors for individual fields if any
					if (response.data && response.data.errors && Object.keys(response.data.errors).length > 0) {
						const errorMessages = Object.values(response.data.errors).join('\n');
						console.warn('[RM Inline Edit] Field errors:', response.data.errors);
						setStatus(errorMessages, 'error');
						return;
					}

					// Remove welcome banner after successful save
					removeWelcomeBanner();

					// Reload page after short delay so template styles render properly
					setTimeout(function () {
						// Reset flags so beforeunload doesn't trigger confirmation popup
						isEditMode = false;
						hasChanges = false;
						window.location.reload();
					}, 800);
				} else {
					var msg = config.i18n.error;
					if (response.data) {
						if (response.data.errors) {
							msg = Object.values(response.data.errors).join(', ');
						} else if (typeof response.data === 'string') {
							msg = response.data;
						}
					}
					console.error('[RM Inline Edit] Save failed:', response.data);
					setStatus(msg, 'error');
				}
			},
			error: function (xhr, status, error) {
				console.error('[RM Inline Edit] AJAX error:', status, error);
				if (xhr.responseText) {
					console.error('[RM Inline Edit] Response body:', xhr.responseText.substring(0, 500));
				}
				saveBtn.disabled = false;
				saveBtn.textContent = config.i18n.save;
				setStatus(config.i18n.error, 'error');
			},
		});
	}

	function cancelChanges() {
		// Detach FABs before restoring innerHTML — they live inside editable
		// containers and would be destroyed by the restore.
		var fab = document.getElementById('rm-edit-fab');
		if (fab) fab.remove();
		var galleryFab = document.querySelector('.rm-edit-fab--in-gallery');
		if (galleryFab) galleryFab.remove();

		// Destroy flatpickr instances before restoring HTML
		document.querySelectorAll('[data-rm-field]').forEach(function (el) {
			if (el._rmFlatpickr) {
				el._rmFlatpickr.destroy();
				el._rmFlatpickr = null;
			}
			if (el._rmFlatpickrs) {
				el._rmFlatpickrs.forEach(function (fp) { fp.destroy(); });
				el._rmFlatpickrs = null;
			}
		});

		// Restore each element to its OWN original HTML (per-element, not
		// per-fieldName).  This prevents cross-contamination when multiple
		// elements share the same rm-edit-{field} class.
		document.querySelectorAll('[data-rm-field]').forEach(function (el) {
			if (el._rmOriginalHtml !== undefined) {
				// Gallery fields: DON'T restore innerHTML — it destroys third-party
				// lightbox event listeners (WooCommerce/JetWoo "+X photos" overlay).
				// Instead, remove our overlay and restore image sources if changed.
				if (el.dataset.rmType === 'gallery') {
					var galOverlay = el.querySelector('.rm-gallery-overlay');
					if (galOverlay) galOverlay.remove();
					// Restore original images if they were changed during this session
					var fieldName = el.dataset.rmField;
					if (changedFields[fieldName] !== undefined && originalValues[fieldName] && originalValues[fieldName].images) {
						var imgs = el.querySelectorAll('img');
						originalValues[fieldName].images.forEach(function (origImg, i) {
							if (imgs[i]) {
								imgs[i].src = origImg.url;
								imgs[i].srcset = '';
							}
						});
					}
					return;
				}
				el.innerHTML = el._rmOriginalHtml;
			}
		});

		// Re-attach main FAB
		if (fab) {
			var coverContainer = document.querySelector('.rm-edit-coach_cover_photo');
			if (coverContainer) {
				coverContainer.style.position = 'relative';
				coverContainer.appendChild(fab);
			} else {
				document.body.appendChild(fab);
			}
		}

		// Re-attach gallery FAB (was destroyed by innerHTML restore)
		if (galleryFab) {
			var gc = document.querySelector('.rm-edit-camp_gallery');
			if (gc) {
				gc.style.position = 'relative';
				gc.appendChild(galleryFab);
			}
		}

		exitEditMode();
	}

	// =========================================================================
	// APPLY SERVER UPDATES TO DOM (avoids page reload / caching issues)
	// =========================================================================

	/**
	 * After a successful save, update the DOM and internal config
	 * with the server-confirmed values. No page reload needed.
	 */
	function applyServerUpdates(updated) {
		Object.keys(updated).forEach(function (fieldName) {
			var el = document.querySelector('[data-rm-field="' + fieldName + '"]');
			if (!el) return;

			var type = el.dataset.rmType;
			var value = updated[fieldName];

			if (type === 'url') {
				var link = el.querySelector('a');
				if (link) {
					link.href = value || '#';
					var linkText = link.textContent.trim();
					if (linkText.startsWith('http') || linkText === '' || linkText === '#') {
						link.textContent = value || '';
					}
				} else {
					var textEl = findTextNode(el);
					if (textEl) {
						textEl.textContent = value || '';
					}
				}
			}

			if (type === 'text') {
				var textEl = findTextNode(el);
				if (textEl && value) {
					textEl.textContent = value;
				}
			}

			if (type === 'wysiwyg' || type === 'textarea') {
				var textEl = findTextNode(el);
				if (textEl && value) {
					textEl.innerHTML = value;
				}
			}

			if ((type === 'image' || type === 'featured_image') && value && typeof value === 'object') {
				if (value.url) {
					updateImageVisual(el, value.url);
				}
				config.images[fieldName] = { id: value.id, url: value.url };
			}

			if (type === 'gallery' && Array.isArray(value)) {
				var urls = value.map(function (img) { return img.url; });
				updateGalleryVisual(el, urls);
				config.galleries[fieldName] = value;
			}

			if (type === 'number' && value !== undefined) {
				var textEl = findTextNode(el);
				if (textEl) {
					textEl.textContent = String(value);
				}
			}

			if (type === 'date' && value) {
				config.dates[fieldName] = value;
				var textEl = findTextNode(el);
				if (textEl) {
					var d = new Date(value + 'T00:00:00');
					if (!isNaN(d.getTime())) {
						textEl.textContent = d.toLocaleDateString('en-US', {
							year: 'numeric',
							month: 'long',
							day: 'numeric',
						});
					} else {
						textEl.textContent = value;
					}
					textEl.style.removeProperty('display');
				}
			}

			if (type === 'date_range' && value && typeof value === 'object') {
				config.dates[fieldName] = value;
				var textEl = findTextNode(el);
				if (textEl) {
					var opts = { year: 'numeric', month: 'long', day: 'numeric' };
					var startDate = new Date(value.start + 'T00:00:00');
					var endDate = new Date(value.end + 'T00:00:00');
					if (!isNaN(startDate.getTime()) && !isNaN(endDate.getTime())) {
						textEl.textContent = startDate.toLocaleDateString('en-US', opts) + ' \u2014 ' + endDate.toLocaleDateString('en-US', opts);
					}
					textEl.style.removeProperty('display');
				}
			}

			if (type === 'repeater' && Array.isArray(value)) {
				// Server returns flat strings: ['val1', 'val2']
				updateRepeaterDisplay(el, fieldName, value);
				config.repeaters[fieldName] = value;
			}

			if (type === 'taxonomy' && Array.isArray(value)) {
				var taxonomy = config.fields[fieldName].taxonomy;
				if (config.taxonomies[taxonomy]) {
					config.taxonomies[taxonomy].selected = value.map(function (t) {
						return t.id;
					});
				}
			}

			if (type === 'cpt_select' && value && typeof value === 'object') {
				if (config.cptOptions && config.cptOptions[fieldName]) {
					config.cptOptions[fieldName].selected = value.id;
				}
				updateCptSelectDisplay(el, fieldName, value.title);
			}
		});

		// Re-store original values so Cancel works correctly next time
		document.querySelectorAll('[data-rm-field]').forEach(function (el) {
			storeOriginalValue(el, el.dataset.rmField);
		});
	}

	// =========================================================================
	// UTILITY FUNCTIONS
	// =========================================================================

	/**
	 * Re-number repeater items after adding/removing.
	 */
	function renumberRepeaterItems(container) {
		container.querySelectorAll('.rm-repeater-item').forEach(function (item, idx) {
			var num = item.querySelector('.rm-repeater-num');
			if (num) num.textContent = idx + 1;
		});
	}

	function markChanged(fieldName) {
		hasChanges = true;
		setStatus('');
	}

	/**
	 * Reposition a popup if it goes off-screen.
	 * Switches to fixed positioning anchored near the parent element (trigger context).
	 */
	function adjustPopupViewport(popup) {
		requestAnimationFrame(function () {
			if (!popup.parentElement) return;
			var rect = popup.getBoundingClientRect();
			var vw = window.innerWidth;
			var vh = window.innerHeight;
			var margin = 12;
			var toolbarH = 70; // bottom save/cancel toolbar

			var offRight = rect.right > vw - margin;
			var offLeft = rect.left < margin;
			var offBottom = rect.bottom > vh - toolbarH;

			if (!offRight && !offLeft && !offBottom) return;

			// Get parent (editable element) rect as anchor
			var anchorRect = popup.parentElement.getBoundingClientRect();
			var popupW = Math.min(rect.width || 300, vw - margin * 2);
			var popupH = rect.height || 200;

			// Switch to fixed positioning near the anchor
			popup.style.position = 'fixed';
			popup.style.zIndex = '10002';
			popup.style.maxHeight = (vh - toolbarH - margin * 2) + 'px';
			popup.style.overflowY = 'auto';
			popup.style.width = popupW + 'px';
			popup.style.transform = 'none';
			popup.style.marginTop = '0';

			// Vertical: prefer below anchor, flip above if needed
			var top = anchorRect.bottom + 8;
			if (top + popupH > vh - toolbarH) {
				top = Math.max(margin, anchorRect.top - popupH - 8);
			}
			popup.style.top = top + 'px';
			popup.style.bottom = 'auto';

			// Horizontal: align right edge with anchor right edge
			var right = vw - anchorRect.right;
			if (right < margin) right = margin;
			if (right + popupW > vw - margin) right = vw - popupW - margin;
			if (right < margin) right = margin;
			popup.style.right = right + 'px';
			popup.style.left = 'auto';
		});
	}

	/**
	 * Force an empty field to be visible by setting min-height/display
	 * on textEl and all ancestor elements up to (and including) el.
	 * Elementor often nests widgets in multiple containers that collapse
	 * to zero height when empty.
	 */
	function forceEmptyFieldVisible(el, textEl) {
		// Walk from textEl UP to el, forcing visibility
		var node = textEl;
		while (node) {
			node.style.setProperty('min-height', '3em', 'important');
			// Only override display if element is actually hidden
			var comp = window.getComputedStyle(node);
			if (comp.display === 'none') {
				node.style.setProperty('display', 'block', 'important');
			}
			if (comp.visibility === 'hidden') {
				node.style.setProperty('visibility', 'visible', 'important');
			}
			if (comp.overflow === 'hidden' && parseInt(comp.height) === 0) {
				node.style.setProperty('overflow', 'visible', 'important');
			}
			node.setAttribute('data-rm-forced-visible', '1');
			if (node === el) break;
			node = node.parentElement;
		}
		// Continue walking UP from el to parent Elementor wrappers (8 levels).
		node = el.parentElement;
		var levels = 0;
		while (node && levels < 8) {
			var comp = window.getComputedStyle(node);
			node.style.setProperty('min-height', '3em', 'important');
			if (comp.display === 'none') {
				node.style.setProperty('display', 'block', 'important');
			}
			if (comp.visibility === 'hidden') {
				node.style.setProperty('visibility', 'visible', 'important');
			}
			node.setAttribute('data-rm-forced-visible', '1');
			node = node.parentElement;
			levels++;
		}
	}

	/**
	 * Clean up forced visibility styles added by forceEmptyFieldVisible.
	 */
	function cleanupForcedVisibility(el) {
		function cleanNode(node) {
			node.style.removeProperty('min-height');
			node.style.removeProperty('display');
			node.style.removeProperty('visibility');
			node.style.removeProperty('overflow');
			node.removeAttribute('data-rm-forced-visible');
		}
		// Clean descendants
		el.querySelectorAll('[data-rm-forced-visible]').forEach(cleanNode);
		// Clean el itself
		if (el.hasAttribute('data-rm-forced-visible')) {
			cleanNode(el);
		}
		// Clean ancestors that were forced visible
		var node = el.parentElement;
		while (node) {
			if (node.hasAttribute('data-rm-forced-visible')) {
				cleanNode(node);
			} else {
				break;
			}
			node = node.parentElement;
		}
	}

	/**
	 * Update an image visually - handles both <img> tags and CSS background-image
	 * (Elementor containers can use background-image for cover photos).
	 */
	function updateImageVisual(el, imgUrl) {
		var img = el.querySelector('img');
		if (img) {
			img.src = imgUrl;
			img.srcset = '';
			if (img.dataset.lazySrc) img.dataset.lazySrc = imgUrl;
			if (img.dataset.src) img.dataset.src = imgUrl;
		}

		var targets = [el].concat(Array.from(el.querySelectorAll('*')));
		for (var i = 0; i < targets.length; i++) {
			var bg = window.getComputedStyle(targets[i]).backgroundImage;
			if (bg && bg !== 'none' && bg.indexOf('url(') !== -1) {
				targets[i].style.backgroundImage = 'url(' + imgUrl + ')';
			}
		}
	}

	function setStatus(message, type) {
		const status = document.getElementById('rm-toolbar-status');
		if (!status) return;
		status.textContent = message;
		status.className = 'rm-toolbar-status';
		if (type) status.classList.add('rm-status-' + type);
	}

	/**
	 * Escape HTML special characters for safe insertion.
	 */
	function escapeHtml(str) {
		if (!str) return '';
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	/**
	 * Convert innerHTML to plain text, preserving line breaks.
	 */
	function htmlToText(html) {
		let text = html
			.replace(/<br\s*\/?>/gi, '\n')
			.replace(/<\/div>/gi, '\n')
			.replace(/<\/p>/gi, '\n')
			.replace(/<[^>]+>/g, '')
			.replace(/&nbsp;/g, ' ')
			.replace(/&amp;/g, '&')
			.replace(/&lt;/g, '<')
			.replace(/&gt;/g, '>')
			.replace(/\n{3,}/g, '\n\n')
			.trim();
		return text;
	}

	function bindEvents() {
		// Warn about unsaved changes
		window.addEventListener('beforeunload', function (e) {
			if (isEditMode && hasChanges) {
				e.preventDefault();
				e.returnValue = config.i18n.unsavedChanges;
			}
		});

		// ESC key to cancel
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && isEditMode) {
				cancelChanges();
			}
		});
	}

	// =========================================================================
	// START
	// =========================================================================

	$(document).ready(function () {
		init();
	});
})(jQuery);

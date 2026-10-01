/* Keel's glue for pages styled with Deck.
 *
 * Deck's [data-deck-theme] button and Deck.theme() save the choice to
 * localStorage only. A signed-in Keel user's theme also lives on the server
 * (users.theme_preference, POST /admin/theme), so it follows them to their
 * other devices. Deck fires no event when the theme changes, so this watches
 * the attribute Deck writes.
 */
(() => {
	const root = document.documentElement;
	const authenticated = document.querySelector('meta[name="keel-authenticated"]')?.getAttribute('content') === '1';
	const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

	if (!authenticated || !csrfToken) {
		return;
	}

	new MutationObserver((records) => {
		const theme = root.getAttribute('data-theme');

		// deck.js re-applies the saved theme on DOMContentLoaded; an unchanged value is not a choice.
		if (records[0].oldValue === theme || (theme !== 'light' && theme !== 'dark')) {
			return;
		}

		fetch('/admin/theme', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'Accept': 'application/json',
				'X-CSRF-Token': csrfToken,
			},
			body: JSON.stringify({ theme }),
		}).catch(() => {
			// Keep the local choice even if saving it fails.
		});
	}).observe(root, { attributes: true, attributeFilter: ['data-theme'], attributeOldValue: true });
})();

/* Confirmation before a destructive form posts.
 *
 * Deck styles .modal as a native <dialog> and says to open it with showModal(), but ships
 * no wiring for one: drawers get data-deck-drawer in deck-extras.js, modals get nothing.
 * A form carrying data-confirm="<dialog id>" asks first when JavaScript is available. With
 * scripts off the form posts straight through, so the action still works — it just loses
 * the confirmation step.
 *
 * One dialog can serve many forms (a delete button per file): its confirm button carries an
 * empty data-confirm-submit, and the form that opened the dialog, with the button pressed in
 * it, is the one submitted.
 */
(() => {
	const opener = new WeakMap();

	document.addEventListener('submit', (event) => {
		const form = event.target.closest('form[data-confirm]');

		// A button marked data-skip-confirm posts without asking (Save note, beside Reject).
		if (!form || form.dataset.confirmed === 'true' || event.submitter?.hasAttribute('data-skip-confirm')) {
			return;
		}

		const dialog = document.getElementById(form.dataset.confirm);

		if (!dialog || typeof dialog.showModal !== 'function') {
			return;
		}

		event.preventDefault();
		opener.set(dialog, { form, submitter: event.submitter || null });
		dialog.showModal();
	});

	document.addEventListener('click', (event) => {
		event.target.closest('[data-modal-close]')?.closest('dialog')?.close();

		const confirmer = event.target.closest('[data-confirm-submit]');
		if (!confirmer) {
			return;
		}

		const dialog = confirmer.closest('dialog');
		const pending = dialog ? opener.get(dialog) : null;
		const form = confirmer.dataset.confirmSubmit ? document.getElementById(confirmer.dataset.confirmSubmit) : pending?.form;

		if (!form) {
			return;
		}

		form.dataset.confirmed = 'true';
		dialog?.close();
		const submitter = pending && pending.form === form ? pending.submitter : null;
		submitter ? form.requestSubmit(submitter) : form.requestSubmit();
	});
})();

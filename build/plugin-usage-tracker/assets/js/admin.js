/**
 * Plugin Usage Tracker admin helpers.
 */

document.addEventListener('DOMContentLoaded', () => {
	const busyButtons = document.querySelectorAll('.put-scan-form button');

	if (!busyButtons.length) {
		return;
	}

	busyButtons.forEach((button) => {
		button.addEventListener('click', () => {
			button.classList.add('is-busy');
			button.setAttribute('aria-busy', 'true');
		});
	});
});

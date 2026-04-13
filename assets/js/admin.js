/**
 * Plugin Usage Tracker admin helpers.
 */

document.addEventListener('DOMContentLoaded', () => {
	const scanButton = document.querySelector('.put-actions .button-primary');

	if (!scanButton) {
		return;
	}

	scanButton.addEventListener('click', () => {
		scanButton.classList.add('is-busy');
		scanButton.setAttribute('aria-busy', 'true');
	});
});

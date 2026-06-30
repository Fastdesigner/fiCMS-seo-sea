function seo__open_integrations(event) {
	if (event) {
		event.preventDefault();
		event.stopImmediatePropagation();
	}
	return typeof settings__open === 'function' ? settings__open('general-integrations') : false;
}

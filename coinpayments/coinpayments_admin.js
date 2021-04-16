document.addEventListener('DOMContentLoaded', function () {
	Array.prototype.forEach.call(document.querySelectorAll("[name=\"data[payment][payment_params][webhooks]\"]"), function (radio) {
		var hide = document.querySelector('[name="data[payment][payment_params][client_secret]"]').closest('tr');

		if (radio.checked) {
			if (radio.value == 0) {
				hide.style.display = 'none';
			}
		}

		radio.addEventListener('change', function (e) {
			if (e.target.value == 1) {
				hide.style.display = 'table-row';
			} else {
				hide.style.display = 'none';
			}
		});
	});
});

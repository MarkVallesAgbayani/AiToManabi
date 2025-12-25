
// Handle Download Invoice button click
document.addEventListener('DOMContentLoaded', function () {
	document.body.addEventListener('click', function (e) {
		if (e.target.classList.contains('download-invoice-btn')) {
			const paymentId = e.target.getAttribute('data-payment-id');
			const userId = e.target.getAttribute('data-user-id') || document.body.getAttribute('data-user-id');
			if (paymentId && userId) {
				window.location.href = `reports-payment.php?payment_id=${paymentId}&user_id=${userId}`;
			}
		}
	});
});

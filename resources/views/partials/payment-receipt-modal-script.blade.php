@if (session('payment_receipt') || session('booking_confirmed'))
    <script>
        (function () {
            var receiptModal = document.getElementById('paymentReceiptModal');
            var printBtn = document.getElementById('paymentReceiptPrintBtn');
            var printArea = document.getElementById('paymentReceiptPrintArea');

            function closeReceiptModal() {
                if (!receiptModal) return;
                receiptModal.classList.add('payment-receipt-hidden');
                receiptModal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('payment-receipt-open');
            }

            function openReceiptModal() {
                if (!receiptModal) return;
                receiptModal.classList.remove('payment-receipt-hidden');
                receiptModal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('payment-receipt-open');
                receiptModal.querySelector('.payment-receipt-btn--done')?.focus();
            }

            document.querySelectorAll('[data-receipt-close]').forEach(function (el) {
                el.addEventListener('click', function (e) {
                    e.preventDefault();
                    closeReceiptModal();
                });
            });

            printBtn?.addEventListener('click', function () {
                if (!printArea) return;
                var printWindow = window.open('', '_blank', 'width=420,height=720');
                if (!printWindow) return;
                printWindow.document.write('<!DOCTYPE html><html><head><title>Receipt</title><style>');
                printWindow.document.write('body{font-family:Arial,sans-serif;margin:24px;color:#1a2e1f;}');
                printWindow.document.write('h3{margin:0 0 4px;font-size:18px;}');
                printWindow.document.write('.sub{margin:0 0 16px;font-size:12px;color:#5a6771;}');
                printWindow.document.write('dl{margin:0;}');
                printWindow.document.write('.row{display:flex;justify-content:space-between;gap:12px;padding:6px 0;border-bottom:1px dashed #d8e3dc;font-size:13px;}');
                printWindow.document.write('.row dt{font-weight:600;margin:0;}');
                printWindow.document.write('.row dd{margin:0;text-align:right;font-weight:700;}');
                printWindow.document.write('.amount{font-size:16px;color:#124a2f;}');
                printWindow.document.write('.foot{margin-top:18px;font-size:12px;text-align:center;color:#5a6771;}');
                printWindow.document.write('</style></head><body>');
                printWindow.document.write(printArea.innerHTML);
                printWindow.document.write('</body></html>');
                printWindow.document.close();
                printWindow.focus();
                printWindow.print();
                printWindow.close();
            });

            document.addEventListener('keydown', function (e) {
                if (e.key !== 'Escape') return;
                if (receiptModal && !receiptModal.classList.contains('payment-receipt-hidden')) {
                    closeReceiptModal();
                }
            });

            if (receiptModal) {
                openReceiptModal();
            }
        })();
    </script>
@endif

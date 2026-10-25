document.addEventListener('DOMContentLoaded', () => {
    const money = (value) => `₱${Number(value).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
    const cents = (value) => Math.round((value + Number.EPSILON) * 100) / 100;

    document.querySelectorAll('[data-pos-payment-form]').forEach((form) => {
        const subtotal = Number(form.dataset.subtotal);
        const discountType = form.querySelector('[data-discount-type]');
        const customDiscount = form.querySelector('[data-custom-discount]');
        const discountRate = form.querySelector('[data-discount-rate]');
        const idFields = form.querySelector('[data-discount-id-fields]');
        const cardholder = form.querySelector('[name="discount_cardholder_name"]');
        const idNumber = form.querySelector('[name="discount_id_number"]');
        const paymentMethod = form.querySelector('[data-payment-method]');
        const cashPayment = form.querySelector('[data-cash-payment]');
        const cashTendered = form.querySelector('[data-cash-tendered]');
        const otherPayment = form.querySelector('[data-other-payment]');
        const paymentReference = form.querySelector('[data-payment-reference]');
        const paymentReferenceLabel = form.querySelector('[data-payment-reference-label]');
        const error = form.querySelector('[data-payment-error]');
        const vatAdded = form.dataset.vatMode === 'added';
        let calculatedTotal = vatAdded ? cents(subtotal + cents(subtotal * 0.12)) : subtotal;

        const update = () => {
            const type = discountType.value;
            const isCustom = type === 'CUSTOM';
            const hasIdDiscount = type === 'SC' || type === 'PWD';
            const isCash = paymentMethod.value === 'cash';
            let vatExemption = 0;
            let discount = 0;
            let vat = vatAdded ? cents(subtotal * 0.12) : cents(subtotal * 12 / 112);
            let total = vatAdded ? cents(subtotal + vat) : subtotal;

            customDiscount.hidden = !isCustom;
            discountRate.required = isCustom;
            idFields.hidden = !hasIdDiscount;
            cardholder.required = hasIdDiscount;
            idNumber.required = hasIdDiscount;
            cashPayment.hidden = !isCash;
            cashTendered.required = isCash;
            otherPayment.hidden = isCash;
            paymentReference.required = !isCash;
            paymentReferenceLabel.textContent = paymentMethod.value === 'card' ? 'Approval code' : 'GCash reference';

            if (!hasIdDiscount) {
                cardholder.value = '';
                idNumber.value = '';
                form.querySelector('[name="discount_tin"]').value = '';
            }

            if (!isCash) cashTendered.value = '';
            else paymentReference.value = '';

            if (hasIdDiscount) {
                if (vatAdded) {
                    discount = cents(subtotal * 0.2);
                    total = cents(subtotal - discount);
                } else {
                    const vatExclusive = cents(subtotal / 1.12);
                    vatExemption = cents(subtotal - vatExclusive);
                    discount = cents(vatExclusive * 0.2);
                    total = cents(vatExclusive - discount);
                }
                vat = 0;
            } else if (isCustom) {
                const rate = Math.min(100, Math.max(0, Number(discountRate.value) || 0));
                discount = cents(subtotal * rate / 100);
                const taxableAmount = cents(subtotal - discount);
                if (vatAdded) {
                    vat = cents(taxableAmount * 0.12);
                    total = cents(taxableAmount + vat);
                } else {
                    total = taxableAmount;
                    vat = cents(total * 12 / 112);
                }
            }

            calculatedTotal = total;
            form.querySelector('[data-vat-exemption-row]').hidden = vatExemption <= 0;
            form.querySelector('[data-vat-exemption]').textContent = `−${money(vatExemption)}`;
            form.querySelector('[data-summary-discount]').textContent = `−${money(discount)}`;
            form.querySelector('[data-summary-vat]').textContent = money(vat);
            form.querySelector('[data-summary-total]').textContent = money(total);

            const tender = Number(cashTendered.value) || 0;
            form.querySelector('[data-cash-change]').textContent = money(Math.max(0, tender - total));
            error.hidden = true;
            error.textContent = '';
        };

        form.addEventListener('input', update);
        form.addEventListener('change', update);
        form.addEventListener('click', (event) => {
            const quickTender = event.target.closest('[data-quick-tender]');

            if (!quickTender) return;

            cashTendered.value = quickTender.dataset.quickTender === 'exact'
                ? calculatedTotal.toFixed(2)
                : quickTender.dataset.quickTender;
            update();
        });
        form.addEventListener('submit', (event) => {
            update();

            if (!form.checkValidity()) {
                event.preventDefault();
                form.reportValidity();
                return;
            }

            if (calculatedTotal <= 0) {
                event.preventDefault();
                error.textContent = 'The total must be greater than zero.';
                error.hidden = false;
                return;
            }

            if (paymentMethod.value === 'cash' && cents(Number(cashTendered.value)) < calculatedTotal) {
                event.preventDefault();
                error.textContent = 'Cash tendered must be at least the order total.';
                error.hidden = false;
                cashTendered.focus();
                return;
            }

            const submitButton = form.querySelector('[type="submit"]');
            submitButton.disabled = true;
            submitButton.textContent = 'Recording payment…';
        });

        update();
    });

    const clock = document.querySelector('[data-pos-clock]');
    const updateClock = () => {
        if (clock) {
            clock.textContent = new Date().toLocaleTimeString('en-PH', {
                hour: 'numeric',
                minute: '2-digit',
                second: '2-digit',
            });
        }
    };

    document.querySelector('[data-print-receipt]')?.addEventListener('click', () => window.print());

    updateClock();
    window.setInterval(updateClock, 1000);
});

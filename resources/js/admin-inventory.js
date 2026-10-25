const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

async function postJson(url, body) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(body),
    });
    const data = await response.json();

    if (!response.ok) {
        throw new Error(data.message || 'The request could not be completed.');
    }

    return data;
}

function showScannerMessage(kind, message) {
    const output = document.getElementById('msg');
    if (!output) return;
    output.className = `admin-inventory-message ${kind}`;
    output.textContent = message;
}

function formDataAsObject(form) {
    return Object.fromEntries(new FormData(form).entries());
}

document.addEventListener('DOMContentLoaded', () => {
    const reorderDialog = document.getElementById('reorderDialog');
    const reorderForm = document.getElementById('reorderForm');
    if (reorderDialog && reorderForm) {
        const urlTemplate = reorderForm.dataset.urlTemplate;
        document.querySelectorAll('[data-reorder-trigger]').forEach((row) => {
            const openDialog = () => {
                const ingredientId = row.dataset.ingredientId;
                const recommended = Number(row.dataset.recommended);
                const days = row.dataset.days === '—' ? 'Days left unavailable' : `${row.dataset.days} days left`;
                document.getElementById('reorderIngredientName').textContent = row.dataset.ingredientName;
                document.getElementById('reorderSummary').textContent =
                    `${row.dataset.stock} ${row.dataset.unit} on hand · min ${row.dataset.minimum} · ${days}`;
                document.getElementById('reorderQuantity').textContent = recommended > 0
                    ? `${recommended} ${row.dataset.unit}`
                    : 'No positive recommendation; set a minimum stock threshold first.';
                reorderForm.action = urlTemplate.replace('__INGREDIENT__', encodeURIComponent(ingredientId));
                const submit = reorderForm.querySelector('[type="submit"]');
                submit.disabled = recommended <= 0;
                reorderDialog.showModal();
            };

            row.addEventListener('click', (event) => {
                if (event.target instanceof Element && event.target.closest('a, button, input, select')) return;
                openDialog();
            });
            row.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter' && event.key !== ' ') return;
                event.preventDefault();
                openDialog();
            });
        });

        document.querySelectorAll('[data-close-reorder]').forEach((button) => {
            button.addEventListener('click', () => reorderDialog.close());
        });
    }

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.dataset.confirmMessage) return;
        if (!window.confirm(form.dataset.confirmMessage)) event.preventDefault();
    }, true);

    const lookupForm = document.getElementById('barcodeLookupForm');
    const receiptForm = document.getElementById('receiptForm');
    const matchedDelivery = document.getElementById('matchedDelivery');
    if (lookupForm && receiptForm && matchedDelivery) {
        const codeInput = document.getElementById('code');
        const scan = async (rawCode) => {
            const barcode = (rawCode || codeInput.value).trim();
            if (!barcode) {
                showScannerMessage('warn', 'Enter or scan a barcode first.');
                codeInput.focus();
                return;
            }

            document.dispatchEvent(new Event('inventory:camera-stop'));
            matchedDelivery.hidden = true;
            showScannerMessage('', 'Looking up the barcode and matching an open purchase order…');
            document.getElementById('manualLookup').disabled = true;

            try {
                const data = await postJson(lookupForm.dataset.lookupUrl, { barcode });
                const expected = data.purchase_order.remaining_quantity;
                document.getElementById('matchedIngredient').textContent = data.ingredient.name;
                document.getElementById('matchedPo').textContent = data.purchase_order.reference;
                document.getElementById('matchedSupplier').textContent = data.purchase_order.supplier;
                document.getElementById('matchedOrderSummary').textContent =
                    `Expected delivery: ${expected} ${data.ingredient.unit} remaining on this order.`;
                document.getElementById('matchedCurrentStock').textContent =
                    `${Number(data.current_stock).toFixed(3)} ${data.ingredient.unit}`;
                receiptForm.elements.barcode.value = data.barcode;
                receiptForm.elements.purchase_order_item_id.value = String(data.purchase_order_item_id);
                receiptForm.elements.quantity.value = Number(data.default_quantity).toFixed(3);
                receiptForm.elements.quantity.max = String(expected);
                receiptForm.elements.unit_cost.value = data.unit_cost ?? '';
                receiptForm.elements.batch_number.value = '';
                receiptForm.elements.expires_at.value = '';
                receiptForm.elements.storage_zone.value = data.storage_zone || '';
                matchedDelivery.hidden = false;
                showScannerMessage('ok', `Package found on ${data.purchase_order.reference}. Check the details, then confirm.`);
                document.getElementById('matchedDelivery').scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            } catch (error) {
                showScannerMessage('bad', error.message);
            } finally {
                document.getElementById('manualLookup').disabled = false;
            }
        };

        lookupForm.addEventListener('submit', (event) => {
            event.preventDefault();
            void scan();
        });
        codeInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                void scan();
            }
        });
        document.querySelectorAll('[data-barcode-sample]').forEach((button) => {
            button.addEventListener('click', () => {
                codeInput.value = button.dataset.barcodeSample;
                void scan(button.dataset.barcodeSample);
            });
        });
        document.addEventListener('inventory:barcode', (event) => {
            void scan(event.detail.value);
        });
        document.querySelector('[data-rescan]')?.addEventListener('click', () => {
            matchedDelivery.hidden = true;
            receiptForm.reset();
            codeInput.value = '';
            document.dispatchEvent(new Event('inventory:camera-stop'));
            showScannerMessage('', 'Stock changes only after you confirm.');
            codeInput.focus();
        });
        receiptForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const submit = receiptForm.querySelector('[type="submit"]');
            submit.disabled = true;

            try {
                const data = await postJson(receiptForm.dataset.receiveUrl, formDataAsObject(receiptForm));
                receiptForm.elements.receipt_token.value = data.next_receipt_token;
                matchedDelivery.hidden = true;
                codeInput.value = '';
                showScannerMessage('ok', `${data.message} ${data.ingredient}: ${Number(data.current_stock).toFixed(3)} → ${Number(data.expected_stock).toFixed(3)}.`);
                window.setTimeout(() => window.location.reload(), 1200);
            } catch (error) {
                showScannerMessage('bad', error.message);
            } finally {
                submit.disabled = false;
            }
        });
    }

    const labelDialog = document.getElementById('lotLabelDialog');
    if (labelDialog) {
        document.querySelectorAll('[data-print-lot]').forEach((button) => {
            button.addEventListener('click', () => {
                document.getElementById('labelIngredient').textContent = button.dataset.ingredient;
                document.getElementById('labelReceived').textContent = button.dataset.received;
                document.getElementById('labelBatch').textContent = button.dataset.batch;
                document.getElementById('labelExpiry').textContent = button.dataset.expiry;
                document.getElementById('labelBin').textContent = button.dataset.bin;
                document.getElementById('labelBarcode').textContent = `LOT ${button.dataset.batch}`;
                labelDialog.showModal();
            });
        });
        document.querySelectorAll('[data-close-label]').forEach((button) => {
            button.addEventListener('click', () => labelDialog.close());
        });
        document.getElementById('printLotLabel')?.addEventListener('click', () => window.print());
    }
});

(() => {
    const $ = (id) => document.getElementById(id);
    const app = $("inventoryApp");
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const number = (value, unit) => `${Number(value).toFixed(3)} ${unit}`;
    let selected = null;
    let receiptToken = null;
    let previewSequence = 0;
    let previewTimer = null;

    const setMessage = (kind, text) => {
        const message = $("msg");
        message.className = `msg ${kind}`;
        message.textContent = text;
    };

    const postJson = async (url, payload) => {
        const response = await fetch(url, {
            method: "POST",
            headers: {
                "Accept": "application/json",
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": csrf,
            },
            body: JSON.stringify(payload),
        });
        const data = await response.json();
        if (!response.ok) {
            const validationMessage = Object.values(data.errors || {}).flat()[0];
            throw new Error(validationMessage || data.message || `Request failed (${response.status}).`);
        }
        return data;
    };

    const newReceiptToken = () => {
        if (crypto.randomUUID) return crypto.randomUUID();
        const bytes = crypto.getRandomValues(new Uint8Array(16));
        bytes[6] = (bytes[6] & 0x0f) | 0x40;
        bytes[8] = (bytes[8] & 0x3f) | 0x80;
        const hex = [...bytes].map((value) => value.toString(16).padStart(2, "0")).join("");
        return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
    };

    const updatePreview = (data) => {
        if (selected === null) return;
        selected.quantity = Number(data.package_quantity);
        $("pCur").textContent = number(data.current_stock, selected.unit);
        $("pQtyDisplay").textContent = number(data.package_quantity, selected.unit);
        $("pAfter").textContent = number(data.expected_stock, selected.unit);
        $("confirm").disabled = false;
    };

    const requestPreview = async () => {
        if (selected === null) return;
        const sequence = ++previewSequence;
        const quantity = $("pQtyInput").value;
        $("confirm").disabled = true;

        try {
            const data = await postJson(app.dataset.previewUrl, {
                barcode: selected.barcode,
                quantity: quantity === "" ? null : Number(quantity),
            });
            if (sequence === previewSequence && selected !== null) {
                updatePreview(data);
                setMessage("ok", "Server preview updated. Stock changes only after you confirm.");
            }
        } catch (error) {
            if (sequence === previewSequence) {
                $("pQtyDisplay").textContent = "—";
                $("pAfter").textContent = "—";
                setMessage("warn", error.message);
            }
        }
    };

    const renderReceipt = (data) => {
        const quantity = data.package_quantity ?? data.package?.quantity ?? null;
        selected = {
            barcode: data.barcode,
            name: data.ingredient.name,
            unit: data.ingredient.unit,
            package: data.package,
            quantity,
        };
        receiptToken = newReceiptToken();
        $("pkg").classList.remove("hide");
        $("barcodeMapping").classList.add("hide");
        $("pName").textContent = data.package
            ? `${data.ingredient.name} · ${data.package.label}`
            : data.ingredient.name;
        $("pMeta").textContent = `Barcode ${data.barcode}`;
        $("pCur").textContent = number(data.current_stock, data.ingredient.unit);
        $("pQtyInput").readOnly = quantity !== null;
        $("pQtyInput").value = quantity === null ? "" : String(quantity);
        $("pQtyInput").placeholder = quantity === null ? "Enter quantity" : "";
        $("pQtyDisplay").textContent = quantity === null
            ? "Enter quantity to calculate"
            : number(quantity, data.ingredient.unit);
        $("pAfter").textContent = data.expected_stock === null
            ? "Preview required"
            : number(data.expected_stock, data.ingredient.unit);
        $("confirm").disabled = data.expected_stock === null;
        if (quantity === null) $("pQtyInput").focus();
    };

    const lookup = async (rawCode) => {
        const barcode = rawCode.trim();
        if (!barcode) {
            setMessage("warn", "Scan or enter a barcode first.");
            return;
        }

        document.dispatchEvent(new Event("inventory:camera-stop"));
        selected = null;
        receiptToken = null;
        previewSequence += 1;
        $("pkg").classList.add("hide");
        $("barcodeMapping").classList.add("hide");
        setMessage("", "Looking up the registered package…");

        try {
            const data = await postJson(app.dataset.lookupUrl, { barcode });
            $("code").value = "";
            renderReceipt(data);
            setMessage("ok", "Package found. Check the lot details, then confirm the receipt.");
        } catch (error) {
            if (error.message.includes("not registered")) {
                $("unmappedBarcode").textContent = `Scanned value: ${barcode}`;
                $("unmappedBarcodeValue").value = barcode;
                $("barcodeMapping").classList.remove("hide");
                setMessage("warn", `${error.message} Register it below if it belongs to an ingredient.`);
                return;
            }
            setMessage("bad", error.message);
        }
    };

    const addSampleButton = (data) => {
        const button = document.createElement("button");
        button.type = "button";
        button.className = "chip";
        button.dataset.code = data.barcode;
        button.dataset.barcode = data.barcode;
        button.dataset.samplePackage = "";
        button.textContent = `${data.package.label} · ${data.ingredient.name}`;
        $("chips").append(button);
        $("sample").disabled = false;
    };

    $("manual").addEventListener("submit", (event) => {
        event.preventDefault();
        lookup($("code").value);
    });

    $("chips").addEventListener("click", (event) => {
        const button = event.target.closest("button[data-code]");
        if (button) lookup(button.dataset.code);
    });

    $("sample").addEventListener("click", () => {
        const packageButton = $("chips").querySelector("button[data-sample-package]");
        if (packageButton) lookup(packageButton.dataset.code);
    });

    $("packageForm").addEventListener("submit", async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const payload = Object.fromEntries(new FormData(form).entries());
        payload.package_quantity = Number(payload.package_quantity);

        try {
            const data = await postJson(app.dataset.registerUrl, payload);
            addSampleButton(data);
            renderReceipt(data);
            setMessage("ok", `${data.message} Confirm only after entering the batch and expiry details.`);
            form.reset();
        } catch (error) {
            setMessage("bad", error.message);
        }
    });

    $("pQtyInput").addEventListener("input", () => {
        if (selected === null || selected.package !== null) return;
        window.clearTimeout(previewTimer);
        previewTimer = window.setTimeout(requestPreview, 250);
    });

    $("confirm").addEventListener("click", async () => {
        if (selected === null || receiptToken === null) return;
        if (!$("batchNumber").value.trim() || !$("expiresAt").value || !$("storageZone").value) {
            setMessage("warn", "Enter the batch number, expiry date, and storage zone before confirming.");
            return;
        }

        const button = $("confirm");
        button.disabled = true;
        button.textContent = "Recording receipt…";
        try {
            const data = await postJson(app.dataset.confirmUrl, {
                barcode: selected.barcode,
                quantity: $("pQtyInput").value === "" ? null : Number($("pQtyInput").value),
                receipt_token: receiptToken,
                batch_number: $("batchNumber").value.trim(),
                expires_at: $("expiresAt").value,
                storage_zone: $("storageZone").value,
            });
            setMessage("ok", `${data.message} ${data.ingredient}: ${number(data.current_stock, data.unit)} + ${number(data.package_quantity, data.unit)} = ${number(data.expected_stock, data.unit)}.`);
            selected = null;
            receiptToken = null;
            $("pkg").classList.add("hide");
            $("batchNumber").value = "";
            $("expiresAt").value = "";
            $("storageZone").value = "";
        } catch (error) {
            setMessage("bad", error.message);
        } finally {
            button.textContent = "Confirm receipt";
            button.disabled = selected === null;
        }
    });

    $("rescan").addEventListener("click", () => {
        selected = null;
        receiptToken = null;
        previewSequence += 1;
        $("pkg").classList.add("hide");
        $("code").focus();
        setMessage("", "Waiting for a barcode. Stock changes only after you confirm.");
    });

    $("tabs").addEventListener("click", (event) => {
        const button = event.target.closest("button[data-tab]");
        if (!button) return;
        document.dispatchEvent(new Event("inventory:camera-stop"));
        const url = new URL(window.location.href);
        if (button.dataset.tab === "rec") url.searchParams.delete("tab");
        else url.searchParams.set("tab", button.dataset.tab);
        window.history.replaceState(null, "", url);
        document.querySelectorAll("#tabs [role=tab]").forEach((tab) => {
            tab.setAttribute("aria-selected", String(tab === button));
        });
        document.querySelectorAll("[data-view]").forEach((view) => {
            view.classList.toggle("hide", view.dataset.view !== button.dataset.tab);
        });
    });

    const recipeLines = document.querySelector("[data-prep-recipe-lines]");
    if (recipeLines) {
        const syncRemoveButtons = () => {
            const rows = recipeLines.querySelectorAll("[data-prep-recipe-line]");
            rows.forEach((row) => {
                row.querySelector("[data-remove-prep-line]").disabled = rows.length === 1;
            });
        };
        recipeLines.addEventListener("click", (event) => {
            const remove = event.target.closest("[data-remove-prep-line]");
            if (remove && !remove.disabled) {
                remove.closest("[data-prep-recipe-line]").remove();
                syncRemoveButtons();
            }
        });
        document.querySelector("[data-add-prep-line]").addEventListener("click", () => {
            const template = recipeLines.querySelector("[data-prep-recipe-line]");
            const row = template.cloneNode(true);
            const index = recipeLines.querySelectorAll("[data-prep-recipe-line]").length;
            row.querySelector("select").name = `lines[${index}][ingredient_id]`;
            row.querySelector("select").selectedIndex = 0;
            row.querySelector("input").name = `lines[${index}][quantity]`;
            row.querySelector("input").value = "";
            recipeLines.append(row);
            syncRemoveButtons();
        });
        syncRemoveButtons();
    }

    const notificationToggle = document.querySelector("[data-notification-toggle]");
    const notificationMenu = $("inventoryNotifications");
    const setNotificationsOpen = (open) => {
        notificationMenu.hidden = !open;
        notificationToggle.setAttribute("aria-expanded", String(open));
    };
    notificationToggle.addEventListener("click", () => setNotificationsOpen(notificationMenu.hidden));
    document.addEventListener("click", (event) => {
        if (!event.target.closest(".notification-wrap")) setNotificationsOpen(false);
    });
    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape" && !notificationMenu.hidden) {
            setNotificationsOpen(false);
            notificationToggle.focus();
        }
    });

    document.addEventListener("inventory:barcode", (event) => lookup(event.detail.value));

    const initialTab = document.querySelector(`#tabs [data-tab="${app.dataset.initialTab}"]`);
    if (initialTab && initialTab.dataset.tab !== "rec") initialTab.click();
})();

(() => {
    const $ = (id) => document.getElementById(id);
    const indexUrl = $("inventoryApp").dataset.indexUrl;
    const ingredientRows = [...document.querySelectorAll("#invBody tr[data-id]")];
    const ingredients = ingredientRows.map((row) => ({
        id: row.dataset.id,
        name: row.dataset.name,
        code: row.dataset.code,
        barcode: row.dataset.barcode,
        url: row.dataset.url,
        stock: Number(row.dataset.current),
        unit: row.dataset.unit,
    }));
    const toast = (text) => {
        const element = $("toast");
        element.textContent = text;
        element.classList.add("on");
        window.setTimeout(() => element.classList.remove("on"), 2400);
    };
    const message = (kind, text) => {
        const element = $("msg");
        element.className = `msg ${kind}`;
        element.textContent = text;
    };

    let mode = "in";
    let selectedIngredient = null;

    const stopCamera = () => {
        document.dispatchEvent(new Event("inventory:camera-stop"));
    };

    const updatePreview = () => {
        if (selectedIngredient === null) return;

        const quantity = Number($("pQtyInput").value);
        const movement = mode === "in" ? quantity : -quantity;
        const after = selectedIngredient.stock + movement;
        const validQuantity = Number.isFinite(quantity) && quantity >= 0.001;

        $("movementQuantity").value = validQuantity ? String(quantity) : "";
        $("movementType").value = mode === "in" ? "stock_in" : "stock_out";
        $("pAfter").textContent = `${mode === "in" ? "Expected after receipt" : "Expected after stock out"}: ${Math.max(after, 0).toFixed(3)} ${selectedIngredient.unit}`;
        $("confirm").disabled = !validQuantity || (mode === "out" && after < 0);
        if (mode === "out" && validQuantity && after < 0) {
            message("bad", `Not enough ${selectedIngredient.name} in stock for that quantity.`);
        }
    };

    const showIngredient = (ingredient) => {
        selectedIngredient = ingredient;
        $("pkg").classList.remove("hide");
        $("pName").textContent = ingredient.name;
        $("pMeta").textContent = ingredient.barcode
            ? `Package barcode ${ingredient.barcode}`
            : `Internal code ${ingredient.code.toUpperCase()}`;
        $("pCur").textContent = `Current stock: ${ingredient.stock.toFixed(3)} ${ingredient.unit}`;
        $("pQtyLabel").textContent = mode === "in" ? "Quantity to receive" : "Quantity to remove";
        $("pUnit").textContent = ingredient.unit;
        $("pQtyInput").value = "1";
        $("confirmForm").action = ingredient.url;
        $("movementNotes").value = mode === "in" ? "Barcode receiving" : "Barcode stock out";
        $("confirm").textContent = mode === "in" ? "Confirm receipt" : "Confirm stock out";
        updatePreview();
        message("ok", "Ingredient found. Check the quantity, then confirm.");
    };

    const lookup = (raw) => {
        const code = raw.trim().replace(/\s+/g, "").toLowerCase();
        if (!code) {
            message("warn", "Scan an ingredient code or enter its exact name.");
            return;
        }

        let ingredient = ingredients.find((item) =>
            item.code === code
            || item.barcode === code
            || item.name === raw.trim().toLowerCase()
        );
        const codeMatch = code.match(/^ing-?0*(\d+)$/i);
        if (ingredient === undefined && codeMatch !== null) {
            ingredient = ingredients.find((item) => item.id === String(Number(codeMatch[1])));
        }

        if (ingredient === undefined) {
            selectedIngredient = null;
            $("pkg").classList.add("hide");
            stopCamera();
            $("unmappedBarcode").textContent = `Scanned value: ${raw.trim()}`;
            $("unmappedBarcodeValue").value = raw.trim();
            $("barcodeMapping").classList.remove("hide");
            message("warn", "This barcode is not assigned yet. Select the matching ingredient to register it for future scans.");
            return;
        }

        stopCamera();
        $("barcodeMapping").classList.add("hide");
        $("code").value = "";
        showIngredient(ingredient);
    };

    document.addEventListener("inventory:barcode", (event) => {
        lookup(event.detail.value);
    });

    $("tabs").addEventListener("click", (event) => {
        const button = event.target.closest("button[data-t]");
        if (button === null) return;

        mode = button.dataset.t;
        stopCamera();
        selectedIngredient = null;
        $("pkg").classList.add("hide");
        $("barcodeMapping").classList.add("hide");
        document.querySelectorAll("#tabs button").forEach((tab) => {
            tab.setAttribute("aria-selected", String(tab === button));
        });
        $("scanView").classList.toggle("hide", mode !== "in" && mode !== "out");
        $("countView").classList.toggle("hide", mode !== "count");
        $("logView").classList.toggle("hide", mode !== "log");
        $("invView").classList.toggle("hide", mode !== "inv");
        $("title").textContent = {
            in: "Barcode receiving",
            out: "Stock out",
            count: "Count and waste",
            log: "Stock movement history",
            inv: "Inventory",
        }[mode];
        $("crumb").textContent = `Passenger Seat / Inventory / ${$("title").textContent}`;
        $("scanTitle").textContent = mode === "out" ? "Scan ingredient to remove stock" : "Scan package";
        $("msg").className = "msg";
        $("msg").textContent = "Waiting for a barcode. Stock changes only after you confirm.";
        $("confirmForm").action = indexUrl;
    });

    const notificationToggle = document.querySelector("[data-notification-toggle]");
    const notificationMenu = $("inventoryNotifications");
    const setNotificationsOpen = (open) => {
        notificationMenu.hidden = !open;
        notificationToggle.setAttribute("aria-expanded", String(open));
    };

    notificationToggle.addEventListener("click", () => {
        setNotificationsOpen(notificationMenu.hidden);
    });
    document.addEventListener("click", (event) => {
        if (!event.target.closest(".notification-wrap")) setNotificationsOpen(false);
    });
    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape" && !notificationMenu.hidden) {
            setNotificationsOpen(false);
            notificationToggle.focus();
        }
    });

    $("manual").addEventListener("submit", (event) => {
        event.preventDefault();
        lookup($("code").value);
    });
    $("sample").addEventListener("click", () => {
        if (ingredients.length > 0) lookup(ingredients[0].code);
    });
    $("chips").addEventListener("click", (event) => {
        const button = event.target.closest("button[data-code]");
        if (button !== null) lookup(button.dataset.code);
    });
    $("pQtyInput").addEventListener("input", updatePreview);
    $("rescan").addEventListener("click", () => {
        selectedIngredient = null;
        $("pkg").classList.add("hide");
        $("code").focus();
        message("", "Waiting for a barcode. Stock changes only after you confirm.");
    });

    $("wasteIngredient").addEventListener("change", (event) => {
        const form = $("wasteForm");
        form.action = event.target.value || indexUrl;
    });
    $("wasteForm").addEventListener("submit", (event) => {
        if ($("wasteIngredient").value === "") {
            event.preventDefault();
            message("bad", "Choose an ingredient before recording waste.");
            $("wasteIngredient").focus();
        }
    });

    const statusFor = (row) => row.dataset.status;
    const filterInventory = () => {
        const query = $("iq").value.trim().toLowerCase();
        const status = $("is").value;
        ingredientRows.forEach((row) => {
            const matchesText = !query || `${row.dataset.name} ${row.dataset.code}`.includes(query);
            const matchesStatus = !status || statusFor(row) === status;
            row.classList.toggle("hide", !matchesText || !matchesStatus);
        });
    };
    $("iq").addEventListener("input", filterInventory);
    $("is").addEventListener("change", filterInventory);

    document.querySelectorAll("[data-counted]").forEach((input) => {
        input.addEventListener("input", () => {
            const row = input.closest("tr");
            const variance = Number(input.value) - Number(row.querySelector("[data-variance]").dataset.system);
            row.querySelector("[data-variance]").textContent = `${variance > 0 ? "+" : ""}${variance.toFixed(3)}`;
        });
    });

    $("lf").addEventListener("change", (event) => {
        document.querySelectorAll("#lBody tr[data-type]").forEach((row) => {
            row.classList.toggle("hide", event.target.value !== "" && row.dataset.type !== event.target.value);
        });
    });
    $("lPrint").addEventListener("click", () => window.print());
    $("lCsv").addEventListener("click", async () => {
        const visibleRows = [...document.querySelectorAll("#lBody tr[data-type]:not(.hide)")];
        const csvValue = (value) => `"${String(value).replaceAll('"', '""')}"`;
        const csv = [
            ["Time", "Staff", "Movement", "Ingredient", "Change", "Note"].map(csvValue).join(","),
            ...visibleRows.map((row) => [...row.cells].map((cell) => csvValue(cell.textContent.trim())).join(",")),
        ].join("\r\n");

        try {
            await navigator.clipboard.writeText(csv);
            toast("Visible movement history copied as CSV.");
        } catch (error) {
            toast(`Could not copy CSV: ${error.message}`);
        }
    });

})();

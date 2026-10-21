const video = document.getElementById("vid");
const button = document.getElementById("camBtn");
const frame = document.getElementById("frame");
const hint = document.getElementById("hint");
const status = document.getElementById("msg");
let stream = null;
let animationFrame = null;
let starting = false;
let generation = 0;

const showMessage = (kind, text) => {
    if (!status) return;
    status.className = status.classList.contains("admin-inventory-message")
        ? `admin-inventory-message ${kind}`
        : `msg ${kind}`;
    status.textContent = text;
};

const stopScanner = () => {
    generation += 1;
    if (animationFrame !== null) cancelAnimationFrame(animationFrame);
    animationFrame = null;
    stream?.getTracks().forEach((track) => track.stop());
    stream = null;
    video?.pause();
    if (video) {
        video.srcObject = null;
        video.classList.add("hide");
    }
    frame?.classList.remove("live");
    if (button) {
        button.disabled = false;
        button.textContent = button.dataset.startText || "Start camera";
    }
    if (hint) hint.textContent = "Point the rear camera at the package barcode";
};

const supportedBarcodeFormats = async () => {
    const candidates = ["ean_13", "ean_8", "upc_a", "upc_e", "code_128", "code_39", "qr_code"];
    if (typeof window.BarcodeDetector.getSupportedFormats !== "function") return candidates;
    const supported = await window.BarcodeDetector.getSupportedFormats();
    return candidates.filter((format) => supported.includes(format));
};

const scanFrame = async (detector) => {
    if (stream === null) return;
    const activeStream = stream;
    try {
        const barcodes = await detector.detect(video);
        if (stream !== activeStream) return;
        if (barcodes.length > 0 && barcodes[0].rawValue) {
            const value = barcodes[0].rawValue;
            stopScanner();
            document.dispatchEvent(new CustomEvent("inventory:barcode", { detail: { value } }));
            return;
        }
    } catch (error) {
        stopScanner();
        showMessage("bad", `Barcode scanning stopped: ${error.message}. Enter the code manually instead.`);
        return;
    }
    if (stream !== activeStream) return;
    animationFrame = requestAnimationFrame(() => scanFrame(detector));
};

const startScanner = async () => {
    if (!video || !button || !frame || !hint || !status) return;
    if (starting || stream !== null) {
        stopScanner();
        return;
    }
    if (typeof window.BarcodeDetector !== "function") {
        showMessage("warn", "This browser does not support native barcode scanning. Enter the barcode manually.");
        return;
    }
    if (!navigator.mediaDevices?.getUserMedia) {
        showMessage("warn", "Camera access requires HTTPS or localhost. Enter the barcode manually on this connection.");
        return;
    }

    starting = true;
    const activeGeneration = ++generation;
    button.disabled = true;
    showMessage("", "Requesting rear camera access…");

    try {
        const formats = await supportedBarcodeFormats();
        if (activeGeneration !== generation) return;
        if (formats.length === 0) {
            showMessage("warn", "This browser does not support the package barcode formats. Enter the barcode manually.");
            return;
        }
        const detector = new window.BarcodeDetector({ formats });
        const cameraStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: { ideal: "environment" } },
            audio: false,
        });
        if (activeGeneration !== generation) {
            cameraStream.getTracks().forEach((track) => track.stop());
            return;
        }
        stream = cameraStream;
        video.srcObject = stream;
        await video.play();
        video.classList.remove("hide");
        frame.classList.add("live");
        button.textContent = "Stop camera";
        hint.textContent = "Hold the package barcode inside the frame";
        showMessage("ok", "Camera is ready. Hold a package barcode inside the frame.");
        animationFrame = requestAnimationFrame(() => scanFrame(detector));
    } catch (error) {
        if (activeGeneration !== generation) return;
        stopScanner();
        const reason = error.name === "NotAllowedError"
            ? "Camera permission was denied."
            : error.name === "NotFoundError"
                ? "No camera was found on this device."
                : `Camera could not be started: ${error.message}`;
        showMessage("bad", `${reason} You can still enter the barcode manually.`);
    } finally {
        starting = false;
        button.disabled = false;
    }
};

button?.addEventListener("click", startScanner);
document.addEventListener("inventory:camera-stop", stopScanner);
window.addEventListener("pagehide", stopScanner);

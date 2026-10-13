import { BrowserMultiFormatReader } from "@zxing/browser";

const reader = new BrowserMultiFormatReader();
const video = document.getElementById("vid");
const button = document.getElementById("camBtn");
const frame = document.getElementById("frame");
const hint = document.getElementById("hint");
const status = document.getElementById("msg");
let controls = null;
let starting = false;
let startGeneration = 0;

const showMessage = (kind, text) => {
    status.className = `msg ${kind}`;
    status.textContent = text;
};

const stopScanner = () => {
    startGeneration += 1;
    controls?.stop();
    controls = null;
    video.pause();
    video.srcObject = null;
    video.classList.add("hide");
    frame.classList.remove("live");
    button.disabled = false;
    button.textContent = "Start camera";
    hint.textContent = "Point the rear camera at an ingredient barcode";
};

document.addEventListener("inventory:camera-stop", stopScanner);

button.addEventListener("click", async () => {
    if (controls !== null || starting) {
        stopScanner();
        return;
    }

    if (!navigator.mediaDevices?.getUserMedia) {
        showMessage("warn", "Camera access requires HTTPS or localhost. Use the manual code field on this connection.");
        return;
    }

    starting = true;
    const generation = ++startGeneration;
    button.disabled = true;
    showMessage("", "Requesting camera access…");

    try {
        const scannerControls = await reader.decodeFromVideoDevice(undefined, video, (result) => {
            if (result && typeof result.getText === "function") {
                document.dispatchEvent(new CustomEvent("inventory:barcode", {
                    detail: { value: result.getText() },
                }));
            }
        });
        if (generation !== startGeneration) {
            scannerControls.stop();
            return;
        }
        controls = scannerControls;
        video.classList.remove("hide");
        frame.classList.add("live");
        button.textContent = "Stop camera";
        hint.textContent = "Hold the barcode inside the frame";
        showMessage("ok", "Camera is ready. Hold an ingredient barcode inside the frame.");
    } catch (error) {
        stopScanner();
        const reason = error.name === "NotAllowedError"
            ? "Camera permission was denied."
            : error.name === "NotFoundError"
                ? "No camera was found on this device."
                : `Camera could not be started: ${error.message}`;
        showMessage("bad", `${reason} You can still enter the ingredient code manually.`);
    } finally {
        starting = false;
        button.disabled = false;
    }
});

window.addEventListener("pagehide", stopScanner);

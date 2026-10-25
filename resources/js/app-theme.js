const root = document.documentElement;
const themeButtons = [...document.querySelectorAll("[data-app-theme]")];

const applyTheme = (theme) => {
    root.dataset.theme = theme;
    root.style.colorScheme = theme;

    themeButtons.forEach((button) => {
        const nextTheme = theme === "dark" ? "light" : "dark";
        const label = nextTheme === "dark" ? "Switch to dark mode" : "Switch to light mode";
        button.setAttribute("aria-label", label);
        button.setAttribute("title", label);
        button.setAttribute("aria-pressed", String(theme === "dark"));
    });
};

applyTheme(root.dataset.theme === "dark" ? "dark" : "light");

document.addEventListener("click", (event) => {
    if (!event.target.closest("[data-app-theme]")) return;

    const nextTheme = root.dataset.theme === "dark" ? "light" : "dark";
    applyTheme(nextTheme);

    try {
        localStorage.setItem("ps-app-theme", nextTheme);
    } catch {
        const button = event.target.closest("[data-app-theme]");
        button?.setAttribute("title", `${button.getAttribute("aria-label")}; preference could not be saved`);
    }
});

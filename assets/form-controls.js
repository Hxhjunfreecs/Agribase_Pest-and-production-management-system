document.addEventListener("click", (event) => {
    const labels = {
        en: { show_password: "Show password", hide_password: "Hide password" },
        fil: { show_password: "Ipakita ang password", hide_password: "Itago ang password" },
        ceb: { show_password: "Ipakita ang password", hide_password: "Tagoa ang password" }
    };
    const toggle = event.target.closest("[data-password-toggle]");

    if (!toggle) {
        return;
    }

    const password = document.getElementById(toggle.dataset.passwordToggle);

    if (!(password instanceof HTMLInputElement)) {
        return;
    }

    const isVisible = password.type === "password";
    password.type = isVisible ? "text" : "password";

    const labelKey = isVisible ? "hide_password" : "show_password";
    toggle.dataset.i18nAria = labelKey;
    toggle.dataset.i18nTitle = labelKey;
    toggle.setAttribute("aria-pressed", String(isVisible));

    const language = document.documentElement.lang || "en";
    if (typeof window.applyAgriBaseLanguage === "function") {
        window.applyAgriBaseLanguage(language);
    }
    const label = (labels[language] || labels.en)[labelKey];
    toggle.setAttribute("aria-label", label);
    toggle.title = label;

    password.focus({ preventScroll: true });
});
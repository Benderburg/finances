// Runs before the application bundle, under the application's script-src 'self' policy.
try {
  document.documentElement.dataset.theme =
    localStorage.getItem("norocel-theme") || "system";
  const saved = localStorage.getItem("norocel-locale");
  const locale = ["ro", "ru", "en"].includes(saved) ? saved : "ro";
  document.documentElement.lang = locale;
  document.addEventListener(
    "DOMContentLoaded",
    () => {
      const label = { ro: "Se încarcă…", ru: "Загрузка…", en: "Loading…" }[
        locale
      ];
      document
        .querySelector("#root .logo-loader")
        ?.setAttribute("aria-label", label);
      const text = document.querySelector("#root .logo-loader__label");
      if (text) text.textContent = label;
    },
    { once: true },
  );
} catch {}

const STORAGE_KEY = "theme";

function getSystemTheme() {
  return window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
}

function getStoredTheme() {
  const value = localStorage.getItem(STORAGE_KEY);
  return value === "dark" || value === "light" ? value : null;
}

function getActiveTheme() {
  return getStoredTheme() ?? getSystemTheme();
}

function updateToggleUi(theme) {
  const isDark = theme === "dark";
  const label = isDark ? "Увімкнути світлу тему" : "Увімкнути темну тему";
  const iconClass = isDark ? "fa-sun" : "fa-moon";

  document.querySelectorAll("[data-theme-toggle]").forEach((button) => {
    button.setAttribute("aria-pressed", isDark ? "true" : "false");
    button.setAttribute("aria-label", label);
    const icon = button.querySelector("i");
    if (icon) {
      icon.className = `fa-solid ${iconClass}`;
    }
    const text = button.querySelector(".theme-toggle__text");
    if (text) {
      text.textContent = isDark ? "Світла тема" : "Темна тема";
    }
  });
}

export function applyTheme(theme, persist) {
  const root = document.documentElement;
  root.classList.remove("theme-light", "theme-dark");
  root.classList.add(theme === "dark" ? "theme-dark" : "theme-light");
  if (persist) {
    localStorage.setItem(STORAGE_KEY, theme);
  }
  updateToggleUi(theme);
}

export function toggleTheme() {
  const next = getActiveTheme() === "dark" ? "light" : "dark";
  applyTheme(next, true);
}

function mountThemeToggles() {
  const headerInner = document.querySelector(".site-header .header-inner");
  const navList = document.querySelector(".site-header .nav ul");
  if (!headerInner || !navList) {
    return;
  }

  if (document.querySelector("[data-theme-toggle]")) {
    updateToggleUi(getActiveTheme());
    return;
  }

  function createToggle(extraClass) {
    const button = document.createElement("button");
    button.type = "button";
    button.className = `theme-toggle ${extraClass}`.trim();
    button.setAttribute("data-theme-toggle", "");
    button.innerHTML =
      '<i class="fa-solid fa-moon" aria-hidden="true"></i><span class="theme-toggle__text">Темна тема</span>';
    button.addEventListener("click", () => toggleTheme());
    return button;
  }

  const barToggle = createToggle("theme-toggle--bar");
  const burger = headerInner.querySelector(".burger");
  if (burger) {
    headerInner.insertBefore(barToggle, burger);
  } else {
    headerInner.appendChild(barToggle);
  }

  const navItem = document.createElement("li");
  navItem.className = "theme-toggle-item theme-toggle-item--nav";
  navItem.appendChild(createToggle("theme-toggle--nav"));
  navList.appendChild(navItem);

  updateToggleUi(getActiveTheme());
}

function initTheme() {
  applyTheme(getActiveTheme(), false);
  mountThemeToggles();

  window.matchMedia("(prefers-color-scheme: dark)").addEventListener("change", (event) => {
    if (getStoredTheme() !== null) {
      return;
    }
    applyTheme(event.matches ? "dark" : "light", false);
  });
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", initTheme);
} else {
  initTheme();
}

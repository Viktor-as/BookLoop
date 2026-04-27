/** HTML-escape a string for safe interpolation into innerHTML templates. */
export function esc(s) {
    const d = document.createElement("div");
    d.textContent = String(s ?? "");
    return d.innerHTML;
}

export function getCookie(name) {
    const row = document.cookie
        .split("; ")
        .find((c) => c.startsWith(name + "="));
    if (!row) {
        return null;
    }
    return decodeURIComponent(row.slice(name.length + 1));
}

export function formatDateLabel(iso) {
    if (!iso || typeof iso !== "string") {
        return "—";
    }
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) {
        return "—";
    }
    return d.toLocaleDateString(undefined, {
        year: "numeric",
        month: "short",
        day: "numeric",
    });
}

export function detailHref(slug) {
    if (typeof slug === "string" && slug.length > 0) {
        return `/books/${encodeURIComponent(slug)}`;
    }
    return "#";
}

/**
 * Plain-text alert panel: toggles visibility via display and sets textContent (not HTML).
 * @param {HTMLElement} el
 * @returns {{ showError: (msg: string) => void, hideError: () => void }}
 */
export function bindErrorPanel(el) {
    return {
        showError(msg) {
            el.textContent = msg;
            el.style.display = "";
        },
        hideError() {
            el.textContent = "";
            el.style.display = "none";
        },
    };
}

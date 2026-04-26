const PER_PAGE = 25;
const API_URL = "/api/books/catalog";

function getPageFromUrl() {
    const p = new URLSearchParams(window.location.search).get("page");
    const n = p ? parseInt(p, 10) : 1;
    return Number.isFinite(n) && n >= 1 ? n : 1;
}

/** Filter params from the current location (normalized). */
function getFilterSearchParams() {
    const sp = new URLSearchParams(window.location.search);
    const out = new URLSearchParams();
    const q = (sp.get("q") || "").trim();
    if (q) {
        out.set("q", q);
    }
    const author = (sp.get("author") || "").trim();
    if (author) {
        out.set("author", author);
    }
    const categoryId = (sp.get("categoryId") || "").trim();
    if (categoryId) {
        out.set("categoryId", categoryId);
    }
    const av = sp.get("available");
    if (
        av !== null &&
        ["1", "true", "on"].includes(String(av).toLowerCase())
    ) {
        out.set("available", "1");
    }
    return out;
}

/** Browser URL query: filters + optional page (omit page when 1). */
function buildCatalogQuery(page) {
    const p = getFilterSearchParams();
    if (page > 1) {
        p.set("page", String(page));
    }
    return p;
}

function buildBrowserUrl(page) {
    const p = buildCatalogQuery(page);
    const qs = p.toString();
    return qs ? `${window.location.pathname}?${qs}` : window.location.pathname;
}

function buildCatalogApiUrl(page) {
    const p = getFilterSearchParams();
    p.set("page", String(page));
    p.set("perPage", String(PER_PAGE));
    return `${API_URL}?${p.toString()}`;
}

function isAvailableParamTrue(raw) {
    return (
        raw !== null &&
        ["1", "true", "on"].includes(String(raw).toLowerCase())
    );
}

function populateFormFromUrl() {
    const form = document.getElementById("catalog-filters");
    if (!form) {
        return;
    }
    const sp = new URLSearchParams(window.location.search);
    form.querySelector("#filter-q").value = sp.get("q") || "";
    form.querySelector("#filter-author").value = sp.get("author") || "";
    form.querySelector("#filter-category").value = sp.get("categoryId") || "";
    form.querySelector("#filter-available").checked = isAvailableParamTrue(
        sp.get("available"),
    );
}

function hasActiveFilters() {
    const p = getFilterSearchParams();
    return [...p.keys()].length > 0;
}

function pushBrowserUrl(page) {
    const url = buildBrowserUrl(page);
    window.history.pushState({ page }, "", url);
}

function esc(s) {
    const d = document.createElement("div");
    d.textContent = s;
    return d.innerHTML;
}

function renderList(items) {
    const list = document.getElementById("catalog-list");
    if (!items.length) {
        const msg = hasActiveFilters()
            ? "No books match your filters."
            : "No books in the catalog yet.";
        list.innerHTML = `<li class="catalog-empty">${esc(msg)}</li>`;
        return;
    }

    list.innerHTML = items
        .map((item) => {
            const authors = item.authors ?? "—";
            const categories = item.categories ?? "—";
            const statusClass = item.available ? "available" : "unavailable";
            const statusLabel = item.available
                ? "Available to borrow"
                : "All copies borrowed";

            const slug =
                typeof item.slug === "string" && item.slug.length > 0
                    ? item.slug
                    : "";
            const detailHref = slug
                ? `/books/${encodeURIComponent(slug)}`
                : "#";
            const actionLabel = item.available
                ? "Read more / Borrow"
                : "Read more";

            return `<li class="catalog-card">
            <h2>${esc(item.title)}</h2>
            <div class="catalog-meta">
                <div class="catalog-meta-item">
                    <span class="catalog-meta-label">Authors</span>
                    <span class="catalog-meta-value">${esc(authors)}</span>
                </div>
                <div class="catalog-meta-item">
                    <span class="catalog-meta-label">Categories</span>
                    <span class="catalog-meta-value">${esc(categories)}</span>
                </div>
                <div class="catalog-meta-item">
                    <span class="catalog-meta-label">Copies</span>
                    <span class="catalog-meta-value">${item.copiesTotal} total, ${item.activeBorrows} on loan</span>
                </div>
            </div>
            <div class="catalog-card-footer">
                <span class="catalog-status ${statusClass}">${esc(statusLabel)}</span>
                <a href="${detailHref}" class="catalog-card-action">${esc(actionLabel)}</a>
            </div>
        </li>`;
        })
        .join("");
}

function renderPagination(page, lastPage) {
    const root = document.getElementById("catalog-pagination");
    if (lastPage <= 1) {
        root.innerHTML = "";
        return;
    }

    const summary = `<span class="catalog-pagination-summary">Page ${page} of ${lastPage}</span>`;

    const pages = [];

    const windowSize = 5;
    let start = Math.max(1, page - Math.floor(windowSize / 2));
    let end = Math.min(lastPage, start + windowSize - 1);
    start = Math.max(1, end - windowSize + 1);

    if (start > 1) {
        pages.push(
            `<a href="${esc(buildBrowserUrl(1))}" data-page="1" class="catalog-pagination-num">1</a>`,
        );
        if (start > 2) {
            pages.push(
                '<span class="catalog-pagination-ellipsis" aria-hidden="true">…</span>',
            );
        }
    }

    for (let i = start; i <= end; ++i) {
        if (i === page) {
            pages.push(`<em class="catalog-pagination-current">${i}</em>`);
        } else {
            pages.push(
                `<a href="${esc(buildBrowserUrl(i))}" data-page="${i}" class="catalog-pagination-num">${i}</a>`,
            );
        }
    }

    if (end < lastPage) {
        if (end < lastPage - 1) {
            pages.push(
                '<span class="catalog-pagination-ellipsis" aria-hidden="true">…</span>',
            );
        }
        pages.push(
            `<a href="${esc(buildBrowserUrl(lastPage))}" data-page="${lastPage}" class="catalog-pagination-num">${lastPage}</a>`,
        );
    }

    const pagesHtml = `<div class="catalog-pagination-pages">${pages.join("")}</div>`;

    const prev =
        page > 1
            ? `<a href="${esc(buildBrowserUrl(page - 1))}" data-page="${page - 1}" rel="prev" class="catalog-pagination-edge">Prev</a>`
            : "";

    const next =
        page < lastPage
            ? `<a href="${esc(buildBrowserUrl(page + 1))}" data-page="${page + 1}" rel="next" class="catalog-pagination-edge">Next</a>`
            : "";

    root.innerHTML = `
        <div class="catalog-pagination-bar">
            ${summary}
            <div class="catalog-pagination-controls">
                ${prev}
                ${pagesHtml}
                ${next}
            </div>
        </div>
    `;
}

async function loadCatalog(page) {
    const loading = document.getElementById("catalog-loading");
    const errEl = document.getElementById("catalog-error");
    const list = document.getElementById("catalog-list");

    errEl.style.display = "none";
    errEl.textContent = "";
    loading.style.display = "block";
    list.innerHTML = "";

    const url = buildCatalogApiUrl(page);

    try {
        const res = await fetch(url, { credentials: "include" });
        const data = await res.json();

        if (!res.ok) {
            errEl.textContent = data.message ?? "Could not load catalog.";
            errEl.style.display = "block";
            loading.style.display = "none";
            document.getElementById("catalog-pagination").innerHTML = "";
            return;
        }

        renderList(data.items);
        renderPagination(data.page, data.lastPage);
        loading.style.display = "none";
    } catch {
        errEl.textContent = "Network error. Please try again.";
        errEl.style.display = "block";
        loading.style.display = "none";
        document.getElementById("catalog-pagination").innerHTML = "";
    }
}

function bindPagination() {
    document
        .getElementById("catalog-pagination")
        .addEventListener("click", (e) => {
            const a = e.target.closest("a[data-page]");
            if (!a) {
                return;
            }
            e.preventDefault();
            const page = parseInt(a.dataset.page, 10);
            if (!Number.isFinite(page) || page < 1) {
                return;
            }
            pushBrowserUrl(page);
            loadCatalog(page);
        });

    window.addEventListener("popstate", () => {
        populateFormFromUrl();
        loadCatalog(getPageFromUrl());
    });
}

function bindFilters() {
    const form = document.getElementById("catalog-filters");
    const clearBtn = document.getElementById("catalog-filters-clear");
    if (!form || !clearBtn) {
        return;
    }

    form.addEventListener("submit", (e) => {
        e.preventDefault();
        const p = new URLSearchParams();
        const q = form.querySelector("#filter-q").value.trim();
        if (q) {
            p.set("q", q);
        }
        const author = form.querySelector("#filter-author").value.trim();
        if (author) {
            p.set("author", author);
        }
        const cat = form.querySelector("#filter-category").value.trim();
        if (cat) {
            p.set("categoryId", cat);
        }
        if (form.querySelector("#filter-available").checked) {
            p.set("available", "1");
        }
        const qs = p.toString();
        const path = qs
            ? `${window.location.pathname}?${qs}`
            : window.location.pathname;
        window.history.pushState({}, "", path);
        loadCatalog(1);
    });

    clearBtn.addEventListener("click", () => {
        form.reset();
        window.history.pushState({}, "", window.location.pathname);
        loadCatalog(1);
    });
}

document.addEventListener("DOMContentLoaded", () => {
    populateFormFromUrl();
    bindFilters();
    bindPagination();
    loadCatalog(getPageFromUrl());
});

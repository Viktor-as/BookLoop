(function () {
    const API_URL = "/api/admin/overdue-borrows";
    const BASE_PATH = "/overdue-borrows";
    const PER_PAGE = 10;

    const loadingEl = document.getElementById("overdue-loading");
    const errorEl = document.getElementById("overdue-error");
    const listEl = document.getElementById("overdue-list");
    const paginationEl = document.getElementById("overdue-pagination");

    if (!loadingEl || !errorEl || !listEl || !paginationEl) {
        return;
    }

    function esc(s) {
        const d = document.createElement("div");
        d.textContent = s;
        return d.innerHTML;
    }

    function getPageFromUrl() {
        const p = new URLSearchParams(window.location.search).get("page");
        const n = p ? parseInt(p, 10) : 1;
        return Number.isFinite(n) && n >= 1 ? n : 1;
    }

    function buildBrowserUrl(page) {
        const q = new URLSearchParams();
        if (page > 1) {
            q.set("page", String(page));
        }
        const s = q.toString();
        return s ? `${BASE_PATH}?${s}` : BASE_PATH;
    }

    function buildApiUrl(page) {
        const q = new URLSearchParams();
        q.set("page", String(page));
        q.set("perPage", String(PER_PAGE));
        return `${API_URL}?${q.toString()}`;
    }

    function formatDateLabel(iso) {
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

    function detailHref(slug) {
        if (typeof slug === "string" && slug.length > 0) {
            return `/books/${encodeURIComponent(slug)}`;
        }
        return "#";
    }

    function renderCard(item) {
        const authors = item.authors ?? "—";
        const categories = item.categories ?? "—";
        const borrowed = formatDateLabel(item.borrowedAt);
        const due = formatDateLabel(item.dueDate);
        const member = item.memberName ?? "—";
        const href = detailHref(item.slug);

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
                    <span class="catalog-meta-label">Borrowed</span>
                    <span class="catalog-meta-value">${esc(borrowed)}</span>
                </div>
                <div class="catalog-meta-item">
                    <span class="catalog-meta-label">Due</span>
                    <span class="catalog-meta-value">${esc(due)}</span>
                </div>
                <div class="catalog-meta-item">
                    <span class="catalog-meta-label">Borrowed by</span>
                    <span class="catalog-meta-value">${esc(member)}</span>
                </div>
            </div>
            <div class="catalog-card-footer">
                <span class="catalog-status overdue">Overdue</span>
                <a href="${href}" class="catalog-card-action">Read more</a>
            </div>
        </li>`;
    }

    function renderPagination(page, lastPage) {
        if (lastPage <= 1) {
            paginationEl.innerHTML = "";
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

        paginationEl.innerHTML = `
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

    function showError(msg) {
        errorEl.textContent = msg;
        errorEl.style.display = "";
    }

    function hideError() {
        errorEl.textContent = "";
        errorEl.style.display = "none";
    }

    async function load(page) {
        hideError();
        loadingEl.style.display = "";
        listEl.innerHTML = "";
        paginationEl.innerHTML = "";

        try {
            const res = await fetch(buildApiUrl(page), {
                credentials: "include",
                headers: { Accept: "application/json" },
            });

            let data = {};
            try {
                data = await res.json();
            } catch {
                data = {};
            }

            const errText =
                data.detail ||
                data.message ||
                (res.status === 401
                    ? "Please sign in."
                    : res.status === 403
                      ? "You do not have access to this page."
                      : "Could not load overdue borrows.");

            if (res.status === 401 || res.status === 403) {
                loadingEl.style.display = "none";
                showError(errText);
                return;
            }

            if (res.status === 400) {
                if (
                    typeof data.message === "string" &&
                    data.message.includes("out of range")
                ) {
                    window.history.replaceState({}, "", buildBrowserUrl(1));
                    await load(1);
                    return;
                }
                loadingEl.style.display = "none";
                showError(errText);
                return;
            }

            if (!res.ok) {
                loadingEl.style.display = "none";
                showError(errText);
                return;
            }

            const items = Array.isArray(data.items) ? data.items : [];
            const lastPage =
                typeof data.lastPage === "number" && data.lastPage >= 1
                    ? data.lastPage
                    : 1;

            if (!items.length) {
                listEl.innerHTML =
                    '<li class="catalog-empty">No overdue loans right now.</li>';
            } else {
                listEl.innerHTML = items.map(renderCard).join("");
            }

            renderPagination(
                typeof data.page === "number" && data.page >= 1
                    ? data.page
                    : page,
                lastPage,
            );
            loadingEl.style.display = "none";
        } catch {
            loadingEl.style.display = "none";
            showError("Network error. Please try again.");
        }
    }

    window.addEventListener("popstate", () => {
        load(getPageFromUrl());
    });

    paginationEl.addEventListener("click", (ev) => {
        const a = ev.target.closest("a[data-page]");
        if (!a || !paginationEl.contains(a)) {
            return;
        }
        ev.preventDefault();
        const nextPage = parseInt(a.dataset.page, 10);
        if (!Number.isFinite(nextPage) || nextPage < 1) {
            return;
        }
        window.history.pushState(
            { page: nextPage },
            "",
            buildBrowserUrl(nextPage),
        );
        load(nextPage);
    });

    load(getPageFromUrl());
})();

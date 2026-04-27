import { esc, getCookie, formatDateLabel, detailHref } from "books_ui_utils";

(function () {
    const API_BASE = "/api/me/borrowed-books";
    const PER_PAGE = 5;
    const CSRF_COOKIE = "XSRF-TOKEN";
    const CSRF_HEADER = "X-XSRF-TOKEN";

    const loadingEl = document.getElementById("borrowed-loading");
    const errorEl = document.getElementById("borrowed-error");
    const sectionActive = document.getElementById("borrowed-section-active");
    const sectionHistory = document.getElementById("borrowed-section-history");
    const listActive = document.getElementById("borrowed-active-list");
    const listHistory = document.getElementById("borrowed-history-list");
    const paginationActive = document.getElementById("borrowed-active-pagination");
    const paginationHistory = document.getElementById("borrowed-history-pagination");

    if (
        !loadingEl ||
        !errorEl ||
        !sectionActive ||
        !sectionHistory ||
        !listActive ||
        !listHistory ||
        !paginationActive ||
        !paginationHistory
    ) {
        return;
    }

    function showError(msg) {
        errorEl.textContent = msg;
        errorEl.style.display = "";
    }

    function hideError() {
        errorEl.textContent = "";
        errorEl.style.display = "none";
    }

    function getLoanPageFromUrl() {
        const p = new URLSearchParams(window.location.search).get("loanPage");
        const n = p ? parseInt(p, 10) : 1;
        return Number.isFinite(n) && n >= 1 ? n : 1;
    }

    function getHistoryPageFromUrl() {
        const p = new URLSearchParams(window.location.search).get("historyPage");
        const n = p ? parseInt(p, 10) : 1;
        return Number.isFinite(n) && n >= 1 ? n : 1;
    }

    function buildBrowserUrl(loanPage, historyPage) {
        const q = new URLSearchParams();
        if (loanPage > 1) {
            q.set("loanPage", String(loanPage));
        }
        if (historyPage > 1) {
            q.set("historyPage", String(historyPage));
        }
        const s = q.toString();
        return s
            ? `${window.location.pathname}?${s}`
            : window.location.pathname;
    }

    function buildApiUrl(scope, page) {
        const q = new URLSearchParams();
        q.set("scope", scope);
        q.set("page", String(page));
        q.set("perPage", String(PER_PAGE));
        return `${API_BASE}?${q.toString()}`;
    }

    function lastPageFromTotal(total, perPage) {
        const t = typeof total === "number" && total >= 0 ? total : 0;
        return Math.max(1, Math.ceil(t / perPage));
    }

    function renderPagination(paginationEl, page, lastPage, buildUrl) {
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
                `<a href="${esc(buildUrl(1))}" data-page="1" class="catalog-pagination-num">1</a>`,
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
                    `<a href="${esc(buildUrl(i))}" data-page="${i}" class="catalog-pagination-num">${i}</a>`,
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
                `<a href="${esc(buildUrl(lastPage))}" data-page="${lastPage}" class="catalog-pagination-num">${lastPage}</a>`,
            );
        }

        const pagesHtml = `<div class="catalog-pagination-pages">${pages.join("")}</div>`;

        const prev =
            page > 1
                ? `<a href="${esc(buildUrl(page - 1))}" data-page="${page - 1}" rel="prev" class="catalog-pagination-edge">Prev</a>`
                : "";

        const next =
            page < lastPage
                ? `<a href="${esc(buildUrl(page + 1))}" data-page="${page + 1}" rel="next" class="catalog-pagination-edge">Next</a>`
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

    function renderActiveCard(item) {
        const authors = item.authors ?? "—";
        const categories = item.categories ?? "—";
        const slug = item.slug;
        const href = detailHref(slug);
        const due = formatDateLabel(item.dueDate);
        const borrowed = formatDateLabel(item.borrowedAt);

        return `<li class="catalog-card borrowed-card borrowed-card--active">
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
            </div>
            <div class="catalog-card-footer borrowed-card-footer">
                <div class="borrowed-actions">
                    <button type="button" class="borrowed-return-btn catalog-card-action" data-borrow-id="${Number(item.borrowId)}">Return book</button>
                    <a href="${href}" class="catalog-card-action catalog-card-action--secondary">Read more</a>
                </div>
            </div>
        </li>`;
    }

    function renderHistoryCard(item) {
        const authors = item.authors ?? "—";
        const categories = item.categories ?? "—";
        const slug = item.slug;
        const href = detailHref(slug);
        const borrowed = formatDateLabel(item.borrowedAt);
        const returned = formatDateLabel(item.returnedAt);

        return `<li class="catalog-card borrowed-card borrowed-card--returned">
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
                    <span class="catalog-meta-label">Returned</span>
                    <span class="catalog-meta-value">${esc(returned)}</span>
                </div>
            </div>
            <div class="catalog-card-footer">
                <span class="catalog-status returned">Returned</span>
                <a href="${href}" class="catalog-card-action">Read more</a>
            </div>
        </li>`;
    }

    function applyActiveList(items, total, loanPage) {
        const lastPage = lastPageFromTotal(total, PER_PAGE);
        listActive.innerHTML =
            items.length > 0
                ? items.map(renderActiveCard).join("")
                : `<li class="catalog-empty">You have no books on loan.</li>`;

        renderPagination(
            paginationActive,
            loanPage,
            lastPage,
            (p) => buildBrowserUrl(p, getHistoryPageFromUrl()),
        );
    }

    function applyHistoryList(items, total, historyPage) {
        const lastPage = lastPageFromTotal(total, PER_PAGE);
        listHistory.innerHTML =
            items.length > 0
                ? items.map(renderHistoryCard).join("")
                : `<li class="catalog-empty">No past loans yet.</li>`;

        renderPagination(
            paginationHistory,
            historyPage,
            lastPage,
            (p) => buildBrowserUrl(getLoanPageFromUrl(), p),
        );
    }

    async function fetchScope(scope, page) {
        const res = await fetch(buildApiUrl(scope, page), {
            credentials: "include",
            headers: { Accept: "application/json" },
        });

        let data = {};
        try {
            data = await res.json();
        } catch {
            data = {};
        }

        return { res, data };
    }

    async function loadAll() {
        hideError();
        loadingEl.style.display = "";
        paginationActive.innerHTML = "";
        paginationHistory.innerHTML = "";

        let loanPage = getLoanPageFromUrl();
        let historyPage = getHistoryPageFromUrl();

        try {
            const [activeResult, historyResult] = await Promise.all([
                fetchScope("active", loanPage),
                fetchScope("history", historyPage),
            ]);

            const errText = (data, res) =>
                data.detail ||
                data.message ||
                (res.status === 401
                    ? "Please sign in to see your borrowed books."
                    : "Could not load your borrowed books.");

            if (
                activeResult.res.status === 401 ||
                historyResult.res.status === 401
            ) {
                loadingEl.style.display = "none";
                showError(
                    errText(
                        activeResult.res.status === 401
                            ? activeResult.data
                            : historyResult.data,
                        activeResult.res.status === 401
                            ? activeResult.res
                            : historyResult.res,
                    ),
                );
                return;
            }

            if (!activeResult.res.ok) {
                loadingEl.style.display = "none";
                showError(errText(activeResult.data, activeResult.res));
                return;
            }
            if (!historyResult.res.ok) {
                loadingEl.style.display = "none";
                showError(errText(historyResult.data, historyResult.res));
                return;
            }

            const activeData = activeResult.data;
            const historyData = historyResult.data;

            const activeItems = Array.isArray(activeData.items)
                ? activeData.items
                : [];
            const historyItems = Array.isArray(historyData.items)
                ? historyData.items
                : [];
            const activeTotal =
                typeof activeData.total === "number" ? activeData.total : 0;
            const historyTotal =
                typeof historyData.total === "number" ? historyData.total : 0;

            const activeLast = lastPageFromTotal(activeTotal, PER_PAGE);
            const historyLast = lastPageFromTotal(historyTotal, PER_PAGE);

            let replaced = false;
            if (loanPage > activeLast && activeLast >= 1) {
                loanPage = activeLast;
                replaced = true;
            }
            if (historyPage > historyLast && historyLast >= 1) {
                historyPage = historyLast;
                replaced = true;
            }
            if (replaced) {
                window.history.replaceState(
                    {},
                    "",
                    buildBrowserUrl(loanPage, historyPage),
                );
                loadingEl.style.display = "none";
                await loadAll();
                return;
            }

            applyActiveList(activeItems, activeTotal, loanPage);
            applyHistoryList(historyItems, historyTotal, historyPage);

            sectionActive.style.display = "";
            sectionHistory.style.display = "";
            loadingEl.style.display = "none";
        } catch {
            loadingEl.style.display = "none";
            showError("Network error. Please try again.");
        }
    }

    function bindPaginationClicks(paginationEl, kind) {
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
            const loan = getLoanPageFromUrl();
            const hist = getHistoryPageFromUrl();
            if (kind === "active") {
                window.history.pushState(
                    {},
                    "",
                    buildBrowserUrl(nextPage, hist),
                );
            } else {
                window.history.pushState(
                    {},
                    "",
                    buildBrowserUrl(loan, nextPage),
                );
            }
            loadAll();
        });
    }

    bindPaginationClicks(paginationActive, "active");
    bindPaginationClicks(paginationHistory, "history");

    window.addEventListener("popstate", () => {
        loadAll();
    });

    document.addEventListener("click", async (ev) => {
        const btn = ev.target.closest(".borrowed-return-btn");
        if (!btn || !listActive.contains(btn)) {
            return;
        }

        const idRaw = btn.getAttribute("data-borrow-id");
        const borrowId = idRaw ? parseInt(idRaw, 10) : NaN;
        if (!Number.isFinite(borrowId)) {
            return;
        }

        const csrf = getCookie(CSRF_COOKIE);
        if (!csrf) {
            showError(
                "Session security token missing. Please refresh the page.",
            );
            return;
        }

        hideError();
        btn.disabled = true;

        try {
            const res = await fetch(
                `/api/borrows/${encodeURIComponent(String(borrowId))}/return`,
                {
                    method: "POST",
                    credentials: "include",
                    headers: {
                        "Content-Type": "application/json",
                        Accept: "application/json",
                        [CSRF_HEADER]: csrf,
                    },
                    body: "{}",
                },
            );

            let data = {};
            try {
                data = await res.json();
            } catch {
                data = {};
            }

            if (!res.ok) {
                const msg =
                    data.detail ||
                    data.message ||
                    "Could not return this book. Try again.";
                showError(msg);
                btn.disabled = false;
                return;
            }

            await loadAll();
        } catch {
            showError("Network error. Please try again.");
            btn.disabled = false;
        }
    });

    loadAll();
})();

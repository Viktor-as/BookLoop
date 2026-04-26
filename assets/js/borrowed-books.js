(function () {
    const API_LIST = "/api/me/borrowed-books";
    const CSRF_COOKIE = "XSRF-TOKEN";
    const CSRF_HEADER = "X-XSRF-TOKEN";

    const loadingEl = document.getElementById("borrowed-loading");
    const errorEl = document.getElementById("borrowed-error");
    const sectionActive = document.getElementById("borrowed-section-active");
    const sectionHistory = document.getElementById("borrowed-section-history");
    const listActive = document.getElementById("borrowed-active-list");
    const listHistory = document.getElementById("borrowed-history-list");

    if (
        !loadingEl ||
        !errorEl ||
        !sectionActive ||
        !sectionHistory ||
        !listActive ||
        !listHistory
    ) {
        return;
    }

    function esc(s) {
        const d = document.createElement("div");
        d.textContent = s;
        return d.innerHTML;
    }

    function getCookie(name) {
        const row = document.cookie
            .split("; ")
            .find((c) => c.startsWith(name + "="));
        if (!row) {
            return null;
        }
        return decodeURIComponent(row.slice(name.length + 1));
    }

    function showError(msg) {
        errorEl.textContent = msg;
        errorEl.style.display = "";
    }

    function hideError() {
        errorEl.textContent = "";
        errorEl.style.display = "none";
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

    function renderLists(items) {
        const active = items.filter((i) => i.isActive === true);
        const history = items.filter((i) => i.isActive !== true);

        listActive.innerHTML = active.length
            ? active.map(renderActiveCard).join("")
            : `<li class="catalog-empty">You have no books on loan.</li>`;

        listHistory.innerHTML = history.length
            ? history.map(renderHistoryCard).join("")
            : `<li class="catalog-empty">No past loans yet.</li>`;

        sectionActive.style.display = "";
        sectionHistory.style.display = "";
    }

    async function loadList() {
        hideError();
        loadingEl.style.display = "";

        try {
            const res = await fetch(API_LIST, {
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
                    ? "Please sign in to see your borrowed books."
                    : "Could not load your borrowed books.");

            if (res.status === 401) {
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
            renderLists(items);
            loadingEl.style.display = "none";
        } catch {
            loadingEl.style.display = "none";
            showError("Network error. Please try again.");
        }
    }

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

            await loadList();
        } catch {
            showError("Network error. Please try again.");
            btn.disabled = false;
        }
    });

    loadList();
})();

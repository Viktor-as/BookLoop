(function () {
    const form = document.getElementById("book-borrow-form");
    const feedback = document.getElementById("book-borrow-feedback");
    const submitBtn = document.getElementById("book-borrow-submit");
    const daysInput = document.getElementById("borrow-days");

    if (!form || !feedback || !submitBtn || !daysInput) {
        return;
    }

    const slug = form.dataset.borrowSlug;
    if (!slug) {
        return;
    }

    const CSRF_COOKIE = "XSRF-TOKEN";
    const CSRF_HEADER = "X-XSRF-TOKEN";

    function getCookie(name) {
        const row = document.cookie
            .split("; ")
            .find((c) => c.startsWith(name + "="));
        if (!row) {
            return null;
        }
        return decodeURIComponent(row.slice(name.length + 1));
    }

    function showFeedback(text, kind) {
        feedback.textContent = text;
        feedback.classList.remove(
            "is-hidden",
            "book-borrow-feedback--success",
            "book-borrow-feedback--error",
        );
        feedback.classList.add(
            kind === "success"
                ? "book-borrow-feedback--success"
                : "book-borrow-feedback--error",
        );
    }

    function hideFeedback() {
        feedback.textContent = "";
        feedback.classList.add("is-hidden");
        feedback.classList.remove(
            "book-borrow-feedback--success",
            "book-borrow-feedback--error",
        );
    }

    submitBtn.addEventListener("click", async () => {
        hideFeedback();

        const days = parseInt(daysInput.value, 10);
        if (!Number.isFinite(days)) {
            showFeedback("Please enter a valid number of days.", "error");
            return;
        }

        const max = parseInt(daysInput.getAttribute("max") || "0", 10);
        const min = parseInt(daysInput.getAttribute("min") || "1", 10);
        if (days < min || (max > 0 && days > max)) {
            showFeedback(`Days must be between ${min} and ${max}.`, "error");
            return;
        }

        const csrf = getCookie(CSRF_COOKIE);
        if (!csrf) {
            showFeedback(
                "Session security token missing. Please refresh the page or sign in again.",
                "error",
            );
            return;
        }

        submitBtn.disabled = true;

        try {
            const res = await fetch(
                `/api/books/${encodeURIComponent(slug)}/borrow`,
                {
                    method: "POST",
                    credentials: "include",
                    headers: {
                        "Content-Type": "application/json",
                        Accept: "application/json",
                        [CSRF_HEADER]: csrf,
                    },
                    body: JSON.stringify({ days }),
                },
            );

            let data = {};
            try {
                data = await res.json();
            } catch {
                data = {};
            }

            if (res.status === 401) {
                showFeedback(
                    data.message || "Please log in again to borrow this book.",
                    "error",
                );
                submitBtn.disabled = false;
                return;
            }

            if (!res.ok) {
                showFeedback(
                    data.message || "Could not complete the borrow request.",
                    "error",
                );
                submitBtn.disabled = false;
                return;
            }

            showFeedback(
                data.message || "Book borrowed successfully.",
                "success",
            );
            window.setTimeout(() => {
                window.location.reload();
            }, 1000);
        } catch {
            showFeedback("Network error. Please try again.", "error");
            submitBtn.disabled = false;
        }
    });
})();

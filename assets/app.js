/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import "books_ui_utils";
import "./js/catalog.js";

// Load auth form handlers only on those pages, via dynamic import, so the Asset
// Mapper fingerprints them and browsers do not keep a stale <script> forever.
if (document.getElementById("login-form") !== null) {
    void import("./js/login.js");
}
if (document.getElementById("register-form") !== null) {
    void import("./js/register.js");
}

if (document.getElementById("book-borrow-form") !== null) {
    void import("./js/book-borrow.js");
}

if (document.getElementById("borrowed-section-active") !== null) {
    void import("./js/borrowed-books.js");
}

if (document.getElementById("overdue-list") !== null) {
    void import("./js/overdue-borrows.js");
}

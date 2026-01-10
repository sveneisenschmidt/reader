(function () {
    const form = document.querySelector("[data-auto-mark-read-form]");
    if (!form) return;

    setTimeout(function () {
        form.submit();
    }, 5000);
})();

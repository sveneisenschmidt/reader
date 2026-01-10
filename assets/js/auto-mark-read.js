(function () {
    const form = document.querySelector("[data-auto-mark-read-form]");
    if (!form) return;

    const stayInput = form.querySelector("[data-auto-mark-read-stay]");
    if (!stayInput) return;

    setTimeout(function () {
        stayInput.value = "1";
        form.submit();
    }, 5000);
})();

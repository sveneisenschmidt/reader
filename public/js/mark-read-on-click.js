document.querySelectorAll("[data-external-link]").forEach((el) => {
    el.addEventListener("click", (e) => {
        e.preventDefault();
        const form = document.querySelector("[data-mark-read-stay-form]");
        if (form) {
            window.open(form.dataset.externalUrl, "_blank");
            form.submit();
        }
    });
});

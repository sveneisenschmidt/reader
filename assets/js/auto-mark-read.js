(function () {
    const pane = document.querySelector("[data-reading-pane][data-unread]");
    if (!pane) return;

    const form = pane.querySelector('form[action$="/read"]');
    if (!form) return;

    setTimeout(() => form.submit(), 5000);
})();

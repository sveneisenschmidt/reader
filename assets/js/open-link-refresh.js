(() => {
    document.querySelectorAll('a[href*="/open?"]').forEach((link) => {
        link.addEventListener('click', () => {
            setTimeout(() => {
                window.location.reload();
            }, 100);
        });
    });
})();

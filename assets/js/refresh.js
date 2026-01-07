(() => {
    const SPINNER = ["⠋", "⠙", "⠹", "⠸", "⠼", "⠴", "⠦", "⠧", "⠇", "⠏"];
    const form = document.querySelector("[data-refresh-form]");

    const showOverlay = () => {
        let i = 0;
        const overlay = document.createElement("div");
        overlay.setAttribute("data-refresh-overlay", "");
        overlay.textContent = SPINNER[0];
        document.body.appendChild(overlay);
        setInterval(() => {
            overlay.textContent = SPINNER[++i % SPINNER.length];
        }, 150);
    };

    form?.addEventListener("submit", showOverlay);

    document
        .querySelector("[data-trigger-refresh]")
        ?.addEventListener("click", (e) => {
            e.preventDefault();
            showOverlay();
            form?.requestSubmit();
        });
})();

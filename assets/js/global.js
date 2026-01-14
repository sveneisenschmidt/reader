if (
    window.navigator.standalone ||
    window.matchMedia("(display-mode: standalone)").matches
) {
    document.body.classList.add("standalone");
}

document.body.focus();
document.activeElement?.blur();

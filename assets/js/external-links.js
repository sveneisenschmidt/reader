document.addEventListener("DOMContentLoaded", () => {
    const content = document.getElementById("article-content");
    content?.querySelectorAll("a").forEach((link) => {
        link.setAttribute("target", "_blank");
    });
});

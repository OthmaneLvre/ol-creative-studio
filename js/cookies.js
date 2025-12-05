document.addEventListener("DOMContentLoaded", () => {
    if (!localStorage.getItem("cookiesAccepted")) {
        document.getElementById("cookie-banner").style.display = "flex";
    }

    document.getElementById("cookie-accept").addEventListener("click", () => {
        localStorage.setItem("cookiesAccepted", "true");
        document.getElementById("cookie-banner").style.display = "none";
    });
});

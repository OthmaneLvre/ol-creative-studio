/*********************************/
/*          BURGER MENU          */
/*********************************/

const burger = document.getElementById("burger");
const mobileMenu = document.getElementById("mobileMenu");

burger.addEventListener("click", () => {
    burger.classList.toggle("open");
    mobileMenu.classList.toggle("open");
});

/*********************************/
/*    AVIS CLIENTS (ACCUEIL)     */
/*********************************/

// si PHP a bien fourni les avis dans "testimonials"
if (typeof testimonials !== "undefined" && testimonials.length > 0) {

    let testimonialIndex = 0;

    const nameEl = document.getElementById("clientName");
    const typeEl = document.getElementById("clientType");
    const textEl = document.getElementById("clientText");

    function updateTestimonial() {
        const t = testimonials[testimonialIndex];

        nameEl.textContent = t.nom;
        typeEl.textContent = t.categorie;
        textEl.textContent = `"${t.commentaire}"`;
    }

    // Initial load 
    updateTestimonial();

    // ==== SECURITE POUR EVITER LES ERREURS ==== 

    const nextBtn = document.getElementById("nextTestimonial");
    const prevBtn = document.getElementById("prevTestimonial");

    if (nextBtn && prevBtn) {

        nextBtn.addEventListener("click", () => {
            testimonialIndex = (testimonialIndex+ 1) % testimonials.length;
            updateTestimonial();
        });

        prevBtn.addEventListener("click", () => {
            testimonialIndex = (testimonialIndex - 1 + testimonials.length) % testimonials.length;
            updateTestimonial();
        });
    }
}

/*********************************/
/*    SERVICES CAROUSEL MOBILE   */
/*********************************/
document.addEventListener('DOMContentLoaded', () => {

    const container = document.querySelector('.services-grid');
    const dots = document.querySelectorAll('.services-pagination .dot');

    if (!container) return;

    // === Afficher CARD DU MILIEU AU CHARGEMENT ===
    const cards = document.querySelectorAll('.service-card');

    if (cards.length > 1) {
        const middleIndex = Math.floor(cards.length / 2);
        const cardWidth = cards[0].offsetWidth + 20; // width + gap

        const scrollTo = middleIndex * cardWidth - (window.innerWidth / 2) + (cardWidth / 2);

        container.scrollTo({
            left: scrollTo,
            behavior: "instant"
        });

        dots[middleIndex]?.classList.add('active');
    }

    // === Mise à jour des dots ===
    container.addEventListener('scroll', () => {
        const cardWidth = cards[0].offsetWidth + 20;
        const slideIndex = Math.round(container.scrollLeft / cardWidth);

        dots.forEach(dot => dot.classList.remove('active'));
        dots[slideIndex]?.classList.add('active');
    });
});


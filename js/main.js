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

const testimonials = [
    {
        name: "Client 1",
        type: "Création d’un site vitrine",
        text: "Othmane a fait un travail exceptionnel pour mon site vitrine. Rapidité, qualité, écoute... Je recommande à 200 % !"
    },
    {
        name: "Client 2",
        type: "Refonte visuelle complète",
        text: "Professionnel, à l'écoute, et force de proposition. Le résultat dépasse mes attentes !"
    },
    {
        name: "Client 3",
        type: "Identité visuelle + site",
        text: "Une superbe collaboration, je recommande vivement OL Creative Studio !"
    }
];

let testimonialIndex = 0;

function updateTestimonial() {
    document.getElementById("clientName").textContent = testimonials[index].name;
    document.getElementById("clientType").textContent = testimonials[index].type;
    document.getElementById("clientText").textContent = "“" + testimonials[index].text + "”";
}

// ==== SECURITE POUR EVITER LES ERREURS ==== 

const nextBtn = document.getElementById("nextTestimonial");
const prevBtn = document.getElementById("prevTestimonial");

if (nextBtn && prevBtn) {
    nextBtn.addEventListener("click", () => {
        index = (index+ 1) % testimonials.length;
        updateTestimonial();
    });

    prevBtn.addEventListener("click", () => {
        index = (index - 1 + testimonials.length) % testimonials.length;
        updateTestimonial();
    });
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


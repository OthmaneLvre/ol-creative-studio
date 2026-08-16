
// ===== FILTRES =====
const filterButtons = document.querySelectorAll('.filter-btn');
const cards = document.querySelectorAll('.portfolio-card');

filterButtons.forEach(btn => {
    btn.addEventListener('click', () => {

        document.querySelector('.filter-btn.active')?.classList.remove('active');
        btn.classList.add('active');

        const filter = btn.dataset.category;

        cards.forEach(card => {
            if (filter === 'all' || card.dataset.category === filter) {
                card.style.display = "";
                card.style.animation = "fadeIn .3s ease";
            } else {
                card.style.display = "none";
            }
        });
    });
});


// ================== CARROUSEL MOBILE ==================
const carousel = document.querySelector(".portfolio-cat-wrapper");
const dots = document.querySelectorAll(".portfolio-cat-pagination .dot");
const catCards = document.querySelectorAll(".portfolio-cat-grid .cat-card");

if (window.innerWidth <= 768) {

    function updateDots() {
        const carouselRect = carousel.getBoundingClientRect();
        const center = carouselRect.left + carouselRect.width / 2;

        let activeIndex = 0;
        let minDistance = Infinity;

        catCards.forEach((card, index) => {
            const cardRect = card.getBoundingClientRect();
            const cardCenter = cardRect.left + cardRect.width / 2;

            const distance = Math.abs(cardCenter - center);

            if (distance < minDistance) {
                minDistance = distance;
                activeIndex = index;
            }
        });

        dots.forEach((dot, i) => {
            dot.classList.toggle("active", i === activeIndex);
        });
    }

    carousel.addEventListener("scroll", updateDots);
    window.addEventListener("resize", updateDots);
    updateDots();

    // Clic sur dot → scrol vers la carte correcte
    dots.forEach((dot, i) => {
        dot.addEventListener("click", () => {
            const cardRect = cards[i].getBoundingClientRect();
            const carouselRect = carousel.getBoundingClientRect();

            const targetScroll =
                catCards[i].offsetLeft -
                (carouselRect.width / 2 - cardRect.width / 2);

            carousel.scrollTo({
                left: targetScroll,
                behavior: "smooth"
            });
        });
    });
}

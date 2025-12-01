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

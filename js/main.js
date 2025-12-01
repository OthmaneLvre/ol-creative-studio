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

let index = 0;

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

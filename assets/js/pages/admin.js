/*******************************
 *   SYSTEME TAGS (ADD + EDIT)
 *******************************/

let selectedTechs = [];
const techSelect = document.getElementById("techSelect");
const techTags = document.getElementById("techTags");
const techHidden = document.getElementById("technologiesHidden");

// On vérifie que la page contient les éléments (add ou edit)
if (techSelect && techTags && techHidden) {

    // Ajout d'une techno (ADD ou EDIT)
    techSelect.addEventListener("change", function () {
        const value = this.value;

        if (value !== "" && !selectedTechs.includes(value)) {
            selectedTechs.push(value);
            renderTags();
        }

        this.value = "";
    });

    // Supprimer un tag
    globalThis.removeTech = function (tech) {
        selectedTechs = selectedTechs.filter(t => t !== tech);
        renderTags();
    }

    // Affichage des tags
    function renderTags() {
        techTags.innerHTML = "";

        selectedTechs.forEach(tech => {
            const tag = document.createElement("div");
            tag.className = "tech-tag";
            tag.innerHTML = `
                ${tech}
                <button type="button" onclick="removeTech('${tech}')">×</button>
            `;
            techTags.appendChild(tag);
        });

        techHidden.value = JSON.stringify(selectedTechs);
    }

    // ----- INIT POUR EDIT.PHP -----
    if (typeof initialTechs !== "undefined") { 
        selectedTechs = initialTechs;
    }

    renderTags();
}

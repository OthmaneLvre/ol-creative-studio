/*
|--------------------------------------------------------------------------
| OL CREATIVE STUDIO
| CONTACT
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| FORMULAIRE
|--------------------------------------------------------------------------
*/

function initContactForm() {

    const form = document.getElementById('contactForm');

    if (!form) {
        return;
    }

    const firstName = form.querySelector('#prenom');
    const lastName = form.querySelector('#nom');
    const email = form.querySelector('#email');
    const project = form.querySelector('#objet');
    const message = form.querySelector('#message');
    const consent = form.querySelector('#consent');
    const submitButton = form.querySelector(
        'button[type="submit"]'
    );

    if (
        !firstName ||
        !lastName ||
        !email ||
        !project ||
        !message ||
        !consent
    ) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    form.addEventListener('submit', (event) => {

        const errors = [];

        const firstNameValue =
            firstName.value.trim();

        const lastNameValue =
            lastName.value.trim();

        const emailValue =
            email.value.trim();

        const messageValue =
            message.value.trim();


        if (firstNameValue.length < 2) {
            errors.push(
                'Le prénom doit contenir au moins 2 caractères.'
            );
        }

        if (lastNameValue.length < 2) {
            errors.push(
                'Le nom doit contenir au moins 2 caractères.'
            );
        }


        const emailPattern =
            /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if (!emailPattern.test(emailValue)) {
            errors.push(
                'L’adresse email saisie n’est pas valide.'
            );
        }


        if (!project.value) {
            errors.push(
                'Veuillez sélectionner un type de projet.'
            );
        }


        if (messageValue.length < 10) {
            errors.push(
                'Votre message doit contenir au moins 10 caractères.'
            );
        }


        if (!consent.checked) {
            errors.push(
                'Veuillez accepter l’utilisation de vos informations.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | ERREURS
        |--------------------------------------------------------------------------
        */

        if (errors.length > 0) {

            event.preventDefault();

            window.alert(
                errors.join('\n')
            );

            return;
        }


        /*
        |--------------------------------------------------------------------------
        | ÉTAT D'ENVOI
        |--------------------------------------------------------------------------
        */

        if (submitButton) {

            submitButton.disabled = true;

            submitButton.classList.add(
                'is-loading'
            );

            const label =
                submitButton.querySelector('span');

            if (label) {
                label.textContent =
                    'Envoi en cours…';
            }
        }

    });

}


/*
|--------------------------------------------------------------------------
| INITIALISATION
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'DOMContentLoaded',
    initContactForm
);
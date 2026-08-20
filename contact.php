<?php

$pageTitle = "Contact – OL Creative Studio";

$pageDescription =
    "Contactez OL Creative Studio pour discuter de votre projet : "
    . "création de site internet, e-commerce, identité visuelle, "
    . "maintenance ou solution digitale sur mesure.";

include_once __DIR__ . '/partials/header.php';

?>

<main class="page-content">

    <!-- =========================== ALERTS =========================== -->

    <?php if (
        isset($_GET['success'])
        && $_GET['success'] === '1'
    ): ?>

        <div class="contact-alert contact-alert--success">
            <div class="container">
                <p>
                    Votre message a bien été envoyé.
                    Je reviens vers vous rapidement.
                </p>
            </div>
        </div>

    <?php endif; ?>


    <?php if (
        isset($_GET['error'])
        && $_GET['error'] === 'email'
    ): ?>

        <div class="contact-alert contact-alert--error">
            <div class="container">
                <p>
                    L’adresse email saisie n’est pas valide.
                    Merci de la vérifier.
                </p>
            </div>
        </div>

    <?php endif; ?>


    <!-- =========================== HERO =========================== -->

    <section class="contact-hero">

        <div class="container contact-hero__container">

            <div class="contact-hero__main reveal">

                <span class="contact-hero__eyebrow">
                    Contact
                </span>

                <h1 class="contact-hero__title">
                    Parlons de votre
                    <em>prochain projet.</em>
                </h1>

            </div>


            <div class="contact-hero__side reveal reveal--delay-100">

                <p class="contact-hero__intro">
                    Vous avez une idée, un besoin précis ou simplement
                    une question ? Expliquez-moi votre projet et
                    échangeons sur la meilleure façon de le concrétiser.
                </p>

                <div class="contact-hero__availability">

                    <span class="contact-hero__availability-status">
                        <span aria-hidden="true"></span>
                        Disponible pour de nouveaux projets
                    </span>

                    <p>
                        Basé à Céret · Pyrénées-Orientales<br>
                        Projets locaux & à distance
                    </p>

                </div>

            </div>

        </div>


        <div class="container contact-hero__footer">

            <span>
                01 · Votre projet
            </span>

            <a href="#contact-form">
                Écrivez-moi
                <span aria-hidden="true">↓</span>
            </a>

        </div>

    </section>


    <!-- =========================== CONTACT =========================== -->

    <section
        class="contact-project"
        id="contact-form"
    >

        <div class="container contact-project__layout">


            <!-- =========================== ASIDE =========================== -->

            <aside class="contact-project__aside">

                <div class="reveal">

                    <span class="contact-project__eyebrow">
                        02 · Contact
                    </span>

                    <h2 class="contact-project__title">
                        Dites-moi ce que
                        <em>vous avez en tête.</em>
                    </h2>

                    <p class="contact-project__intro">
                        Quelques informations suffisent pour commencer.
                        Je vous répondrai avec une première orientation
                        adaptée à votre projet.
                    </p>

                </div>


                <div class="contact-project__details reveal reveal--delay-100">

                    <div class="contact-detail">

                        <span class="contact-detail__label">
                            Email
                        </span>

                        <a href="mailto:contact@olcreativestudio.fr">
                            contact@olcreativestudio.fr
                        </a>

                    </div>


                    <div class="contact-detail">

                        <span class="contact-detail__label">
                            Téléphone
                        </span>

                        <a href="tel:+33767841013">
                            07 67 84 10 13
                        </a>

                    </div>


                    <div class="contact-detail">

                        <span class="contact-detail__label">
                            Localisation
                        </span>

                        <p>
                            Céret · Pyrénées-Orientales
                        </p>

                    </div>


                    <div class="contact-detail">

                        <span class="contact-detail__label">
                            Accompagnement
                        </span>

                        <p>
                            Partout en France
                        </p>

                    </div>

                </div>

            </aside>


            <!-- =========================== FORM =========================== -->

            <form
                action="/php/contact.php"
                method="POST"
                class="contact-form reveal reveal--delay-100"
                id="contactForm"
            >

                <!-- Honeypot -->
                <div
                    class="contact-form__honeypot"
                    aria-hidden="true"
                >
                    <label for="website">
                        Website
                    </label>

                    <input
                        type="text"
                        name="website"
                        id="website"
                        tabindex="-1"
                        autocomplete="off"
                    >
                </div>


                <!-- NAME -->

                <div class="contact-form__row">

                    <div class="contact-field">

                        <label for="prenom">
                            Prénom
                        </label>

                        <input
                            type="text"
                            name="prenom"
                            id="prenom"
                            autocomplete="given-name"
                            required
                        >

                    </div>


                    <div class="contact-field">

                        <label for="nom">
                            Nom
                        </label>

                        <input
                            type="text"
                            name="nom"
                            id="nom"
                            autocomplete="family-name"
                            required
                        >

                    </div>

                </div>


                <!-- EMAIL / PHONE -->

                <div class="contact-form__row">

                    <div class="contact-field">

                        <label for="email">
                            Email
                        </label>

                        <input
                            type="email"
                            name="email"
                            id="email"
                            autocomplete="email"
                            required
                        >

                    </div>


                    <div class="contact-field">

                        <label for="telephone">
                            Téléphone
                            <span>Facultatif</span>
                        </label>

                        <input
                            type="tel"
                            name="telephone"
                            id="telephone"
                            autocomplete="tel"
                        >

                    </div>

                </div>


                <!-- PROJECT TYPE -->

                <div class="contact-field">

                    <label for="objet">
                        Votre projet
                    </label>

                    <select
                        name="objet"
                        id="objet"
                        required
                    >
                        <option value="" selected disabled>
                            Sélectionnez un type de projet
                        </option>

                        <option value="Site vitrine">
                            Site vitrine
                        </option>

                        <option value="E-commerce">
                            E-commerce
                        </option>

                        <option value="Identité visuelle">
                            Identité visuelle
                        </option>

                        <option value="Maintenance / SEO">
                            Maintenance / SEO
                        </option>

                        <option value="Automatisation / outil métier">
                            Automatisation / outil métier
                        </option>

                        <option value="Refonte">
                            Refonte d’un projet existant
                        </option>

                        <option value="Autre">
                            Autre projet
                        </option>
                    </select>

                </div>


                <!-- BUDGET -->

                <div class="contact-field">

                    <label for="budget">
                        Budget indicatif
                        <span>Facultatif</span>
                    </label>

                    <select
                        name="budget"
                        id="budget"
                    >
                        <option value="">
                            Je ne sais pas encore
                        </option>

                        <option value="Moins de 1 000 €">
                            Moins de 1 000 €
                        </option>

                        <option value="1 000 € – 2 500 €">
                            1 000 € – 2 500 €
                        </option>

                        <option value="2 500 € – 5 000 €">
                            2 500 € – 5 000 €
                        </option>

                        <option value="5 000 € et plus">
                            5 000 € et plus
                        </option>
                    </select>

                </div>


                <!-- MESSAGE -->

                <div class="contact-field">

                    <label for="message">
                        Parlez-moi de votre projet
                    </label>

                    <textarea
                        name="message"
                        id="message"
                        rows="7"
                        placeholder="Votre activité, vos besoins, vos objectifs, vos délais..."
                        required
                    ></textarea>

                </div>


                <!-- RGPD -->

                <div class="contact-consent">

                    <input
                        type="checkbox"
                        name="consent"
                        id="consent"
                        value="1"
                        required
                    >

                    <label for="consent">
                        J’accepte que les informations saisies soient
                        utilisées afin de répondre à ma demande.
                        <a href="/confidentialite.php">
                            Politique de confidentialité
                        </a>
                    </label>

                </div>


                <!-- SUBMIT -->

                <div class="contact-form__footer">

                    <button
                        type="submit"
                        class="button button--primary button--lg"
                    >
                        <span>
                            Envoyer ma demande
                        </span>
                    </button>

                    <p>
                        Réponse généralement sous 24 h ouvrées.
                    </p>

                </div>

            </form>

        </div>

    </section>

</main>


<script
    type="module"
    src="/assets/js/pages/contact.js"
></script>

<?php
$footerVariant = 'contact';

include_once __DIR__ . '/partials/footer.php';
?>

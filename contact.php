<?php
$pageTitle = "Contact – OL Creative Studio";
$pageDescription = "Contactez OL Creative Studio pour discuter de votre projet : création de site internet, identité visuelle, graphisme ou maintenance web.";
?>

<?php include 'partials/header.php'; ?>
<?php require_once 'php/db.php'; ?>

<!-- SCRIPT PHP d'envoi du formulaire -->

<main class="page-content">

<?php if (isset($_GET["success"]) && $_GET["success"] == 1): ?>
<div class="alert-success">
    <p>✅ Votre message a bien été envoyé. Je vous réponds très rapidement !</p>
</div>
<?php endif; ?>

<?php if (isset($_GET["error"]) && $_GET["error"] == "email"): ?>
<div class="alert-error">
    <p>⚠️ L'adresse email saisie n'est pas valide. Merci de vérifier.</p>
</div>
<?php endif; ?>

    <!-- ========== HERO ========== -->
    <section class="contact-hero">
        <img src="/assets/images/illustration-hero.webp"
            alt="Illustration portfolio"
            width="1200"
            height="1200"
            class="hero-bg"
            loading="eager">
        <div class="container">
            <h1>Contactez OL Creative Studio</h1>
            <p>Besoin d’un site web, d’une identité visuelle ou d’un accompagnement digital ? Je vous réponds sous 24 heures.</p>
        </div>
    </section>

    <!-- ========== INFOS CONTACT (3cards) ========== -->
    <section class="contact-infos">
        <div class="container contact-infos-grid">

            <div class="contact-card">
                <img src="/assets/icons/envelope.svg" loading="lazy" alt="Envoyer un email à OL Creative Studio">
                <a href="mailto:contact@olcreativestudio.com">contact@olcreativestudio.fr</a>
            </div>

            <div class="contact-card">
                <img src="/assets/icons/phone.svg" loading="lazy" alt="Téléphoner à OL Creative Studio">
                <a href="tel:+33767841013">07 67 84 10 13</a>
            </div>

            <div class="contact-card">
                <img src="/assets/icons/location.svg" loading="lazy" alt="Localisation OL Creative Studio à Céret">
                <p>Céret - Pyrénées-Orientales</p>
            </div>
        </div>
    </section>

    <!-- ========== FORMULAIRE ========== -->
    <section class="contact-form-section">
        <div class="container">

            <form action="/php/contact.php" method="POST" class="contact-form">
            <input type="text" name="website" id="website" autocomplete="off" style="display:none;">

                <h2>Envoyer un message à OL Creative Studio</h2>
                <p class="form-subtitle">Je vous réponds très rapidement (24H maximum)</p>

                <div class="form-row">
                    <div class="form-group">
                        <label for="prenom">Prénom</label>
                        <input type="text" name="prenom" id="prenom" autocomplete="given-name" required>
                    </div>

                    <div class="form-group">
                        <label for="nom">Nom</label>
                        <input type="text" name="nom" id="nom" autocomplete="family-name" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">Votre email</label>
                    <input type="email" name="email" id="email" autocomplete="email" required>
                </div>

                <div class="form-group">
                    <label for="objet">Objet</label>
                    <input type="text" name="objet" id="objet" required>
                </div>

                <div class="form-group">
                    <label for="message">Message</label>
                    <textarea id="message" name="message" required></textarea>
                </div>

                <button type="submit" class="btn-primary btn-submit">Envoyer</button>

            </form>

        </div>
    </section>

</main>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("contactForm");
    if (!form) return;

    form.addEventListener("submit", function (e) {
        const prenom = document.getElementById("prenom").value.trim();
        const nom = document.getElementById("nom").value.trim();
        const email = document.getElementById("email").value.trim();
        const objet = document.getElementById("objet").value.trim();
        const message = document.getElementById("message").value.trim();

        let errors = [];

        if (prenom.length < 2) errors.push("Le prénom doit contenir au moins 2 caractères.");
        if (nom.length < 2) errors.push("Le nom doit contenir au moins 2 caractères.");
        
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) errors.push("L'adresse e-mail n'est pas valide.");

        if (objet.length < 3) errors.push("L'objet doit contenir au moins 3 caractères.");
        if (message.length < 10) errors.push("Le message doit contenir au moins 10 caractères.");

        if (errors.length > 0) {
            e.preventDefault();
            alert(errors.join("\n"));
        }
    });
});
</script>

<?php include 'partials/footer.php'; ?>

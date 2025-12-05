<?php
$pageTitle = "Contact – OL Creative Studio";
$pageDescription = "Contactez OL Creative Studio pour discuter de votre projet : création de site internet, identité visuelle, graphisme ou maintenance web.";
?>

<?php include 'partials/header.php'; ?>
<?php require_once 'php/db.php'; ?>

<!-- SCRIPT PHP d'envoi du formulaire -->

<main class="page-content">

    <!-- ========== HERO ========== -->
    <section class="contact-hero">
        <div class="container">
            <h1>Contactez OL Creative Studio</h1>
            <p>Besoin d’un site web, d’une identité visuelle ou d’un accompagnement digital ? Je vous réponds sous 24 heures.</p>
        </div>
    </section>

    <!-- ========== INFOS CONTACT (3cards) ========== -->
    <section class="contact-infos">
        <div class="container contact-infos-grid">

            <div class="contact-card">
                <img src="assets/icons/envelope.svg" loading="lazy" alt="Envoyer un email à OL Creative Studio">
                <a href="mailto:contact@olcreativestudio.com">contact@olcreativestudio.com</a>
            </div>

            <div class="contact-card">
                <img src="assets/icons/phone.svg" loading="lazy" alt="Téléphoner à OL Creative Studio">
                <a href="tel:+33767841013">07 67 84 10 13</a>
            </div>

            <div class="contact-card">
                <img src="assets/icons/location.svg" loading="lazy" alt="Localisation OL Creative Studio à Céret">
                <p>Céret - Pyrénées-Orientales</p>
            </div>
        </div>
    </section>

    <!-- ========== FORMULAIRE ========== -->
    <section class="contact-form-section">
        <div class="container">

            <form action="contact.php" method="POST" class="contact-form">

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

<?php include 'partials/footer.php'; ?>

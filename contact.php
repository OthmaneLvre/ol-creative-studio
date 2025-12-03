<?php include 'partials/header.php'; ?>
<?php require_once 'php/db.php'; ?>

<!-- SCRIPT PHP d'envoi du formulaire -->

<main>

    <!-- ========== HERO ========== -->
    <section class="contact-hero">
        <div class="container">
            <h1>Contactez-moi</h1>
            <p>Je suis disponible pour discuter de votre projet et vous accompagner dans sa réalisation.</p>
        </div>
    </section>

    <!-- ========== INFOS CONTACT (3cards) ========== -->
    <section class="contact-infos">
        <div class="container contact-infos-grid">

            <div class="contact-card">
                <img src="assets/icons/envelope.svg" alt="Email">
                <p>contact@olcreativestudio.com</p>
            </div>

            <div class="contact-card">
                <img src="assets/icons/phone.svg" alt="Téléphone">
                <p>07 00 00 00 00</p>
            </div>

            <div class="contact-card">
                <img src="assets/icons/location.svg" alt="Localisation">
                <p>Céret - Pyrénées-Orientales</p>
            </div>
        </div>
    </section>

    <!-- ========== FORMULAIRE ========== -->
    <section class="contact-form-section">
        <div class="container">

            <form action="contact.php" method="POST" class="contact-form">

                <h2>Envoyez-moi un message</h2>
                <p class="form-subtitle">Je vous réponds très rapidement (24H maximum)</p>

                <div class="form-row">
                    <div class="form-group">
                        <label for="prenom">Prénom</label>
                        <input type="text" name="prenom" id="prenom" required>
                    </div>

                    <div class="form-group">
                        <label for="nom">Nom</label>
                        <input type="text" name="nom" id="nom" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">Votre email</label>
                    <input type="email" name="email" id="email" required>
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
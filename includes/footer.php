    </main>
    <footer class="footer">
        <div class="container">
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 2rem; text-align: left;">
                <div>
                    <h4>Age of Donnation</h4>
                    <p>Plateforme solidaire 100% bénévolat pour faciliter le don et l'entraide.</p>
                </div>
                <div>
                    <h4>Liens rapides</h4>
                    <ul style="list-style: none; padding: 0;">
                        <li><a href="../index.php" style="color: white; text-decoration: none;">Accueil</a></li>
                        <li><a href="../auth/signup.php" style="color: white; text-decoration: none;">S'inscrire</a></li>
                        <li><a href="../livreur/inscription.php" style="color: white; text-decoration: none;">Devenir livreur</a></li>
                    </ul>
                </div>
                <div>
                    <h4>Contact</h4>
                    <p>Email: contact@ageofdonnation.org</p>
                    <p>Téléphone: +212 5 XX XX XX XX</p>
                </div>
            </div>
            <hr style="margin: 2rem 0; border-color: #555;">
            <p>&copy; 2024 Age of Donnation. Tous droits réservés.</p>
        </div>
    </footer>
    
    <script>
    function confirmAction(message) {
        return confirm(message || 'Êtes-vous sûr de vouloir effectuer cette action ?');
    }
    
    function showAlert(message, type = 'info') {
        const alert = document.createElement('div');
        alert.className = `alert alert-${type}`;
        alert.textContent = message;
        alert.style.position = 'fixed';
        alert.style.top = '100px';
        alert.style.right = '20px';
        alert.style.zIndex = '9999';
        document.body.appendChild(alert);
        
        setTimeout(() => {
            alert.remove();
        }, 5000);
    }
    </script>
</body>
</html>
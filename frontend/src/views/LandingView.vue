<template>
  <div class="landing-page">
    <!-- Header -->
    <header class="landing-header">
      <div class="container">
        <div class="header-content">
          <div class="logo">
            <span class="logo-icon">W</span>
            <span class="logo-text">eMatChef</span>
          </div>
          <nav class="header-nav">
            <router-link to="/login" class="nav-link">Anmelden</router-link>
            <button class="btn-primary">Registrieren</button>
          </nav>
        </div>
      </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
      <div class="container">
        <div class="hero-content">
          <h1 class="hero-title">Materialverwaltung<br />für Vermietungen</h1>
          <p class="hero-subtitle">
            Professionelle Verwaltung Ihrer Vermietartikel – einfach, effizient und übersichtlich
          </p>
          <div class="hero-actions">
            <router-link to="/login" class="btn-primary btn-large">Jetzt starten</router-link>
            <button class="btn-secondary btn-large">Mehr erfahren</button>
          </div>
        </div>
      </div>
    </section>

    <!-- Features Section -->
    <section class="features">
      <div class="container">
        <h2 class="section-title">Warum eMatChef?</h2>
        <div class="features-grid">
          <div class="feature-card">
            <div class="feature-icon">📦</div>
            <h3 class="feature-title">Materialverwaltung</h3>
            <p class="feature-description">
              Übersichtliche Verwaltung aller Vermietartikel mit detaillierten Informationen
            </p>
          </div>
          <div class="feature-card">
            <div class="feature-icon">📅</div>
            <h3 class="feature-title">Ausleihverwaltung</h3>
            <p class="feature-description">
              Einfache Verwaltung von Ausleihen mit automatischer Verfügbarkeitsprüfung
            </p>
          </div>
          <div class="feature-card">
            <div class="feature-icon">📊</div>
            <h3 class="feature-title">Dashboard & Reports</h3>
            <p class="feature-description">
              Übersichtliche Dashboards und detaillierte Reports für bessere Entscheidungen
            </p>
          </div>
          <div class="feature-card">
            <div class="feature-icon">👥</div>
            <h3 class="feature-title">Multi-User</h3>
            <p class="feature-description">
              Unterstützung für mehrere Benutzer mit rollenbasierter Zugriffskontrolle
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- Footer -->
    <footer class="landing-footer">
      <div class="container">
        <div class="footer-content">
          <div class="footer-section">
            <h4 class="footer-title">eMatChef</h4>
            <p class="footer-text">Materialverwaltung für Vermietungen</p>
          </div>
          <div class="footer-section">
            <h4 class="footer-title">Links</h4>
            <a href="#" class="footer-link">Über uns</a>
            <a href="#" class="footer-link">Kontakt</a>
            <a href="#" class="footer-link">Impressum</a>
          </div>
          <div class="footer-section">
            <h4 class="footer-title">Support</h4>
            <a href="#" class="footer-link">Hilfe</a>
            <a href="#" class="footer-link">Dokumentation</a>
          </div>
        </div>
        <div class="footer-bottom">
          <p>&copy; 2025 eMatChef. Alle Rechte vorbehalten.</p>
        </div>
      </div>
    </footer>
  </div>
</template>

<script setup lang="ts">
</script>

<style scoped>
.landing-page {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

.container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 24px;
}

/* Header */
.landing-header {
  background: white;
  border-bottom: 1px solid #e5e7eb;
  padding: 16px 0;
  position: sticky;
  top: 0;
  z-index: 100;
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.logo {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 24px;
  font-weight: 700;
  color: #1f2937;
}

.logo-icon {
  width: 40px;
  height: 40px;
  background: #3b82f6;
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  font-weight: 700;
}

.header-nav {
  display: flex;
  align-items: center;
  gap: 16px;
}

.nav-link {
  color: #4b5563;
  text-decoration: none;
  font-weight: 500;
  transition: color 0.2s;
}

.nav-link:hover {
  color: #1f2937;
}

/* Buttons use shared ui/buttons.css */

/* Hero Section */
.hero {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  padding: 120px 0;
  text-align: center;
}

.hero-content {
  max-width: 800px;
  margin: 0 auto;
}

.hero-title {
  font-size: 56px;
  font-weight: 800;
  line-height: 1.1;
  margin-bottom: 24px;
}

.hero-subtitle {
  font-size: 20px;
  opacity: 0.9;
  margin-bottom: 40px;
  line-height: 1.6;
}

.hero-actions {
  display: flex;
  gap: 16px;
  justify-content: center;
}

/* Features Section */
.features {
  padding: 100px 0;
  background: #f9fafb;
}

.section-title {
  text-align: center;
  font-size: 40px;
  font-weight: 700;
  color: #1f2937;
  margin-bottom: 60px;
}

.features-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 32px;
}

.feature-card {
  background: white;
  padding: 32px;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  text-align: center;
  transition: transform 0.2s, box-shadow 0.2s;
}

.feature-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.feature-icon {
  font-size: 48px;
  margin-bottom: 16px;
}

.feature-title {
  font-size: 20px;
  font-weight: 600;
  color: #1f2937;
  margin-bottom: 12px;
}

.feature-description {
  color: #6b7280;
  line-height: 1.6;
}

/* Footer */
.landing-footer {
  background: #1f2937;
  color: white;
  padding: 60px 0 24px;
  margin-top: auto;
}

.footer-content {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 40px;
  margin-bottom: 40px;
}

.footer-title {
  font-size: 18px;
  font-weight: 600;
  margin-bottom: 16px;
}

.footer-text {
  color: #9ca3af;
  line-height: 1.6;
}

.footer-link {
  display: block;
  color: #9ca3af;
  text-decoration: none;
  margin-bottom: 8px;
  transition: color 0.2s;
}

.footer-link:hover {
  color: white;
}

.footer-bottom {
  border-top: 1px solid #374151;
  padding-top: 24px;
  text-align: center;
  color: #9ca3af;
}

@media (max-width: 768px) {
  .hero-title {
    font-size: 36px;
  }
  
  .hero-actions {
    flex-direction: column;
  }
  
  .features-grid {
    grid-template-columns: 1fr;
  }
}
</style>

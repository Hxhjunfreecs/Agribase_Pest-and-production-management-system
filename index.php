<?php

require 'includes/config.php';
require 'includes/functions.php';

$stmt = $pdo->query("
    SELECT title, crop, risk_level, content, created_at
    FROM advisories
    WHERE status = 'Published'
    ORDER BY created_at DESC
    LIMIT 3
");

$latestAdvisories = $stmt->fetchAll();

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="AgriBase supports rice and coconut growers with farm monitoring, crop assessments, incident reporting, and agricultural advisories.">
    <title>AgriBase · Agricultural Monitoring</title>
    <script src="assets/theme.js?v=20261001-theme-contrast"></script>
    <script>
        (() => {
            const language = ["en", "fil", "ceb"].includes(localStorage.getItem("agribase.language"))
                ? localStorage.getItem("agribase.language")
                : "en";
            document.documentElement.lang = language;
        })();
    </script>
    <link rel="stylesheet" href="assets/style.css?v=20261001-button-motion-4">
</head>
<body class="public-home">
    <header class="public-header">
        <a class="public-brand" href="#home" aria-label="AgriBase home">
            <span class="brand-mark">A</span>
            <span>AgriBase</span>
        </a>

        <nav class="public-nav" aria-label="Main navigation">
            <a href="#home">Home</a>
            <a href="#account">Account</a>
            <a href="#about">About</a>
            <a href="#advisories">Advisories</a>
        </nav>

        <div class="public-header-actions">
            <?php if (user()): ?>
                <a class="public-signin" href="dashboard.php">Dashboard <span aria-hidden="true">→</span></a>
            <?php else: ?>
                <a class="public-signin" href="login.php">Sign in <span aria-hidden="true">→</span></a>
            <?php endif; ?>

            <details class="account-options public-options">
                <summary
                    class="account-options-trigger"
                    aria-label="Options"
                    title="Options"
                >
                    <span class="account-options-icon" aria-hidden="true">⚙</span>
                    <span class="visually-hidden" data-i18n="options">Options</span>
                </summary>
                <div class="account-options-panel">
                    <label class="account-options-language" for="language-switch">
                        <span>Language</span>
                        <select id="language-switch" class="account-options-select" aria-label="Change language" data-i18n-aria="change_language">
                            <option value="en">English</option>
                            <option value="fil">Filipino</option>
                            <option value="ceb">Cebuano</option>
                        </select>
                    </label>
                    <button
                        class="theme-toggle account-options-theme"
                        id="theme-toggle"
                        type="button"
                        role="switch"
                        aria-checked="false"
                        aria-label="Switch to dark mode"
                        data-i18n-aria="switch_to_dark"
                        title="Switch to dark mode"
                        data-i18n-title="switch_to_dark"
                    >
                        <span class="theme-icon" aria-hidden="true">☾</span>
                        <span class="theme-toggle-label" data-i18n="switch_to_dark">Switch to dark mode</span>
                        <span class="theme-switch" aria-hidden="true"><span class="theme-switch-thumb"></span></span>
                    </button>
                    <?php if (user()): ?>
                        <a class="account-options-logout" href="logout.php"><span>Logout</span></a>
                    <?php endif; ?>
                </div>
            </details>
        </div>
    </header>

    <main>
        <section class="public-hero" id="home">
            <img
                class="public-hero-image"
                src="assets/farm-landscape.jpg"
                alt="Green agricultural fields stretching across a rural landscape"
                fetchpriority="high"
            >
            <div class="public-hero-shade"></div>
            <div class="public-hero-content">
                <p class="public-kicker">RICE &amp; COCONUT PRODUCTION MONITORING</p>
                <h1>AgriBase</h1>
                <p class="public-hero-copy">Clearer farm records. Earlier signals. Better-informed decisions for local agriculture.</p>
                <div class="public-hero-actions">
                    <a class="public-button light" href="login.php">Sign in <span aria-hidden="true">→</span></a>
                    <a class="public-text-link" href="#about">Explore AgriBase <span aria-hidden="true">↓</span></a>
                </div>
            </div>
            <div class="public-hero-caption">FIELD MONITORING <span>·</span> CROP HEALTH <span>·</span> COMMUNITY GUIDANCE</div>
        </section>

        <section class="public-section account-section" id="account">
            <div class="public-section-inner account-layout">
                <div class="public-section-lead">
                    <p class="public-kicker">YOUR AGRIBASE ACCOUNT</p>
                    <h2>One workspace for the people who grow and support our crops.</h2>
                </div>
                <div class="account-details">
                    <div class="account-row">
                        <span class="account-index">01</span>
                        <div>
                            <h3>For farmers</h3>
                            <p>Keep farm details together, record crop checks, report field incidents, and follow guidance relevant to rice and coconut production.</p>
                        </div>
                        <a href="register.php" aria-label="Create a farmer account">Create account <span aria-hidden="true">↗</span></a>
                    </div>
                    <div class="account-row">
                        <span class="account-index">02</span>
                        <div>
                            <h3>For administrators</h3>
                            <p>Review farm submissions and reports, monitor incident risks, and share agricultural advisories with registered farmers.</p>
                        </div>
                        <a href="login.php" aria-label="Sign in to the administrator account">Administrator sign in <span aria-hidden="true">↗</span></a>
                    </div>
                </div>
            </div>
        </section>

        <section class="public-section about-section" id="about">
            <div class="public-section-inner">
                <div class="about-heading">
                    <p class="public-kicker">ABOUT THE PLATFORM</p>
                    <h2>Local field information, organized for action.</h2>
                    <p>AgriBase is a rice and coconut production monitoring and pest decision-support system. It brings observations, follow-up, and practical guidance into one shared workflow.</p>
                </div>
                <div class="feature-grid">
                    <article class="feature-item">
                        <span class="feature-index">01 / RECORD</span>
                        <h3>Farm and crop records</h3>
                        <p>Maintain farm profiles and structured crop assessments that make field conditions easier to review over time.</p>
                    </article>
                    <article class="feature-item">
                        <span class="feature-index">02 / REVIEW</span>
                        <h3>Incident follow-up</h3>
                        <p>Document crop concerns, track review status, and help teams prioritize cases that need attention.</p>
                    </article>
                    <article class="feature-item">
                        <span class="feature-index">03 / RESPOND</span>
                        <h3>Practical advisories</h3>
                        <p>Share crop-specific guidance and monitoring recommendations with the farming community.</p>
                    </article>
                </div>
            </div>
        </section>

        <?php
        $farmGalleryVariant = 'public';
        require __DIR__ . '/includes/farm_gallery.php';
        unset($farmGalleryVariant);
        ?>

        <section class="public-section advisories-section" id="advisories">
            <div class="public-section-inner">
                <div class="advisories-heading">
                    <div>
                        <p class="public-kicker">LATEST FIELD GUIDANCE</p>
                        <h2>Agricultural advisories</h2>
                    </div>
                    <p>Published guidance for current crop monitoring and field conditions.</p>
                </div>

                <?php if ($latestAdvisories): ?>
                    <div class="public-advisory-grid">
                        <?php foreach ($latestAdvisories as $advisory): ?>
                            <article class="public-advisory">
                                <div class="public-advisory-meta">
                                    <span><?= e($advisory['crop']) ?></span>
                                </div>
                                <h3><?= e($advisory['title']) ?></h3>
                                <p><?= nl2br(e($advisory['content'])) ?></p>
                                <time datetime="<?= e(date('Y-m-d', strtotime($advisory['created_at']))) ?>">
                                    <?= e(date('M d, Y', strtotime($advisory['created_at']))) ?>
                                </time>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="public-empty">New advisories will appear here when published.</p>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <footer class="public-footer">
        <a class="public-brand" href="#home"><span class="brand-mark">A</span><span>AgriBase</span></a>
        <p>Rice &amp; Coconut Production Monitoring and Pest Decision Support</p>
        <a href="login.php">Sign in <span aria-hidden="true">→</span></a>
    </footer>
    <script src="assets/i18n.js?v=20260929-5"></script>
    <script>
        const languageSwitch = document.getElementById("language-switch");
        const themeToggle = document.getElementById("theme-toggle");

        languageSwitch.value = document.documentElement.lang;
        window.applyAgriBaseLanguage(languageSwitch.value);

        languageSwitch.addEventListener("change", () => {
            localStorage.setItem("agribase.language", languageSwitch.value);
            window.applyAgriBaseLanguage(languageSwitch.value);
        });

        const updateThemeToggle = () => {
            const isDark = document.documentElement.dataset.theme === "dark";
            themeToggle.setAttribute("aria-checked", String(isDark));
            themeToggle.querySelector(".theme-icon").textContent = isDark ? "☼" : "☾";
            const nextMode = isDark ? "switch_to_light" : "switch_to_dark";
            themeToggle.querySelector(".theme-toggle-label").dataset.i18n = nextMode;
            themeToggle.dataset.i18nAria = nextMode;
            themeToggle.dataset.i18nTitle = themeToggle.dataset.i18nAria;
            window.applyAgriBaseLanguage(languageSwitch.value);
        };

        themeToggle.addEventListener("click", () => {
            const theme = document.documentElement.dataset.theme === "dark" ? "light" : "dark";
            document.documentElement.dataset.theme = theme;
            localStorage.setItem("agribase.theme", theme);
            updateThemeToggle();
        });

        updateThemeToggle();
    </script>
</body>
</html>

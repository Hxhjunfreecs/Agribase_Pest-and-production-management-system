<?php

function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

function verifyCsrfToken(?string $token): bool
{
    $expected = $_SESSION['csrf_token'] ?? null;
    $valid = is_string($expected) && is_string($token) && hash_equals($expected, $token);

    if ($valid) {
        unset($_SESSION['csrf_token']);
    }

    return $valid;
}

function redirect($url)
{
    header('Location: ' . $url);
    exit;
}

function flash($type, $message)
{
    $_SESSION['flash'] = [
        'type' => $type,
        'msg' => $message
    ];
}

function getFlash()
{
    $flash = $_SESSION['flash'] ?? null;

    unset($_SESSION['flash']);

    return $flash;
}

function user()
{
    return $_SESSION['user'] ?? null;
}

function requireLogin()
{
    if (!user()) {
        redirect('index.php');
    }
}

function requireAdmin()
{
    requireLogin();

    if (user()['role'] !== 'admin') {
        redirect('dashboard.php');
    }
}

function requireFarmer()
{
    requireLogin();

    if (user()['role'] !== 'farmer') {
        redirect('dashboard.php');
    }
}


/*
|--------------------------------------------------------------------------
| FARM ACCESS
|--------------------------------------------------------------------------
*/

function canViewFarm($pdo, $farmId)
{
    if (user()['role'] === 'admin') {
        return true;
    }

    $stmt = $pdo->prepare("
        SELECT id
        FROM farms
        WHERE id = ?
          AND farmer_id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $farmId,
        user()['id']
    ]);

    return (bool) $stmt->fetchColumn();
}


/*
|--------------------------------------------------------------------------
| MONITORING ACCESS
|--------------------------------------------------------------------------
|
| A farm can only be used for assessments, incidents, and farmer
| reports after the administrator has approved it.
|
*/

function canMonitorFarm($pdo, $farmId)
{
    $stmt = $pdo->prepare("
        SELECT id
        FROM farms
        WHERE id = ?
          AND approval_status = 'Approved'
    ");

    $stmt->execute([
        $farmId
    ]);

    if (!$stmt->fetchColumn()) {
        return false;
    }

    if (user()['role'] === 'admin') {
        return true;
    }

    $stmt = $pdo->prepare("
        SELECT id
        FROM farms
        WHERE id = ?
          AND farmer_id = ?
          AND approval_status = 'Approved'
        LIMIT 1
    ");

    $stmt->execute([
        $farmId,
        user()['id']
    ]);

    return (bool) $stmt->fetchColumn();
}


/*
|--------------------------------------------------------------------------
| BACKWARD-COMPATIBLE FARM ACCESS
|--------------------------------------------------------------------------
|
| Keep this helper for existing pages that need to check whether the
| logged-in user may access an approved farm.
|
*/

function canAccessFarm($pdo, $farmId)
{
    return canMonitorFarm($pdo, $farmId);
}


/*
|--------------------------------------------------------------------------
| RISK FUNCTIONS
|--------------------------------------------------------------------------
*/

function availableIncidentAssessmentProcedures()
{
    return [
        'inspect_nearby' => 'Inspect affected crops and nearby plants',
        'remove_affected' => 'Safely remove severely affected plant parts',
        'improve_sanitation' => 'Improve field sanitation and remove crop debris',
        'contain_spread' => 'Limit movement of tools and plant material from the affected area',
        'approved_treatment' => 'Use only crop-approved treatments as directed on the label',
        'schedule_follow_up' => 'Schedule a follow-up field inspection',
        'contact_extension' => 'Consult local agricultural extension staff'
    ];
}

function incidentAnalyticsData(PDO $pdo, string $riskFilter = 'All'): array
{
    $validRisks = ['Low', 'Moderate', 'High'];
    if (!in_array($riskFilter, $validRisks, true)) {
        $riskFilter = 'All';
    }

    $sql = "
        SELECT f.barangay, p.risk, p.crop, p.problem, i.status, COUNT(*) AS total
        FROM incidents i
        INNER JOIN pest_assessments p ON p.id = i.assessment_id
        INNER JOIN farms f ON f.id = i.farm_id
    ";
    $params = [];

    if ($riskFilter !== 'All') {
        $sql .= ' WHERE p.risk = ?';
        $params[] = $riskFilter;
    }

    $sql .= ' GROUP BY f.barangay, p.risk, p.crop, p.problem, i.status';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $data = [
        'barangays' => [],
        'risk_by_barangay' => [],
        'risks' => ['Low' => 0, 'Moderate' => 0, 'High' => 0],
        'problems' => [],
        'crops' => []
    ];

    foreach ($stmt as $row) {
        $total = (int) $row['total'];
        $barangay = (string) $row['barangay'];
        $risk = (string) $row['risk'];
        $crop = (string) $row['crop'];
        $problem = trim((string) $row['problem']) ?: 'Unspecified incident';

        $data['barangays'][$barangay] = ($data['barangays'][$barangay] ?? 0) + $total;
        $data['risk_by_barangay'][$barangay][$risk] = ($data['risk_by_barangay'][$barangay][$risk] ?? 0) + $total;
        $data['risks'][$risk] = ($data['risks'][$risk] ?? 0) + $total;
        $data['problems'][$problem] = ($data['problems'][$problem] ?? 0) + $total;
        $data['crops'][$crop] = ($data['crops'][$crop] ?? 0) + $total;
    }

    arsort($data['barangays']);
    arsort($data['problems']);
    arsort($data['crops']);
    ksort($data['risk_by_barangay']);

    return $data;
}

function riskFromAssessment($area, $severity, $symptoms)
{
    $score = 0;

    $area = (float) $area;

    if ($area >= 50) {
        $score += 3;
    } elseif ($area >= 20) {
        $score += 2;
    } else {
        $score += 1;
    }

    if ($severity === 'Severe') {
        $score += 3;
    } elseif ($severity === 'Moderate') {
        $score += 2;
    } else {
        $score += 1;
    }

    $symptomCount = count($symptoms);

    if ($symptomCount >= 4) {
        $score += 2;
    } elseif ($symptomCount >= 2) {
        $score += 1;
    }

    if ($score >= 7) {
        return 'High';
    }

    if ($score >= 4) {
        return 'Moderate';
    }

    return 'Low';
}

function estimatedProductionRateFromReport($severity, $affectedArea)
{
    $impactBySeverity = [
        'Minor' => 0.25,
        'Moderate' => 0.5,
        'Severe' => 0.75
    ];

    if (!isset($impactBySeverity[$severity])) {
        return null;
    }

    $area = max(0, min(100, (float) $affectedArea));

    return round(100 - ($area * $impactBySeverity[$severity]), 1);
}

function cropOutlookFromProductionRate($rate)
{
    if (!is_numeric($rate)) {
        return 'Not enough assessment data for an outlook.';
    }

    if ((float) $rate >= 85) {
        return 'Production outlook is favorable if current conditions continue.';
    }

    if ((float) $rate >= 70) {
        return 'Production may be affected; monitor the crop and follow the recommended actions.';
    }

    return 'Production may be reduced if symptoms continue; follow the recommended actions and reassess.';
}

function problemFromSymptoms($crop, $symptoms)
{
    $symptoms = array_map(
        'strtolower',
        $symptoms
    );

    if (
        in_array('holes', $symptoms, true) ||
        in_array('insects visible', $symptoms, true)
    ) {
        if ($crop === 'Rice') {
            return 'Possible insect damage';
        }

        return 'Possible insect or feeding damage';
    }

    if (
        in_array('spots', $symptoms, true) ||
        in_array('discoloration', $symptoms, true)
    ) {
        return 'Possible leaf or tissue disease symptoms';
    }

    if (
        in_array('wilting', $symptoms, true) ||
        in_array('yellowing', $symptoms, true)
    ) {
        return 'Possible plant stress symptoms';
    }

    return 'General crop condition concern';
}

function recommendation($risk, $crop)
{
    if ($risk === 'High') {
        return
            'Conduct prompt field verification, document affected areas, and coordinate appropriate agricultural intervention.';
    }

    if ($risk === 'Moderate') {
        return
            'Increase monitoring frequency, document changes, and review the applicable agricultural advisory.';
    }

    return
        'Continue routine monitoring and record any change in symptoms.';
}


/*
|--------------------------------------------------------------------------
| PAGE HEADER
|--------------------------------------------------------------------------
*/

function layoutHeader($title, $active = '')
{
    $currentUser = user();

    $isAdmin =
        $currentUser &&
        $currentUser['role'] === 'admin';

    if ($isAdmin) {
        $nav = [
            ['dashboard.php', 'Overview', 'dashboard'],
            ['farmers.php', 'Farmers & Farms', 'farmers'],
            ['incidents.php', 'Incidents', 'incidents'],
            ['map.php', 'Risk Map', 'map'],
            ['analytics.php', 'Analytics', 'analytics'],
            ['advisories.php', 'Advisories', 'advisories']
        ];

        $roleClass = 'admin-theme';
        $roleLabel = 'Administrator';
        $roleMark = 'DA';
    } else {
        $nav = [
            ['dashboard.php', 'My Dashboard', 'dashboard'],
            ['farm.php', 'My Farms', 'farms'],
            ['farmer_production_assessment.php', 'Crop Health', 'production'],
            ['incidents.php', 'My Incidents', 'incidents'],
            ['timeline.php', 'Crop History', 'timeline'],
            ['farmer_pest_assessment.php', 'Pest Check', 'assessment'],
            ['advisories.php', 'Advisories', 'advisories']
        ];

        $roleClass = 'farmer-theme';
        $roleLabel = 'Farmer Portal';
        $roleMark = 'F';
    }

    echo '
    <!doctype html>

    <html lang="en">

    <head>

        <meta charset="utf-8">

        <meta
            name="viewport"
            content="width=device-width, initial-scale=1"
        >
        <title>
            ' . e($title) . ' · AgriBase
        </title>

        <script src="assets/theme.js?v=20261001-theme-contrast"></script>
        <script>
            (() => {
                const savedLanguage = localStorage.getItem("agribase.language");
                const language = savedLanguage === "fil" || savedLanguage === "ceb"
                    ? savedLanguage
                    : "en";
                document.documentElement.lang = language;
            })();
        </script>

        <link
            rel="stylesheet"
            href="assets/style.css?v=20261001-button-motion-4"
        >

    </head>

    <body class="' . $roleClass . '">

        <header class="topbar">

            <div class="topbar-inner">

                <a
                    class="brand"
                    href="dashboard.php"
                >
                    <span class="brand-mark">
                        A
                    </span>

                    <span>
                        AgriBase
                    </span>
                </a>

                <div class="role-label">
                    <span class="role-mark">
                        ' . $roleMark . '
                    </span>

                    <span>
                        <span data-i18n="' . strtolower(str_replace(' ', '_', $roleLabel)) . '">' . $roleLabel . '</span>
                    </span>
                </div>

                <nav class="nav">
    ';

    foreach ($nav as $item) {
        $activeClass =
            $active === $item[2]
                ? 'active'
                : '';

        echo '
                    <a
                        class="' . $activeClass . '"
                        href="' . $item[0] . '"
                    >
                        <span data-i18n="' . strtolower(str_replace([' ', '&'], ['_', 'and'], $item[1])) . '">' . $item[1] . '</span>
                    </a>
        ';
    }

    echo '
                </nav>

                <details class="account-options">
                    <summary
                        class="account-options-trigger"
                        aria-label="Options"
                        title="Options"
                    >
                        <span class="account-options-icon" aria-hidden="true">⚙</span>
                        <span class="visually-hidden" data-i18n="options">Options</span>
                    </summary>
                    <div class="account-options-panel">
                        <p class="account-options-user">' . e($currentUser['full_name'] ?? 'Guest') . '</p>
                        <label class="account-options-language" for="language-switch">
                            <span data-i18n="language">Language</span>
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
                        <a class="account-options-logout" href="logout.php">
                            <span data-i18n="logout">Logout</span>
                        </a>
                    </div>
                </details>

            </div>

        </header>

        <main class="page">
    ';

    $flash = getFlash();

    if ($flash) {
        echo '
            <div class="flash ' . e($flash['type']) . '">
                ' . e($flash['msg']) . '
            </div>
        ';
    }
}

function layoutFooter()
{
    echo '
        </main>

        <footer class="footer-note">
            <span data-i18n="footer">AgriBase · Rice & Coconut Production Monitoring and Pest Decision-Support System</span>
        </footer>

        <script>
            const translations = {
                en: {
                    options: "Options",
                    language: "Language",
                    dark_mode: "Dark mode",
                    light_mode: "Light mode",
                    change_language: "Change language",
                    switch_to_dark: "Switch to dark mode",
                    switch_to_light: "Switch to light mode"
                },
                fil: {
                    options: "Mga Opsyon",
                    language: "Wika",
                    dashboard: "Dashboard",
                    administrator: "Tagapangasiwa",
                    farmer_portal: "Portal ng Magsasaka",
                    overview: "Pangkalahatang-ideya",
                    farmers_and_farms: "Mga Magsasaka at Sakahan",
                    incidents: "Mga Insidente",
                    risk_map: "Mapa ng Panganib",
                    analytics: "Pagsusuri ng Datos",
                    reports: "Mga Ulat",
                    advisories: "Mga Pabatid",
                    my_dashboard: "Aking Dashboard",
                    my_farms: "Aking mga Sakahan",
                    pest_check: "Pagsusuri ng Peste",
                    crop_health: "Kalusugan ng Pananim",
                    my_incidents: "Aking mga Insidente",
                    crop_history: "Kasaysayan ng Pananim",
                    my_reports: "Aking mga Ulat",
                    logout: "Mag-logout",
                    footer: "AgriBase · Sistema ng Pagsubaybay sa Palay at Niyog at Pagsuporta sa Pagpapasya ukol sa Peste",
                    dark_mode: "Madilim na mode",
                    light_mode: "Maliwanag na mode",
                    change_language: "Palitan ang wika",
                    switch_to_dark: "Lumipat sa madilim na mode",
                    switch_to_light: "Lumipat sa maliwanag na mode"
                },
                ceb: {
                    options: "Mga Opsyon",
                    language: "Pinulongan",
                    dashboard: "Dashboard",
                    administrator: "Administrador",
                    farmer_portal: "Portal sa Mag-uuma",
                    overview: "Kinatibuk-ang Tan-aw",
                    farmers_and_farms: "Mga Mag-uuma ug Uma",
                    incidents: "Mga Insidente",
                    risk_map: "Mapa sa Risgo",
                    analytics: "Pagtuki sa Datos",
                    reports: "Mga Report",
                    advisories: "Mga Pahimangno",
                    my_dashboard: "Akong Dashboard",
                    my_farms: "Akong mga Uma",
                    pest_check: "Pagsusi sa Peste",
                    crop_health: "Kahimsog sa Tanom",
                    my_incidents: "Akong mga Insidente",
                    crop_history: "Kasaysayan sa Tanom",
                    my_reports: "Akong mga Report",
                    logout: "Pag-log out",
                    footer: "AgriBase · Sistema sa Pagmonitor sa Humay ug Lubi ug Pagsuporta sa Desisyon bahin sa Peste",
                    dark_mode: "Ngitngit nga tema",
                    light_mode: "Hayag nga tema",
                    change_language: "Usba ang pinulongan",
                    switch_to_dark: "Balhin sa ngitngit nga tema",
                    switch_to_light: "Balhin sa hayag nga tema"
                }
            };

            window.applyAgriBaseLanguage = (language) => {
                const selectedLanguage = translations[language] ? language : "en";
                document.documentElement.lang = language;
                document.querySelectorAll("[data-i18n]").forEach((element) => {
                    const key = element.dataset.i18n;
                    if (!element.dataset.english) {
                        element.dataset.english = element.textContent;
                    }
                    element.textContent = translations[selectedLanguage][key]
                        || element.dataset.english;
                });
                document.querySelectorAll("[data-i18n-aria]").forEach((element) => {
                    const key = element.dataset.i18nAria;
                    element.setAttribute(
                        "aria-label",
                        translations[selectedLanguage][key] || key
                    );
                });
                document.querySelectorAll("[data-i18n-title]").forEach((element) => {
                    const key = element.dataset.i18nTitle;
                    element.setAttribute(
                        "title",
                        translations[selectedLanguage][key] || key
                    );
                });
                document.documentElement.lang = selectedLanguage;
            };

            const languageSwitch = document.getElementById("language-switch");
            const themeToggle = document.getElementById("theme-toggle");
            const savedLanguage = translations[localStorage.getItem("agribase.language")]
                ? localStorage.getItem("agribase.language")
                : "en";

            languageSwitch.value = savedLanguage;
            window.applyAgriBaseLanguage(savedLanguage);

            languageSwitch.addEventListener("change", () => {
                localStorage.setItem("agribase.language", languageSwitch.value);
                window.applyAgriBaseLanguage(languageSwitch.value);
            });

            const updateThemeToggle = () => {
                const isDark = document.documentElement.dataset.theme === "dark";
                themeToggle.setAttribute("aria-checked", String(isDark));
                themeToggle.querySelector(".theme-icon").textContent = isDark
                    ? "☼"
                    : "☾";
                const nextMode = isDark ? "switch_to_light" : "switch_to_dark";
                themeToggle.querySelector(".theme-toggle-label").dataset.i18n = nextMode;
                themeToggle.dataset.i18nAria = isDark
                    ? "switch_to_light"
                    : "switch_to_dark";
                themeToggle.dataset.i18nTitle = themeToggle.dataset.i18nAria;
                window.applyAgriBaseLanguage(languageSwitch.value);
            };

            themeToggle.addEventListener("click", () => {
                const theme = document.documentElement.dataset.theme === "dark"
                    ? "light"
                    : "dark";
                document.documentElement.dataset.theme = theme;
                localStorage.setItem("agribase.theme", theme);
                updateThemeToggle();
            });

            updateThemeToggle();
        </script>

        <script src="assets/i18n.js?v=20260929-5"></script>

        </body>

    </html>
    ';
}

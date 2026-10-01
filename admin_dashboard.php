<?php

require 'includes/config.php';
require 'includes/functions.php';

requireAdmin();


/*
|--------------------------------------------------------------------------
| DASHBOARD COUNTS
|--------------------------------------------------------------------------
*/

$incidents =
    (int) $pdo->query("
        SELECT COUNT(*)
        FROM incidents
    ")->fetchColumn();

$farmers =
    (int) $pdo->query("
        SELECT COUNT(*)
        FROM users
        WHERE role = 'farmer'
    ")->fetchColumn();

$farms =
    (int) $pdo->query("
        SELECT COUNT(*)
        FROM farms
        WHERE approval_status = 'Approved'
    ")->fetchColumn();

$pendingFarms =
    (int) $pdo->query("
        SELECT COUNT(*)
        FROM farms
        WHERE approval_status = 'Pending'
    ")->fetchColumn();

$pendingIncidents =
    (int) $pdo->query("
        SELECT COUNT(*)
        FROM incidents
        WHERE status IN (
            'Reported',
            'Under Review'
        )
    ")->fetchColumn();

$verified =
    (int) $pdo->query("
        SELECT COUNT(*)
        FROM incidents
        WHERE status = 'Verified'
    ")->fetchColumn();


/*
|--------------------------------------------------------------------------
| RISK DISTRIBUTION
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        risk,
        COUNT(*) AS total

    FROM pest_assessments

    GROUP BY risk
");

$risks = [
    'High' => 0,
    'Moderate' => 0,
    'Low' => 0
];

foreach ($stmt as $row) {
    $risks[$row['risk']] =
        (int) $row['total'];
}

$totalRiskRecords =
    max(
        1,
        array_sum($risks)
    );


/*
|--------------------------------------------------------------------------
| RECENT INCIDENTS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        i.id,
        i.status,
        i.created_at,

        p.crop,
        p.risk,

        f.farm_name,
        f.barangay,

        u.full_name AS farmer

    FROM incidents i

    INNER JOIN pest_assessments p
        ON p.id = i.assessment_id

    INNER JOIN farms f
        ON f.id = i.farm_id

    INNER JOIN users u
        ON u.id = f.farmer_id

    ORDER BY i.created_at DESC

    LIMIT 8
");

$recentIncidents =
    $stmt->fetchAll();


layoutHeader(
    'Administrator Dashboard',
    'dashboard'
);

?>

<div class="admin-hero">

    <div>

        <div class="eyebrow">
            AGRICULTURAL INTELLIGENCE CENTER
        </div>

        <h1>
            Administrator Dashboard
        </h1>

        <p>
            Monitor approved farms, review agricultural
            conditions and validate field incidents.
        </p>

    </div>

</div>


<!-- =========================================================
     SUMMARY
========================================================== -->

<div class="admin-kpis">

    <div class="admin-kpi">

        <span>
            Registered Farmers
        </span>

        <strong>
            <?= $farmers ?>
        </strong>

        <small>
            Farmer accounts
        </small>

    </div>


    <div class="admin-kpi">

        <span>
            Approved Farms
        </span>

        <strong>
            <?= $farms ?>
        </strong>

        <small>
            Available for monitoring
        </small>

    </div>


    <div class="admin-kpi">

        <span>
            Pending Farms
        </span>

        <strong>
            <?= $pendingFarms ?>
        </strong>

        <small>
            Waiting for validation
        </small>

    </div>


    <div class="admin-kpi danger">

        <span>
            Pest Incidents
        </span>

        <strong>
            <?= $incidents ?>
        </strong>

        <small>
            <?= $pendingIncidents ?>
            requiring attention
        </small>

    </div>


    <div class="admin-kpi success">

        <span>
            Verified Incidents
        </span>

        <strong>
            <?= $verified ?>
        </strong>

        <small>
            Verified records
        </small>

    </div>

</div>


<!-- =========================================================
     DIRECT ADMIN ACTIONS
========================================================== -->

<section class="admin-panel">

    <div class="panel-title">

        <div>

            <span class="eyebrow">
                MONITORING ACTIONS
            </span>

            <h2>
                Administrative Work Areas
            </h2>

        </div>

    </div>


    <div class="admin-action-row">

        <a
            class="admin-action-card"
            href="farmers.php"
        >
            <strong>
                Farmers & Farms
            </strong>

            <span>
                Validate farmer-owned farms
            </span>
        </a>


        <a
            class="admin-action-card"
            href="admin_pest_assessment.php"
        >
            <strong>
                Pest Assessment
            </strong>

            <span>
                Record a field observation
            </span>
        </a>


        <a
            class="admin-action-card"
            href="admin_production_assessment.php"
        >
            <strong>
                Crop Health
            </strong>

            <span>
                Assess an approved farm
            </span>
        </a>


        <a
            class="admin-action-card"
            href="incidents.php"
        >
            <strong>
                Incident Review
            </strong>

            <span>
                Verify and manage incidents
            </span>
        </a>


    </div>

</section>


<!-- =========================================================
     RISK DISTRIBUTION
========================================================== -->

<section
    class="admin-panel"
    style="margin-top: 22px"
>

    <div class="panel-title">

        <div>

            <span class="eyebrow">
                DECISION SUPPORT
            </span>

            <h2>
                Pest Risk Distribution
            </h2>

        </div>

        <a href="analytics.php">
            Open Analytics →
        </a>

    </div>


    <div class="admin-risk-list">

        <?php foreach ($risks as $risk => $count): ?>

            <div class="admin-risk-row">

                <div>

                    <span
                        class="
                            risk-dot
                            <?= strtolower($risk) ?>
                        "
                    ></span>

                    <strong>
                        <?= e($risk) ?>
                    </strong>

                </div>


                <progress
                    class="analytics-progress <?= e(strtolower($risk)) ?>"
                    value="<?= (int) $count ?>"
                    max="<?= $totalRiskRecords ?>"
                ><?= round(($count / $totalRiskRecords) * 100) ?>%</progress>


                <b>
                    <?= $count ?>
                </b>

            </div>

        <?php endforeach; ?>

    </div>

</section>


<!-- =========================================================
     RECENT INCIDENTS
========================================================== -->

<section
    class="admin-panel"
    style="margin-top: 22px"
>

    <div class="panel-title">

        <div>

            <span class="eyebrow">
                FIELD ACTIVITY
            </span>

            <h2>
                Recent Incidents
            </h2>

        </div>

        <a href="incidents.php">
            View all →
        </a>

    </div>


    <div class="table-wrap">

        <table class="admin-table">

            <thead>

                <tr>

                    <th>
                        Date
                    </th>

                    <th>
                        Farmer
                    </th>

                    <th>
                        Farm
                    </th>

                    <th>
                        Crop
                    </th>

                    <th>
                        Barangay
                    </th>

                    <th>
                        Risk
                    </th>

                    <th>
                        Status
                    </th>

                </tr>

            </thead>

            <tbody>

                <?php foreach ($recentIncidents as $incident): ?>

                    <tr>

                        <td>
                            <?= e(
                                date(
                                    'M d, Y',
                                    strtotime(
                                        $incident['created_at']
                                    )
                                )
                            ) ?>
                        </td>

                        <td>
                            <?= e($incident['farmer']) ?>
                        </td>

                        <td>
                            <?= e($incident['farm_name']) ?>
                        </td>

                        <td>
                            <?= e($incident['crop']) ?>
                        </td>

                        <td>
                            <?= e($incident['barangay']) ?>
                        </td>

                        <td>

                            <span
                                class="
                                    badge
                                    <?= strtolower(
                                        $incident['risk']
                                    ) ?>
                                "
                            >
                                <?= e($incident['risk']) ?>
                            </span>

                        </td>

                        <td>
                            <?= e($incident['status']) ?>
                        </td>

                    </tr>

                <?php endforeach; ?>


                <?php if (!$recentIncidents): ?>

                    <tr>

                        <td
                            colspan="7"
                            class="empty"
                        >
                            No incidents have been recorded.
                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</section>


<?php

layoutFooter();
?>

<?php

require 'includes/config.php';
require 'includes/functions.php';

requireAdmin();


$assessmentCount =
    (int) $pdo->query("
        SELECT COUNT(*)
        FROM pest_assessments
    ")->fetchColumn();

$incidentCount =
    (int) $pdo->query("
        SELECT COUNT(*)
        FROM incidents
    ")->fetchColumn();

$highRiskCount =
    (int) $pdo->query("
        SELECT COUNT(*)
        FROM pest_assessments
        WHERE risk = 'High'
    ")->fetchColumn();

$approvedFarms =
    (int) $pdo->query("
        SELECT COUNT(*)
        FROM farms
        WHERE approval_status = 'Approved'
    ")->fetchColumn();


$stmt = $pdo->query("
    SELECT
        risk,
        COUNT(*) AS total

    FROM pest_assessments

    GROUP BY risk
");

$riskData = [
    'High' => 0,
    'Moderate' => 0,
    'Low' => 0
];

foreach ($stmt as $row) {
    $riskData[$row['risk']] =
        (int) $row['total'];
}

$totalRisk =
    max(
        1,
        array_sum($riskData)
    );

?>
<!doctype html>

<html lang="en">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        AgriBase Analytics Report
    </title>

    <script src="assets/theme.js?v=20261001-theme-contrast"></script>
    <link
        rel="stylesheet"
        href="assets/style.css?v=20261001-button-motion-4"
    >

</head>

<body>

    <main class="page">

        <div class="actions no-print">

            <button
                class="btn"
                type="button"
                onclick="window.print()"
            >
                Print Report
            </button>

            <a
                class="btn secondary"
                href="analytics.php"
            >
                Back
            </a>

        </div>


        <section class="report-sheet">

            <div class="report-header">

                <div>

                    <h1>
                        AgriBase
                    </h1>

                    <div>
                        Agricultural Analytics Report
                    </div>

                </div>

                <div>
                    <?= date('F d, Y') ?>
                </div>

            </div>


            <div class="grid grid-4">

                <div class="card stat">

                    <div class="label">
                        Assessments
                    </div>

                    <div class="value">
                        <?= $assessmentCount ?>
                    </div>

                </div>


                <div class="card stat">

                    <div class="label">
                        Incidents
                    </div>

                    <div class="value">
                        <?= $incidentCount ?>
                    </div>

                </div>


                <div class="card stat">

                    <div class="label">
                        High Risk
                    </div>

                    <div class="value">
                        <?= $highRiskCount ?>
                    </div>

                </div>


                <div class="card stat">

                    <div class="label">
                        Approved Farms
                    </div>

                    <div class="value">
                        <?= $approvedFarms ?>
                    </div>

                </div>

            </div>


            <h2>
                Risk Distribution
            </h2>

            <table class="analytics-risk-table">

                <thead>

                    <tr>
                        <th>
                            Risk
                        </th>

                        <th>
                            Records
                        </th>

                        <th>
                            Percentage
                        </th>
                    </tr>

                </thead>

                <tbody>

                    <?php foreach ($riskData as $risk => $count): ?>

                        <tr>

                            <td>
                                <?= e($risk) ?>
                            </td>

                            <td>
                                <?= $count ?>
                            </td>

                            <td>
                                <?= round(
                                    ($count / $totalRisk) * 100
                                ) ?>%
                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>


            <p style="margin-top: 20px">

                This report summarizes records stored in
                AgriBase. Risk classification is based on
                the system's rule-based assessment process.

            </p>

        </section>

    </main>

    <script src="assets/i18n.js?v=20260929-5"></script>
</body>

</html>

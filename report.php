<?php

require 'includes/config.php';
require 'includes/functions.php';

requireLogin();

$id = (int) (
    $_GET['id'] ?? 0
);

$stmt = $pdo->prepare("
    SELECT
        i.*,

        p.crop,
        p.growth_stage,
        p.plant_part,
        p.symptoms,
        p.affected_area,
        p.severity,
        p.problem,
        p.risk,
        p.recommendation,

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

    WHERE i.id = ?
");

$stmt->execute([
    $id
]);

$incident = $stmt->fetch();

if (
    !$incident ||
    !canAccessFarm(
        $pdo,
        $incident['farm_id']
    )
) {
    die('Incident not found.');
}

$symptoms = json_decode(
    $incident['symptoms'],
    true
) ?: [];

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
        AgriBase Incident #<?= e($incident['id']) ?>
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
                href="incident.php?id=<?= $incident['id'] ?>"
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
                        Agricultural Pest Incident Report
                    </div>

                </div>

                <div>
                    <?= date('F d, Y') ?>
                </div>

            </div>


            <div class="grid grid-2">

                <div>

                    <p>
                        <strong>Incident:</strong>
                        #<?= e($incident['id']) ?>
                    </p>

                    <p>
                        <strong>Farmer:</strong>
                        <?= e($incident['farmer']) ?>
                    </p>

                    <p>
                        <strong>Farm:</strong>
                        <?= e($incident['farm_name']) ?>
                    </p>

                    <p>
                        <strong>Location:</strong>
                        <?= e($incident['barangay']) ?>
                    </p>

                    <p>
                        <strong>Crop:</strong>
                        <?= e($incident['crop']) ?>
                    </p>

                </div>


                <div>

                    <?php if (user()['role'] === 'admin'): ?>
                        <p><strong>Risk:</strong> <?=e($incident['risk'])?></p>
                    <?php else: ?>
                        <?php $productionRate = estimatedProductionRateFromReport($incident['severity'], $incident['affected_area']); ?>
                        <p><strong>Estimated Production Rate:</strong> <?=e(number_format($productionRate, 1))?>%</p>
                        <p><strong>Crop Outlook:</strong> <?=e(cropOutlookFromProductionRate($productionRate))?></p>
                    <?php endif; ?>

                    <p>
                        <strong>Status:</strong>
                        <?= e($incident['status']) ?>
                    </p>

                    <p>
                        <strong>Severity:</strong>
                        <?= e($incident['severity']) ?>
                    </p>

                    <p>
                        <strong>Affected Area:</strong>
                        <?= e(
                            $incident['affected_area']
                        ) ?>%
                    </p>

                    <p>
                        <strong>Date:</strong>
                        <?= e(
                            date(
                                'M d, Y h:i A',
                                strtotime(
                                    $incident['created_at']
                                )
                            )
                        ) ?>
                    </p>

                </div>

            </div>


            <hr>


            <h2>
                Observation
            </h2>

            <p>
                <strong>Problem:</strong>
                <?= e($incident['problem']) ?>
            </p>

            <p>
                <strong>Growth Stage:</strong>
                <?= e(
                    $incident['growth_stage']
                ) ?>
            </p>

            <p>
                <strong>Plant Part:</strong>
                <?= e(
                    $incident['plant_part']
                ) ?>
            </p>

            <p>
                <strong>Symptoms:</strong>
                <?= e(
                    implode(', ', $symptoms)
                ) ?>
            </p>


            <h2>
                Recommendation
            </h2>

            <p>
                <?= e(
                    $incident['recommendation']
                ) ?>
            </p>


            <?php if ($incident['notes']): ?>

                <h2>
                    Review Notes
                </h2>

                <p>
                    <?= nl2br(
                        e($incident['notes'])
                    ) ?>
                </p>

            <?php endif; ?>

        </section>

    </main>

    <script src="assets/i18n.js?v=20260929-5"></script>
</body>

</html>

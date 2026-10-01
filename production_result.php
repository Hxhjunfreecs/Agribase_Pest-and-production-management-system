<?php

require 'includes/config.php';
require 'includes/functions.php';

requireLogin();

$id = (int) (
    $_GET['id'] ?? 0
);

$stmt = $pdo->prepare("
    SELECT
        p.*,
        f.farm_name,
        f.barangay

    FROM production_assessments p

    INNER JOIN farms f
        ON f.id = p.farm_id

    WHERE p.id = ?
");

$stmt->execute([
    $id
]);

$assessment = $stmt->fetch();

if (
    !$assessment ||
    !canAccessFarm(
        $pdo,
        $assessment['farm_id']
    )
) {
    die('Assessment not found.');
}

$isAdmin = user()['role'] === 'admin';

$productionRate = null;
$productionSeverity = null;

if (preg_match(
    '/Estimated production rate: ([0-9]+(?:\.[0-9]+)?)% \((Minor|Moderate|Severe) severity\)/',
    (string) $assessment['production_condition'],
    $matches
)) {
    $productionRate = max(0, min(100, (float) $matches[1]));
    $productionSeverity = $matches[2];
} elseif ($assessment['production_condition'] !== 'Not estimated') {
    $severityByCondition = [
        'Healthy' => 'Minor',
        'Fair' => 'Moderate',
        'At Risk' => 'Severe'
    ];
    $productionSeverity = $severityByCondition[$assessment['overall_condition']] ?? null;
    $productionRate = estimatedProductionRateFromReport(
        $productionSeverity,
        $assessment['affected_area']
    );
}


layoutHeader(
    'Crop Health Result',
    'production'
);

?>

<div class="page-head">

    <div>

        <h1>
            Crop Health Result
        </h1>

        <p>
            Saved condition assessment for
            <?= e($assessment['farm_name']) ?>.
        </p>

    </div>

</div>


<div class="grid grid-3">

    <div class="card stat">

        <div class="label"><?= $isAdmin ? 'Overall Condition' : 'Crop Outlook' ?></div>

        <div
            class="value"
            style="font-size: 25px"
        >
            <?= e($isAdmin
                ? $assessment['overall_condition']
                : cropOutlookFromProductionRate($productionRate)) ?>
        </div>

    </div>


    <div class="card stat">

        <div class="label">
            Affected Area
        </div>

        <div class="value">
            <?= e(
                $assessment['affected_area']
            ) ?>%
        </div>

    </div>


    <div class="card stat">

        <div class="label">
            Pest Presence
        </div>

        <div
            class="value"
            style="font-size: 25px"
        >
            <?= e(
                $assessment['pest_presence']
            ) ?>
        </div>

    </div>

</div>


<section
    class="card"
    style="margin-top: 18px"
>

    <h2>
        Assessment Recommendation
    </h2>

    <div
        class="
            notice
            <?=
                strtolower(
                    $assessment['overall_condition']
                ) === 'at risk'
                    ? 'danger'
                    : (
                        strtolower(
                            $assessment['overall_condition']
                        ) === 'fair'
                            ? 'warn'
                            : 'success'
                    )
            ?>
        "
    >
        <?= e(
            $assessment['recommendation']
        ) ?>
    </div>


    <p>
        <strong>Farm:</strong>
        <?= e($assessment['farm_name']) ?>

        ·

        <?= e($assessment['barangay']) ?>
    </p>

    <p>
        <strong>Crop:</strong>
        <?= e($assessment['crop']) ?>

        ·

        <strong>Stage:</strong>
        <?= e($assessment['growth_stage']) ?>
    </p>

    <p>
        <strong>Plant Condition:</strong>
        <?= e($assessment['plant_condition']) ?>
    </p>

    <h2>Estimated Production Rate</h2>

    <?php if ($productionRate !== null): ?>
        <div class="production-rate-sidebar result-production-rate">
            <div
                class="production-rate-vertical-track"
                role="meter"
                aria-label="Estimated crop production rate"
                aria-valuemin="0"
                aria-valuemax="100"
                aria-valuenow="<?=e($productionRate)?>"
                aria-valuetext="<?=e($productionRate)?>% estimated production rate"
            >
                <div class="production-rate-vertical-fill" style="height: <?=e($productionRate)?>%"></div>
            </div>
            <strong><?=e($productionRate)?>%</strong>
        </div>
        <p>
            Report severity: <?=e($productionSeverity)?>
        </p>
    <?php else: ?>
        <p>No pest-check report is available to estimate production.</p>
    <?php endif; ?>

</section>


<?php

layoutFooter();
?>

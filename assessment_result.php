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
        f.barangay,
        u.full_name AS farmer

    FROM pest_assessments p

    INNER JOIN farms f
        ON f.id = p.farm_id

    INNER JOIN users u
        ON u.id = f.farmer_id

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

$symptoms = json_decode(
    $assessment['symptoms'],
    true
) ?: [];

$isAdmin = user()['role'] === 'admin';
$productionRate = estimatedProductionRateFromReport(
    $assessment['severity'],
    $assessment['affected_area']
);
$cropOutlook = cropOutlookFromProductionRate($productionRate);


layoutHeader(
    'Pest Assessment Result',
    'assessment'
);

?>

<div class="page-head">

    <div>

        <h1>
            Pest Assessment Result
        </h1>

        <p>
            <?= $isAdmin
                ? 'Rule-based classification from the recorded crop observation.'
                : 'Estimated production and crop outlook from the latest field observation.' ?>
        </p>

    </div>

    <a
        class="btn secondary"
        href="incidents.php"
    >
        View Incidents
    </a>

</div>


<div class="grid <?= $isAdmin ? 'grid-3' : 'grid-4' ?>">

    <?php if ($isAdmin): ?>
    <div class="card stat">

        <div class="label">
            Risk Level
        </div>

        <div class="value">

            <span
                class="
                    badge
                    <?= strtolower(
                        $assessment['risk']
                    ) ?>
                "
            >
                <?= e($assessment['risk']) ?>
            </span>

        </div>

    </div>
    <?php else: ?>
    <div class="card stat">
        <div class="label">Estimated Production Rate</div>
        <div class="value"><?= e(number_format($productionRate, 1)) ?>%</div>
    </div>

    <div class="card stat">
        <div class="label">Crop Outlook</div>
        <div class="value" style="font-size:18px"><?= e($cropOutlook) ?></div>
    </div>
    <?php endif; ?>


    <div class="card stat">

        <div class="label">
            Possible Problem
        </div>

        <div
            class="value"
            style="font-size: 22px"
        >
            <?= e($assessment['problem']) ?>
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

</div>

<?php if (!$isAdmin): ?>
    <p class="muted" style="margin-top:12px">
        This is a rule-based estimate from reported severity and affected area, not a guaranteed yield forecast.
    </p>
<?php endif; ?>


<div
    class="grid grid-2"
    style="margin-top: 18px"
>

    <section class="card">

        <h2>
            Assessment Details
        </h2>

        <p>
            <strong>Farmer:</strong>
            <?= e($assessment['farmer']) ?>
        </p>

        <p>
            <strong>Farm:</strong>
            <?= e($assessment['farm_name']) ?>
        </p>

        <p>
            <strong>Barangay:</strong>
            <?= e($assessment['barangay']) ?>
        </p>

        <p>
            <strong>Crop:</strong>
            <?= e($assessment['crop']) ?>
        </p>

        <p>
            <strong>Growth Stage:</strong>
            <?= e($assessment['growth_stage']) ?>
        </p>

        <p>
            <strong>Plant Part:</strong>
            <?= e($assessment['plant_part']) ?>
        </p>

        <p>
            <strong>Severity:</strong>
            <?= e($assessment['severity']) ?>
        </p>

        <p>
            <strong>Symptoms:</strong>
            <?= e(
                implode(', ', $symptoms)
            ) ?>
        </p>

    </section>


    <section class="card">

        <h2>
            Recommended Action
        </h2>

        <div
            class="
                notice
                <?=
                    strtolower(
                        $assessment['risk']
                    ) === 'high'
                        ? 'danger'
                        : (
                            strtolower(
                                $assessment['risk']
                            ) === 'moderate'
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


        <h3>
            Automated Incident
        </h3>

        <p>
            The system automatically created an incident
            linked to this assessment so the administrator
            can review and monitor it.
        </p>

    </section>

</div>


<?php

layoutFooter();
?>

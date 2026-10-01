<?php

require 'includes/config.php';
require 'includes/functions.php';
require 'includes/notifications.php';

requireAdmin();


/*
|--------------------------------------------------------------------------
| APPROVED FARMS ONLY
|--------------------------------------------------------------------------
|
| The administrator does not create farms.
| Farmers register their own farms and the administrator validates them.
|
*/

$stmt = $pdo->query("
    SELECT
        f.id,
        f.farm_name,
        f.crop,
        f.barangay,
        f.area_hectares,
        f.growth_stage,
        u.full_name AS farmer_name

    FROM farms f

    INNER JOIN users u
        ON u.id = f.farmer_id

    WHERE f.approval_status = 'Approved'

    ORDER BY
        u.full_name ASC,
        f.farm_name ASC
");

$farms = $stmt->fetchAll();
$selectedFarmId = (int) ($_GET['farm_id'] ?? 0);
$selectedFarm = null;
foreach ($farms as $farmOption) {
    if ((int) $farmOption['id'] === $selectedFarmId) {
        $selectedFarm = $farmOption;
        break;
    }
}


/*
|--------------------------------------------------------------------------
| SAVE ASSESSMENT
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $farmId = (int) (
        $_POST['farm_id'] ?? 0
    );

    if (!canMonitorFarm($pdo, $farmId)) {
        flash(
            'error',
            'Only approved farms can be monitored.'
        );

        redirect('admin_pest_assessment.php');
    }

    $farmDetailsStmt = $pdo->prepare('SELECT crop, growth_stage FROM farms WHERE id = ?');
    $farmDetailsStmt->execute([$farmId]);
    $farmDetails = $farmDetailsStmt->fetch();
    $crop = $farmDetails['crop'];
    $growthStage = trim((string) $farmDetails['growth_stage']) ?: 'Not specified';

    $plantPart = trim(
        $_POST['plant_part'] ?? ''
    );

    $symptoms = $_POST['symptoms'] ?? [];

    $allowedSymptoms = [
        'Yellowing',
        'Holes',
        'Wilting',
        'Spots',
        'Curling',
        'Discoloration',
        'Insects visible',
        'Damaged fruit',
        'Stunted growth'
    ];

    $symptoms = array_values(
        array_intersect(
            $symptoms,
            $allowedSymptoms
        )
    );

    $affectedArea = (float) (
        $_POST['affected_area'] ?? 0
    );

    $affectedArea = max(
        0,
        min(100, $affectedArea)
    );

    $severity = trim(
        $_POST['severity'] ?? ''
    );

    $allowedSeverity = [
        'Minor',
        'Moderate',
        'Severe'
    ];

    if (!in_array(
        $severity,
        $allowedSeverity,
        true
    )) {
        $severity = 'Minor';
    }

    if (
        $crop === '' ||
        $growthStage === '' ||
        $plantPart === ''
    ) {
        flash(
            'error',
            'Please complete all required assessment fields.'
        );

        redirect('admin_pest_assessment.php');
    }

    $risk = riskFromAssessment(
        $affectedArea,
        $severity,
        $symptoms
    );

    $problem = problemFromSymptoms(
        $crop,
        $symptoms
    );

    $recommendation = recommendation(
        $risk,
        $crop
    );


    /*
    |--------------------------------------------------------------------------
    | SAVE ASSESSMENT + INCIDENT AS ONE TRANSACTION
    |--------------------------------------------------------------------------
    */

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO pest_assessments (
                farm_id,
                crop,
                growth_stage,
                plant_part,
                symptoms,
                affected_area,
                severity,
                problem,
                risk,
                recommendation,
                assessed_by
            )
            VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            )
        ");

        $stmt->execute([
            $farmId,
            $crop,
            $growthStage,
            $plantPart,
            json_encode(
                $symptoms,
                JSON_UNESCAPED_UNICODE
            ),
            $affectedArea,
            $severity,
            $problem,
            $risk,
            $recommendation,
            user()['id']
        ]);

        $assessmentId =
            (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare("
            INSERT INTO incidents (
                assessment_id,
                farm_id,
                status
            )
            VALUES (
                ?, ?, 'Reported'
            )
        ");

        $stmt->execute([
            $assessmentId,
            $farmId
        ]);
        $incidentId = (int) $pdo->lastInsertId();

        $pdo->commit();

        notifyAgriBaseHighPestActivity($pdo, $incidentId);

        flash(
            'success',
            'Pest assessment saved and incident created successfully.'
        );

        redirect(
            'assessment_result.php?id=' . $assessmentId
        );
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        flash(
            'error',
            'The assessment could not be saved. Please try again.'
        );

        redirect('admin_pest_assessment.php');
    }
}


layoutHeader(
    'Administrator Pest Assessment',
    'assessment'
);

?>

<div class="admin-page-heading">

    <div>
        <span class="eyebrow">
            ADMINISTRATIVE FIELD REVIEW
        </span>

        <h1>
            New Pest Assessment
        </h1>

        <p>
            Record a structured observation for an
            administrator-approved farm.
        </p>
    </div>

    <a
        class="admin-btn ghost"
        href="incidents.php"
    >
        Review Incidents
    </a>

</div>


<?php if (!$farms): ?>

    <section class="admin-panel">

        <div class="empty-panel">

            <div class="empty-icon">
                🌾
            </div>

            <h2>
                No Approved Farms
            </h2>

            <p>
                Farmers must register their farms first.
                An administrator must approve a farm before
                it can be monitored.
            </p>

            <a
                class="admin-btn"
                href="farmers.php"
            >
                Review Farms
            </a>

        </div>

    </section>

<?php else: ?>

    <form
        method="post"
        class="admin-form"
    >

        <section class="admin-panel">

            <div class="panel-title">

                <div>
                    <span class="eyebrow">
                        FARM CONTEXT
                    </span>

                    <h2>
                        Select the approved farm
                    </h2>
                </div>

            </div>

            <div class="form-grid">

                <div class="field full">

                    <label for="farm_id">
                        Farmer / Farm
                    </label>

                    <select
                        id="farm_id"
                        name="farm_id"
                        required
                    >

                        <option value="">
                            Select an approved farm
                        </option>

                        <?php foreach ($farms as $farm): ?>

                            <option
                                value="<?= e($farm['id']) ?>"
                                data-crop="<?= e($farm['crop']) ?>"
                                data-growth-stage="<?= e($farm['growth_stage'] ?: 'Not specified') ?>"
                                <?= $selectedFarmId === (int) $farm['id'] ? 'selected' : '' ?>
                            >
                                <?= e($farm['farmer_name']) ?>
                                —
                                <?= e($farm['farm_name']) ?>
                                —
                                <?= e($farm['crop']) ?>
                                —
                                <?= e($farm['barangay']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="field">

                    <label for="crop">
                        Crop
                    </label>

                    <input
                        id="crop"
                        name="crop"
                        value="<?= e($selectedFarm['crop'] ?? '') ?>"
                        readonly
                        required
                    >

                </div>


                <div class="field">

                    <label for="growth_stage">
                        Growth Stage
                    </label>

                    <input
                        id="growth_stage"
                        name="growth_stage"
                        value="<?= e($selectedFarm ? ($selectedFarm['growth_stage'] ?: 'Not specified') : '') ?>"
                        readonly
                        required
                    >

                </div>

            </div>

        </section>


        <section class="admin-panel">

            <div class="panel-title">

                <div>
                    <span class="eyebrow">
                        FIELD OBSERVATION
                    </span>

                    <h2>
                        Plant condition
                    </h2>
                </div>

            </div>

            <div class="form-grid">

                <div class="field full">

                    <label>
                        Affected Plant Part
                    </label>

                    <div class="admin-choice-grid">

                        <?php
                        $plantParts = [
                            'Leaf',
                            'Stem',
                            'Root',
                            'Fruit',
                            'Trunk',
                            'Crown',
                            'Panicle'
                        ];
                        ?>

                        <?php foreach ($plantParts as $part): ?>

                            <label>

                                <input
                                    type="radio"
                                    name="plant_part"
                                    value="<?= e($part) ?>"
                                    required
                                >

                                <span>
                                    <?= e($part) ?>
                                </span>

                            </label>

                        <?php endforeach; ?>

                    </div>

                </div>


                <div class="field full">

                    <label>
                        Observed Symptoms
                    </label>

                    <div class="admin-choice-grid symptoms">

                        <?php
                        $symptomList = [
                            'Yellowing',
                            'Holes',
                            'Wilting',
                            'Spots',
                            'Curling',
                            'Discoloration',
                            'Insects visible',
                            'Damaged fruit',
                            'Stunted growth'
                        ];
                        ?>

                        <?php foreach ($symptomList as $symptom): ?>

                            <label>

                                <input
                                    type="checkbox"
                                    name="symptoms[]"
                                    value="<?= e($symptom) ?>"
                                >

                                <span>
                                    <?= e($symptom) ?>
                                </span>

                            </label>

                        <?php endforeach; ?>

                    </div>

                </div>


                <div class="field">

                    <label for="affected_area">
                        Affected Area (%)
                    </label>

                    <input
                        id="affected_area"
                        type="number"
                        name="affected_area"
                        min="0"
                        max="100"
                        step="0.01"
                        value="10"
                        required
                    >

                </div>


                <div class="field">

                    <label for="severity">
                        Visible Damage
                    </label>

                    <select
                        id="severity"
                        name="severity"
                        required
                    >
                        <option value="Minor">
                            Minor
                        </option>

                        <option value="Moderate">
                            Moderate
                        </option>

                        <option value="Severe">
                            Severe
                        </option>
                    </select>

                </div>

            </div>

            <div class="admin-help">

                <strong>
                    Decision-support process:
                </strong>

                AgriBase calculates the risk level from the
                recorded observations and automatically creates
                an incident for monitoring.

            </div>

        </section>


        <div class="actions">

            <button
                class="admin-btn"
                type="submit"
            >
                Save Assessment
            </button>

            <a
                class="admin-btn ghost"
                href="admin_dashboard.php"
            >
                Cancel
            </a>

        </div>

    </form>

<?php endif; ?>


<script>
const adminFarmSelector = document.getElementById('farm_id');
adminFarmSelector?.addEventListener('change', function () {
    const farm = this.selectedOptions[0];
    document.getElementById('crop').value = farm.dataset.crop || '';
    document.getElementById('growth_stage').value = farm.dataset.growthStage || 'Not specified';
});
</script>

<?php

layoutFooter();
?>

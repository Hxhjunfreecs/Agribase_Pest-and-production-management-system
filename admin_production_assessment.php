<?php

require 'includes/config.php';
require 'includes/functions.php';

requireAdmin();


/*
|--------------------------------------------------------------------------
| APPROVED FARMS ONLY
|--------------------------------------------------------------------------
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

        redirect('admin_production_assessment.php');
    }

    $farmDetailsStmt = $pdo->prepare('SELECT crop, growth_stage FROM farms WHERE id = ?');
    $farmDetailsStmt->execute([$farmId]);
    $farmDetails = $farmDetailsStmt->fetch();
    $crop = $farmDetails['crop'];
    $growthStage = trim((string) $farmDetails['growth_stage']) ?: 'Not specified';

    $overallCondition = trim(
        $_POST['overall_condition'] ?? ''
    );

    $plantCondition = trim(
        $_POST['plant_condition'] ?? ''
    );

    $pestPresence = trim(
        $_POST['pest_presence'] ?? ''
    );

    $affectedArea = (float) (
        $_POST['affected_area'] ?? 0
    );

    $affectedArea = max(
        0,
        min(100, $affectedArea)
    );

    $productionCondition = trim(
        $_POST['production_condition'] ?? ''
    );


    $allowedConditions = [
        'Healthy',
        'Fair',
        'At Risk'
    ];

    if (!in_array(
        $overallCondition,
        $allowedConditions,
        true
    )) {
        $overallCondition = 'Healthy';
    }


    if ($overallCondition === 'At Risk') {
        $recommendation =
            'Increase monitoring frequency, document affected areas, and conduct a follow-up field assessment.';
    } elseif ($overallCondition === 'Fair') {
        $recommendation =
            'Continue regular monitoring and document changes in crop condition.';
    } else {
        $recommendation =
            'Continue routine monitoring and record any change in crop condition.';
    }


    if (
        $crop === '' ||
        $growthStage === '' ||
        $plantCondition === '' ||
        $pestPresence === '' ||
        $productionCondition === ''
    ) {
        flash(
            'error',
            'Please complete all required assessment fields.'
        );

        redirect('admin_production_assessment.php');
    }


    $stmt = $pdo->prepare("
        INSERT INTO production_assessments (
            farm_id,
            crop,
            growth_stage,
            overall_condition,
            plant_condition,
            pest_presence,
            affected_area,
            production_condition,
            recommendation,
            assessed_by
        )
        VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )
    ");

    $stmt->execute([
        $farmId,
        $crop,
        $growthStage,
        $overallCondition,
        $plantCondition,
        $pestPresence,
        $affectedArea,
        $productionCondition,
        $recommendation,
        user()['id']
    ]);

    $assessmentId =
        (int) $pdo->lastInsertId();

    flash(
        'success',
        'Crop health assessment saved successfully.'
    );

    redirect(
        'production_result.php?id=' . $assessmentId
    );
}


layoutHeader(
    'Administrator Crop Health Assessment',
    'production'
);

?>

<div class="admin-page-heading">

    <div>

        <span class="eyebrow">
            ADMINISTRATIVE FIELD REVIEW
        </span>

        <h1>
            Crop Health Assessment
        </h1>

        <p>
            Record the current production condition
            of an approved farmer-owned farm.
        </p>

    </div>

    <a
        class="admin-btn light"
        href="analytics.php"
    >
        Open Analytics
    </a>

</div>


<?php if (!$farms): ?>

    <section class="admin-panel">

        <div class="empty-panel">

            <div class="empty-icon">
                🌱
            </div>

            <h2>
                No Approved Farms
            </h2>

            <p>
                No farm is currently available for
                administrative monitoring.
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
                        CROP CONDITION
                    </span>

                    <h2>
                        Current health
                    </h2>
                </div>

            </div>

            <div class="form-grid">

                <div class="field">

                    <label for="overall_condition">
                        Overall Condition
                    </label>

                    <select
                        id="overall_condition"
                        name="overall_condition"
                        required
                    >
                        <option value="Healthy">
                            Healthy
                        </option>

                        <option value="Fair">
                            Fair
                        </option>

                        <option value="At Risk">
                            At Risk
                        </option>
                    </select>

                </div>


                <div class="field">

                    <label for="plant_condition">
                        Plant Condition
                    </label>

                    <select
                        id="plant_condition"
                        name="plant_condition"
                        required
                    >
                        <option value="Normal">
                            Normal
                        </option>

                        <option value="Minor Damage">
                            Minor Damage
                        </option>

                        <option value="Significant Damage">
                            Significant Damage
                        </option>
                    </select>

                </div>


                <div class="field">

                    <label for="pest_presence">
                        Pest Presence
                    </label>

                    <select
                        id="pest_presence"
                        name="pest_presence"
                        required
                    >
                        <option value="None">
                            None
                        </option>

                        <option value="Suspected">
                            Suspected
                        </option>

                        <option value="Confirmed">
                            Confirmed
                        </option>
                    </select>

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
                        value="0"
                        required
                    >

                </div>


                <div class="field full">

                    <label for="production_condition">
                        Production Condition
                    </label>

                    <select
                        id="production_condition"
                        name="production_condition"
                        required
                    >
                        <option value="Normal">
                            Normal
                        </option>

                        <option value="Below Expected">
                            Below Expected
                        </option>

                        <option value="Severely Affected">
                            Severely Affected
                        </option>
                    </select>

                </div>

            </div>

            <div class="admin-help">

                <strong>
                    Administrative Monitoring:
                </strong>

                This record contributes to the farm's
                agricultural monitoring history and analytics.

            </div>

        </section>


        <div class="actions">

            <button
                class="admin-btn"
                type="submit"
            >
                Save Health Assessment
            </button>

            <a
                class="admin-btn light"
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

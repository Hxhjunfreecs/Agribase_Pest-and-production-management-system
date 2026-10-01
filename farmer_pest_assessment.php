<?php

require 'includes/config.php';
require 'includes/functions.php';
require_once __DIR__ . '/includes/notifications.php';

requireFarmer();

/*
|--------------------------------------------------------------------------
| ONLY APPROVED FARMS
|--------------------------------------------------------------------------
*/

$s = $pdo->prepare("
    SELECT *
    FROM farms
    WHERE farmer_id = ?
    AND approval_status = 'Approved'
    ORDER BY id DESC
");

$s->execute([
    user()['id']
]);

$farms = $s->fetchAll();


/*
|--------------------------------------------------------------------------
| SUBMIT ASSESSMENT
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $farm = (int)$_POST['farm_id'];

    /*
    This now rejects Pending and Rejected farms.
    */

    if (!canAccessFarm($pdo, $farm)) {

        flash(
            'error',
            'This farm has not been approved for monitoring.'
        );

        redirect('farmer_pest_assessment.php');
    }

    $farmQuery = $pdo->prepare("
        SELECT crop, growth_stage
        FROM farms
        WHERE id = ?
        AND farmer_id = ?
        AND approval_status = 'Approved'
    ");
    $farmQuery->execute([$farm, user()['id']]);
    $farmDetails = $farmQuery->fetch();

    if (!$farmDetails) {
        flash('error', 'This farm is not available for pest monitoring.');
        redirect('farmer_pest_assessment.php');
    }

    $crop = $farmDetails['crop'];
    $stage = trim((string) $farmDetails['growth_stage']) ?: 'Not specified';

    $allowedPlantParts = [
        'Leaf',
        'Stem',
        'Root',
        'Fruit',
        'Trunk',
        'Crown',
        'Panicle'
    ];
    $submittedPlantParts = $_POST['plant_part'] ?? [];
    $selectedPlantParts = [];

    if (is_array($submittedPlantParts)) {
        foreach ($submittedPlantParts as $plantPart) {
            if (is_string($plantPart) && in_array($plantPart, $allowedPlantParts, true)) {
                $selectedPlantParts[] = $plantPart;
            }
        }
    }

    $selectedPlantParts = array_values(array_unique($selectedPlantParts));

    if (!$selectedPlantParts) {
        flash('error', 'Select at least one affected plant part.');
        redirect('farmer_pest_assessment.php');
    }

    $part = implode(', ', $selectedPlantParts);

    $symptoms =
        $_POST['symptoms'] ?? [];

    $area =
        max(
            0,
            min(
                100,
                (float)$_POST['affected_area']
            )
        );

    $severity =
        $_POST['severity'];


    $risk =
        riskFromAssessment(
            $area,
            $severity,
            $symptoms
        );


    $problem =
        problemFromSymptoms(
            $crop,
            $symptoms
        );


    $rec =
        recommendation(
            $risk,
            $crop
        );


    $s = $pdo->prepare("
        INSERT INTO pest_assessments
        (
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
        VALUES
        (
            ?,?,?,?,?,?,?,?,?,?,?
        )
    ");


    $s->execute([
        $farm,
        $crop,
        $stage,
        $part,
        json_encode(
            array_values($symptoms)
        ),
        $area,
        $severity,
        $problem,
        $risk,
        $rec,
        user()['id']
    ]);


    $aid =
        $pdo->lastInsertId();


    /*
    Automatically create incident.
    */

    $s = $pdo->prepare("
        INSERT INTO incidents
        (
            assessment_id,
            farm_id,
            status
        )
        VALUES
        (?, ?, 'Reported')
    ");

    $s->execute([
        $aid,
        $farm
    ]);
    $incidentId = (int) $pdo->lastInsertId();

    notifyAgriBaseHighPestActivity($pdo, $incidentId);


    flash(
        'success',
        'Your pest check was submitted successfully.'
    );


    redirect(
        'assessment_result.php?id=' .
        $aid
    );
}


layoutHeader(
    'Pest Check',
    'assessment'
);

?>


<div class="farmer-page-heading">

    <div>

        <span class="farmer-kicker">
            FIELD CHECK
        </span>

        <h1>
            Pest Check
        </h1>

        <p>
            Only farms approved by the administrator
            can be used for pest monitoring.
        </p>

    </div>

</div>

<?php if (!$farms): ?>

    <div class="farmer-panel">

        <div
            class="farmer-empty"
            style="padding:50px;text-align:center"
        >

            <h2>
                No approved farms yet
            </h2>

            <p>
                You need to register a farm and wait for
                administrator approval before performing
                a pest check.
            </p>

            <a
                class="farmer-primary"
                href="farm.php#add-farm"
                style="display:inline-flex;margin-top:15px"
            >
                + Add Farm
            </a>

        </div>

    </div>

<?php else: ?>


<form
    method="post"
    class="farmer-assessment"
>


<section class="farmer-form-card">

    <div class="form-number">
        01
    </div>

    <div class="farmer-form-content">

        <span class="farmer-kicker">
            YOUR FARM
        </span>

        <h2>
            Where did you observe the problem?
        </h2>


        <div class="form-grid">

            <div class="field full">

                <label>
                    Approved Farm
                </label>

                <select
                    name="farm_id"
                    id="pest-farm-select"
                    required
                >

                    <option value="">
                        Select your farm
                    </option>

                    <?php foreach (
                        $farms as $f
                    ): ?>

                        <option
                            value="<?=$f['id']?>"
                            data-crop="<?=e($f['crop'])?>"
                            data-growth-stage="<?=e($f['growth_stage'] ?: 'Not specified')?>"
                        >

                            <?=e(
                                $f['farm_name']
                            )?>

                            ·

                            <?=e(
                                $f['crop']
                            )?>

                            ·

                            <?=e(
                                $f['barangay']
                            )?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="field">

                <label>
                    Crop
                </label>

                <input id="pest-farm-crop" value="" readonly>

            </div>


            <div class="field">

                <label>
                    Growth Stage
                </label>

                <input id="pest-farm-growth-stage" value="" readonly>

            </div>

        </div>

    </div>

</section>


<section class="farmer-form-card">

    <div class="form-number">
        02
    </div>

    <div class="farmer-form-content">

        <span class="farmer-kicker">
            WHAT DO YOU SEE?
        </span>

        <h2>
            Describe the plant condition
        </h2>


        <div class="field">

            <label>
                Affected Plant Parts
            </label>

            <div class="farmer-choice-grid">

                <?php foreach (
                    [
                        'Leaf',
                        'Stem',
                        'Root',
                        'Fruit',
                        'Trunk',
                        'Crown',
                        'Panicle'
                    ] as $x
                ): ?>

                    <label>

                        <input
                            type="checkbox"
                            name="plant_part[]"
                            value="<?=e($x)?>"
                        >

                        <span>
                            <?=e($x)?>
                        </span>

                    </label>

                <?php endforeach; ?>

            </div>

        </div>


        <div
            class="field"
            style="margin-top:20px"
        >

            <label>
                Observed Symptoms
            </label>

            <div class="farmer-choice-grid">

                <?php foreach (
                    [
                        'Holes',
                        'Insects Visible',
                        'Spots',
                        'Discoloration',
                        'Wilting',
                        'Yellowing'
                    ] as $x
                ): ?>

                    <label>

                        <input
                            type="checkbox"
                            name="symptoms[]"
                            value="<?=e($x)?>"
                        >

                        <span>
                            <?=e($x)?>
                        </span>

                    </label>

                <?php endforeach; ?>

            </div>

        </div>


        <div
            class="form-grid"
            style="margin-top:20px"
        >

            <div class="field">

                <label>
                    Affected Area (%)
                </label>

                <input
                    type="number"
                    name="affected_area"
                    min="0"
                    max="100"
                    value="10"
                    required
                >

            </div>


            <div class="field">

                <label>
                    Severity
                </label>

                <select
                    name="severity"
                    required
                >

                    <option>
                        Minor
                    </option>

                    <option>
                        Moderate
                    </option>

                    <option>
                        Severe
                    </option>

                </select>

            </div>

        </div>


        <div class="farmer-help">

            This is a decision-support check.
            It does not replace professional field
            diagnosis or laboratory testing.

        </div>

    </div>

</section>


<div class="farmer-submit-row">

    <a
        class="farmer-cancel"
        href="dashboard.php"
    >
        Cancel
    </a>

    <button
        class="farmer-primary submit"
        type="submit"
    >
        Submit Pest Check →
    </button>

</div>


</form>

<script>
document.getElementById('pest-farm-select')?.addEventListener('change', function () {
    const selectedFarm = this.selectedOptions[0];
    document.getElementById('pest-farm-crop').value = selectedFarm.dataset.crop || '';
    document.getElementById('pest-farm-growth-stage').value = selectedFarm.dataset.growthStage || 'Not specified';
});
</script>


<?php endif; ?>


<?php layoutFooter(); ?>
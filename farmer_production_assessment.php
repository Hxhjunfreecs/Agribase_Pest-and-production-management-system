<?php

require 'includes/config.php';
require 'includes/functions.php';

requireFarmer();


$s = $pdo->prepare("
    SELECT
        f.*,
        p.severity AS report_severity,
        p.affected_area AS report_affected_area,
        p.plant_part AS report_plant_part,
        p.symptoms AS report_symptoms,
        p.created_at AS report_created_at
    FROM farms f
    LEFT JOIN pest_assessments p ON p.id = (
        SELECT p2.id
        FROM pest_assessments p2
        WHERE p2.farm_id = f.id
        AND p2.assessed_by = f.farmer_id
        ORDER BY p2.created_at DESC, p2.id DESC
        LIMIT 1
    )
    WHERE f.farmer_id = ?
    AND f.approval_status = 'Approved'
    ORDER BY f.id DESC
");

$s->execute([
    user()['id']
]);

$farms = $s->fetchAll();


layoutHeader(
    'Crop Health Check',
    'production'
);

?>


<div class="farmer-page-heading">

    <div>

        <span class="farmer-kicker">
            FIELD CHECK
        </span>

        <h1>
            Assess Crop Health
        </h1>

        <p>
            Record the current condition of an
            administrator-approved farm.
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
                Add a farm first and wait for the
                administrator to approve it.
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


<div class="farmer-assessment">


<section class="farmer-form-card">

    <div class="form-number">
        01
    </div>

    <div class="farmer-form-content">

        <span class="farmer-kicker">
            YOUR FARM
        </span>

        <h2>
            Choose an approved farm
        </h2>


        <div class="form-grid">

            <div class="field full">

                <label>
                    Farm
                </label>

                <select
                    id="crop-health-farm"
                >

                    <?php foreach (
                        $farms as $f
                    ): ?>
                        <?php
                        $reportRate = $f['report_severity'] !== null
                            ? estimatedProductionRateFromReport($f['report_severity'], $f['report_affected_area'])
                            : null;
                        ?>

                        <option
                            value="<?=$f['id']?>"
                            data-crop="<?=e($f['crop'])?>"
                            data-growth-stage="<?=e($f['growth_stage'] ?: 'Not specified')?>"
                            data-severity="<?=e($f['report_severity'] ?? '')?>"
                            data-affected-area="<?=e($f['report_affected_area'] ?? '')?>"
                            data-production-rate="<?=$reportRate === null ? '' : e($reportRate)?>"
                            data-outlook="<?=e(cropOutlookFromProductionRate($reportRate))?>"
                            data-plant-part="<?=e($f['report_plant_part'] ?? '')?>"
                            data-symptoms="<?=e(implode(', ', (array) json_decode($f['report_symptoms'] ?: '[]', true)))?>"
                            data-report-date="<?=e($f['report_created_at'] ? date('M d, Y', strtotime($f['report_created_at'])) : '')?>"
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

                <label for="crop-health-crop">
                    Crop
                </label>

                <input id="crop-health-crop" value="<?=e($farms[0]['crop'])?>" readonly>

            </div>


            <div class="field">

                <label for="crop-health-stage">
                    Growth Stage
                </label>

                <input id="crop-health-stage" value="<?=e($farms[0]['growth_stage'] ?: 'Not specified')?>" readonly>

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
            CROP CONDITION
        </span>

        <h2>
            How is your crop doing?
        </h2>


        <div class="crop-health-layout">
        <div class="form-grid">
            <div class="field">
                <label for="crop-health-severity">Report Severity</label>
                <output id="crop-health-severity">No pest-check report</output>
            </div>
            <div class="field">
                <label for="crop-health-outlook">Crop Outlook</label>
                <output id="crop-health-outlook">No report available</output>
            </div>
            <div class="field">
                <label for="crop-health-plant-part">Plant Part</label>
                <output id="crop-health-plant-part">—</output>
            </div>
            <div class="field">
                <label for="crop-health-affected-area">Affected Area</label>
                <output id="crop-health-affected-area">—</output>
            </div>
            <div class="field full">
                <label for="crop-health-symptoms">Reported Symptoms</label>
                <output id="crop-health-symptoms">—</output>
            </div>
            <div class="field">
                <label for="crop-health-report-date">Report Date</label>
                <output id="crop-health-report-date">—</output>
            </div>
        </div>
        <aside class="production-rate-sidebar">
            <h3>Estimated Production Rate</h3>
            <div
                id="crop-health-production-meter"
                class="production-rate-vertical-track"
                role="meter"
                aria-label="Estimated crop production rate"
                aria-valuemin="0"
                aria-valuemax="100"
                aria-valuenow="0"
                aria-valuetext="No report data"
            >
                <div id="crop-health-production-fill" class="production-rate-vertical-fill"></div>
            </div>
            <strong id="crop-health-production-value">Not available</strong>
            <p>Estimate based on the latest pest-check severity and affected area.</p>
        </aside>
        </div>

    </div>

</section>


</div>

<script>
const cropHealthFarmSelect = document.getElementById('crop-health-farm');

function updateCropHealthReport() {
    const farm = cropHealthFarmSelect.selectedOptions[0];
    const severity = farm.dataset.severity || '';
    const rate = farm.dataset.productionRate === ''
        ? null
        : Number(farm.dataset.productionRate);
    const hasReport = rate !== null;
    const affectedArea = farm.dataset.affectedArea || '';

    document.getElementById('crop-health-crop').value = farm.dataset.crop || '';
    document.getElementById('crop-health-stage').value = farm.dataset.growthStage || 'Not specified';
    document.getElementById('crop-health-severity').textContent = severity || 'No pest-check report';
    document.getElementById('crop-health-plant-part').textContent = farm.dataset.plantPart || '—';
    document.getElementById('crop-health-affected-area').textContent = hasReport ? `${affectedArea}%` : '—';
    document.getElementById('crop-health-symptoms').textContent = farm.dataset.symptoms || '—';
    document.getElementById('crop-health-outlook').textContent = farm.dataset.outlook || 'No report available';
    document.getElementById('crop-health-report-date').textContent = farm.dataset.reportDate || '—';
    document.getElementById('crop-health-production-value').textContent = rate === null ? 'Not available' : `${rate}%`;
    document.getElementById('crop-health-production-fill').style.height = `${rate || 0}%`;
    document.getElementById('crop-health-production-meter').setAttribute('aria-valuenow', rate === null ? '0' : String(rate));
    document.getElementById('crop-health-production-meter').setAttribute('aria-valuetext', rate === null ? 'No report data' : `${rate}% estimated production rate`);
}

cropHealthFarmSelect.addEventListener('change', updateCropHealthReport);
updateCropHealthReport();
</script>


<?php endif; ?>


<?php layoutFooter(); ?>
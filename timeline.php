<?php

require 'includes/config.php';
require 'includes/functions.php';

requireFarmer();


/*
|--------------------------------------------------------------------------
| APPROVED FARMS ONLY
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT *
    FROM farms
    WHERE farmer_id = ?
    AND approval_status = 'Approved'
    ORDER BY id DESC
";

$q = $pdo->prepare($sql);

$q->execute([
    user()['id']
]);

$farms = $q->fetchAll();


$fid = (int)(
    $_GET['farm_id']
    ?? ($farms[0]['id'] ?? 0)
);


if ($fid && !canAccessFarm($pdo, $fid)) {

    flash(
        'error',
        'This farm is not approved for monitoring.'
    );

    redirect('timeline.php');
}


/*
|--------------------------------------------------------------------------
| HISTORY
|--------------------------------------------------------------------------
*/

$events = [];


if ($fid) {
    $pestStmt = $pdo->prepare("
        SELECT
            'Pest Check' AS type,
            created_at,
            crop,
            growth_stage,
            plant_part,
            symptoms,
            affected_area,
            severity,
            risk,
            problem,
            recommendation
        FROM pest_assessments
        WHERE farm_id = ?
    ");
    $pestStmt->execute([$fid]);
    $events = array_merge($events, $pestStmt->fetchAll());

    $productionStmt = $pdo->prepare("
        SELECT
            'Crop Health' AS type,
            p.created_at,
            p.crop,
            p.growth_stage,
            p.affected_area,
            p.overall_condition,
            p.plant_condition,
            p.pest_presence,
            p.production_condition,
            p.recommendation,
            u.full_name AS assessor_name
        FROM production_assessments p
        INNER JOIN users u ON u.id = p.assessed_by
        WHERE p.farm_id = ?
    ");
    $productionStmt->execute([$fid]);
    $events = array_merge($events, $productionStmt->fetchAll());

    $adminAssessmentStmt = $pdo->prepare("
        SELECT
            'Administrator Assessment' AS type,
            a.created_at,
            p.crop,
            p.growth_stage,
            p.plant_part,
            p.symptoms,
            p.affected_area,
            p.severity,
            p.risk,
            p.problem,
            p.recommendation,
            a.assessment AS admin_assessment,
            a.procedures,
            u.full_name AS assessor_name
        FROM incident_assessments a
        INNER JOIN incidents i ON i.id = a.incident_id
        INNER JOIN pest_assessments p ON p.id = i.assessment_id
        INNER JOIN users u ON u.id = a.assessed_by
        WHERE i.farm_id = ?
    ");
    $adminAssessmentStmt->execute([$fid]);
    $events = array_merge($events, $adminAssessmentStmt->fetchAll());

    $legacyNotesStmt = $pdo->prepare("
        SELECT
            'Administrator Assessment' AS type,
            i.updated_at AS created_at,
            p.crop,
            p.growth_stage,
            p.plant_part,
            p.symptoms,
            p.affected_area,
            p.severity,
            p.risk,
            p.problem,
            p.recommendation,
            i.notes AS admin_assessment,
            '[]' AS procedures,
            'Administrator' AS assessor_name
        FROM incidents i
        INNER JOIN pest_assessments p ON p.id = i.assessment_id
        WHERE i.farm_id = ?
        AND i.notes IS NOT NULL
        AND i.notes <> ''
    ");
    $legacyNotesStmt->execute([$fid]);
    $events = array_merge($events, $legacyNotesStmt->fetchAll());

    usort($events, static function ($first, $second) {
        return strtotime($second['created_at']) <=> strtotime($first['created_at']);
    });
}


layoutHeader(
    'Crop History',
    'timeline'
);

?>


<div class="farmer-page-heading">

    <div>

        <span class="farmer-kicker">
            FARM MONITORING
        </span>

        <h1>
            Crop Condition Timeline
        </h1>

        <p>
            Review the history of pest and crop-health
            assessments for each approved farm.
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
                No approved farms
            </h2>

            <p>
                Your crop history becomes available
                after one of your farms is approved.
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


<div class="farmer-panel">

    <div class="farmer-panel-head">

        <div>

            <span class="farmer-kicker">
                SELECT FARM
            </span>

            <h2>
                Farm History
            </h2>

        </div>

    </div>


    <form method="get">

        <div
            class="field"
            style="max-width:600px"
        >

            <label>
                Approved Farm
            </label>

            <select
                name="farm_id"
                onchange="this.form.submit()"
            >

                <?php foreach (
                    $farms as $f
                ): ?>

                    <option
                        value="<?=$f['id']?>"
                        <?=$fid == $f['id']
                            ? 'selected'
                            : ''?>
                    >

                        <?=e(
                            $f['farm_name']
                        )?>

                        —

                        <?=e(
                            $f['crop']
                        )?>

                    </option>

                <?php endforeach; ?>

            </select>

        </div>

    </form>

</div>


<div
    class="farmer-panel"
    style="margin-top:18px"
>

    <div class="farmer-panel-head">

        <div>

            <span class="farmer-kicker">
                HISTORY
            </span>

            <h2>
                Condition History
            </h2>

        </div>

    </div>


    <div class="timeline">

        <?php foreach (
            $events as $x
        ): ?>

            <div class="timeline-item">

                <div class="timeline-dot"></div>

                <div class="timeline-content">

                    <strong>
                        <?=e($x['type'])?>
                    </strong>

                    <div class="meta">

                        <?=e(
                            date(
                                'M d, Y h:i A',
                                strtotime(
                                    $x['created_at']
                                )
                            )
                        )?>

                    </div>

                    <div class="timeline-detail-grid">
                        <p><strong>Crop:</strong> <?=e($x['crop'] ?? '—')?></p>
                        <p><strong>Growth Stage:</strong> <?=e($x['growth_stage'] ?? '—')?></p>

                        <?php if ($x['type'] === 'Pest Check' || $x['type'] === 'Administrator Assessment'): ?>
                            <?php $symptoms = json_decode($x['symptoms'] ?? '[]', true) ?: []; ?>
                            <?php $productionRate = estimatedProductionRateFromReport($x['severity'] ?? null, $x['affected_area'] ?? null); ?>
                            <p><strong>Problem:</strong> <?=e($x['problem'] ?? '—')?></p>
                            <p><strong>Severity:</strong> <?=e($x['severity'] ?? '—')?></p>
                            <p><strong>Estimated Production Rate:</strong> <?= $productionRate === null ? '—' : e(number_format($productionRate, 1)) . '%' ?></p>
                            <p><strong>Crop Outlook:</strong> <?=e(cropOutlookFromProductionRate($productionRate))?></p>
                            <p><strong>Affected Area:</strong> <?=e($x['affected_area'] ?? '—')?>%</p>
                            <p><strong>Plant Part:</strong> <?=e($x['plant_part'] ?? '—')?></p>
                            <p><strong>Symptoms:</strong> <?=e(implode(', ', $symptoms) ?: 'None recorded')?></p>
                            <p class="timeline-detail-wide"><strong>System Recommendation:</strong> <?=e($x['recommendation'] ?? '—')?></p>

                            <?php if (!empty($x['admin_assessment'])): ?>
                                <p class="timeline-detail-wide"><strong>Administrator Assessment:</strong> <?=nl2br(e($x['admin_assessment']))?></p>
                                <?php $procedures = json_decode($x['procedures'] ?? '[]', true) ?: []; ?>
                                <?php if ($procedures): ?>
                                    <div class="timeline-detail-wide">
                                        <strong>Recommended Procedures:</strong>
                                        <ul>
                                            <?php foreach ($procedures as $procedure): ?>
                                                <li><?=e(availableIncidentAssessmentProcedures()[$procedure] ?? $procedure)?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>

                        <?php else: ?>
                            <p><strong>Overall Condition:</strong> <?=e($x['overall_condition'] ?? '—')?></p>
                            <p><strong>Plant Condition:</strong> <?=e($x['plant_condition'] ?? '—')?></p>
                            <p><strong>Pest Presence:</strong> <?=e($x['pest_presence'] ?? '—')?></p>
                            <p><strong>Affected Area:</strong> <?=e($x['affected_area'] ?? '—')?>%</p>
                            <p><strong>Assessed By:</strong> <?=e($x['assessor_name'] ?? '—')?></p>
                            <p class="timeline-detail-wide"><strong>Production Condition:</strong> <?=e($x['production_condition'] ?? '—')?></p>
                            <p class="timeline-detail-wide"><strong>Recommendation:</strong> <?=e($x['recommendation'] ?? '—')?></p>
                        <?php endif; ?>
                    </div>

                </div>

            </div>

        <?php endforeach; ?>


        <?php if (!$events): ?>

            <p class="empty">
                No history has been recorded for this farm yet.
            </p>

        <?php endif; ?>

    </div>

</div>


<?php endif; ?>


<?php layoutFooter(); ?>
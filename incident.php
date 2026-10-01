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

$assessmentStmt = $pdo->prepare("
    SELECT a.*, u.full_name AS assessor_name
    FROM incident_assessments a
    INNER JOIN users u ON u.id = a.assessed_by
    WHERE a.incident_id = ?
    ORDER BY a.created_at DESC, a.id DESC
");
$assessmentStmt->execute([$id]);
$assessmentHistory = $assessmentStmt->fetchAll();
$availableProcedures = availableIncidentAssessmentProcedures();


layoutHeader(
    'Incident Details',
    'incidents'
);

?>

<div class="page-head">

    <div>

        <h1>
            Incident #<?= e($incident['id']) ?>
        </h1>

        <p>
            <?= e($incident['problem']) ?>
            ·
            <?= e($incident['crop']) ?>
            ·
            <?= e($incident['barangay']) ?>
        </p>

    </div>

    <div class="actions">

        <a
            class="btn secondary"
            href="report.php?id=<?= $incident['id'] ?>"
        >
            Printable Report
        </a>

        <a
            class="btn secondary"
            href="incidents.php"
        >
            Back
        </a>

    </div>

</div>


<div class="grid grid-2">

    <section class="card">

        <h2>
            Incident Information
        </h2>

        <p>
            <strong>Farmer:</strong>
            <?= e($incident['farmer']) ?>
        </p>

        <p>
            <strong>Farm:</strong>
            <?= e($incident['farm_name']) ?>
        </p>

        <p>
            <strong>Crop:</strong>
            <?= e($incident['crop']) ?>
        </p>

        <p>
            <strong>Growth Stage:</strong>
            <?= e($incident['growth_stage']) ?>
        </p>

        <p>
            <strong>Plant Part:</strong>
            <?= e($incident['plant_part']) ?>
        </p>

        <p>
            <strong>Symptoms:</strong>
            <?= e(
                implode(', ', $symptoms)
            ) ?>
        </p>

        <p>
            <strong>Affected Area:</strong>
            <?= e(
                $incident['affected_area']
            ) ?>%
        </p>

        <p>
            <strong>Severity:</strong>
            <?= e($incident['severity'] ?: 'Not recorded') ?>
        </p>

        <?php if (user()['role'] === 'admin'): ?>
            <p>
                <strong>Risk:</strong>
                <span class="badge <?=strtolower($incident['risk'])?>"><?=e($incident['risk'])?></span>
            </p>
        <?php else: ?>
            <?php $productionRate = estimatedProductionRateFromReport($incident['severity'], $incident['affected_area']); ?>
            <p><strong>Estimated Production Rate:</strong> <?=e(number_format($productionRate, 1))?>%</p>
            <p><strong>Crop Outlook:</strong> <?=e(cropOutlookFromProductionRate($productionRate))?></p>
        <?php endif; ?>

        <p>
            <strong>Recommendation:</strong>
            <?= e(
                $incident['recommendation']
            ) ?>
        </p>

    </section>


    <section class="card incident-assessment-panel">

        <?php if (user()['role'] === 'admin'): ?>
            <h2>Crop Assessment</h2>
        <?php else: ?>
            <h2>Administrator Assessment</h2>
            <div class="notice success">
                <strong><?= e($incident['status']) ?></strong><br>
                Your incident is being handled through the administrator review process.
            </div>
        <?php endif; ?>

        <?php if ($incident['notes']): ?>
            <div class="notice">
                <strong>Previous incident note</strong><br>
                <?= nl2br(e($incident['notes'])) ?>
            </div>
        <?php endif; ?>

        <?php if ($assessmentHistory): ?>
            <h3>Assessment History</h3>
            <div class="incident-assessment-history">
                <?php foreach ($assessmentHistory as $entry): ?>
                    <?php $procedures = json_decode($entry['procedures'], true) ?: []; ?>
                    <article class="incident-assessment-entry">
                        <div class="incident-assessment-meta">
                            <strong><?= e($entry['assessor_name']) ?></strong>
                            <time datetime="<?= e(date('c', strtotime($entry['created_at']))) ?>">
                                <?= e(date('M d, Y h:i A', strtotime($entry['created_at']))) ?>
                            </time>
                        </div>
                        <p><?= nl2br(e($entry['assessment'])) ?></p>
                        <?php if ($procedures): ?>
                            <ul>
                                <?php foreach ($procedures as $procedure): ?>
                                    <?php if (isset($availableProcedures[$procedure])): ?>
                                        <li><?= e($availableProcedures[$procedure]) ?></li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php elseif (!$incident['notes'] && user()['role'] !== 'admin'): ?>
            <div class="notice">The administrator has not added a crop assessment yet.</div>
        <?php endif; ?>

        <?php if (user()['role'] === 'admin' && $incident['status'] !== 'Resolved'): ?>
            <form method="post" action="incidents.php" class="incident-assessment-form">
                <input type="hidden" name="incident_id" value="<?= e($incident['id']) ?>">

                <div class="field">
                    <label for="status">Incident Status</label>
                    <select id="status" name="status">
                        <?php foreach (['Reported', 'Under Review', 'Verified', 'Monitoring', 'Resolved'] as $status): ?>
                            <option value="<?= e($status) ?>" <?= $incident['status'] === $status ? 'selected' : '' ?>>
                                <?= e($status) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="assessment">Assessment</label>
                    <textarea
                        id="assessment"
                        name="assessment"
                        placeholder="Assess the farmer's reported crop, stage, symptoms, severity, and affected area."
                    ></textarea>
                </div>

                <fieldset class="incident-procedure-options">
                    <legend>Recommended Procedures</legend>
                    <?php foreach ($availableProcedures as $key => $procedure): ?>
                        <label>
                            <input type="checkbox" name="procedures[]" value="<?= e($key) ?>">
                            <span><?= e($procedure) ?></span>
                        </label>
                    <?php endforeach; ?>
                </fieldset>

                <button class="btn" type="submit">Save Assessment</button>
            </form>
        <?php elseif (user()['role'] === 'admin'): ?>
            <div class="notice">This incident is resolved. No further assessments can be added.</div>
        <?php endif; ?>

    </section>

</div>


<?php

layoutFooter();
?>

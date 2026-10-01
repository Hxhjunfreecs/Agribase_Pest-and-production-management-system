<?php

require 'includes/config.php';
require 'includes/functions.php';
require 'includes/notifications.php';

requireLogin();

$availableProcedures = availableIncidentAssessmentProcedures();


/*
|--------------------------------------------------------------------------
| ADMIN UPDATE INCIDENT
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['incident_id'])
    &&
    user()['role'] === 'admin'
) {

    $id =
        (int)$_POST['incident_id'];

    $status =
        $_POST['status'] ?? 'Reported';

    $allowed = [
        'Reported',
        'Under Review',
        'Verified',
        'Monitoring',
        'Resolved'
    ];

    if (!in_array($status, $allowed, true)) {
        $status = 'Reported';
    }


    $s = $pdo->prepare("
        SELECT i.status, f.farmer_id
        FROM incidents i
        INNER JOIN farms f ON f.id = i.farm_id
        WHERE i.id = ?
    ");

    $s->execute([
        $id
    ]);

    $incident = $s->fetch();


    if (!$incident) {

        flash(
            'error',
            'Incident not found.'
        );

        redirect('incidents.php');
    }


    if ($incident['status'] === 'Resolved') {
        flash('error', 'This incident is resolved and no longer accepts assessments.');
        redirect('incident.php?id=' . $id);
    }

    $assessment = trim($_POST['assessment'] ?? '');
    $requestedProcedures = $_POST['procedures'] ?? [];
    if (!is_array($requestedProcedures)) {
        $requestedProcedures = [];
    }
    $requestedProcedures = array_filter($requestedProcedures, 'is_string');
    $procedures = array_values(array_intersect(
        array_unique($requestedProcedures),
        array_keys($availableProcedures)
    ));

    if ($assessment === '' && $procedures) {
        flash('error', 'Add assessment notes when recommending procedures.');
        redirect('incident.php?id=' . $id);
    }

    $assessmentAdded = $assessment !== '';

    try {
        $pdo->beginTransaction();

        $update = $pdo->prepare("UPDATE incidents SET status = ? WHERE id = ?");
        $update->execute([$status, $id]);

        if ($assessmentAdded) {
            $insert = $pdo->prepare("
                INSERT INTO incident_assessments
                    (incident_id, assessed_by, assessment, procedures)
                VALUES (?, ?, ?, ?)
            ");
            $insert->execute([
                $id,
                user()['id'],
                $assessment,
                json_encode($procedures, JSON_UNESCAPED_UNICODE)
            ]);
        }

        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('error', 'The incident update could not be saved. Please try again.');
        redirect('incident.php?id=' . $id);
    }

    if ($incident['status'] !== $status || $assessmentAdded) {
        $message = 'Incident #' . $id . ' was updated.';
        if ($incident['status'] !== $status) {
            $message .= ' Status: ' . $status . '.';
        }
        if ($assessmentAdded) {
            $message .= ' The administrator added a crop assessment.';
        }

        notifyAgriBaseFarmer(
            $pdo,
            (int) $incident['farmer_id'],
            'An incident on your farm was updated',
            $message
        );
    }


    flash(
        'success',
        'Incident updated successfully.'
    );


    redirect('incident.php?id=' . $id);
}


/*
|--------------------------------------------------------------------------
| INCIDENT LIST
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        i.*,
        p.crop,
        p.risk,
        p.problem,
        p.severity,
        p.affected_area,
        p.symptoms,
        f.farm_name,
        f.barangay,
        u.full_name AS farmer

    FROM incidents i

    JOIN pest_assessments p
        ON p.id = i.assessment_id

    JOIN farms f
        ON f.id = i.farm_id

    JOIN users u
        ON u.id = f.farmer_id
";


$args = [];


if (user()['role'] !== 'admin') {

    $sql .= "
        WHERE f.farmer_id = ?
        AND f.approval_status = 'Approved'
    ";

    $args[] =
        user()['id'];
}


$sql .= "
    ORDER BY i.created_at DESC
";


$s = $pdo->prepare($sql);

$s->execute($args);

$rows =
    $s->fetchAll();


layoutHeader(
    user()['role'] === 'admin'
        ? 'Pest Incidents'
        : 'My Incidents',
    'incidents'
);

?>


<div class="page-head">

    <div>

        <h1>

            <?=user()['role'] === 'admin'
                ? 'Pest Incidents'
                : 'My Pest Incidents'?>

        </h1>

        <p>

            <?=
                user()['role'] === 'admin'
                ? 'Review and manage pest incidents submitted through AgriBase.'
                : 'View pest incidents recorded for your approved farms.'
            ?>

        </p>

    </div>


    <?php if (
        user()['role'] === 'admin'
    ): ?>

        <a
            class="btn"
            href="admin_pest_assessment.php"
        >
            + New Assessment
        </a>

    <?php else: ?>

        <a
            class="btn"
            href="farmer_pest_assessment.php"
        >
            + New Pest Check
        </a>

    <?php endif; ?>

</div>


<div class="card">

    <div class="table-wrap">

        <table>

            <thead>

                <tr>

                    <th>
                        ID
                    </th>

                    <th>
                        Date
                    </th>

                    <?php if (
                        user()['role'] === 'admin'
                    ): ?>

                        <th>
                            Farmer
                        </th>

                    <?php endif; ?>

                    <th>
                        Crop
                    </th>

                    <th>
                        Farm / Location
                    </th>

                    <th><?= user()['role'] === 'admin' ? 'Risk' : 'Production Outlook' ?></th>

                    <th>
                        Status
                    </th>

                    <th>
                        Action
                    </th>

                </tr>

            </thead>


            <tbody>

            <?php foreach (
                $rows as $r
            ): ?>

                <tr>

                    <td>
                        #<?=$r['id']?>
                    </td>

                    <td>

                        <?=e(
                            date(
                                'M d, Y',
                                strtotime(
                                    $r['created_at']
                                )
                            )
                        )?>

                    </td>


                    <?php if (
                        user()['role'] === 'admin'
                    ): ?>

                        <td>
                            <?=e(
                                $r['farmer']
                            )?>
                        </td>

                    <?php endif; ?>


                    <td>
                        <?=e(
                            $r['crop']
                        )?>
                    </td>


                    <td>

                        <?=e(
                            $r['farm_name']
                        )?>

                        <br>

                        <small>
                            <?=e(
                                $r['barangay']
                            )?>
                        </small>

                    </td>


                    <td>
                        <?php if (user()['role'] === 'admin'): ?>
                            <span class="badge <?=strtolower($r['risk'])?>"><?=e($r['risk'])?></span>
                        <?php else: ?>
                            <?php $productionRate = estimatedProductionRateFromReport($r['severity'], $r['affected_area']); ?>
                            <strong><?=e(number_format($productionRate, 1))?>%</strong>
                            <small><?=e(cropOutlookFromProductionRate($productionRate))?></small>
                        <?php endif; ?>
                    </td>


                    <td>
                        <?=e(
                            $r['status']
                        )?>
                    </td>


                    <td>

                        <a
                            class="btn small secondary"
                            href="incident.php?id=<?=$r['id']?>"
                        >
                            Details
                        </a>

                    </td>

                </tr>

            <?php endforeach; ?>


            <?php if (!$rows): ?>

                <tr>

                    <td
                        colspan="8"
                        class="empty"
                    >
                        No incidents found.
                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


<?php layoutFooter(); ?>
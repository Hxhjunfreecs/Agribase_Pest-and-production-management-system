<?php

require 'includes/config.php';
require 'includes/functions.php';

requireFarmer();

$farmerId = (int) user()['id'];


/*
|--------------------------------------------------------------------------
| FARM COUNTS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total_farms,

        SUM(
            approval_status = 'Approved'
        ) AS approved_farms,

        SUM(
            approval_status = 'Pending'
        ) AS pending_farms,

        SUM(
            approval_status = 'Rejected'
        ) AS rejected_farms

    FROM farms

    WHERE farmer_id = ?
");

$stmt->execute([
    $farmerId
]);

$farmStats = $stmt->fetch();

$totalFarms =
    (int) ($farmStats['total_farms'] ?? 0);

$approvedFarms =
    (int) ($farmStats['approved_farms'] ?? 0);

$pendingFarms =
    (int) ($farmStats['pending_farms'] ?? 0);

$rejectedFarms =
    (int) ($farmStats['rejected_farms'] ?? 0);


/*
|--------------------------------------------------------------------------
| PEST CHECK COUNT
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*)

    FROM pest_assessments p

    INNER JOIN farms f
        ON f.id = p.farm_id

    WHERE f.farmer_id = ?
");

$stmt->execute([
    $farmerId
]);

$pestChecks =
    (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| INCIDENT COUNT
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*)

    FROM incidents i

    INNER JOIN farms f
        ON f.id = i.farm_id

    WHERE f.farmer_id = ?
");

$stmt->execute([
    $farmerId
]);

$incidentCount =
    (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| FOLLOW-UP COUNT
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*)

    FROM incidents i

    INNER JOIN farms f
        ON f.id = i.farm_id

    WHERE f.farmer_id = ?

      AND i.status IN (
          'Reported',
          'Under Review'
      )
");

$stmt->execute([
    $farmerId
]);

$followUp =
    (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| FARM LIST
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        f.*,

        (
            SELECT COUNT(*)
            FROM incidents i
            WHERE i.farm_id = f.id
        ) AS incident_count

    FROM farms f

    WHERE f.farmer_id = ?

    ORDER BY
        CASE f.approval_status
            WHEN 'Pending' THEN 1
            WHEN 'Approved' THEN 2
            WHEN 'Rejected' THEN 3
            ELSE 4
        END,

        f.id DESC

    LIMIT 6
");

$stmt->execute([
    $farmerId
]);

$myFarms =
    $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| RECENT INCIDENTS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        i.id,
        i.status,
        i.created_at,

        p.crop,
        p.severity,
        p.affected_area,

        f.farm_name,
        f.barangay

    FROM incidents i

    INNER JOIN pest_assessments p
        ON p.id = i.assessment_id

    INNER JOIN farms f
        ON f.id = i.farm_id

    WHERE f.farmer_id = ?

    ORDER BY i.created_at DESC

    LIMIT 5
");

$stmt->execute([
    $farmerId
]);

$recentIncidents =
    $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| LATEST PUBLISHED ADVISORIES
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        id,
        title,
        crop,
        created_at

    FROM advisories

    WHERE status = 'Published'

    ORDER BY created_at DESC

    LIMIT 3
");

$advisories =
    $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| FIRST NAME
|--------------------------------------------------------------------------
*/

$nameParts = preg_split(
    '/\s+/',
    trim(user()['full_name'])
);

$firstName =
    $nameParts[0] ?? 'Farmer';


layoutHeader(
    'Farmer Dashboard',
    'dashboard'
);

?>

<div class="farmer-welcome">

    <div>

        <span class="farmer-kicker">
            FARMER PORTAL
        </span>

        <h1>
            Welcome, <?= e($firstName) ?>.
        </h1>

        <p>
            Manage your farms, monitor crop conditions,
            submit field reports, and follow agricultural
            advisories.
        </p>

    </div>


    <div class="farmer-season">

        <span>
            Today
        </span>

        <strong>
            <?= date('M d, Y') ?>
        </strong>

    </div>

</div>


<?php if ($pendingFarms > 0): ?>

    <div class="farmer-notice">

        <div>

            <strong>
                <?= $pendingFarms ?>
                farm<?= $pendingFarms > 1 ? 's are' : ' is' ?>
                waiting for administrator validation.
            </strong>

            <p>
                Approved farms become available for pest
                and crop-health monitoring.
            </p>

        </div>

        <a
            class="farmer-primary"
            href="farm.php"
        >
            View My Farms
        </a>

    </div>

<?php endif; ?>


<!-- =========================================================
     QUICK ACTIONS
========================================================== -->

<section class="farmer-action-grid">

    <a
        class="farmer-action primary"
        href="farm.php#add-farm"
    >
        <span class="action-icon">
            ＋
        </span>

        <div>
            <strong>
                Add Farm
            </strong>

            <small>
                Register another farm for validation
            </small>
        </div>
    </a>


    <a
        class="farmer-action"
        href="farmer_pest_assessment.php"
    >
        <span class="action-icon">
            🐛
        </span>

        <div>
            <strong>
                Pest Check
            </strong>

            <small>
                Check an approved farm
            </small>
        </div>
    </a>


    <a
        class="farmer-action"
        href="farmer_production_assessment.php"
    >
        <span class="action-icon">
            🌱
        </span>

        <div>
            <strong>
                Crop Health
            </strong>

            <small>
                Record current crop condition
            </small>
        </div>
    </a>


</section>


<!-- =========================================================
     STATISTICS
========================================================== -->

<section class="farmer-stats">

    <div>
        <span>
            My Farms
        </span>

        <strong>
            <?= $totalFarms ?>
        </strong>

        <small>
            Total registered farms
        </small>
    </div>


    <div>
        <span>
            Approved Farms
        </span>

        <strong>
            <?= $approvedFarms ?>
        </strong>

        <small>
            Monitoring available
        </small>
    </div>


    <div>
        <span>
            Pest Checks
        </span>

        <strong>
            <?= $pestChecks ?>
        </strong>

        <small>
            Assessments submitted
        </small>
    </div>


    <div class="attention">
        <span>
            Needs Follow-up
        </span>

        <strong>
            <?= $followUp ?>
        </strong>

        <small>
            Incidents under review
        </small>
    </div>

</section>

<?php
$farmGalleryVariant = 'farmer';
require __DIR__ . '/includes/farm_gallery.php';
unset($farmGalleryVariant);
?>


<!-- =========================================================
     FARM PROFILES + ADVISORIES
========================================================== -->

<div class="farmer-grid">

    <section class="farmer-panel">

        <div class="farmer-panel-head">

            <div>

                <span class="farmer-kicker">
                    MY FARMS
                </span>

                <h2>
                    Farm Profiles
                </h2>

            </div>

            <a href="farm.php">
                View all →
            </a>

        </div>


        <?php if ($myFarms): ?>

            <div class="farm-cards">

                <?php foreach ($myFarms as $farm): ?>

                    <a
                        class="farm-card"
                        href="farm.php?id=<?= $farm['id'] ?>"
                    >

                        <div class="farm-crop-icon">
                            🌾
                        </div>

                        <div>

                            <b>
                                <?= e($farm['farm_name']) ?>
                            </b>

                            <span>
                                <?= e($farm['crop']) ?>
                                ·
                                <?= e($farm['barangay']) ?>
                            </span>

                            <small>
                                <?= e($farm['area_hectares']) ?>
                                ha
                                ·
                                <?= e(
                                    $farm['growth_stage']
                                    ?: 'Stage not set'
                                ) ?>
                            </small>


                            <?php if (
                                $farm['approval_status'] === 'Approved'
                            ): ?>

                                <small class="farm-status approved">
                                    ✓ Approved · Monitoring Active
                                </small>

                            <?php elseif (
                                $farm['approval_status'] === 'Rejected'
                            ): ?>

                                <small class="farm-status rejected">
                                    ✕ Rejected · View Details
                                </small>

                            <?php else: ?>

                                <small class="farm-status pending">
                                    ● Pending Validation
                                </small>

                            <?php endif; ?>

                        </div>

                    </a>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="farmer-empty">

                <div class="empty-icon">
                    🌾
                </div>

                <h3>
                    No Farms Yet
                </h3>

                <p>
                    Register your first farm and submit it
                    for administrator validation.
                </p>

                <a
                    class="farmer-primary"
                    href="farm.php#add-farm"
                >
                    + Add Your First Farm
                </a>

            </div>

        <?php endif; ?>

    </section>


    <section class="farmer-panel">

        <div class="farmer-panel-head">

            <div>

                <span class="farmer-kicker">
                    AGRICULTURAL GUIDANCE
                </span>

                <h2>
                    Latest Advisories
                </h2>

            </div>

            <a href="advisories.php">
                See all →
            </a>

        </div>


        <div class="advisory-mini-list">

            <?php foreach ($advisories as $advisory): ?>

                <div>

                    <b>
                        <?= e($advisory['title']) ?>
                    </b>

                    <small>
                        <?= e($advisory['crop']) ?>
                        ·
                        <?= e(
                            date(
                                'M d',
                                strtotime(
                                    $advisory['created_at']
                                )
                            )
                        ) ?>
                    </small>

                </div>

            <?php endforeach; ?>


            <?php if (!$advisories): ?>

                <div class="farmer-empty">
                    No published advisories yet.
                </div>

            <?php endif; ?>

        </div>

    </section>

</div>


<!-- =========================================================
     RECENT INCIDENTS
========================================================== -->

<section
    class="farmer-panel"
    style="margin-top: 20px"
>

    <div class="farmer-panel-head">

        <div>

            <span class="farmer-kicker">
                MONITORING ACTIVITY
            </span>

            <h2>
                Recent Pest Incidents
            </h2>

        </div>

        <a href="incidents.php">
            View all →
        </a>

    </div>


    <div class="table-wrap">

        <table class="farmer-table">

            <thead>

                <tr>

                    <th>
                        Date
                    </th>

                    <th>
                        Farm
                    </th>

                    <th>
                        Location
                    </th>

                    <th>
                        Crop
                    </th>

                    <th>Production Outlook</th>

                    <th>
                        Status
                    </th>

                </tr>

            </thead>

            <tbody>

                <?php foreach ($recentIncidents as $incident): ?>

                    <tr>

                        <td>
                            <?= e(
                                date(
                                    'M d, Y',
                                    strtotime(
                                        $incident['created_at']
                                    )
                                )
                            ) ?>
                        </td>

                        <td>
                            <?= e($incident['farm_name']) ?>
                        </td>

                        <td>
                            <?= e($incident['barangay']) ?>
                        </td>

                        <td>
                            <?= e($incident['crop']) ?>
                        </td>

                        <?php $productionRate = estimatedProductionRateFromReport($incident['severity'], $incident['affected_area']); ?>
                        <td>
                            <strong><?= e(number_format($productionRate, 1)) ?>%</strong>
                            <small><?= e(cropOutlookFromProductionRate($productionRate)) ?></small>
                        </td>

                        <td>
                            <?= e($incident['status']) ?>
                        </td>

                    </tr>

                <?php endforeach; ?>


                <?php if (!$recentIncidents): ?>

                    <tr>

                        <td
                            colspan="6"
                            class="empty"
                        >
                            No pest incidents have been
                            recorded for your farms yet.
                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</section>


<!-- =========================================================
     VALIDATION SUMMARY
========================================================== -->

<section
    class="farmer-panel"
    style="margin-top: 20px"
>

    <div class="farmer-panel-head">

        <div>

            <span class="farmer-kicker">
                FARM VALIDATION
            </span>

            <h2>
                Farm Status Summary
            </h2>

        </div>

        <a href="farm.php">
            Manage My Farms →
        </a>

    </div>


    <div class="farmer-status-grid">

        <div class="approved">
            <span>
                Approved
            </span>

            <strong>
                <?= $approvedFarms ?>
            </strong>

            <small>
                Monitoring available
            </small>
        </div>


        <div class="pending">
            <span>
                Pending
            </span>

            <strong>
                <?= $pendingFarms ?>
            </strong>

            <small>
                Awaiting validation
            </small>
        </div>


        <div class="rejected">
            <span>
                Rejected
            </span>

            <strong>
                <?= $rejectedFarms ?>
            </strong>

            <small>
                Needs correction
            </small>
        </div>

    </div>

</section>


<?php

layoutFooter();
?>

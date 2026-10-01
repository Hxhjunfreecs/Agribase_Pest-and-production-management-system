<?php

require 'includes/config.php';
require 'includes/functions.php';
require 'includes/notifications.php';

requireLogin();

$isAdmin = user()['role'] === 'admin';
if (!$isAdmin && user()['role'] !== 'farmer') {
    redirect('dashboard.php');
}

$farmerId = (int) user()['id'];

/*
|--------------------------------------------------------------------------
| ADD FARM
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_farm']) && !$isAdmin) {

    $farmName = trim($_POST['farm_name'] ?? '');
    $barangay = trim($_POST['barangay'] ?? '');
    $crop = trim($_POST['crop'] ?? '');
    $area = (float) ($_POST['area_hectares'] ?? 0);
    $growthStage = trim($_POST['growth_stage'] ?? '');

    if ($farmName === '' || $barangay === '' || $crop === '') {
        flash('error', 'Please complete all required farm information.');
        redirect('farm.php');
    }

    if ($area <= 0) {
        flash('error', 'Farm area must be greater than 0.');
        redirect('farm.php');
    }

    /*
    New farmer farms are ALWAYS Pending.
    The farmer cannot approve their own farm.
    */

    $stmt = $pdo->prepare("
        INSERT INTO farms
        (
            farmer_id,
            farm_name,
            barangay,
            crop,
            area_hectares,
            growth_stage,
            approval_status
        )
        VALUES (?, ?, ?, ?, ?, ?, 'Pending')
    ");

    $stmt->execute([
        $farmerId,
        $farmName,
        $barangay,
        $crop,
        $area,
        $growthStage
    ]);

    notifyAgriBaseAdmins(
        $pdo,
        'New farm awaiting validation',
        user()['full_name'] . ' submitted ' . $farmName . ' in ' . $barangay . '. Review it in AgriBase.'
    );

    flash(
        'success',
        'Farm submitted successfully. It is now waiting for administrator validation.'
    );

    redirect('farm.php');
}


/*
|--------------------------------------------------------------------------
| VIEW SINGLE FARM
|--------------------------------------------------------------------------
*/

$id = (int) ($_GET['id'] ?? 0);

if ($id > 0) {

    $farmSql = "
        SELECT f.*, u.full_name AS farmer_name
        FROM farms f
        INNER JOIN users u ON u.id = f.farmer_id
        WHERE f.id = ?
    ";
    $farmParams = [$id];
    if (!$isAdmin) {
        $farmSql .= ' AND f.farmer_id = ?';
        $farmParams[] = $farmerId;
    }

    $stmt = $pdo->prepare($farmSql);
    $stmt->execute($farmParams);

    $farm = $stmt->fetch();

    if (!$farm) {
        flash('error', 'Farm not found.');
        redirect('farm.php');
    }

    /*
    Only approved farms can be monitored.
    */

    if ($farm['approval_status'] !== 'Approved') {

        layoutHeader('Farm Status', 'farms');
        ?>

        <div class="farmer-page-heading">

            <div>
                <span class="farmer-kicker">FARM PROFILE</span>

                <h1><?= e($farm['farm_name']) ?></h1>

                <p>
                    <?= e($farm['crop']) ?>
                    ·
                    <?= e($farm['barangay']) ?>
                    ·
                    <?= e($farm['area_hectares']) ?> hectares
                </p>
            </div>

            <a class="farmer-cancel" href="farm.php">
                ← Back to My Farms
            </a>

        </div>


        <div class="farmer-panel">

            <div class="farmer-panel-head">

                <div>
                    <span class="farmer-kicker">
                        VALIDATION STATUS
                    </span>

                    <h2>Farm Awaiting Approval</h2>
                </div>

            </div>


            <div style="padding:30px;text-align:center;">

                <?php if ($farm['approval_status'] === 'Pending'): ?>

                    <div style="
                        display:inline-block;
                        padding:10px 18px;
                        border-radius:30px;
                        background:#fff4d6;
                        color:#946200;
                        font-weight:700;
                        margin-bottom:18px;
                    ">
                        PENDING ADMIN VALIDATION
                    </div>

                    <h2>Your farm is waiting for validation.</h2>

                    <p>
                        The administrator must review and approve this farm
                        before it can be monitored and used for crop assessments.
                    </p>

                <?php elseif ($farm['approval_status'] === 'Rejected'): ?>

                    <div style="
                        display:inline-block;
                        padding:10px 18px;
                        border-radius:30px;
                        background:#ffe5e5;
                        color:#a33a3a;
                        font-weight:700;
                        margin-bottom:18px;
                    ">
                        FARM REJECTED
                    </div>

                    <h2>This farm was not approved.</h2>

                    <?php if (!empty($farm['rejection_reason'])): ?>

                        <div class="farmer-help" style="margin-top:20px;">
                            <strong>Administrator Reason:</strong><br>
                            <?= nl2br(e($farm['rejection_reason'])) ?>
                        </div>

                    <?php endif; ?>

                <?php endif; ?>

            </div>

        </div>

        <?php

        layoutFooter();
        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | APPROVED FARM MONITORING
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT *
        FROM pest_assessments
        WHERE farm_id = ?
        ORDER BY created_at DESC
        LIMIT 10
    ");

    $stmt->execute([$id]);
    $assessments = $stmt->fetchAll();


    $stmt = $pdo->prepare("
        SELECT *
        FROM production_assessments
        WHERE farm_id = ?
        ORDER BY created_at DESC
        LIMIT 10
    ");

    $stmt->execute([$id]);
    $production = $stmt->fetchAll();


    $stmt = $pdo->prepare("
        SELECT
            i.*,
            p.crop,
            p.risk,
            p.problem,
            p.severity,
            p.affected_area
        FROM incidents i
        JOIN pest_assessments p
            ON p.id = i.assessment_id
        WHERE i.farm_id = ?
        ORDER BY i.created_at DESC
        LIMIT 10
    ");

    $stmt->execute([$id]);
    $incidents = $stmt->fetchAll();


    layoutHeader('Farm Monitoring', 'farms');

    ?>

    <div class="farmer-page-heading">

        <div>

            <span class="farmer-kicker">
                APPROVED FARM
            </span>

            <h1><?= e($farm['farm_name']) ?></h1>

            <p>
                <?= e($farm['crop']) ?>
                ·
                <?= e($farm['barangay']) ?>
                ·
                <?= e($farm['area_hectares']) ?> hectares
            </p>

        </div>

        <div class="actions">
            <?php if ($isAdmin): ?>
                <a class="btn" href="admin_pest_assessment.php?farm_id=<?= (int) $farm['id'] ?>">New Pest Assessment</a>
                <a class="btn secondary" href="admin_production_assessment.php?farm_id=<?= (int) $farm['id'] ?>">Crop Health</a>
                <a class="btn secondary" href="incidents.php">All Incidents</a>
            <?php else: ?>
                <a class="btn" href="farmer_pest_assessment.php">Pest Check</a>
                <a class="btn secondary" href="farmer_production_assessment.php">Crop Health</a>
            <?php endif; ?>
        </div>

    </div>


    <div class="farmer-stats">

        <div>
            <span>Crop</span>
            <strong style="font-size:20px">
                <?= e($farm['crop']) ?>
            </strong>
            <small>Registered crop</small>
        </div>

        <div>
            <span>Area</span>
            <strong style="font-size:20px">
                <?= e($farm['area_hectares']) ?> ha
            </strong>
            <small>Farm size</small>
        </div>

        <div>
            <span>Growth Stage</span>
            <strong style="font-size:20px">
                <?= e($farm['growth_stage'] ?: 'Not set') ?>
            </strong>
            <small>Current stage</small>
        </div>

        <div>
            <span>Incidents</span>
            <strong style="font-size:20px">
                <?= count($incidents) ?>
            </strong>
            <small>Recorded incidents</small>
        </div>

    </div>


    <div class="farmer-grid">

        <!-- PEST HISTORY -->

        <section class="farmer-panel">

            <div class="farmer-panel-head">

                <div>
                    <span class="farmer-kicker">
                        PEST MONITORING
                    </span>

                    <h2>Assessment History</h2>
                </div>

                <a href="<?=$isAdmin ? 'admin_pest_assessment.php?farm_id=' . (int) $farm['id'] : 'farmer_pest_assessment.php'?>">
                    New Check →
                </a>

            </div>


            <?php if ($assessments): ?>

                <?php foreach ($assessments as $a): ?>
                    <?php $productionRate = estimatedProductionRateFromReport($a['severity'], $a['affected_area']); ?>

                    <div class="list-row">

                        <span>
                            <?= e(date(
                                'M d, Y',
                                strtotime($a['created_at'])
                            )) ?>

                            ·

                            <?= e($a['problem']) ?>
                        </span>

                        <?php if ($isAdmin): ?>
                            <span class="badge <?= strtolower($a['risk']) ?>">
                                <?= e($a['risk']) ?>
                            </span>
                        <?php else: ?>
                            <span>
                                <strong><?= e(number_format($productionRate, 1)) ?>% estimated</strong>
                                <small><?= e(cropOutlookFromProductionRate($productionRate)) ?></small>
                            </span>
                        <?php endif; ?>

                        <a class="btn small secondary" href="assessment_result.php?id=<?= (int) $a['id'] ?>">Details</a>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="farmer-empty">
                    No pest assessments for this farm yet.
                </div>

            <?php endif; ?>

        </section>


        <!-- CROP HEALTH -->

        <section class="farmer-panel">

            <div class="farmer-panel-head">

                <div>
                    <span class="farmer-kicker">
                        CROP HEALTH
                    </span>

                    <h2>Production History</h2>
                </div>

                <a href="<?=$isAdmin ? 'admin_production_assessment.php?farm_id=' . (int) $farm['id'] : 'farmer_production_assessment.php'?>">
                    New Check →
                </a>

            </div>


            <?php if ($production): ?>

                <?php foreach ($production as $p): ?>

                    <div class="list-row">

                        <span>
                            <?= e(date(
                                'M d, Y',
                                strtotime($p['created_at'])
                            )) ?>
                        </span>

                        <span>
                            <?= e($p['overall_condition']) ?>
                        </span>

                        <a class="btn small secondary" href="production_result.php?id=<?= (int) $p['id'] ?>">Details</a>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="farmer-empty">
                    No crop health assessments yet.
                </div>

            <?php endif; ?>

        </section>

    </div>


    <!-- INCIDENTS -->

    <section class="farmer-panel" style="margin-top:20px">

        <div class="farmer-panel-head">

            <div>
                <span class="farmer-kicker">
                    FARM INCIDENTS
                </span>

                <h2>Recent Pest Incidents</h2>
            </div>

        </div>


        <div class="table-wrap">

            <table class="farmer-table">

                <thead>

                    <tr>
                        <th>Date</th>
                        <th>Problem</th>
                        <th><?= $isAdmin ? 'Risk' : 'Production Outlook' ?></th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>

                </thead>

                <tbody>

                    <?php foreach ($incidents as $incident): ?>

                        <tr>

                            <td>
                                <?= e(date(
                                    'M d, Y',
                                    strtotime($incident['created_at'])
                                )) ?>
                            </td>

                            <td>
                                <?= e($incident['problem']) ?>
                            </td>

                            <td>
                                <?php if ($isAdmin): ?>
                                    <span class="badge <?= strtolower($incident['risk']) ?>">
                                        <?= e($incident['risk']) ?>
                                    </span>
                                <?php else: ?>
                                    <?php $productionRate = estimatedProductionRateFromReport($incident['severity'], $incident['affected_area']); ?>
                                    <strong><?= e(number_format($productionRate, 1)) ?>%</strong>
                                    <small><?= e(cropOutlookFromProductionRate($productionRate)) ?></small>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= e($incident['status']) ?>
                            </td>

                            <td>
                                <a class="btn small secondary" href="incident.php?id=<?= (int) $incident['id'] ?>">Details</a>
                            </td>

                        </tr>

                    <?php endforeach; ?>


                    <?php if (!$incidents): ?>

                        <tr>
                            <td colspan="5" class="empty">
                                No incidents recorded for this farm.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>


    <div class="actions" style="margin-top:18px">

        <a class="farmer-cancel" href="<?= $isAdmin ? 'farmer.php?id=' . (int) $farm['farmer_id'] : 'farm.php' ?>">
            <?= $isAdmin ? '← Back to Farmer Farms' : '← Back to My Farms' ?>
        </a>

    </div>

    <?php

    layoutFooter();
    exit;
}


/*
|--------------------------------------------------------------------------
| FARM LIST
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM farms
    WHERE farmer_id = ?
    ORDER BY
        CASE approval_status
            WHEN 'Pending' THEN 1
            WHEN 'Approved' THEN 2
            WHEN 'Rejected' THEN 3
        END,
        id DESC
");

$stmt->execute([$farmerId]);

$farms = $stmt->fetchAll();


layoutHeader('My Farms', 'farms');

?>

<div class="farmer-page-heading">

    <div>

        <span class="farmer-kicker">
            MY FARM PROFILES
        </span>

        <h1>My Farms</h1>

        <p>
            Add as many farm profiles as you own.
            Every farm must be validated by the administrator
            before monitoring begins.
        </p>

    </div>

    <a class="farmer-primary" href="#add-farm">
        + Add New Farm
    </a>

</div>


<!-- FARM SUMMARY -->

<div class="farmer-stats">

    <?php

    $total = count($farms);

    $pending = 0;
    $approved = 0;
    $rejected = 0;

    foreach ($farms as $f) {

        if ($f['approval_status'] === 'Pending') {
            $pending++;
        }

        if ($f['approval_status'] === 'Approved') {
            $approved++;
        }

        if ($f['approval_status'] === 'Rejected') {
            $rejected++;
        }
    }

    ?>

    <div>
        <span>Total Farms</span>
        <strong><?= $total ?></strong>
        <small>All submitted farms</small>
    </div>

    <div>
        <span>Approved</span>
        <strong><?= $approved ?></strong>
        <small>Available for monitoring</small>
    </div>

    <div>
        <span>Pending</span>
        <strong><?= $pending ?></strong>
        <small>Waiting for validation</small>
    </div>

    <div>
        <span>Rejected</span>
        <strong><?= $rejected ?></strong>
        <small>Needs correction</small>
    </div>

</div>


<!-- FARM LIST -->

<div class="farm-profile-grid">

<?php foreach ($farms as $farm): ?>

    <a
        class="farm-profile-card"
        href="farm.php?id=<?= (int) $farm['id'] ?>"
    >

        <div class="farm-profile-icon">
            🌾
        </div>

        <div class="farm-profile-main">

            <h2>
                <?= e($farm['farm_name']) ?>
            </h2>

            <p>
                <?= e($farm['crop']) ?>
                ·
                <?= e($farm['barangay']) ?>
            </p>

            <div class="farm-profile-meta">

                <span>
                    <?= e($farm['area_hectares']) ?> ha
                </span>

                <span>
                    <?= e($farm['growth_stage'] ?: 'Stage not set') ?>
                </span>

            </div>


            <?php if ($farm['approval_status'] === 'Approved'): ?>

                <div style="
                    margin-top:12px;
                    color:#176b42;
                    font-weight:700;
                ">
                    ● Approved
                </div>

            <?php elseif ($farm['approval_status'] === 'Pending'): ?>

                <div style="
                    margin-top:12px;
                    color:#9a6b00;
                    font-weight:700;
                ">
                    ● Pending Validation
                </div>

            <?php else: ?>

                <div style="
                    margin-top:12px;
                    color:#a33a3a;
                    font-weight:700;
                ">
                    ● Rejected
                </div>

            <?php endif; ?>

        </div>


        <span class="farm-arrow" aria-hidden="true">→</span>

    </a>

<?php endforeach; ?>


<?php if (!$farms): ?>

    <div class="farmer-panel" style="grid-column:1/-1">

        <div class="farmer-empty">

            You do not have any farms yet.
            Use the <strong>Add New Farm</strong> button below
            to register your first farm.

        </div>

    </div>

<?php endif; ?>

</div>


<!-- ADD FARM -->

<section
    class="farmer-panel"
    id="add-farm"
    style="margin-top:24px"
>

    <div class="farmer-panel-head">

        <div>

            <span class="farmer-kicker">
                REGISTER FARM
            </span>

            <h2>Add a New Farm</h2>

        </div>

    </div>


    <div class="farmer-help" style="margin-bottom:25px">

        You may register as many farms as you own.
        New farms are automatically marked
        <strong>Pending Validation</strong>.
        The administrator must approve the farm before
        you can submit pest or crop-health assessments for it.

    </div>


    <form method="post">

        <input
            type="hidden"
            name="add_farm"
            value="1"
        >


        <div class="form-grid">

            <div class="field">

                <label>Farm Name *</label>

                <input
                    type="text"
                    name="farm_name"
                    placeholder="Example: Juan Rice Farm 01"
                    required
                >

            </div>


            <div class="field">

                <label>Barangay *</label>

                <input
                    type="text"
                    name="barangay"
                    placeholder="Example: Campo"
                    required
                >

            </div>


            <div class="field">

                <label>Crop *</label>

                <select name="crop" required>

                    <option value="Rice">
                        Rice
                    </option>

                    <option value="Coconut">
                        Coconut
                    </option>

                </select>

            </div>


            <div class="field">

                <label>Farm Area (hectares) *</label>

                <input
                    type="number"
                    name="area_hectares"
                    step="0.01"
                    min="0.01"
                    placeholder="Example: 2.50"
                    required
                >

            </div>


            <div class="field">

                <label>Growth Stage</label>

                <select name="growth_stage">

                    <option value="">
                        Select stage
                    </option>

                    <option>
                        Seedling
                    </option>

                    <option>
                        Vegetative
                    </option>

                    <option>
                        Reproductive
                    </option>

                    <option>
                        Maturity
                    </option>

                    <option>
                        Young
                    </option>

                    <option>
                        Flowering
                    </option>

                    <option>
                        Fruiting
                    </option>

                </select>

            </div>

        </div>


        <div class="farmer-submit-row">

            <button
                type="submit"
                class="farmer-primary submit"
            >
                Submit Farm for Validation →
            </button>

        </div>

    </form>

</section>


<?php layoutFooter(); ?>
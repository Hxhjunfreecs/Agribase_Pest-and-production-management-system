<?php

require 'includes/config.php';
require 'includes/functions.php';
require 'includes/notifications.php';

requireAdmin();

$id = (int) ($_GET['id'] ?? 0);


/*
|--------------------------------------------------------------------------
| GET FARMER
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM users
    WHERE id = ?
    AND role = 'farmer'
");

$stmt->execute([$id]);

$farmer = $stmt->fetch();

if (!$farmer) {
    die('Farmer not found.');
}


/*
|--------------------------------------------------------------------------
| FARM VALIDATION
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['farm_action'])
) {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        flash('error', 'Your session expired. Please refresh the page and try again.');
        redirect('farmer.php?id=' . $id);
    }

    $farmId = (int) ($_POST['farm_id'] ?? 0);

    /*
    Make sure this farm belongs to this farmer.
    */

    $stmt = $pdo->prepare("
        SELECT *
        FROM farms
        WHERE id = ?
        AND farmer_id = ?
    ");

    $stmt->execute([
        $farmId,
        $id
    ]);

    $farm = $stmt->fetch();

    if (!$farm) {
        flash('error', 'Farm not found.');
        redirect('farmer.php?id=' . $id);
    }


    $status = $_POST['approval_status'] ?? '';
    $allowedStatuses = ['Pending', 'Approved', 'Rejected'];

    if (!in_array($status, $allowedStatuses, true)) {
        flash('error', 'Choose a valid farm status.');
        redirect('farmer.php?id=' . $id);
    }

    $reasonLabels = [
        'incomplete_information' => 'Farm information is incomplete',
        'location_unverified' => 'Farm location could not be verified',
        'crop_or_area_unclear' => 'Crop or farm area needs clarification',
        'duplicate_submission' => 'Duplicate or invalid farm submission',
        'other' => 'Other validation concern'
    ];

    $rejectionReason = null;
    if ($status === 'Rejected') {
        $selectedReasons = $_POST['rejection_reasons'] ?? [];
        if (!is_array($selectedReasons)) {
            $selectedReasons = [];
        }
        $selectedReasons = array_filter($selectedReasons, 'is_string');
        $selectedReasons = array_values(array_intersect(
            array_unique($selectedReasons),
            array_keys($reasonLabels)
        ));
        $details = trim($_POST['rejection_details'] ?? '');

        if (!$selectedReasons || $details === '') {
            flash('error', 'Select at least one reason and explain the rejection.');
            redirect('farmer.php?id=' . $id);
        }

        $rejectionReason = "Reasons:\n- " . implode(
            "\n- ",
            array_map(static fn($reason) => $reasonLabels[$reason], $selectedReasons)
        ) . "\n\nAdditional details:\n" . $details;
    }

    $stmt = $pdo->prepare("
        UPDATE farms
        SET
            approval_status = ?,
            approved_by = ?,
            approved_at = CASE WHEN ? = 'Approved' THEN NOW() ELSE NULL END,
            rejection_reason = ?
        WHERE id = ?
        AND farmer_id = ?
    ");
    $stmt->execute([
        $status,
        user()['id'],
        $status,
        $rejectionReason,
        $farmId,
        $id
    ]);

    if ($farm['approval_status'] !== $status) {
        $message = 'Your farm "' . $farm['farm_name'] . '" status is now ' . $status . '.';
        if ($rejectionReason) {
            $message .= ' ' . $rejectionReason;
        }
        sendAgriBaseNotification(
            $farmer,
            $status === 'Approved' ? 'Your AgriBase farm was approved' : 'Your AgriBase farm status was updated',
            $message
        );
    }

    flash('success', 'Farm status updated successfully.');
    redirect('farmer.php?id=' . $id);
}


/*
|--------------------------------------------------------------------------
| GET FARMS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        f.*,
        u.full_name AS approved_by_name
    FROM farms f
    LEFT JOIN users u
        ON u.id = f.approved_by
    WHERE f.farmer_id = ?
    ORDER BY
        CASE f.approval_status
            WHEN 'Pending' THEN 1
            WHEN 'Approved' THEN 2
            WHEN 'Rejected' THEN 3
        END,
        f.id DESC
");

$stmt->execute([$id]);

$farms = $stmt->fetchAll();


$totalFarms = count($farms);

$pending = 0;
$approved = 0;
$rejected = 0;

foreach ($farms as $farm) {

    if ($farm['approval_status'] === 'Pending') {
        $pending++;
    }

    if ($farm['approval_status'] === 'Approved') {
        $approved++;
    }

    if ($farm['approval_status'] === 'Rejected') {
        $rejected++;
    }
}


layoutHeader('Farmer Profile', 'farmers');

?>


<div class="admin-hero compact">

    <div>

        <div class="eyebrow">
            FARMER PROFILE
        </div>

        <h1>
            <?= e($farmer['full_name']) ?>
        </h1>

        <p>
            <?= e($farmer['email']) ?>
            ·
            <?= e($farmer['phone']) ?>
            ·
            <?= e($farmer['address']) ?>
        </p>

    </div>


    <a
        class="admin-btn ghost"
        href="farmers.php"
    >
        ← Back to Farmers
    </a>

</div>


<!-- FARM SUMMARY -->

<div class="admin-kpi-grid">

    <div class="admin-kpi">

        <span>Total Farms</span>

        <strong>
            <?= $totalFarms ?>
        </strong>

        <small>
            Farms submitted
        </small>

    </div>


    <div class="admin-kpi">

        <span>Approved</span>

        <strong>
            <?= $approved ?>
        </strong>

        <small>
            Being monitored
        </small>

    </div>


    <div class="admin-kpi">

        <span>Pending</span>

        <strong>
            <?= $pending ?>
        </strong>

        <small>
            Need validation
        </small>

    </div>


    <div class="admin-kpi">

        <span>Rejected</span>

        <strong>
            <?= $rejected ?>
        </strong>

        <small>
            Not approved
        </small>

    </div>

</div>


<!-- IMPORTANT NOTICE -->

<div class="admin-panel">

    <div style="
        padding:20px;
        background:#eef6ff;
        border-radius:12px;
        color:#27496d;
    ">

        <strong>Farm ownership and validation</strong>

        <p style="margin:8px 0 0">

            The farmer creates and submits their own farms.
            There is no limit on how many farms a farmer may register.
            The administrator only validates the submitted farm.
            Approved farms become available for monitoring and assessment.

        </p>

    </div>

</div>


<!-- FARM LIST -->

<div class="admin-panel" style="margin-top:20px">

    <div class="panel-title">

        <div>

            <span class="eyebrow">
                FARM VALIDATION
            </span>

            <h2>
                <?= e($farmer['full_name']) ?>'s Farms
            </h2>

        </div>

    </div>


    <?php if (!$farms): ?>

        <div class="empty">

            This farmer has not submitted any farms yet.

        </div>

    <?php endif; ?>


    <?php foreach ($farms as $farm): ?>

        <div style="
            border:1px solid #e3e8ef;
            border-radius:16px;
            padding:22px;
            margin-bottom:15px;
            background:#fff;
        ">

            <div style="
                display:flex;
                justify-content:space-between;
                align-items:flex-start;
                gap:20px;
                flex-wrap:wrap;
            ">

                <div>

                    <div class="eyebrow">
                        <?= e($farm['crop']) ?>
                    </div>

                    <h2 style="margin:6px 0">

                        <?= e($farm['farm_name']) ?>

                    </h2>

                    <p>

                        <?= e($farm['barangay']) ?>

                        ·

                        <?= e($farm['area_hectares']) ?>
                        hectares

                        ·

                        <?= e(
                            $farm['growth_stage']
                            ?: 'Stage not set'
                        ) ?>

                    </p>

                </div>


                <?php if ($farm['approval_status'] === 'Approved'): ?>

                    <span style="
                        background:#e3f6eb;
                        color:#176b42;
                        padding:8px 14px;
                        border-radius:30px;
                        font-weight:700;
                    ">
                        APPROVED
                    </span>

                <?php elseif ($farm['approval_status'] === 'Pending'): ?>

                    <span style="
                        background:#fff3d6;
                        color:#946200;
                        padding:8px 14px;
                        border-radius:30px;
                        font-weight:700;
                    ">
                        PENDING
                    </span>

                <?php else: ?>

                    <span style="
                        background:#ffe6e6;
                        color:#a33a3a;
                        padding:8px 14px;
                        border-radius:30px;
                        font-weight:700;
                    ">
                        REJECTED
                    </span>

                <?php endif; ?>

            </div>


            <form method="post" class="farm-status-form">
                <?= csrfField() ?>
                <input type="hidden" name="farm_id" value="<?= (int) $farm['id'] ?>">
                <input type="hidden" name="farm_action" value="status">
                <label for="farm-status-<?= (int) $farm['id'] ?>">Farm status</label>
                <select
                    class="farm-status-select"
                    id="farm-status-<?= (int) $farm['id'] ?>"
                    name="approval_status"
                    data-current="<?= e($farm['approval_status']) ?>"
                    data-dialog="farm-rejection-<?= (int) $farm['id'] ?>"
                >
                    <?php foreach (['Pending', 'Approved', 'Rejected'] as $statusOption): ?>
                        <option value="<?= e($statusOption) ?>" <?= $farm['approval_status'] === $statusOption ? 'selected' : '' ?>>
                            <?= e($statusOption) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="admin-btn ghost" type="submit">Save Status</button>
            </form>

            <dialog class="farm-rejection-dialog" id="farm-rejection-<?= (int) $farm['id'] ?>">
                <form method="post" class="farm-rejection-form">
                    <?= csrfField() ?>
                    <input type="hidden" name="farm_id" value="<?= (int) $farm['id'] ?>">
                    <input type="hidden" name="farm_action" value="status">
                    <input type="hidden" name="approval_status" value="Rejected">
                    <div class="advisory-section-heading">
                        <span class="advisory-kicker">FARM VALIDATION</span>
                        <h2>Rejection reason</h2>
                        <p>Select the relevant reasons and explain what the farmer should correct.</p>
                    </div>
                    <fieldset>
                        <legend>Reasons</legend>
                        <?php foreach ([
                            'incomplete_information' => 'Farm information is incomplete',
                            'location_unverified' => 'Farm location could not be verified',
                            'crop_or_area_unclear' => 'Crop or farm area needs clarification',
                            'duplicate_submission' => 'Duplicate or invalid farm submission',
                            'other' => 'Other validation concern'
                        ] as $reasonKey => $reasonLabel): ?>
                            <label>
                                <input type="checkbox" name="rejection_reasons[]" value="<?= e($reasonKey) ?>">
                                <span><?= e($reasonLabel) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                    <label for="rejection-details-<?= (int) $farm['id'] ?>">Additional explanation</label>
                    <textarea id="rejection-details-<?= (int) $farm['id'] ?>" name="rejection_details" required></textarea>
                    <div class="actions">
                        <button class="admin-btn ghost" type="button" data-close-dialog="farm-rejection-<?= (int) $farm['id'] ?>">Cancel</button>
                        <button class="admin-btn" type="submit">Submit Status Update</button>
                    </div>
                </form>
            </dialog>

            <script>
            if (!window.agribaseFarmStatusHandlersAttached) {
                window.agribaseFarmStatusHandlersAttached = true;

                function cancelFarmRejection(dialog) {
                    dialog.querySelector('form').reset();
                    const statusSelect = [...document.querySelectorAll('.farm-status-select')]
                        .find(select => select.dataset.dialog === dialog.id);
                    if (statusSelect) statusSelect.value = statusSelect.dataset.current;
                }

                document.addEventListener('change', function (event) {
                    const select = event.target.closest('.farm-status-select');
                    if (!select || select.value !== 'Rejected') return;
                    select.value = select.dataset.current;
                    document.getElementById(select.dataset.dialog).showModal();
                });
                document.addEventListener('click', function (event) {
                    const button = event.target.closest('[data-close-dialog]');
                    if (button) {
                        const dialog = document.getElementById(button.dataset.closeDialog);
                        cancelFarmRejection(dialog);
                        dialog.close();
                        return;
                    }

                    if (event.target.matches('.farm-rejection-dialog')) {
                        cancelFarmRejection(event.target);
                        event.target.close();
                    }
                });
                document.addEventListener('cancel', function (event) {
                    const dialog = event.target.closest('.farm-rejection-dialog');
                    if (dialog) cancelFarmRejection(dialog);
                }, true);
                document.addEventListener('keydown', function (event) {
                    if (event.key !== 'Escape') return;
                    const dialog = document.querySelector('.farm-rejection-dialog[open]');
                    if (!dialog) return;
                    event.preventDefault();
                    cancelFarmRejection(dialog);
                    dialog.close();
                });
            }
            </script>


            <?php if (
                $farm['approval_status'] === 'Rejected'
                && !empty($farm['rejection_reason'])
            ): ?>

                <div style="
                    margin-top:18px;
                    padding:15px;
                    border-radius:10px;
                    background:#fff4f4;
                    color:#7e3030;
                ">

                    <strong>
                        Rejection Reason:
                    </strong>

                    <?= nl2br(
                        e($farm['rejection_reason'])
                    ) ?>

                </div>

            <?php endif; ?>


            <?php if (
                $farm['approval_status'] === 'Approved'
            ): ?>

                <div style="
                    margin-top:18px;
                ">

                    <a
                        class="btn small secondary"
                        href="farm.php?id=<?= $farm['id'] ?>"
                    >
                        View Farm Monitoring
                    </a>

                    <?php if ($farm['approved_at']): ?>

                        <small style="margin-left:10px">

                            Approved
                            <?= e(date(
                                'M d, Y',
                                strtotime(
                                    $farm['approved_at']
                                )
                            )) ?>

                            <?php if (
                                !empty(
                                    $farm['approved_by_name']
                                )
                            ): ?>

                                by
                                <?= e(
                                    $farm['approved_by_name']
                                ) ?>

                            <?php endif; ?>

                        </small>

                    <?php endif; ?>

                </div>

            <?php endif; ?>

        </div>

    <?php endforeach; ?>

</div>


<?php layoutFooter(); ?>
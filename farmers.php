<?php

require 'includes/config.php';
require 'includes/functions.php';

requireAdmin();


/*
|--------------------------------------------------------------------------
| GET FARMER LIST
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        u.id,
        u.full_name,
        u.username,
        u.email,
        u.phone,
        u.address,
        u.created_at,

        COUNT(DISTINCT f.id) AS total_farms,

        COUNT(
            DISTINCT CASE
                WHEN f.approval_status = 'Approved'
                THEN f.id
            END
        ) AS approved_farms,

        COUNT(
            DISTINCT CASE
                WHEN f.approval_status = 'Pending'
                THEN f.id
            END
        ) AS pending_farms,

        COUNT(
            DISTINCT CASE
                WHEN f.approval_status = 'Rejected'
                THEN f.id
            END
        ) AS rejected_farms,

        COUNT(DISTINCT i.id) AS incidents

    FROM users u

    LEFT JOIN farms f
        ON f.farmer_id = u.id

    LEFT JOIN incidents i
        ON i.farm_id = f.id

    WHERE u.role = 'farmer'

    GROUP BY u.id

    ORDER BY u.created_at DESC
";

$stmt = $pdo->query($sql);

$farmers = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| FARM VALIDATION SUMMARY
|--------------------------------------------------------------------------
*/

$totalFarmers = count($farmers);

$totalFarms = 0;
$pendingFarms = 0;
$approvedFarms = 0;
$rejectedFarms = 0;

foreach ($farmers as $farmer) {

    $totalFarms += (int) $farmer['total_farms'];
    $pendingFarms += (int) $farmer['pending_farms'];
    $approvedFarms += (int) $farmer['approved_farms'];
    $rejectedFarms += (int) $farmer['rejected_farms'];
}


layoutHeader('Farmer Management', 'farmers');

?>


<div class="admin-hero compact">

    <div>

        <div class="eyebrow">
            FARMER MANAGEMENT
        </div>

        <h1>
            Farmer Management
        </h1>

        <p>
            Monitor registered farmers and validate the farms
            they submit for agricultural monitoring.
        </p>

    </div>

</div>


<!-- SUMMARY -->

<div class="admin-kpi-grid">

    <div class="admin-kpi">

        <span>Registered Farmers</span>

        <strong>
            <?= $totalFarmers ?>
        </strong>

        <small>
            Farmer accounts
        </small>

    </div>


    <div class="admin-kpi">

        <span>Total Farms</span>

        <strong>
            <?= $totalFarms ?>
        </strong>

        <small>
            All farmer submissions
        </small>

    </div>


    <div class="admin-kpi">

        <span>Pending Farms</span>

        <strong>
            <?= $pendingFarms ?>
        </strong>

        <small>
            Require validation
        </small>

    </div>


    <div class="admin-kpi">

        <span>Approved Farms</span>

        <strong>
            <?= $approvedFarms ?>
        </strong>

        <small>
            Currently monitored
        </small>

    </div>

</div>


<!-- FARMER TABLE -->

<div class="admin-panel">

    <div class="panel-title">

        <div>

            <span class="eyebrow">
                REGISTERED FARMERS
            </span>

            <h2>
                Farmer Accounts
            </h2>

        </div>

    </div>


    <div class="table-wrap">

        <table>

            <thead>

                <tr>

                    <th>
                        Farmer
                    </th>

                    <th>
                        Contact
                    </th>

                    <th>
                        Total Farms
                    </th>

                    <th>
                        Approved
                    </th>

                    <th>
                        Pending
                    </th>

                    <th>
                        Rejected
                    </th>

                    <th>
                        Incidents
                    </th>

                    <th>
                        Action
                    </th>

                </tr>

            </thead>


            <tbody>

            <?php foreach ($farmers as $farmer): ?>

                <tr>

                    <td>

                        <strong>
                            <?= e($farmer['full_name']) ?>
                        </strong>

                        <br>

                        <small>
                            @<?= e($farmer['username']) ?>
                        </small>

                    </td>


                    <td>

                        <?= e($farmer['phone']) ?>

                        <br>

                        <small>
                            <?= e($farmer['email']) ?>
                        </small>

                    </td>


                    <td>

                        <strong>
                            <?= $farmer['total_farms'] ?>
                        </strong>

                    </td>


                    <td>

                        <span style="
                            color:#176b42;
                            font-weight:700;
                        ">

                            <?= $farmer['approved_farms'] ?>

                        </span>

                    </td>


                    <td>

                        <?php if ($farmer['pending_farms'] > 0): ?>

                            <span style="
                                color:#9a6b00;
                                font-weight:700;
                            ">

                                <?= $farmer['pending_farms'] ?>

                            </span>

                        <?php else: ?>

                            0

                        <?php endif; ?>

                    </td>


                    <td>

                        <?= $farmer['rejected_farms'] ?>

                    </td>


                    <td>

                        <?= $farmer['incidents'] ?>

                    </td>


                    <td>

                        <a
                            class="btn small secondary"
                            href="farmer.php?id=<?= $farmer['id'] ?>"
                        >
                            Manage Farms
                        </a>

                    </td>

                </tr>

            <?php endforeach; ?>


            <?php if (!$farmers): ?>

                <tr>

                    <td
                        colspan="8"
                        class="empty"
                    >
                        No farmer accounts have been registered yet.
                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


<?php layoutFooter(); ?>
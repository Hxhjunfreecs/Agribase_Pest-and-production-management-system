<?php

require 'includes/config.php';
require 'includes/functions.php';

requireAdmin();


$stmt = $pdo->query("
    SELECT
        i.*,
        p.crop,
        p.risk,
        p.problem,
        p.severity,
        p.affected_area,
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

    ORDER BY i.created_at DESC
");

$rows = $stmt->fetchAll();

?>
<!doctype html>

<html lang="en">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        AgriBase Pest Incident Report
    </title>

    <script src="assets/theme.js?v=20261001-theme-contrast"></script>
    <link
        rel="stylesheet"
        href="assets/style.css?v=20261001-button-motion-4"
    >

</head>

<body>

    <main class="page">

        <div class="actions no-print">

            <button
                class="btn"
                type="button"
                onclick="window.print()"
            >
                Print Report
            </button>

            <a
                class="btn secondary"
                href="analytics.php"
            >
                Back
            </a>

        </div>


        <section class="report-sheet">

            <div class="report-header">

                <div>

                    <h1>
                        AgriBase
                    </h1>

                    <div>
                        Pest Incident Report
                    </div>

                </div>

                <div>
                    <?= date('F d, Y') ?>
                </div>

            </div>


            <div class="table-wrap report-table-wrap">
            <table>

                <thead>

                    <tr>

                        <th>
                            Date
                        </th>

                        <th>
                            Farmer
                        </th>

                        <th>
                            Farm
                        </th>

                        <th>
                            Crop
                        </th>

                        <th>
                            Barangay
                        </th>

                        <th>
                            Risk
                        </th>

                        <th>
                            Status
                        </th>

                    </tr>

                </thead>

                <tbody>

                    <?php foreach ($rows as $row): ?>

                        <tr>

                            <td>
                                <?= e(
                                    date(
                                        'M d, Y',
                                        strtotime(
                                            $row['created_at']
                                        )
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= e($row['farmer']) ?>
                            </td>

                            <td>
                                <?= e($row['farm_name']) ?>
                            </td>

                            <td>
                                <?= e($row['crop']) ?>
                            </td>

                            <td>
                                <?= e($row['barangay']) ?>
                            </td>

                            <td>
                                <?= e($row['risk']) ?>
                            </td>

                            <td>
                                <?= e($row['status']) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>


                    <?php if (!$rows): ?>

                        <tr>

                            <td colspan="7">
                                No incident records found.
                            </td>

                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>
            </div>

        </section>

    </main>

    <script src="assets/i18n.js?v=20260929-5"></script>
</body>

</html>

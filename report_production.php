<?php

require 'includes/config.php';
require 'includes/functions.php';

requireAdmin();


$stmt = $pdo->query("
    SELECT
        p.*,
        f.farm_name,
        f.barangay,
        u.full_name AS assessed_by_name

    FROM production_assessments p

    INNER JOIN farms f
        ON f.id = p.farm_id

    INNER JOIN users u
        ON u.id = p.assessed_by

    ORDER BY p.created_at DESC
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
        AgriBase Production Health Report
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
                        Production Health Assessment Report
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
                            Farm
                        </th>

                        <th>
                            Barangay
                        </th>

                        <th>
                            Crop
                        </th>

                        <th>
                            Condition
                        </th>

                        <th>
                            Affected
                        </th>

                        <th>
                            Production
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
                                <?= e($row['farm_name']) ?>
                            </td>

                            <td>
                                <?= e($row['barangay']) ?>
                            </td>

                            <td>
                                <?= e($row['crop']) ?>
                            </td>

                            <td>
                                <?= e(
                                    $row['overall_condition']
                                ) ?>
                            </td>

                            <td>
                                <?= e(
                                    $row['affected_area']
                                ) ?>%
                            </td>

                            <td>
                                <?= e(
                                    $row['production_condition']
                                ) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>


                    <?php if (!$rows): ?>

                        <tr>

                            <td colspan="7">
                                No crop health records found.
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

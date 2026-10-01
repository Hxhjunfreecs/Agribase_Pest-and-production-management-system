<?php

require 'includes/config.php';
require 'includes/functions.php';

requireAdmin();

$riskFilter = $_GET['risk'] ?? 'All';
if (!in_array($riskFilter, ['All', 'Low', 'Moderate', 'High'], true)) {
    $riskFilter = 'All';
}


function analyticsCount($pdo, $sql, $params = [])
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}


$assessmentCount = analyticsCount(
    $pdo,
    "
        SELECT COUNT(*)
        FROM pest_assessments
    "
);

$incidentCount = analyticsCount(
    $pdo,
    "
        SELECT COUNT(*)
        FROM incidents
    "
);

$highRiskCount = analyticsCount(
    $pdo,
    "
        SELECT COUNT(*)
        FROM pest_assessments
        WHERE risk = 'High'
    "
);

$farmsMonitored = analyticsCount(
    $pdo,
    "
        SELECT COUNT(*)
        FROM farms
        WHERE approval_status = 'Approved'
    "
);


layoutHeader(
    'Agricultural Analytics',
    'analytics'
);

?>

<div class="page-head">
    <div>
        <h1>Agricultural Analytics</h1>
        <p>Analytics generated from records actually stored in the AgriBase database.</p>
    </div>
    <a class="btn secondary" href="report_analytics.php">Printable Analytics Report</a>
</div>

<div class="grid grid-4">
    <div class="card stat">
        <div class="label">Total Pest Assessments</div>
        <div class="value"><?= $assessmentCount ?></div>
    </div>
    <div class="card stat">
        <div class="label">Pest Incidents</div>
        <div class="value"><?= $incidentCount ?></div>
    </div>
    <div class="card stat">
        <div class="label">High-Risk Records</div>
        <div class="value"><?= $highRiskCount ?></div>
    </div>
    <div class="card stat">
        <div class="label">Farms Monitored</div>
        <div class="value"><?= $farmsMonitored ?></div>
    </div>
</div>

<?php require __DIR__ . '/includes/incident_graphs.php'; ?>


<div
    class="grid grid-2"
    style="margin-top: 18px"
>

    <section class="card">

        <h2>
            What the Analytics Do
        </h2>

        <p>
            The system summarizes recorded assessments,
            incidents, risk levels, crop records, and
            approved farms. The dashboard updates as
            new records are entered.

        </p>

    </section>


    <section class="card">

        <h2>
            Dataset-Free Decision Support
        </h2>

        <p>
            Pest risk classification uses predefined
            assessment rules. This allows AgriBase to
            provide structured decision support without
            requiring an unavailable machine-learning
            training dataset.

        </p>

    </section>

</div>


<?php

layoutFooter();
?>

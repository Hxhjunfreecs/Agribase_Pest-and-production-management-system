<?php
$riskFilter = $riskFilter ?? 'All';
$incidentGraphData = incidentAnalyticsData($pdo, $riskFilter);
$barangayMaximum = max(1, max($incidentGraphData['barangays'] ?: [0]));
$problemMaximum = max(1, max($incidentGraphData['problems'] ?: [0]));
$cropMaximum = max(1, max($incidentGraphData['crops'] ?: [0]));
$hasIncidentGraphData = array_sum($incidentGraphData['risks']) > 0;
?>

<section class="incident-analytics-section" aria-labelledby="incident-analytics-title">
    <div class="incident-analytics-heading">
        <div>
            <span class="eyebrow">INCIDENT PATTERNS</span>
            <h2 id="incident-analytics-title">Affected Areas and Crops</h2>
        </div>
        <form method="get" action="<?=e(basename($_SERVER['SCRIPT_NAME'] ?? ''))?>" class="incident-risk-filter">
            <label for="incident-risk-filter">Risk level</label>
            <select id="incident-risk-filter" name="risk" onchange="this.form.submit()">
                <?php foreach (['All', 'Low', 'Moderate', 'High'] as $riskOption): ?>
                    <option value="<?=e($riskOption)?>" <?=$riskFilter === $riskOption ? 'selected' : ''?>>
                        <?=e($riskOption === 'All' ? 'All risk levels' : $riskOption)?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if (!$hasIncidentGraphData): ?>
        <div class="card incident-graphs-empty">No incident data is available for this risk level.</div>
    <?php else: ?>
        <div class="incident-graph-grid">
            <section class="card incident-graph-card">
                <h3>Most Affected Barangays</h3>
                <p>Incident count by barangay</p>
                <div class="incident-graph-rows">
                    <?php foreach ($incidentGraphData['barangays'] as $barangay => $count): ?>
                        <div class="incident-graph-row">
                            <span><?=e($barangay)?></span>
                            <progress max="<?=$barangayMaximum?>" value="<?=e($count)?>" aria-label="<?=e($barangay)?> incidents"></progress>
                            <strong><?=e($count)?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="card incident-graph-card">
                <h3>Risk Cases by Barangay</h3>
                <p>Compare Low, Moderate, and High cases</p>
                <div class="incident-risk-legend">
                    <span class="low">Low</span>
                    <span class="moderate">Moderate</span>
                    <span class="high">High</span>
                </div>
                <div class="incident-graph-rows">
                    <?php foreach ($incidentGraphData['risk_by_barangay'] as $barangay => $riskCounts): ?>
                        <?php foreach (['Low', 'Moderate', 'High'] as $riskLevel): ?>
                            <?php $count = (int) ($riskCounts[$riskLevel] ?? 0); ?>
                            <?php if ($count > 0): ?>
                                <div class="incident-graph-row risk-<?=e(strtolower($riskLevel))?>">
                                    <span><?=e($barangay)?> · <?=e($riskLevel)?></span>
                                    <progress max="<?=$barangayMaximum?>" value="<?=$count?>" aria-label="<?=e($barangay)?> <?=e($riskLevel)?> cases"></progress>
                                    <strong><?=$count?></strong>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="card incident-graph-card">
                <h3>Most Common Incident Types</h3>
                <p>Recorded crop problems</p>
                <div class="incident-graph-rows">
                    <?php foreach ($incidentGraphData['problems'] as $problem => $count): ?>
                        <div class="incident-graph-row">
                            <span><?=e($problem)?></span>
                            <progress max="<?=$problemMaximum?>" value="<?=e($count)?>" aria-label="<?=e($problem)?> incidents"></progress>
                            <strong><?=e($count)?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="card incident-graph-card">
                <h3>Affected Crops</h3>
                <p>Incident count by crop</p>
                <div class="incident-graph-rows">
                    <?php foreach ($incidentGraphData['crops'] as $crop => $count): ?>
                        <div class="incident-graph-row">
                            <span><?=e($crop)?></span>
                            <progress max="<?=$cropMaximum?>" value="<?=e($count)?>" aria-label="<?=e($crop)?> incidents"></progress>
                            <strong><?=e($count)?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
    <?php endif; ?>
</section>
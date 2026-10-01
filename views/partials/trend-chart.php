<?php
/**
 * One small-multiple line chart: a single offense across every imported year.
 *
 * Expects $chartId (unique, safe for an id), $chartLabel, and $chartSeries
 * (year => count|null, oldest first). A null year is a gap in the line, not a
 * zero. Single series, so no legend: the heading names it. The latest value is
 * labelled directly; every value is in the <desc>, in each point's tooltip and
 * in the table below the charts.
 */
$width = 280;
$height = 140;
$left = 30;
$right = 18;
$top = 22;
$bottom = 26;

$years = array_keys($chartSeries);
$values = array_filter($chartSeries, static fn ($v) => $v !== null);
$maxValue = $values === [] ? 0 : max($values);
// At least 5, so a change from 0 to 1 is drawn as the small change it is
// rather than a line from the floor to the ceiling.
$yMax = (int) max(5, ceil($maxValue / 5) * 5);

$count = count($years);
$xFor = static function (int $index) use ($count, $left, $right, $width): float {
    return $count <= 1 ? ($left + $width - $right) / 2 : $left + $index * (($width - $left - $right) / ($count - 1));
};
$yFor = static fn (int $value): float => $top + ($height - $top - $bottom) * (1 - $value / $yMax);

$path = '';
$penDown = false;
$points = [];
foreach ($years as $index => $year) {
    $value = $chartSeries[$year];
    if ($value === null) {
        $penDown = false;
        continue;
    }

    $x = round($xFor($index), 2);
    $y = round($yFor((int) $value), 2);
    $path .= ($penDown ? ' L' : ' M') . $x . ' ' . $y;
    $penDown = true;
    $points[] = ['x' => $x, 'y' => $y, 'year' => $year, 'value' => (int) $value];
}

$labelEvery = $count <= 6 ? 1 : (int) ceil($count / 5);
$latest = $points === [] ? null : end($points);

$descParts = [];
foreach ($chartSeries as $year => $value) {
    $descParts[] = $year . ': ' . ($value === null ? 'not reported' : $value);
}
$baselineY = $height - $bottom;
?>
<svg class="chart-svg" viewBox="0 0 <?= $width ?> <?= $height ?>" role="img" aria-labelledby="<?= $chartId ?>-t <?= $chartId ?>-d" focusable="false">
    <title id="<?= $chartId ?>-t"><?= htmlspecialchars($chartLabel) ?>, reported each year</title>
    <desc id="<?= $chartId ?>-d"><?= htmlspecialchars(implode('; ', $descParts)) ?>.</desc>
    <line class="chart-gridline" x1="<?= $left ?>" x2="<?= $width - $right / 2 ?>" y1="<?= $top ?>" y2="<?= $top ?>"></line>
    <line class="chart-baseline" x1="<?= $left ?>" x2="<?= $width - $right / 2 ?>" y1="<?= $baselineY ?>" y2="<?= $baselineY ?>"></line>
    <text class="trend-label" x="<?= $left - 6 ?>" y="<?= $top + 4 ?>" text-anchor="end"><?= $yMax ?></text>
    <text class="trend-label" x="<?= $left - 6 ?>" y="<?= $baselineY + 4 ?>" text-anchor="end">0</text>
    <?php foreach ($years as $index => $year): ?>
    <?php if ($index % $labelEvery === 0 || $index === $count - 1): ?>
    <text class="trend-label" x="<?= round($xFor($index), 2) ?>" y="<?= $height - 6 ?>" text-anchor="middle"><?= (int) $year ?></text>
    <?php endif; ?>
    <?php endforeach; ?>
    <?php if ($path !== ''): ?>
    <path class="chart-line s1" d="<?= trim($path) ?>"></path>
    <?php endif; ?>
    <?php foreach ($points as $point): ?>
    <g class="s1">
        <title><?= (int) $point['year'] ?>: <?= (int) $point['value'] ?> reported</title>
        <circle cx="<?= $point['x'] ?>" cy="<?= $point['y'] ?>" r="12" fill="transparent"></circle>
        <circle class="chart-dot<?= $point === $latest ? ' chart-dot-active' : '' ?>" cx="<?= $point['x'] ?>" cy="<?= $point['y'] ?>" r="4"></circle>
    </g>
    <?php endforeach; ?>
    <?php if ($latest !== null): ?>
    <text class="trend-value" x="<?= $latest['x'] ?>" y="<?= max(12, $latest['y'] - 9) ?>" text-anchor="<?= $count > 1 ? 'end' : 'middle' ?>"><?= (int) $latest['value'] ?></text>
    <?php endif; ?>
</svg>

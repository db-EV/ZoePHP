<!DOCTYPE html>
<html lang="<?= htmlspecialchars($country, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <link rel="stylesheet" href="stylesheet.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($zoename, ENT_QUOTES, 'UTF-8') ?></title>
</head>
<body>
<div id="container">
<main>
<article>
<table>
    <tr align="left"><th><?= htmlspecialchars($zoename, ENT_QUOTES, 'UTF-8') ?></th></tr>
    <tr><td colspan="2"><hr></td></tr>

    <?php foreach ($charges as $charge): ?>
    <tr>
        <td><?= $lng['Start'] ?>:</td>
        <td><?= htmlspecialchars($charge['start_date'], ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($charge['start_time'], ENT_QUOTES, 'UTF-8') ?></td>
    </tr>
    <tr>
        <td><?= $lng['Charging'] ?>:</td>
        <td><?= htmlspecialchars($charge['energy'], ENT_QUOTES, 'UTF-8') ?> kWh <?= $lng['in'] ?> <?= (int) $charge['duration_min'] ?> <?= $lng['minutes'] ?></td>
    </tr>
    <?php if ($charge['duration_min'] > 0): ?>
    <tr>
        <td><?= $lng['AverageChargingPower'] ?>:</td>
        <td><?= htmlspecialchars($charge['avg_power'], ENT_QUOTES, 'UTF-8') ?> kW</td>
    </tr>
    <?php endif; ?>
    <tr>
        <td><?= $lng['Status'] ?>:</td>
        <td><?= htmlspecialchars($charge['end_status'], ENT_QUOTES, 'UTF-8') ?> <?= $lng['at'] ?> <?= htmlspecialchars($charge['end_date'], ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($charge['end_time'], ENT_QUOTES, 'UTF-8') ?></td>
    </tr>
    <tr><td colspan="2"><hr></td></tr>
    <?php endforeach; ?>

    <tr><td colspan="2"><a href="./"><?= $lng['Back'] ?></a></td></tr>
</table>
</article>
</main>
</div>
</body>
</html>

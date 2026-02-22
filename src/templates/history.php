<!DOCTYPE html>
<html lang="<?= htmlspecialchars($country) ?>">
<head>
    <link rel="stylesheet" href="stylesheet.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($zoename) ?></title>
</head>
<body>
<div id="container">
<main>
<article>
<table>
    <tr align="left"><th><?= htmlspecialchars($zoename) ?></th></tr>
    <tr><td colspan="2"><hr></td></tr>

    <?php foreach ($charges as $charge): ?>
    <tr>
        <td><?= $lng['Start'] ?>:</td>
        <td><?= htmlspecialchars($charge['start_date']) ?> <?= htmlspecialchars($charge['start_time']) ?></td>
    </tr>
    <tr>
        <td><?= $lng['Charging'] ?>:</td>
        <td><?= htmlspecialchars($charge['energy']) ?> kWh <?= $lng['in'] ?> <?= (int) $charge['duration_min'] ?> <?= $lng['minutes'] ?></td>
    </tr>
    <?php if ($charge['duration_min'] > 0): ?>
    <tr>
        <td><?= $lng['AverageChargingPower'] ?>:</td>
        <td><?= htmlspecialchars($charge['avg_power']) ?> kW</td>
    </tr>
    <?php endif; ?>
    <tr>
        <td><?= $lng['Status'] ?>:</td>
        <td><?= htmlspecialchars($charge['end_status']) ?> <?= $lng['at'] ?> <?= htmlspecialchars($charge['end_date']) ?> <?= htmlspecialchars($charge['end_time']) ?></td>
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

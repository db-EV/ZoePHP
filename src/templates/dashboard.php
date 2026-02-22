<!DOCTYPE html>
<html lang="<?= htmlspecialchars($country) ?>">
<head>
    <link rel="manifest" href="zoephp.webmanifest">
    <link rel="stylesheet" href="stylesheet.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($zoename) ?></title>
</head>
<body>
<div id="container">
<main>
<?php if ($mail_bl): ?>
<form action="<?= htmlspecialchars($requestUri) ?>" method="post" autocomplete="off">
<?php endif; ?>
<article>
<table>
    <tr align="left">
        <th><?= htmlspecialchars($zoename) ?></th>
        <td><small><a href="<?= htmlspecialchars($requestUri) ?>"><?= $lng['Update'] ?></a></small></td>
    </tr>

    <?php foreach ($notices as $notice): ?>
    <tr><td colspan="2"><?= $notice ?></td></tr>
    <?php endforeach; ?>

    <tr>
        <td><?= $lng['Mileage'] ?>:</td>
        <td><?= htmlspecialchars((string) $session['mileage']) ?> km</td>
    </tr>
    <tr>
        <td><?= $lng['Connected'] ?>:</td>
        <td><?= $session['plug_status'] == 0 ? $lng['No'] : $lng['Yes'] ?></td>
    </tr>
    <tr>
        <td><?= $lng['Charging'] ?>:</td>
        <td><?= $session['charging_status'] == 1 ? $lng['Yes'] : $lng['No'] ?></td>
    </tr>
    <?php if ($session['charging_status'] == 1): ?>
    <tr>
        <td><?= $lng['Ready'] ?>:</td>
        <td><?= htmlspecialchars($readyTime) ?></td>
    </tr>
    <?php if ($zoeph == 1 && $session['charging_power'] > 0): ?>
    <tr>
        <td><?= $lng['Effect'] ?>:</td>
        <td><?= htmlspecialchars((string) $session['charging_power']) ?> kW</td>
    </tr>
    <?php endif; ?>
    <?php endif; ?>

    <?php if (!$hide_cm): ?>
    <tr>
        <td><?= $lng['Charging schedule'] ?>:</td>
        <td><?php $cm = (string) $session['charge_mode'];
            echo (str_starts_with($cm, 'always') || $cm === 'n/a')
            ? $lng['Inactive'] : $lng['Active']; ?></td>
    </tr>
    <?php endif; ?>

    <tr>
        <td><?= $lng['Battery level'] ?>:</td>
        <td><?= htmlspecialchars((string) $session['battery_level']) ?> %</td>
    </tr>

    <?php if ($mail_bl || $cmon_bl || !empty($exec_bl)): ?>
    <tr>
        <td><?= $lng['Action at battery level'] ?>:</td>
        <td>
            <input type="number" name="bl" value="<?= (int) $session['notify_bl'] ?>" min="1" max="99">
            <input type="submit" value="%">
        </td>
    </tr>
    <?php endif; ?>

    <tr>
        <td><?= $lng['Range'] ?>:</td>
        <td><?= htmlspecialchars((string) $session['range_km']) ?> km</td>
    </tr>

    <?php if ($zoeph == 2 && $weather_api_key !== ''): ?>
    <tr>
        <td><?= $lng['Outside temperature'] ?>:</td>
        <td><?= htmlspecialchars((string) $session['temperature']) ?> &deg;C (<?= htmlspecialchars((string) $session['weather']) ?>)</td>
    </tr>
    <?php endif; ?>

    <tr>
        <td><?= $lng['Status update'] ?>:</td>
        <td><?= htmlspecialchars((string) $session['status_date']) ?> <?= htmlspecialchars((string) $session['status_time']) ?></td>
    </tr>

    <?php if ($zoeph == 2): ?>
    <?php $lat = (string) $session['gps_lat']; $lon = (string) $session['gps_lon']; ?>
    <tr>
        <td><?= $lng['Car position'] ?>:</td>
        <td>
        <?php if ($map_provider === 'osm'): ?>
            <a href="https://www.openstreetmap.org/?mlat=<?= $lat ?>&amp;mlon=<?= $lon ?>&amp;zoom=17" target="_blank">OpenStreetMap</a>
        <?php else: ?>
            <a href="https://www.google.com/maps/place/<?= $lat ?>,<?= $lon ?>" target="_blank">Google Maps</a>
        <?php endif; ?>
        </td>
    </tr>
    <tr>
        <td><?= $lng['Position update'] ?>:</td>
        <td><?= htmlspecialchars((string) $session['gps_date']) ?> <?= htmlspecialchars((string) $session['gps_time']) ?></td>
    </tr>
    <?php endif; ?>

    <tr><td colspan="2"><a href="<?= htmlspecialchars($requestUri) ?>?acnow"><?= $lng['Start preconditioning'] ?></a></td></tr>

    <?php if (!$hide_cm): ?>
    <tr><td colspan="2"><?= $lng['Charging schedule'] ?>: <a href="<?= htmlspecialchars($requestUri) ?>?cmon"><?= $lng['on'] ?></a> | <a href="<?= htmlspecialchars($requestUri) ?>?cmoff"><?= $lng['off'] ?></a></td></tr>
    <tr><td colspan="2"><a href="<?= htmlspecialchars($requestUri) ?>?chargenow"><?= $lng['Start charging'] ?></a></td></tr>
    <?php endif; ?>

    <?php if ($zoeph == 2): ?>
    <tr><td colspan="2"><a href="history.php"><?= $lng['Charging history'] ?></a></td></tr>
    <?php endif; ?>
</table>
</article>
<?php if ($mail_bl): ?>
</form>
<?php endif; ?>
</main>
</div>
</body>
</html>

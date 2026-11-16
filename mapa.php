<?php
// Pared del MAPA: los 4 países y flechas con lo que se venden entre ellos.
// Flecha más gruesa = más comercio. Flecha roja = el país que compra pone un arancel.
// Se dibuja con SVG generado desde PHP (no necesita JavaScript).
require __DIR__ . '/config.php';

$partida = Almacen::leer();

if (!$partida) {
    Vista::cabecera('Mapa', REFRESCO, 'pared centrado');
    echo '<div class="tarjeta login"><h1>🗺️ Mapa del comercio</h1><p>Esperando a que el profesor cree la partida…</p></div>';
    Vista::pie();
    exit;
}

// Posición de cada país en el dibujo (ancho 1100 x alto 660)
$posiciones = [
    'us' => [150, 330],
    'es' => [550, 110],
    'cn' => [950, 330],
    'sa' => [550, 550],
];
// Parejas que se cruzan en el centro: su etiqueta se pone más cerca del país que vende
$cruzadas = ['us-cn', 'cn-us', 'es-sa', 'sa-es'];
$radio = 75;
$ultimo = $partida->getUltimo();
$titulo = $ultimo['anio'] ? 'Comercio en ' . $ultimo['anio'] : 'Comercio al empezar la partida';

// Calcula las flechas
$flechas = [];
foreach ($ultimo['flujos'] as $e => $porImportador) {
    foreach ($porImportador as $i => $porSector) {
        if (!isset($posiciones[$e], $posiciones[$i])) {
            continue;
        }
        [$x1, $y1] = $posiciones[$e];
        [$x2, $y2] = $posiciones[$i];
        $dx = $x2 - $x1;
        $dy = $y2 - $y1;
        $largo = sqrt($dx * $dx + $dy * $dy);
        $ux = $dx / $largo;
        $uy = $dy / $largo;
        // Separar un poco la ida y la vuelta para que no se pisen
        $ox = -$uy * 14;
        $oy = $ux * 14;
        $valor = array_sum($porSector);
        $arancel = $ultimo['aranceles'][$i][$e] ?? 0;
        $flechas[] = [
            'x1' => $x1 + $ux * ($radio + 8) + $ox,
            'y1' => $y1 + $uy * ($radio + 8) + $oy,
            'x2' => $x2 - $ux * ($radio + 22) + $ox,
            'y2' => $y2 - $uy * ($radio + 22) + $oy,
            // Etiqueta encima de su flecha, cerca del país que vende
            'lx' => $x1 + $dx * (in_array("$e-$i", $cruzadas) ? 0.3 : 0.33) + $ox,
            'ly' => $y1 + $dy * (in_array("$e-$i", $cruzadas) ? 0.3 : 0.33) + $oy,
            'grosor' => min(22, 2 + $valor / 4),
            'arancel' => $arancel,
            'texto' => Vista::num($valor) . ' mil M€',
            'iconos' => implode(' ', array_map(fn($s) => SECTORES[$s]['icono'], array_keys($porSector))),
        ];
    }
}

Vista::cabecera('Mapa · ' . $titulo, REFRESCO, 'pared');
?>
<header class="barra">
    <h1>🗺️ <?= Vista::e($titulo) ?></h1>
    <div class="leyenda">
        <span><i class="linea morada"></i> comercio libre</span>
        <span><i class="linea roja"></i> con arancel</span>
        <span>Más gruesa = más comercio</span>
    </div>
    <div class="anio">Año en curso <strong><?= $partida->getAnio() ?></strong></div>
</header>

<svg class="mapa" viewBox="0 0 1100 660" xmlns="http://www.w3.org/2000/svg">
    <defs>
        <marker id="punta-morada" viewBox="0 0 10 10" refX="1" refY="5" markerWidth="16" markerHeight="16"
                markerUnits="userSpaceOnUse" orient="auto">
            <path d="M0,0 L10,5 L0,10 z" fill="#6c5ce7"/>
        </marker>
        <marker id="punta-roja" viewBox="0 0 10 10" refX="1" refY="5" markerWidth="16" markerHeight="16"
                markerUnits="userSpaceOnUse" orient="auto">
            <path d="M0,0 L10,5 L0,10 z" fill="#e5484d"/>
        </marker>
    </defs>

    <!-- Primero todas las flechas... -->
    <?php foreach ($flechas as $f):
        $color = $f['arancel'] > 0 ? '#e5484d' : '#6c5ce7';
        $punta = $f['arancel'] > 0 ? 'punta-roja' : 'punta-morada'; ?>
        <line x1="<?= round($f['x1']) ?>" y1="<?= round($f['y1']) ?>" x2="<?= round($f['x2']) ?>" y2="<?= round($f['y2']) ?>"
              stroke="<?= $color ?>" stroke-width="<?= round($f['grosor'], 1) ?>" stroke-linecap="round"
              opacity="0.85" marker-end="url(#<?= $punta ?>)"/>
    <?php endforeach; ?>

    <!-- ...y después las etiquetas, para que ninguna flecha las tape -->
    <?php foreach ($flechas as $f):
        $color = $f['arancel'] > 0 ? '#e5484d' : '#6c5ce7'; ?>
        <g class="etiqueta-flecha">
            <rect x="<?= round($f['lx'] - 60) ?>" y="<?= round($f['ly'] - 20) ?>" width="120" height="<?= $f['arancel'] > 0 ? 58 : 40 ?>"
                  rx="10" fill="#ffffff" stroke="<?= $color ?>" stroke-width="1.5"/>
            <text x="<?= round($f['lx']) ?>" y="<?= round($f['ly'] - 3) ?>" text-anchor="middle" class="valor"><?= Vista::e($f['texto']) ?></text>
            <text x="<?= round($f['lx']) ?>" y="<?= round($f['ly'] + 14) ?>" text-anchor="middle" class="iconos"><?= $f['iconos'] ?></text>
            <?php if ($f['arancel'] > 0): ?>
                <text x="<?= round($f['lx']) ?>" y="<?= round($f['ly'] + 32) ?>" text-anchor="middle" class="arancel">arancel <?= $f['arancel'] ?> %</text>
            <?php endif; ?>
        </g>
    <?php endforeach; ?>

    <?php foreach ($partida->getPaises() as $codigo => $pais):
        if (!isset($posiciones[$codigo])) continue;
        [$x, $y] = $posiciones[$codigo]; ?>
        <g class="nodo">
            <circle cx="<?= $x ?>" cy="<?= $y ?>" r="<?= $radio ?>" fill="#ffffff" stroke="#6c5ce7" stroke-width="4"/>
            <image href="<?= Vista::e($pais->getBandera()) ?>" x="<?= $x - 36 ?>" y="<?= $y - 52 ?>" width="72" height="48"
                   preserveAspectRatio="xMidYMid meet"/>
            <text x="<?= $x ?>" y="<?= $y + 18 ?>" text-anchor="middle" class="nombre"><?= Vista::e($pais->getNombre()) ?></text>
            <text x="<?= $x ?>" y="<?= $y + 42 ?>" text-anchor="middle" class="dato">💰 <?= Vista::num($pais->getIndicador('pib')) ?></text>
        </g>
    <?php endforeach; ?>
</svg>
<?php
Vista::pie();

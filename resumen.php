<?php
// Resumen de la partida: cómo ha quedado cada país, qué le afectó y cómo les fue a sus negocios (sectores).
require __DIR__ . '/config.php';

$partida = Almacen::leer();

if (!$partida || !$partida->getInicio()) {
    Vista::cabecera('Resumen', 0, 'centrado');
    echo '<div class="tarjeta login"><h1>Resumen</h1><p>No hay ninguna partida.</p></div>';
    Vista::pie();
    exit;
}

$inicio = $partida->getInicio();
$historial = $partida->getHistorial();
$anioFinal = $historial ? end($historial)['anio'] : $inicio['anio'];

// Ranking por bienestar final
$paises = $partida->getPaises();
uasort($paises, fn($a, $b) => $b->getIndicador('bienestar') <=> $a->getIndicador('bienestar'));

Vista::cabecera('Resumen de la partida');
?>
<header class="barra">
    <h1>🏁 Resumen de la partida</h1>
    <div class="anio"><?= $inicio['anio'] ?> – <?= $anioFinal ?> · <strong><?= count($historial) ?></strong> años jugados</div>
    <nav><a class="boton" href="profesor.php">Panel del profesor</a></nav>
</header>

<main class="contenido">
    <?php if ($partida->estaJugando()): ?>
        <p class="aviso">La partida sigue en curso: esto es el resumen hasta ahora.</p>
    <?php endif; ?>

    <section class="tarjeta">
        <h2>🏆 Clasificación por bienestar</h2>
        <ol class="ranking">
            <?php foreach ($paises as $codigo => $pais): ?>
                <li>
                    <?= Vista::bandera($pais, 'bandera mini') ?>
                    <strong><?= Vista::e($pais->getNombre()) ?></strong>
                    <span><?= Vista::num($pais->getIndicador('bienestar')) ?> / 100</span>
                    <?= Vista::cambio('bienestar', $pais->getIndicador('bienestar'), $inicio['paises'][$codigo]['indicadores']['bienestar']) ?>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>

    <?php foreach ($paises as $codigo => $pais):
        $ini = $inicio['paises'][$codigo];
        $exportFinal = $historial ? end($historial)['exportaciones'][$codigo] : $ini['exportaciones']; ?>
        <section class="tarjeta resumen-pais">
            <div class="pais-cabecera">
                <?= Vista::bandera($pais) ?>
                <h2><?= Vista::e($pais->getNombre()) ?></h2>
            </div>

            <div class="dos-columnas">
                <div>
                    <h3>Cómo ha quedado</h3>
                    <table>
                        <tr><th></th><th>Inicio</th><th>Final</th><th></th></tr>
                        <?php foreach (Vista::INDICADORES as $clave => $datos): ?>
                            <tr>
                                <td><?= $datos['icono'] ?> <?= Vista::e($datos['nombre']) ?></td>
                                <td><?= Vista::num($ini['indicadores'][$clave]) ?></td>
                                <td><strong><?= Vista::num($pais->getIndicador($clave)) ?></strong></td>
                                <td><?= Vista::cambio($clave, $pais->getIndicador($clave), $ini['indicadores'][$clave]) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>

                    <h3>Sus negocios (sectores)</h3>
                    <table>
                        <tr><th></th><th>Producción</th><th>Exportaciones</th></tr>
                        <?php foreach (SECTORES as $s => $datos):
                            $prodIni = $ini['produccion'][$s];
                            $prodFin = $pais->getProduccionSector($s);
                            $cambioProd = $prodIni > 0 ? ($prodFin - $prodIni) / $prodIni * 100 : 0;
                            $expIni = $ini['exportaciones'][$s];
                            $expFin = $exportFinal[$s]; ?>
                            <tr>
                                <td><?= $datos['icono'] ?> <?= Vista::e($datos['nombre']) ?></td>
                                <td>
                                    <?= Vista::num($prodIni) ?> → <?= Vista::num($prodFin) ?>
                                    <span class="cambio <?= $cambioProd >= 0 ? 'bueno' : 'malo' ?>">
                                        <?= $cambioProd >= 0 ? '+' : '' ?><?= Vista::num($cambioProd) ?> %</span>
                                </td>
                                <td>
                                    <?php if ($expIni == 0 && $expFin == 0): ?>
                                        <span class="nota">no exporta</span>
                                    <?php else: ?>
                                        <?= Vista::num($expIni) ?> → <?= Vista::num($expFin) ?> mil M€
                                        <?php if ($expFin != $expIni): ?>
                                            <span class="cambio <?= $expFin > $expIni ? 'bueno' : 'malo' ?>"><?= $expFin > $expIni ? '▲' : '▼' ?></span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>

                <div>
                    <h3>Lo que más le afectó</h3>
                    <?= Vista::informe(Cronista::causasDePartida($partida, $codigo)) ?>
                </div>
            </div>

            <?php if ($historial): ?>
                <h3>Año a año</h3>
                <table class="evolucion">
                    <tr>
                        <th>Año</th>
                        <?php foreach (Vista::INDICADORES as $datos): ?><th><?= $datos['icono'] ?> <?= Vista::e($datos['nombre']) ?></th><?php endforeach; ?>
                        <th>Evento</th>
                    </tr>
                    <tr>
                        <td>Inicio</td>
                        <?php foreach (array_keys(Vista::INDICADORES) as $clave): ?><td><?= Vista::num($ini['indicadores'][$clave]) ?></td><?php endforeach; ?>
                        <td></td>
                    </tr>
                    <?php foreach ($historial as $anio):
                        $evento = Evento::buscar($anio['evento']); ?>
                        <tr>
                            <td><?= $anio['anio'] ?></td>
                            <?php foreach (array_keys(Vista::INDICADORES) as $clave): ?><td><?= Vista::num($anio['indicadores'][$codigo][$clave]) ?></td><?php endforeach; ?>
                            <td><?= $evento ? $evento->getIcono() . ' ' . Vista::e($evento->getNombre()) : '' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</main>
<?php
Vista::pie();

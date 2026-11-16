<?php
// Control de un país. Se abre con el enlace secreto que genera el profesor: pais.php?t=CODIGO
//   - Panel con indicadores, informe del año pasado, comercio y barras de decisión (NO se recarga sola,
//     para no perder lo que se está moviendo).
//   - Tras confirmar: pantalla de espera que SÍ se recarga hasta que se cierra el año.
require __DIR__ . '/config.php';

$token = (string) ($_GET['t'] ?? '');
$enlace = 'pais.php?t=' . urlencode($token);

// =====================================================================
//  ACCIONES (POST)
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $anioFormulario = (int) ($_POST['anio'] ?? 0);

    $aviso = Almacen::modificar(function ($partida) use ($token, $accion, $anioFormulario) {
        if (!$partida || !$partida->estaJugando()) {
            return null;
        }
        $pais = $partida->getPaisPorToken($token);
        if (!$pais) {
            return null;
        }
        // Si el profesor cerró el año mientras decidían, no se guarda (era para un año que ya pasó)
        if ($anioFormulario !== $partida->getAnio()) {
            return 'cerrado';
        }
        $decision = $partida->getDecision($pais->getCodigo());

        if ($accion === 'confirmar') {
            $decision->setDesdeFormulario($_POST);
            $decision->setConfirmada(true);
            if ($partida->todosConfirmados()) {
                Simulador::cerrarAnio($partida);   // era el último: se cierra el año
            }
        } elseif ($accion === 'modificar') {
            $decision->setConfirmada(false);
        }
        return null;
    });

    header('Location: ' . $enlace . ($aviso ? '&aviso=' . $aviso : ''));
    exit;
}

// =====================================================================
//  PANTALLAS (GET)
// =====================================================================
$partida = Almacen::leer();
$pais = $partida ? $partida->getPaisPorToken($token) : null;

// ---- Enlace no válido ----
if (!$pais) {
    Vista::cabecera('Enlace no válido', 0, 'centrado');
    ?>
    <div class="tarjeta login">
        <h1>Enlace no válido</h1>
        <p>Este enlace no corresponde a ningún país de la partida actual.
           Pide al profesor que vuelva a generar tu país.</p>
    </div>
    <?php
    Vista::pie();
    exit;
}

// ---- Partida terminada ----
if (!$partida->estaJugando()) {
    Vista::cabecera('Partida terminada', 0, 'centrado');
    ?>
    <div class="tarjeta login">
        <?= Vista::bandera($pais, 'bandera grande') ?>
        <h1>¡Partida terminada!</h1>
        <p>Mirad el resumen en las pantallas para ver cómo ha quedado <?= Vista::e($pais->getNombre()) ?>.</p>
        <a class="boton principal" href="resumen.php">Ver resumen</a>
    </div>
    <?php
    Vista::pie();
    exit;
}

$codigo = $pais->getCodigo();
$decision = $partida->getDecision($codigo);
$otros = array_filter($partida->getPaises(), fn($p) => $p->getCodigo() !== $codigo);
$evento = $partida->getEventoPendiente();

// ---- Ya ha confirmado: pantalla de espera ----
if ($decision->isConfirmada()) {
    Vista::cabecera($pais->getNombre() . ' · Esperando', REFRESCO, 'centrado');
    ?>
    <div class="tarjeta login">
        <?= Vista::bandera($pais, 'bandera grande') ?>
        <h1>✔ Decisión enviada</h1>
        <p>Esperando a que el resto de países confirme o a que el profesor cierre el año <?= $partida->getAnio() ?>…</p>

        <?php if ($evento): ?>
            <div class="aviso malo evento-elegido">
                <span class="icono-grande"><?= $evento->getIcono() ?></span>
                <div>
                    <strong>⚠️ Este año: <?= Vista::e($evento->getNombre()) ?></strong>
                    <p><?= Vista::e($evento->getDescripcion()) ?></p>
                    <p class="nota">Si queréis reaccionar, pulsad «Cambiar mi decisión».</p>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="resumen-decision">
            <h3>Tus aranceles</h3>
            <ul>
                <?php foreach ($otros as $otro): ?>
                    <li><?= Vista::bandera($otro, 'bandera mini') ?> <?= Vista::e($otro->getNombre()) ?>:
                        <strong><?= $decision->getArancel($otro->getCodigo()) ?> %</strong></li>
                <?php endforeach; ?>
            </ul>
            <h3>Tu inversión</h3>
            <ul>
                <?php foreach ($decision->getRepartoInversion() as $s => $parte): ?>
                    <li><?= SECTORES[$s]['icono'] ?> <?= Vista::e(SECTORES[$s]['nombre']) ?>:
                        <strong><?= round($parte * 100) ?> %</strong></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <form method="post" action="<?= Vista::e($enlace) ?>">
            <input type="hidden" name="accion" value="modificar">
            <input type="hidden" name="anio" value="<?= $partida->getAnio() ?>">
            <button class="boton">Cambiar mi decisión</button>
        </form>
    </div>
    <?php
    Vista::pie();
    exit;
}

// ---- Panel del país ----
$ultimo = $partida->getUltimo();
$anioCerrado = $partida->getUltimoAnioCerrado();

// Comercio del último año: a quién vende y a quién compra (en miles de millones)
$ventas = [];
$compras = [];
foreach ($otros as $oc => $otro) {
    $ventas[$oc] = $ultimo['flujos'][$codigo][$oc] ?? [];
    $compras[$oc] = $ultimo['flujos'][$oc][$codigo] ?? [];
}

Vista::cabecera($pais->getNombre() . ' · Año ' . $partida->getAnio());
?>
<header class="barra">
    <div class="pais-cabecera">
        <?= Vista::bandera($pais) ?>
        <h1><?= Vista::e($pais->getNombre()) ?></h1>
    </div>
    <div class="anio">Año <strong><?= $partida->getAnio() ?></strong></div>
</header>

<main class="contenido estrecho">
    <?php if (($_GET['aviso'] ?? '') === 'cerrado'): ?>
        <p class="aviso malo">El profesor cerró el año antes de que confirmarais. Mirad lo que ha pasado y decidid para este año.</p>
    <?php endif; ?>

    <?php if ($evento): ?>
        <div class="aviso malo evento-elegido">
            <span class="icono-grande"><?= $evento->getIcono() ?></span>
            <div>
                <strong>⚠️ Este año: <?= Vista::e($evento->getNombre()) ?></strong>
                <p><?= Vista::e($evento->getDescripcion()) ?></p>
                <p class="nota">Se aplicará al cerrar el año <?= $partida->getAnio() ?>. ¿Cambia algo vuestra decisión?</p>
            </div>
        </div>
    <?php endif; ?>

    <section class="tarjeta">
        <h2>Cómo está tu país</h2>
        <?= Vista::indicadores($pais, true) ?>
        <p class="nota">Precios: 100 es lo normal. Si sube, la vida es más cara.</p>
    </section>

    <?php if ($anioCerrado): ?>
        <section class="tarjeta">
            <h2>Qué pasó en <?= $anioCerrado['anio'] ?> y por qué</h2>
            <?= Vista::informe($pais->getInforme()) ?>
        </section>
    <?php endif; ?>

    <section class="tarjeta">
        <h2>Tu comercio <?= $anioCerrado ? 'en ' . $anioCerrado['anio'] : 'al empezar' ?></h2>
        <div class="dos-columnas">
            <div>
                <h3>📤 Vendes a…</h3>
                <ul class="lista-comercio">
                    <?php foreach ($ventas as $oc => $porSector): ?>
                        <li><?= Vista::bandera($otros[$oc], 'bandera mini') ?> <?= Vista::e($otros[$oc]->getNombre()) ?>:
                            <?php if ($porSector): ?>
                                <strong><?= Vista::num(array_sum($porSector)) ?> mil M€</strong>
                                <small>(<?php foreach ($porSector as $s => $v) echo SECTORES[$s]['icono'] . ' '; ?>)</small>
                            <?php else: ?>
                                <span class="nota">nada</span>
                            <?php endif; ?>
                            <?php $tePone = $ultimo['aranceles'][$oc][$codigo] ?? 0; if ($tePone > 0): ?>
                                <span class="etiqueta malo">te pone un <?= $tePone ?> %</span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div>
                <h3>📥 Compras a…</h3>
                <ul class="lista-comercio">
                    <?php foreach ($compras as $oc => $porSector): ?>
                        <li><?= Vista::bandera($otros[$oc], 'bandera mini') ?> <?= Vista::e($otros[$oc]->getNombre()) ?>:
                            <?php if ($porSector): ?>
                                <strong><?= Vista::num(array_sum($porSector)) ?> mil M€</strong>
                                <small>(<?php foreach ($porSector as $s => $v) echo SECTORES[$s]['icono'] . ' '; ?>)</small>
                            <?php else: ?>
                                <span class="nota">nada</span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </section>

    <form method="post" action="<?= Vista::e($enlace) ?>">
        <input type="hidden" name="accion" value="confirmar">
        <input type="hidden" name="anio" value="<?= $partida->getAnio() ?>">

        <section class="tarjeta">
            <h2>🏷️ Aranceles para <?= $partida->getAnio() ?></h2>
            <p class="nota">Un arancel es un impuesto a lo que te vende otro país. Cuanto más alto,
               menos le compras y más caro te sale, pero proteges a tus productores.</p>
            <?php foreach ($otros as $oc => $otro): ?>
                <div class="control">
                    <label for="ar-<?= Vista::e($oc) ?>">
                        <?= Vista::bandera($otro, 'bandera mini') ?> Arancel a <?= Vista::e($otro->getNombre()) ?>
                        <span class="nota">(ahora: <?= $decision->getArancel($oc) ?> %)</span>
                    </label>
                    <input type="range" id="ar-<?= Vista::e($oc) ?>" name="arancel[<?= Vista::e($oc) ?>]"
                           min="0" max="<?= ARANCEL_MAXIMO ?>" step="5" value="<?= $decision->getArancel($oc) ?>">
                    <div class="marcas">
                        <?php for ($v = 0; $v <= ARANCEL_MAXIMO; $v += 10): ?><span><?= $v ?> %</span><?php endfor; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>

        <section class="tarjeta">
            <h2>🏗️ Inversión para <?= $partida->getAnio() ?></h2>
            <p class="nota">¿En qué sectores invertís? Lo que más inversión reciba producirá más el año que viene.
               Fijaos en lo que os falta y en lo que os sobra.</p>
            <?php foreach (SECTORES as $s => $datos):
                $produce = $pais->getProduccionSector($s);
                $necesita = $pais->getNecesidadSector($s); ?>
                <div class="control">
                    <label for="in-<?= $s ?>">
                        <?= $datos['icono'] ?> <?= Vista::e($datos['nombre']) ?>
                        <span class="nota">Produces <?= Vista::num($produce) ?> · necesitas <?= Vista::num($necesita) ?>
                            <?php if ($produce >= $necesita): ?>
                                <span class="etiqueta bueno">te sobra</span>
                            <?php else: ?>
                                <span class="etiqueta malo">te falta</span>
                            <?php endif; ?>
                        </span>
                    </label>
                    <input type="range" id="in-<?= $s ?>" name="inversion[<?= $s ?>]"
                           min="0" max="<?= INVERSION_MAXIMA ?>" step="1" value="<?= $decision->getInversionSector($s) ?>">
                    <div class="marcas"><span>Nada</span><span>Poco</span><span>Normal</span><span>Bastante</span><span>Mucho</span></div>
                </div>
            <?php endforeach; ?>
        </section>

        <button class="boton principal grande ancho">✔ Confirmar decisiones de <?= $partida->getAnio() ?></button>
    </form>
</main>
<?php
Vista::pie();

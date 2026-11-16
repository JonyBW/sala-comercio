<?php
// Panel del profesor: entrar, crear partida, generar países, lanzar eventos, cerrar el año y terminar.
// Esta pestaña es también la pared de INDICADORES.
require __DIR__ . '/config.php';
session_start();

// =====================================================================
//  ACCIONES (POST). Después de cada una se redirige (así recargar no repite la acción)
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'entrar') {
        if (hash_equals(PASSWORD_PROFE, (string) ($_POST['password'] ?? ''))) {
            session_regenerate_id(true);
            $_SESSION['profe'] = true;
            header('Location: profesor.php');
        } else {
            header('Location: profesor.php?error=1');
        }
        exit;
    }

    if (empty($_SESSION['profe'])) {
        header('Location: profesor.php');
        exit;
    }

    switch ($accion) {
        case 'salir':
            session_destroy();
            break;

        case 'crear':
            Almacen::modificar(function ($partida) {
                return Partida::crear();
            });
            break;

        case 'generar':
            // Se envía desde un formulario con target="_blank": la pestaña nueva acaba en el control del país
            $codigo = $_POST['pais'] ?? '';
            $token = Almacen::modificar(function ($partida) use ($codigo) {
                return ($partida && $partida->estaJugando()) ? $partida->generarToken($codigo) : null;
            });
            if ($token) {
                header('Location: pais.php?t=' . $token);
                exit;
            }
            break;

        case 'evento':
            $codigo = $_POST['evento'] ?? null;
            Almacen::modificar(function ($partida) use ($codigo) {
                if ($partida && $partida->estaJugando()) {
                    $partida->setEventoPendiente($codigo);
                }
            });
            break;

        case 'cerrar':
            Almacen::modificar(function ($partida) {
                if ($partida) {
                    Simulador::cerrarAnio($partida);
                }
            });
            break;

        case 'terminar':
            Almacen::modificar(function ($partida) {
                if ($partida) {
                    $partida->setEstado(Partida::TERMINADA);
                }
            });
            header('Location: resumen.php');
            exit;
    }
    header('Location: profesor.php');
    exit;
}

// =====================================================================
//  PANTALLAS (GET)
// =====================================================================

// ---- Sin sesión: pedir contraseña ----
if (empty($_SESSION['profe'])) {
    Vista::cabecera('Profesor · Comercio mundial', 0, 'centrado');
    ?>
    <div class="tarjeta login">
        <h1>🌍 Comercio mundial</h1>
        <p>Panel del profesor</p>
        <?php if (isset($_GET['error'])): ?>
            <p class="aviso malo">Contraseña incorrecta.</p>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="accion" value="entrar">
            <input type="password" name="password" placeholder="Contraseña" required autofocus>
            <button class="boton principal">Entrar</button>
        </form>
    </div>
    <?php
    Vista::pie();
    exit;
}

$partida = Almacen::leer();

// ---- Sin partida en juego: botón de crear ----
if (!$partida || !$partida->estaJugando()) {
    Vista::cabecera('Profesor · Comercio mundial', 0, 'centrado');
    ?>
    <div class="tarjeta login">
        <h1>🌍 Comercio mundial</h1>
        <p>Cada grupo dirige un país. Cada ronda es un año: deciden aranceles e inversión
           y ven cómo les afecta a ellos y a los demás.</p>
        <form method="post">
            <input type="hidden" name="accion" value="crear">
            <button class="boton principal grande">Crear partida</button>
        </form>
        <?php if ($partida): ?>
            <p><a class="boton" href="resumen.php">Ver el resumen de la última partida</a></p>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="accion" value="salir">
            <button class="boton enlace">Cerrar sesión</button>
        </form>
    </div>
    <?php
    Vista::pie();
    exit;
}

// ---- Partida en juego ----
$evento = $partida->getEventoPendiente();
$confirmarFin = isset($_GET['confirmar']);
// Mientras se pide confirmación de terminar, no se recarga sola (para no perder el aviso)
Vista::cabecera('Indicadores · Año ' . $partida->getAnio(), $confirmarFin ? 0 : REFRESCO);
?>
<header class="barra">
    <h1>🌍 Comercio mundial</h1>
    <div class="anio">Año <strong><?= $partida->getAnio() ?></strong></div>
    <nav>
        <a class="boton" href="mapa.php" target="_blank">🗺️ Abrir mapa</a>
        <a class="boton" href="noticias.php" target="_blank">📰 Abrir noticias</a>
    </nav>
</header>

<main class="contenido">
    <section class="paises">
        <?php foreach ($partida->getPaises() as $codigo => $pais):
            $confirmado = $partida->getDecision($codigo)->isConfirmada(); ?>
            <article class="tarjeta pais <?= $confirmado ? 'confirmado' : '' ?>">
                <div class="pais-cabecera">
                    <?= Vista::bandera($pais) ?>
                    <h2><?= Vista::e($pais->getNombre()) ?></h2>
                </div>

                <?php if (!$pais->estaGenerado()): ?>
                    <p class="estado">Sin generar</p>
                    <form method="post" target="_blank">
                        <input type="hidden" name="accion" value="generar">
                        <input type="hidden" name="pais" value="<?= Vista::e($codigo) ?>">
                        <button class="boton principal">Generar país</button>
                    </form>
                <?php else: ?>
                    <p class="estado"><?= $confirmado ? '✔ Ha confirmado' : '⏳ Decidiendo…' ?></p>
                    <a class="boton pequeno" href="pais.php?t=<?= Vista::e($pais->getToken()) ?>" target="_blank">Abrir de nuevo</a>
                <?php endif; ?>

                <?= Vista::indicadores($pais) ?>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="tarjeta">
        <h2>Evento del año</h2>
        <?php if ($evento): ?>
            <div class="evento-elegido">
                <span class="icono-grande"><?= $evento->getIcono() ?></span>
                <div>
                    <strong><?= Vista::e($evento->getNombre()) ?></strong>
                    <p><?= Vista::e($evento->getDescripcion()) ?></p>
                    <p class="nota">Se aplicará al cerrar el año <?= $partida->getAnio() ?>.</p>
                </div>
                <form method="post">
                    <input type="hidden" name="accion" value="evento">
                    <input type="hidden" name="evento" value="">
                    <button class="boton">Quitar evento</button>
                </form>
            </div>
        <?php else: ?>
            <p class="nota">Opcional. Solo un evento por año; se aplica al cerrar el año.</p>
            <div class="eventos">
                <?php foreach (Evento::todos() as $e): ?>
                    <form method="post">
                        <input type="hidden" name="accion" value="evento">
                        <input type="hidden" name="evento" value="<?= Vista::e($e->getCodigo()) ?>">
                        <button class="boton evento" title="<?= Vista::e($e->getDescripcion()) ?>">
                            <span class="icono-grande"><?= $e->getIcono() ?></span>
                            <?= Vista::e($e->getNombre()) ?>
                        </button>
                    </form>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="tarjeta acciones">
        <form method="post">
            <input type="hidden" name="accion" value="cerrar">
            <button class="boton principal grande">⏭️ Cerrar año <?= $partida->getAnio() ?></button>
        </form>
        <p class="nota">El año se cierra solo cuando todos los países generados confirman.
           Este botón lo fuerza: quien no haya confirmado mantiene lo del año anterior.</p>

        <?php if ($confirmarFin): ?>
            <div class="aviso malo">
                <p><strong>¿Terminar la partida?</strong> Se mostrará el resumen y ya no se podrá seguir jugando.</p>
                <form method="post" class="en-linea">
                    <input type="hidden" name="accion" value="terminar">
                    <button class="boton peligro">Sí, terminar</button>
                </form>
                <a class="boton" href="profesor.php">Cancelar</a>
            </div>
        <?php else: ?>
            <a class="boton peligro" href="profesor.php?confirmar=1">🏁 Terminar partida</a>
        <?php endif; ?>
    </section>
</main>
<?php
Vista::pie();

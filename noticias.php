<?php
// Pared de NOTICIAS: titulares del último año cerrado, cada uno con su causa.
require __DIR__ . '/config.php';

$partida = Almacen::leer();
$anioCerrado = $partida ? $partida->getUltimoAnioCerrado() : null;

Vista::cabecera('Noticias', REFRESCO, 'pared');
?>
<header class="barra">
    <h1>📰 Noticias <?= $anioCerrado ? 'de ' . $anioCerrado['anio'] : '' ?></h1>
    <?php if ($partida): ?>
        <div class="anio">Año en curso <strong><?= $partida->getAnio() ?></strong></div>
    <?php endif; ?>
</header>

<main class="contenido">
    <?php if (!$partida): ?>
        <div class="tarjeta"><p>Esperando a que el profesor cree la partida…</p></div>
    <?php elseif (!$anioCerrado): ?>
        <div class="tarjeta">
            <h2>Todavía no ha pasado ningún año</h2>
            <p>Las noticias aparecerán cuando se cierre el año <?= $partida->getAnio() ?>.</p>
        </div>
    <?php else: ?>
        <div class="noticias">
            <?php foreach ($partida->getNoticias() as $noticia):
                $protagonista = $noticia->getPais() ? $partida->getPais($noticia->getPais()) : null; ?>
                <article class="tarjeta noticia <?= Vista::e($noticia->getTipo()) ?>">
                    <div class="noticia-icono"><?= $noticia->getIcono() ?></div>
                    <div>
                        <h2>
                            <?php if ($protagonista): ?><?= Vista::bandera($protagonista, 'bandera mini') ?><?php endif; ?>
                            <?= Vista::e($noticia->getTitular()) ?>
                        </h2>
                        <p><?= Vista::e($noticia->getDetalle()) ?></p>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
<?php
Vista::pie();

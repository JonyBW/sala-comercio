<?php
// El corazón del juego: calcula el comercio entre países y cómo cambian sus indicadores.
//
// Idea de cada año, sector por sector:
//   1. Cada país produce y primero cubre lo que necesita su población.
//   2. Lo que le sobra lo puede exportar. Lo que le falta lo tiene que importar.
//   3. Cada país con falta reparte sus compras entre los países a los que les sobra.
//   4. ARANCEL: si un país pone un arancel a otro, le compra menos
//      (con un 50 % compra un 75 % menos) y lo que compra le sale más caro.
//   5. Con eso se calculan los indicadores:
//        PIB        = lo que venden las empresas del país (dentro y fuera)
//        Empleo     = depende de cuánta producción consigue vender
//        Precios    = 100 es lo normal; sube si importa caro o si faltan productos
//        Bienestar  = mezcla de empleo, productos cubiertos y precios
class Simulador
{
    // Calcula la situación inicial de una partida nueva (sin cerrar ningún año)
    public static function inicializar(Partida $partida)
    {
        $resultado = self::calcular($partida, null);
        $foto = [];
        foreach ($partida->getPaises() as $codigo => $pais) {
            $pais->setIndicadores($resultado['paises'][$codigo]['indicadores']);
            $foto[$codigo] = [
                'indicadores' => $pais->getIndicadores(),
                'produccion' => $pais->getProduccion(),
                'exportaciones' => self::exportacionesPorSector($resultado, $codigo),
            ];
        }
        $partida->setInicio(['anio' => $partida->getAnio(), 'paises' => $foto]);
        $partida->setUltimo(self::comercioParaMapa($partida, $resultado, null));
    }

    // Cierra el año en curso: calcula, escribe informes y noticias, aplica la inversión y avanza
    public static function cerrarAnio(Partida $partida)
    {
        if (!$partida->estaJugando()) {
            return;
        }
        $evento = $partida->getEventoPendiente();
        $resultado = self::calcular($partida, $evento);

        $antes = [];
        foreach ($partida->getPaises() as $codigo => $pais) {
            $antes[$codigo] = $pais->getIndicadores();
        }

        $cronista = new Cronista($partida, $resultado, $evento);
        $informes = [];
        foreach ($partida->getPaises() as $codigo => $pais) {
            $informes[$codigo] = $cronista->informe($codigo);
            $pais->setIndicadoresAnteriores($antes[$codigo]);
            $pais->setIndicadores($resultado['paises'][$codigo]['indicadores']);
            $pais->setInforme($informes[$codigo]);
        }
        $noticias = $cronista->noticias($antes);

        self::aplicarInversion($partida, $resultado);

        $entrada = [
            'anio' => $partida->getAnio(),
            'evento' => $evento ? $evento->getCodigo() : null,
            'indicadores' => [],
            'produccion' => [],
            'exportaciones' => [],
            'informes' => $informes,
            'noticias' => [],
        ];
        foreach ($partida->getPaises() as $codigo => $pais) {
            $entrada['indicadores'][$codigo] = $pais->getIndicadores();
            $entrada['produccion'][$codigo] = $pais->getProduccion();
            $entrada['exportaciones'][$codigo] = self::exportacionesPorSector($resultado, $codigo);
        }
        foreach ($noticias as $noticia) {
            $entrada['noticias'][] = $noticia->toArray();
        }
        $partida->anadirAlHistorial($entrada);
        $partida->setUltimo(self::comercioParaMapa($partida, $resultado, $partida->getAnio()));
        $partida->avanzarAnio();
    }

    // Hace los cálculos del año SIN modificar la partida
    public static function calcular(Partida $partida, $evento)
    {
        $paises = $partida->getPaises();
        $comercio = $evento ? $evento->getComercio() : 1;

        // Precios, producción y necesidad de este año (con el evento aplicado)
        $precio = [];
        $prod = [];
        $nec = [];
        foreach (SECTORES as $s => $datos) {
            $precio[$s] = $datos['precio'] * ($evento ? $evento->getMultPrecio($s) : 1);
            foreach ($paises as $c => $pais) {
                $prod[$c][$s] = $pais->getProduccionSector($s) * ($evento ? $evento->getMultProduccion($c, $s) : 1);
                $nec[$c][$s] = $pais->getNecesidadSector($s) * ($evento ? $evento->getMultNecesidad($c, $s) : 1);
            }
        }

        // ---- Comercio, sector por sector ----
        $flujos = [];    // [sector][exportador][importador] = unidades vendidas
        $perdidos = [];  // [sector][exportador][importador] = unidades que no se vendieron por el arancel
        foreach (array_keys(SECTORES) as $s) {
            $sobra = [];
            $falta = [];
            foreach (array_keys($paises) as $c) {
                $sobra[$c] = max(0, $prod[$c][$s] - $nec[$c][$s]);
                $falta[$c] = max(0, $nec[$c][$s] - $prod[$c][$s]);
            }
            $totalSobra = array_sum($sobra);

            $pedido = [];      // lo que se pide con aranceles
            $pedidoLibre = []; // lo que se pediría sin aranceles
            if ($totalSobra > 0) {
                foreach (array_keys($paises) as $i) {
                    if ($falta[$i] <= 0) {
                        continue;
                    }
                    foreach (array_keys($paises) as $e) {
                        if ($e === $i || $sobra[$e] <= 0) {
                            continue;
                        }
                        // Compra a cada país en proporción a lo que le sobra
                        $parte = $falta[$i] * $sobra[$e] / $totalSobra * $comercio;
                        $arancel = $partida->getDecision($i)->getArancel($e) / 100;
                        $pedidoLibre[$e][$i] = $parte;
                        $pedido[$e][$i] = $parte * max(0, 1 - EFECTO_ARANCEL * $arancel);
                    }
                }
            }

            // Un país no puede vender más de lo que le sobra
            foreach ($pedido as $e => $porImportador) {
                $total = array_sum($porImportador);
                $totalLibre = array_sum($pedidoLibre[$e]);
                $escala = $total > $sobra[$e] ? $sobra[$e] / $total : 1;
                $escalaLibre = $totalLibre > $sobra[$e] ? $sobra[$e] / $totalLibre : 1;
                foreach ($porImportador as $i => $cantidad) {
                    $vendido = $cantidad * $escala;
                    $flujos[$s][$e][$i] = $vendido;
                    $perdidos[$s][$e][$i] = max(0, $pedidoLibre[$e][$i] * $escalaLibre - $vendido);
                }
            }
        }

        // ---- Indicadores de cada país ----
        $resultadoPaises = [];
        foreach ($paises as $c => $pais) {
            $pib = 0;
            $recaudacion = 0;
            $vendidoBase = 0;
            $capacidadBase = 0;
            $coste = 0;
            $necesidadBase = 0;
            $cubiertoBase = 0;
            $sectores = [];

            foreach (SECTORES as $s => $datos) {
                $base = $datos['precio'];
                $local = min($prod[$c][$s], $nec[$c][$s]);
                $exporta = 0;
                foreach ($flujos[$s][$c] ?? [] as $cantidad) {
                    $exporta += $cantidad;
                }
                $importa = 0;
                $costeImportado = 0;
                foreach (array_keys($paises) as $e) {
                    $cantidad = $flujos[$s][$e][$c] ?? 0;
                    $arancel = $partida->getDecision($c)->getArancel($e) / 100;
                    $importa += $cantidad;
                    $costeImportado += $cantidad * $precio[$s] * (1 + $arancel);
                    $recaudacion += $cantidad * $precio[$s] * $arancel;
                }
                $faltaFinal = max(0, $nec[$c][$s] - $local - $importa);

                $pib += $local * $base + $exporta * $precio[$s];
                $vendidoBase += ($local + $exporta) * $base;
                $capacidadBase += $pais->getProduccionSector($s) * $base;
                // Lo que falta cuenta el doble: escasea y se encarece
                $coste += $local * $base + $costeImportado + $faltaFinal * $base * 2;
                $necesidadBase += $nec[$c][$s] * $base;
                $cubiertoBase += ($local + $importa) * $base;

                $sectores[$s] = [
                    'produccion' => $prod[$c][$s],
                    'necesidad' => $nec[$c][$s],
                    'local' => $local,
                    'exporta' => $exporta,
                    'importa' => $importa,
                    'falta' => $faltaFinal,
                ];
            }
            $utilizacion = $capacidadBase > 0 ? min(1, $vendidoBase / $capacidadBase) : 0;
            $empleo = 60 + 35 * $utilizacion;
            $precios = 100 * $coste / $necesidadBase;
            $cobertura = $cubiertoBase / $necesidadBase;
            $bienestar = 0.4 * $empleo + 40 * $cobertura - max(0, $precios - 100) * 0.8;

            $resultadoPaises[$c] = [
                'indicadores' => [
                    'pib' => round($pib, 1),
                    'empleo' => round($empleo, 1),
                    'precios' => round($precios, 1),
                    'bienestar' => round(max(0, min(100, $bienestar)), 1),
                ],
                'sectores' => $sectores,
                'recaudacion' => $recaudacion,
            ];
        }

        return [
            'precios' => $precio,
            'flujos' => $flujos,
            'perdidos' => $perdidos,
            'paises' => $resultadoPaises,
        ];
    }

    // La inversión hace crecer unos sectores (y los que no reciben nada se desgastan).
    // Los aranceles protegen a los productores del país y también les ayudan a crecer.
    // La población también consume un poco más cada año.
    private static function aplicarInversion(Partida $partida, $resultado)
    {
        foreach ($partida->getPaises() as $codigo => $pais) {
            $reparto = $partida->getDecision($codigo)->getRepartoInversion();
            foreach (array_keys(SECTORES) as $s) {
                $factor = 1 - DESGASTE + CRECIMIENTO * $reparto[$s] + self::proteccion($partida, $resultado, $codigo, $s);
                $pais->setProduccionSector($s, $pais->getProduccionSector($s) * $factor);
                $pais->setNecesidadSector($s, $pais->getNecesidadSector($s) * (1 + CRECIMIENTO_DEMANDA));
            }
        }
    }

    // Crecimiento extra de un sector gracias a los aranceles que el país pone a quien se lo vende.
    // Es la media de esos aranceles (pesando cuánto le compraba a cada uno) por PROTECCION.
    public static function proteccion(Partida $partida, $resultado, $codigo, $sector)
    {
        $suma = 0;
        $peso = 0;
        foreach (array_keys($partida->getPaises()) as $e) {
            $compra = ($resultado['flujos'][$sector][$e][$codigo] ?? 0) + ($resultado['perdidos'][$sector][$e][$codigo] ?? 0);
            $suma += $compra * $partida->getDecision($codigo)->getArancel($e) / 100;
            $peso += $compra;
        }
        return $peso > 0 ? PROTECCION * $suma / $peso : 0;
    }

    // [sector => valor exportado en miles de millones]
    public static function exportacionesPorSector($resultado, $codigo)
    {
        $valores = [];
        foreach (array_keys(SECTORES) as $s) {
            $total = 0;
            foreach ($resultado['flujos'][$s][$codigo] ?? [] as $cantidad) {
                $total += $cantidad;
            }
            $valores[$s] = round($total * $resultado['precios'][$s], 1);
        }
        return $valores;
    }

    // Lo que necesita el mapa: cuánto vende cada país a cada otro y qué aranceles había
    private static function comercioParaMapa(Partida $partida, $resultado, $anio)
    {
        $flujos = [];
        foreach ($resultado['flujos'] as $s => $porExportador) {
            foreach ($porExportador as $e => $porImportador) {
                foreach ($porImportador as $i => $cantidad) {
                    $valor = round($cantidad * $resultado['precios'][$s], 1);
                    if ($valor > 0) {
                        $flujos[$e][$i][$s] = $valor;
                    }
                }
            }
        }
        $aranceles = [];
        foreach ($partida->getDecisiones() as $codigo => $decision) {
            $aranceles[$codigo] = $decision->getAranceles();
        }
        return ['anio' => $anio, 'flujos' => $flujos, 'aranceles' => $aranceles];
    }
}

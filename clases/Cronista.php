<?php
// Convierte los números del Simulador en frases que se entienden:
//   - el informe de cada país (qué le ha pasado y por qué)
//   - las noticias de la pared
class Cronista
{
    private $partida;
    private $resultado;
    private $evento;
    private $informes;   // se guardan para usarlos como "causa" en las noticias

    public function __construct(Partida $partida, $resultado, $evento)
    {
        $this->partida = $partida;
        $this->resultado = $resultado;
        $this->evento = $evento;
        $this->informes = [];
    }

    // ---- Informe de un país ----
    // Cada línea: icono, texto, tipo (buena/mala/neutra), categoria, valor y origen (para el resumen final)
    public function informe($c)
    {
        $lineas = [];
        $decision = $this->partida->getDecision($c);
        $datos = $this->resultado['paises'][$c];

        // 1. Evento del año
        if ($this->evento) {
            list($texto, $tipo) = $this->evento->getTexto($c);
            $lineas[] = $this->linea($this->evento->getIcono(), $this->evento->getNombre() . ': ' . $texto,
                $tipo, 'evento', 0, $this->evento->getCodigo());
        }

        // 2. Aranceles que le han puesto otros países
        foreach ($this->otros($c) as $i) {
            $valor = 0;
            $sectores = [];
            foreach (array_keys(SECTORES) as $s) {
                $unidades = $this->resultado['perdidos'][$s][$c][$i] ?? 0;
                if ($unidades > 0.01) {
                    $valor += $unidades * $this->resultado['precios'][$s];
                    $sectores[] = mb_strtolower(SECTORES[$s]['nombre']);
                }
            }
            if ($valor >= 0.5) {
                $arancel = $this->partida->getDecision($i)->getArancel($c);
                $lineas[] = $this->linea('📉',
                    'Vendiste ' . $this->num($valor) . ' mil M€ menos de ' . $this->lista($sectores) . ' a ' . $this->nombre($i)
                    . ' porque te puso un arancel del ' . $arancel . ' %. Esas empresas venden menos y contratan a menos gente.',
                    'mala', 'arancel_sufrido', round($valor, 1), $i);
            }
        }

        // 3. Aranceles que pone este país
        foreach ($this->otros($c) as $e) {
            $arancel = $decision->getArancel($e);
            if ($arancel <= 0) {
                continue;
            }
            $comprado = 0;
            $dejado = 0;
            foreach (array_keys(SECTORES) as $s) {
                $comprado += ($this->resultado['flujos'][$s][$e][$c] ?? 0) * $this->resultado['precios'][$s];
                $dejado += ($this->resultado['perdidos'][$s][$e][$c] ?? 0) * $this->resultado['precios'][$s];
            }
            if ($comprado + $dejado >= 0.5) {
                $lineas[] = $this->linea('🏷️',
                    'Lo que compras a ' . $this->nombre($e) . ' te cuesta un ' . $arancel . ' % más. Le compras '
                    . $this->num($dejado) . ' mil M€ menos y tus precios suben.',
                    'mala', 'arancel_puesto', round($dejado, 1), $e);
            } else {
                $lineas[] = $this->linea('🤷',
                    'Pusiste un arancel a ' . $this->nombre($e) . ', pero no le compras nada: no ha tenido ningún efecto.',
                    'neutra', 'arancel_inutil', 0, $e);
            }
        }
        if ($datos['recaudacion'] >= 0.5) {
            $lineas[] = $this->linea('🏛️',
                'Tu Estado ha recaudado ' . $this->num($datos['recaudacion']) . ' mil M€ con los aranceles: dinero para servicios públicos.',
                'buena', 'recaudacion', round($datos['recaudacion'], 1), null);
        }
        foreach (array_keys(SECTORES) as $s) {
            $extra = Simulador::proteccion($this->partida, $this->resultado, $c, $s) * 100;
            if ($extra >= 0.5) {
                $lineas[] = $this->linea('🛡️',
                    'Tus aranceles protegen a tus productores de ' . mb_strtolower(SECTORES[$s]['nombre'])
                    . ': como hay menos competencia de fuera, el año que viene producirán un ' . $this->num($extra) . ' % más.',
                    'buena', 'proteccion', round($extra, 1), $s);
            }
        }

        // 4. Productos que faltaron y productos que sobraron
        foreach ($datos['sectores'] as $s => $sector) {
            $nombreSector = mb_strtolower(SECTORES[$s]['nombre']);
            if ($sector['falta'] >= 0.5) {
                $lineas[] = $this->linea('🛒',
                    'Te faltaron ' . $this->num($sector['falta']) . ' unidades de ' . $nombreSector
                    . ': no hay para todos y suben los precios.',
                    'mala', 'escasez', round($sector['falta'], 1), $s);
            }
            $perdidoPorArancel = 0;
            foreach ($this->resultado['perdidos'][$s][$c] ?? [] as $unidades) {
                $perdidoPorArancel += $unidades;
            }
            $sobrante = $sector['produccion'] - $sector['local'] - $sector['exporta'] - $perdidoPorArancel;
            if ($sobrante >= 1) {
                $lineas[] = $this->linea('📦',
                    'Te sobraron ' . $this->num($sobrante) . ' unidades de ' . $nombreSector
                    . ' que nadie necesitaba: esos trabajadores se quedan sin trabajo.',
                    'mala', 'sobrante', round($sobrante, 1), $s);
            }
        }

        if (!$lineas) {
            $lineas[] = $this->linea('🤝', 'Año tranquilo: has comerciado sin problemas con todos los países.',
                'buena', 'tranquilo', 0, null);
        }

        // 5. Inversión (se nota el año que viene)
        $reparto = $decision->getRepartoInversion();
        arsort($reparto);
        $principal = array_key_first($reparto);
        $crecimiento = (CRECIMIENTO * $reparto[$principal] - DESGASTE) * 100;
        if ($reparto[$principal] - end($reparto) < 0.01) {
            $lineas[] = $this->linea('🏗️',
                'Has repartido tu inversión por igual: el año que viene cada sector producirá un '
                . $this->num($crecimiento) . ' % más.',
                'neutra', 'inversion', round($crecimiento, 1), null);
            $this->informes[$c] = $lineas;
            return $lineas;
        }
        $lineas[] = $this->linea('🏗️',
            'Has invertido sobre todo en ' . mb_strtolower(SECTORES[$principal]['nombre']) . ' ('
            . round($reparto[$principal] * 100) . ' % de tu inversión): el año que viene producirás un '
            . $this->num($crecimiento) . ' % más.',
            'neutra', 'inversion', round($crecimiento, 1), $principal);

        $this->informes[$c] = $lineas;
        return $lineas;
    }

    // ---- Noticias de la pared ----
    // $antes = indicadores de cada país antes de cerrar el año
    public function noticias($antes)
    {
        $noticias = [];
        $codigos = array_keys($this->partida->getPaises());

        if ($this->evento) {
            $noticias[] = new Noticia($this->evento->getIcono(), $this->evento->getNombre(),
                $this->evento->getDescripcion(), 'neutra');
        }

        // Aranceles y guerras comerciales
        $parejasHechas = [];
        foreach ($codigos as $i) {
            foreach ($codigos as $e) {
                if ($i === $e) {
                    continue;
                }
                $arancel = $this->partida->getDecision($i)->getArancel($e);
                $pareja = $i < $e ? $i . $e : $e . $i;
                if ($arancel < 5 || isset($parejasHechas[$pareja])) {
                    continue;
                }
                $respuesta = $this->partida->getDecision($e)->getArancel($i);
                $pierdeE = $this->valorPerdido($e, $i);
                if ($respuesta >= 5) {
                    $pierdeI = $this->valorPerdido($i, $e);
                    $detalle = $this->nombre($i) . ' pone un arancel del ' . $arancel . ' % a ' . $this->nombre($e) . ' y '
                        . $this->nombre($e) . ' responde con un ' . $respuesta . ' %. ';
                    if ($pierdeE >= 0.5 && $pierdeI >= 0.5) {
                        $detalle .= $this->nombre($e) . ' vende ' . $this->num($pierdeE) . ' mil M€ menos y '
                            . $this->nombre($i) . ' vende ' . $this->num($pierdeI) . ' mil M€ menos: pierden los dos.';
                    } elseif ($pierdeE >= 0.5 || $pierdeI >= 0.5) {
                        list($pierde, $valor, $otro) = $pierdeE >= $pierdeI ? [$e, $pierdeE, $i] : [$i, $pierdeI, $e];
                        $detalle .= $this->nombre($pierde) . ' vende ' . $this->num($valor) . ' mil M€ menos. '
                            . $this->nombre($otro) . ' apenas le vendía nada, pero ahora paga más caro lo que compra.';
                    } else {
                        $detalle .= 'Casi no comercian entre ellos, así que apenas se nota.';
                    }
                    $noticias[] = new Noticia('⚔️',
                        'Guerra comercial entre ' . $this->nombre($i) . ' y ' . $this->nombre($e), $detalle, 'mala');
                    $parejasHechas[$pareja] = true;
                } else {
                    $detalle = $pierdeE >= 0.5
                        ? $this->nombre($e) . ' vende ' . $this->num($pierdeE) . ' mil M€ menos en ' . $this->nombre($i)
                          . ' y allí sus productos ahora cuestan más.'
                        : $this->nombre($i) . ' apenas le compra nada a ' . $this->nombre($e) . ', así que el arancel casi no se nota.';
                    $noticias[] = new Noticia('🏷️',
                        $this->nombre($i) . ' pone un arancel del ' . $arancel . ' % a ' . $this->nombre($e),
                        $detalle, 'mala', $i);
                }
            }
        }

        // La economía que más crece y la que más cae
        $cambios = [];
        foreach ($codigos as $c) {
            $anterior = $antes[$c]['pib'];
            $cambios[$c] = $anterior > 0 ? ($this->resultado['paises'][$c]['indicadores']['pib'] - $anterior) / $anterior * 100 : 0;
        }
        arsort($cambios);
        $mejor = array_key_first($cambios);
        $peor = array_key_last($cambios);
        if ($cambios[$mejor] >= 1) {
            $noticias[] = new Noticia('📈',
                'La economía de ' . $this->nombre($mejor) . ' crece un ' . $this->num($cambios[$mejor]) . ' %',
                $this->causa($mejor, 'buena', 'Ha vendido más que el año pasado.'), 'buena', $mejor);
        }
        if ($cambios[$peor] <= -1) {
            $noticias[] = new Noticia('📉',
                'La economía de ' . $this->nombre($peor) . ' cae un ' . $this->num(abs($cambios[$peor])) . ' %',
                $this->causa($peor, 'mala', 'Ha vendido menos que el año pasado.'), 'mala', $peor);
        }

        // Empleo y precios
        foreach ($codigos as $c) {
            $nuevos = $this->resultado['paises'][$c]['indicadores'];
            $empleo = $nuevos['empleo'] - $antes[$c]['empleo'];
            $precios = $nuevos['precios'] - $antes[$c]['precios'];
            if ($empleo <= -1.5) {
                $noticias[] = new Noticia('👷', 'Sube el paro en ' . $this->nombre($c),
                    'El empleo baja ' . $this->num(abs($empleo)) . ' puntos: hay empresas que venden menos y despiden a trabajadores.',
                    'mala', $c);
            }
            if ($precios >= 3) {
                $noticias[] = new Noticia('🛒', 'Los precios suben un ' . $this->num($precios) . ' % en ' . $this->nombre($c),
                    'Con el mismo sueldo, la gente de ' . $this->nombre($c) . ' puede comprar menos cosas.', 'mala', $c);
            } elseif ($precios <= -3) {
                $noticias[] = new Noticia('🛍️', 'Bajan los precios en ' . $this->nombre($c),
                    'Los precios bajan un ' . $this->num(abs($precios)) . ' %: con el mismo sueldo se pueden comprar más cosas.',
                    'buena', $c);
            }
        }

        if (!$noticias) {
            $noticias[] = new Noticia('🤝', 'Un año tranquilo en el comercio mundial',
                'Sin aranceles ni sobresaltos: todos los países comercian con normalidad.', 'buena');
        }
        return $noticias;
    }

    // ---- Resumen final: junta todo lo que le pasó a un país durante la partida ----
    public static function causasDePartida(Partida $partida, $codigo)
    {
        $historial = $partida->getHistorial();
        $sufridos = [];     // [país que le puso el arancel => mil M€ perdidos]
        $puestos = [];      // [país al que puso aranceles => mil M€ que dejó de comprar]
        $recaudado = 0;
        $eventos = [];
        $escasez = 0;
        $sobrante = 0;
        foreach ($historial as $anio) {
            foreach ($anio['informes'][$codigo] ?? [] as $linea) {
                switch ($linea['categoria']) {
                    case 'arancel_sufrido':
                        $sufridos[$linea['origen']] = ($sufridos[$linea['origen']] ?? 0) + $linea['valor'];
                        break;
                    case 'arancel_puesto':
                        $puestos[$linea['origen']] = ($puestos[$linea['origen']] ?? 0) + $linea['valor'];
                        break;
                    case 'recaudacion':
                        $recaudado += $linea['valor'];
                        break;
                    case 'evento':
                        $eventos[] = ['anio' => $anio['anio'], 'linea' => $linea];
                        break;
                    case 'escasez':
                        $escasez++;
                        break;
                    case 'sobrante':
                        $sobrante++;
                        break;
                }
            }
        }
        $lineas = [];
        arsort($sufridos);
        foreach ($sufridos as $origen => $valor) {
            $lineas[] = ['icono' => '📉', 'tipo' => 'mala',
                'texto' => 'Los aranceles de ' . $partida->getPais($origen)->getNombre() . ' le hicieron vender '
                    . Vista::num($valor) . ' mil M€ menos en total.'];
        }
        foreach ($puestos as $origen => $valor) {
            $lineas[] = ['icono' => '🏷️', 'tipo' => 'mala',
                'texto' => 'Sus aranceles a ' . $partida->getPais($origen)->getNombre() . ' le hicieron comprar '
                    . Vista::num($valor) . ' mil M€ menos, y más caro.'];
        }
        if ($recaudado > 0) {
            $lineas[] = ['icono' => '🏛️', 'tipo' => 'buena',
                'texto' => 'Recaudó ' . Vista::num($recaudado) . ' mil M€ con sus aranceles.'];
        }
        foreach ($eventos as $evento) {
            $lineas[] = ['icono' => $evento['linea']['icono'], 'tipo' => $evento['linea']['tipo'],
                'texto' => $evento['anio'] . ' · ' . $evento['linea']['texto']];
        }
        if ($escasez > 0) {
            $lineas[] = ['icono' => '🛒', 'tipo' => 'mala',
                'texto' => 'Le faltaron productos ' . $escasez . ' ' . ($escasez === 1 ? 'vez' : 'veces') . ' (sector y año).'];
        }
        if ($sobrante > 0) {
            $lineas[] = ['icono' => '📦', 'tipo' => 'mala',
                'texto' => 'Produjo cosas que nadie compró ' . $sobrante . ' ' . ($sobrante === 1 ? 'vez' : 'veces') . '.'];
        }
        if (!$lineas) {
            $lineas[] = ['icono' => '🤝', 'tipo' => 'buena', 'texto' => 'Comerció sin problemas durante toda la partida.'];
        }
        return $lineas;
    }

    // ---- Ayudas ----
    private function linea($icono, $texto, $tipo, $categoria, $valor, $origen)
    {
        return ['icono' => $icono, 'texto' => $texto, 'tipo' => $tipo,
                'categoria' => $categoria, 'valor' => $valor, 'origen' => $origen];
    }

    private function otros($c)
    {
        return array_values(array_filter(array_keys($this->partida->getPaises()), fn($o) => $o !== $c));
    }

    private function nombre($c)
    {
        return $this->partida->getPais($c)->getNombre();
    }

    // Valor (en miles de millones) que $exportador dejó de vender a $importador por aranceles
    private function valorPerdido($exportador, $importador)
    {
        $valor = 0;
        foreach (array_keys(SECTORES) as $s) {
            $valor += ($this->resultado['perdidos'][$s][$exportador][$importador] ?? 0) * $this->resultado['precios'][$s];
        }
        return $valor;
    }

    // Explica en tercera persona por qué la economía de un país sube o baja (para las noticias)
    private function causa($c, $tipo, $porDefecto)
    {
        foreach ($this->informes[$c] ?? [] as $linea) {
            if ($linea['tipo'] !== $tipo) {
                continue;
            }
            switch ($linea['categoria']) {
                case 'evento':
                    return 'Motivo: ' . $linea['texto'];
                case 'arancel_sufrido':
                    return 'Motivo: el arancel de ' . $this->nombre($linea['origen']) . ' le hace vender '
                        . $this->num($linea['valor']) . ' mil M€ menos.';
                case 'escasez':
                    return 'Motivo: le faltan productos de ' . mb_strtolower(SECTORES[$linea['origen']]['nombre']) . '.';
                case 'sobrante':
                    return 'Motivo: produce ' . mb_strtolower(SECTORES[$linea['origen']]['nombre']) . ' que nadie le compra.';
            }
        }
        // Si el año pasado hubo un evento, ahora todo vuelve a la normalidad
        $anterior = $this->partida->getUltimoAnioCerrado();
        $eventoAnterior = $anterior ? Evento::buscar($anterior['evento']) : null;
        if ($eventoAnterior && !$this->evento) {
            return 'Motivo: se acaba el efecto de «' . $eventoAnterior->getNombre() . '» del año pasado y todo vuelve a la normalidad.';
        }
        return $porDefecto;
    }

    private function lista($elementos)
    {
        if (count($elementos) <= 1) {
            return implode('', $elementos);
        }
        $ultimo = array_pop($elementos);
        return implode(', ', $elementos) . ' y ' . $ultimo;
    }

    private function num($numero)
    {
        return str_replace(',0', '', number_format($numero, 1, ',', '.'));
    }
}

<?php
// Ayudas para pintar el HTML: cabecera, pie, banderas, números y flechas de subida/bajada.
class Vista
{
    const INDICADORES = [
        'pib'       => ['nombre' => 'Dinero (PIB)', 'icono' => '💰', 'unidad' => ' mil M€', 'subirEsBueno' => true],
        'empleo'    => ['nombre' => 'Empleo',       'icono' => '👷', 'unidad' => ' %',      'subirEsBueno' => true],
        'precios'   => ['nombre' => 'Precios',      'icono' => '🏷️', 'unidad' => '',        'subirEsBueno' => false],
        'bienestar' => ['nombre' => 'Bienestar',    'icono' => '🙂', 'unidad' => ' / 100',  'subirEsBueno' => true],
    ];

    // Escapa texto para meterlo en el HTML
    public static function e($texto)
    {
        return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
    }

    public static function num($numero, $decimales = 1)
    {
        return str_replace(',0', '', number_format((float) $numero, $decimales, ',', '.'));
    }

    // $refresco = segundos para recargar sola (0 = no recarga)
    public static function cabecera($titulo, $refresco = 0, $claseBody = '')
    {
        header('Cache-Control: no-store');
        echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
        if ($refresco > 0) {
            echo '<meta http-equiv="refresh" content="' . (int) $refresco . '">';
        }
        echo '<title>' . self::e($titulo) . '</title>';
        echo '<link rel="stylesheet" href="css/estilo.css">';
        echo '</head><body class="' . self::e($claseBody) . '">';
    }

    public static function pie()
    {
        echo '</body></html>';
    }

    public static function bandera(Pais $pais, $clase = 'bandera')
    {
        return '<img class="' . self::e($clase) . '" src="' . self::e($pais->getBandera()) . '" alt="'
            . self::e($pais->getNombre()) . '">';
    }

    // Flecha verde o roja según si el cambio es bueno o malo
    public static function cambio($indicador, $actual, $anterior)
    {
        if ($anterior === null) {
            return '';
        }
        $diferencia = round($actual - $anterior, 1);
        if (abs($diferencia) < 0.1) {
            return '<span class="cambio igual">=</span>';
        }
        $sube = $diferencia > 0;
        $bueno = $sube === self::INDICADORES[$indicador]['subirEsBueno'];
        return '<span class="cambio ' . ($bueno ? 'bueno' : 'malo') . '">' . ($sube ? '▲ +' : '▼ ')
            . self::num($diferencia) . '</span>';
    }

    // Las 4 tarjetas de indicadores de un país
    public static function indicadores(Pais $pais, $grande = false)
    {
        $actuales = $pais->getIndicadores();
        $anteriores = $pais->getIndicadoresAnteriores();
        $html = '<div class="indicadores' . ($grande ? ' grandes' : '') . '">';
        foreach (self::INDICADORES as $clave => $datos) {
            $html .= '<div class="indicador">'
                . '<span class="ind-nombre">' . $datos['icono'] . ' ' . self::e($datos['nombre']) . '</span>'
                . '<span class="ind-valor">' . self::num($actuales[$clave]) . '<small>' . self::e($datos['unidad']) . '</small></span>'
                . self::cambio($clave, $actuales[$clave], $anteriores[$clave] ?? null)
                . '</div>';
        }
        return $html . '</div>';
    }

    // Lista de líneas de un informe
    public static function informe($lineas)
    {
        $html = '<ul class="informe">';
        foreach ($lineas as $linea) {
            $html .= '<li class="' . self::e($linea['tipo']) . '"><span class="icono">' . $linea['icono'] . '</span>'
                . self::e($linea['texto']) . '</li>';
        }
        return $html . '</ul>';
    }
}

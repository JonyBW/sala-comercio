<?php
// Un evento mundial que el profesor puede lanzar. Sus efectos están en config.php (EVENTOS).
class Evento
{
    private $codigo;
    private $nombre;
    private $icono;
    private $descripcion;
    private $precio;       // [sector => multiplicador]
    private $produccion;   // [país => [sector => multiplicador]]
    private $necesidad;    // [país o '*' => [sector => multiplicador]]
    private $comercio;     // multiplicador del comercio entre países
    private $textos;       // [país => [texto, 'buena' | 'mala']]

    public function __construct($codigo, $datos)
    {
        $this->codigo = $codigo;
        $this->nombre = $datos['nombre'];
        $this->icono = $datos['icono'];
        $this->descripcion = $datos['descripcion'];
        $this->precio = $datos['precio'];
        $this->produccion = $datos['produccion'];
        $this->necesidad = $datos['necesidad'];
        $this->comercio = $datos['comercio'];
        $this->textos = $datos['textos'];
    }

    // Devuelve el evento con ese código o null si no existe
    public static function buscar($codigo)
    {
        if ($codigo === null || !isset(EVENTOS[$codigo])) {
            return null;
        }
        return new Evento($codigo, EVENTOS[$codigo]);
    }

    public static function todos()
    {
        $eventos = [];
        foreach (array_keys(EVENTOS) as $codigo) {
            $eventos[] = Evento::buscar($codigo);
        }
        return $eventos;
    }

    // ---- Getters ----
    public function getCodigo() { return $this->codigo; }
    public function getNombre() { return $this->nombre; }
    public function getIcono() { return $this->icono; }
    public function getDescripcion() { return $this->descripcion; }
    public function getComercio() { return $this->comercio; }

    public function getMultPrecio($sector)
    {
        return $this->precio[$sector] ?? 1;
    }

    public function getMultProduccion($pais, $sector)
    {
        return $this->produccion[$pais][$sector] ?? 1;
    }

    public function getMultNecesidad($pais, $sector)
    {
        return $this->necesidad[$pais][$sector] ?? $this->necesidad['*'][$sector] ?? 1;
    }

    // [texto, tipo] para el informe de un país
    public function getTexto($pais)
    {
        return $this->textos[$pais] ?? [$this->descripcion, 'mala'];
    }
}

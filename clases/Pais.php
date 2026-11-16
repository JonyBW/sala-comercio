<?php
// Un país de la partida: lo que produce, lo que necesita, sus indicadores y su informe.
class Pais
{
    private $codigo;
    private $nombre;
    private $produccion;              // [sector => unidades que puede producir]
    private $necesidad;               // [sector => unidades que necesita]
    private $indicadores;             // ['pib', 'empleo', 'precios', 'bienestar']
    private $indicadoresAnteriores;   // los del año anterior (para ver si suben o bajan)
    private $informe;                 // líneas que explican lo que pasó el último año
    private $token;                   // código secreto del enlace del país (null = sin generar)

    public function __construct($codigo, $nombre, $produccion, $necesidad)
    {
        $this->codigo = $codigo;
        $this->nombre = $nombre;
        $this->produccion = $produccion;
        $this->necesidad = $necesidad;
        $this->indicadores = ['pib' => 0, 'empleo' => 0, 'precios' => 100, 'bienestar' => 0];
        $this->indicadoresAnteriores = null;
        $this->informe = [];
        $this->token = null;
    }

    // Crea el país con los datos iniciales de config.php
    public static function desdeConfig($codigo)
    {
        $datos = PAISES[$codigo];
        return new Pais($codigo, $datos['nombre'], $datos['produccion'], $datos['necesidad']);
    }

    // ---- Getters ----
    public function getCodigo() { return $this->codigo; }
    public function getNombre() { return $this->nombre; }
    public function getProduccion() { return $this->produccion; }
    public function getProduccionSector($sector) { return $this->produccion[$sector]; }
    public function getNecesidad() { return $this->necesidad; }
    public function getNecesidadSector($sector) { return $this->necesidad[$sector]; }
    public function getIndicadores() { return $this->indicadores; }
    public function getIndicador($nombre) { return $this->indicadores[$nombre]; }
    public function getIndicadoresAnteriores() { return $this->indicadoresAnteriores; }
    public function getInforme() { return $this->informe; }
    public function getToken() { return $this->token; }
    public function estaGenerado() { return $this->token !== null; }

    public function getBandera() { return 'img/' . $this->codigo . '.svg'; }

    // ---- Setters ----
    public function setProduccionSector($sector, $unidades)
    {
        $this->produccion[$sector] = max(1, round($unidades, 2));
    }

    public function setNecesidadSector($sector, $unidades)
    {
        $this->necesidad[$sector] = max(1, round($unidades, 2));
    }

    public function setIndicadores($indicadores) { $this->indicadores = $indicadores; }
    public function setIndicadoresAnteriores($indicadores) { $this->indicadoresAnteriores = $indicadores; }
    public function setInforme($lineas) { $this->informe = $lineas; }
    public function setToken($token) { $this->token = $token; }

    // ---- Guardar / cargar ----
    public function toArray()
    {
        return [
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'produccion' => $this->produccion,
            'necesidad' => $this->necesidad,
            'indicadores' => $this->indicadores,
            'indicadoresAnteriores' => $this->indicadoresAnteriores,
            'informe' => $this->informe,
            'token' => $this->token,
        ];
    }

    public static function fromArray($datos)
    {
        $pais = new Pais($datos['codigo'], $datos['nombre'], $datos['produccion'], $datos['necesidad']);
        $pais->setIndicadores($datos['indicadores']);
        $pais->setIndicadoresAnteriores($datos['indicadoresAnteriores']);
        $pais->setInforme($datos['informe']);
        $pais->setToken($datos['token']);
        return $pais;
    }
}

<?php
// Un titular de la pared de noticias.
class Noticia
{
    private $icono;
    private $titular;
    private $detalle;
    private $tipo;     // 'buena', 'mala' o 'neutra'
    private $pais;     // código del país protagonista (o null)

    public function __construct($icono, $titular, $detalle, $tipo = 'neutra', $pais = null)
    {
        $this->icono = $icono;
        $this->titular = $titular;
        $this->detalle = $detalle;
        $this->tipo = $tipo;
        $this->pais = $pais;
    }

    // ---- Getters ----
    public function getIcono() { return $this->icono; }
    public function getTitular() { return $this->titular; }
    public function getDetalle() { return $this->detalle; }
    public function getTipo() { return $this->tipo; }
    public function getPais() { return $this->pais; }

    // ---- Setters ----
    public function setTitular($titular) { $this->titular = $titular; }
    public function setDetalle($detalle) { $this->detalle = $detalle; }
    public function setTipo($tipo) { $this->tipo = $tipo; }

    // ---- Guardar / cargar ----
    public function toArray()
    {
        return [
            'icono' => $this->icono, 'titular' => $this->titular, 'detalle' => $this->detalle,
            'tipo' => $this->tipo, 'pais' => $this->pais,
        ];
    }

    public static function fromArray($datos)
    {
        return new Noticia($datos['icono'], $datos['titular'], $datos['detalle'], $datos['tipo'], $datos['pais']);
    }
}

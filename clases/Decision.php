<?php
// Lo que decide un país cada año: aranceles a los demás países e inversión por sector.
class Decision
{
    private $aranceles;    // [código de otro país => % de 0 a ARANCEL_MAXIMO]
    private $inversion;    // [sector => 0 a INVERSION_MAXIMA]
    private $confirmada;

    public function __construct($aranceles, $inversion, $confirmada = false)
    {
        $this->aranceles = $aranceles;
        $this->inversion = $inversion;
        $this->confirmada = $confirmada;
    }

    // Decisión inicial: sin aranceles e inversión repartida a partes iguales
    public static function porDefecto($codigoPais)
    {
        $aranceles = [];
        foreach (array_keys(PAISES) as $otro) {
            if ($otro !== $codigoPais) {
                $aranceles[$otro] = 0;
            }
        }
        $inversion = [];
        foreach (array_keys(SECTORES) as $sector) {
            $inversion[$sector] = 5;
        }
        return new Decision($aranceles, $inversion);
    }

    // ---- Getters ----
    public function getAranceles() { return $this->aranceles; }
    public function getArancel($otroPais) { return $this->aranceles[$otroPais] ?? 0; }
    public function getInversion() { return $this->inversion; }
    public function getInversionSector($sector) { return $this->inversion[$sector] ?? 0; }
    public function isConfirmada() { return $this->confirmada; }

    // Convierte las barras de inversión en porcentajes que suman 1 (si todo es 0, a partes iguales)
    public function getRepartoInversion()
    {
        $total = array_sum($this->inversion);
        $reparto = [];
        foreach ($this->inversion as $sector => $valor) {
            $reparto[$sector] = $total > 0 ? $valor / $total : 1 / count($this->inversion);
        }
        return $reparto;
    }

    // ---- Setters (nunca se fían de lo que llega: siempre se limitan al rango permitido) ----
    public function setArancel($otroPais, $porcentaje)
    {
        if (array_key_exists($otroPais, $this->aranceles)) {
            $porcentaje = (int) $porcentaje;
            $this->aranceles[$otroPais] = max(0, min(ARANCEL_MAXIMO, $porcentaje - $porcentaje % 5));
        }
    }

    public function setInversionSector($sector, $valor)
    {
        if (array_key_exists($sector, $this->inversion)) {
            $this->inversion[$sector] = max(0, min(INVERSION_MAXIMA, (int) $valor));
        }
    }

    public function setConfirmada($confirmada) { $this->confirmada = (bool) $confirmada; }

    // Rellena la decisión con lo que llega del formulario del país
    public function setDesdeFormulario($post)
    {
        foreach (array_keys($this->aranceles) as $otro) {
            if (isset($post['arancel'][$otro])) {
                $this->setArancel($otro, $post['arancel'][$otro]);
            }
        }
        foreach (array_keys($this->inversion) as $sector) {
            if (isset($post['inversion'][$sector])) {
                $this->setInversionSector($sector, $post['inversion'][$sector]);
            }
        }
    }

    // ---- Guardar / cargar ----
    public function toArray()
    {
        return ['aranceles' => $this->aranceles, 'inversion' => $this->inversion, 'confirmada' => $this->confirmada];
    }

    public static function fromArray($datos)
    {
        return new Decision($datos['aranceles'], $datos['inversion'], $datos['confirmada']);
    }
}

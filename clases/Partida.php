<?php
// La partida completa: año, países, decisiones, evento pendiente e historial de años cerrados.
class Partida
{
    const JUGANDO = 'jugando';
    const TERMINADA = 'terminada';

    private $anio;
    private $estado;
    private $paises;           // [código => Pais]
    private $decisiones;       // [código => Decision]
    private $eventoPendiente;  // código del evento que se aplicará al cerrar el año (o null)
    private $historial;        // un elemento por año cerrado (ver Simulador::cerrarAnio)
    private $ultimo;           // comercio del último año calculado (lo dibuja el mapa)
    private $inicio;           // foto del principio de la partida (para el resumen)

    public function __construct()
    {
        $this->anio = ANIO_INICIAL;
        $this->estado = self::JUGANDO;
        $this->paises = [];
        $this->decisiones = [];
        $this->eventoPendiente = null;
        $this->historial = [];
        $this->ultimo = null;
        $this->inicio = null;
    }

    // Partida nueva con los países de config.php y sus indicadores iniciales ya calculados
    public static function crear()
    {
        $partida = new Partida();
        foreach (array_keys(PAISES) as $codigo) {
            $partida->paises[$codigo] = Pais::desdeConfig($codigo);
            $partida->decisiones[$codigo] = Decision::porDefecto($codigo);
        }
        Simulador::inicializar($partida);
        return $partida;
    }

    // ---- Getters ----
    public function getAnio() { return $this->anio; }
    public function getEstado() { return $this->estado; }
    public function estaJugando() { return $this->estado === self::JUGANDO; }
    public function getPaises() { return $this->paises; }
    public function getDecisiones() { return $this->decisiones; }
    public function getHistorial() { return $this->historial; }
    public function getUltimo() { return $this->ultimo; }
    public function getInicio() { return $this->inicio; }
    public function getEventoPendiente() { return Evento::buscar($this->eventoPendiente); }

    public function getPais($codigo) { return $this->paises[$codigo] ?? null; }
    public function getDecision($codigo) { return $this->decisiones[$codigo] ?? null; }

    public function getPaisPorToken($token)
    {
        if (!is_string($token) || $token === '') {
            return null;
        }
        foreach ($this->paises as $pais) {
            if ($pais->getToken() !== null && hash_equals($pais->getToken(), $token)) {
                return $pais;
            }
        }
        return null;
    }

    // El último año cerrado (o null si todavía no se ha cerrado ninguno)
    public function getUltimoAnioCerrado()
    {
        return $this->historial ? end($this->historial) : null;
    }

    public function getNoticias()
    {
        $ultimo = $this->getUltimoAnioCerrado();
        if (!$ultimo) {
            return [];
        }
        $noticias = [];
        foreach ($ultimo['noticias'] as $datos) {
            $noticias[] = Noticia::fromArray($datos);
        }
        return $noticias;
    }

    // Se cierra el año solo cuando todos los países generados han confirmado
    public function todosConfirmados()
    {
        $generados = 0;
        foreach ($this->paises as $codigo => $pais) {
            if ($pais->estaGenerado()) {
                $generados++;
                if (!$this->decisiones[$codigo]->isConfirmada()) {
                    return false;
                }
            }
        }
        return $generados > 0;
    }

    // ---- Setters / acciones ----
    public function setEstado($estado) { $this->estado = $estado; }
    public function setUltimo($ultimo) { $this->ultimo = $ultimo; }
    public function setInicio($inicio) { $this->inicio = $inicio; }

    public function setEventoPendiente($codigo)
    {
        $this->eventoPendiente = Evento::buscar($codigo) ? $codigo : null;
    }

    // Crea el enlace secreto de un país (si ya existe, devuelve el mismo)
    public function generarToken($codigo)
    {
        $pais = $this->getPais($codigo);
        if (!$pais) {
            return null;
        }
        if (!$pais->estaGenerado()) {
            $pais->setToken(bin2hex(random_bytes(8)));
        }
        return $pais->getToken();
    }

    public function anadirAlHistorial($entrada) { $this->historial[] = $entrada; }

    public function avanzarAnio()
    {
        $this->anio++;
        $this->eventoPendiente = null;
        foreach ($this->decisiones as $decision) {
            $decision->setConfirmada(false);   // se mantienen los valores del año anterior
        }
    }

    // ---- Guardar / cargar ----
    public function toArray()
    {
        $paises = [];
        foreach ($this->paises as $codigo => $pais) {
            $paises[$codigo] = $pais->toArray();
        }
        $decisiones = [];
        foreach ($this->decisiones as $codigo => $decision) {
            $decisiones[$codigo] = $decision->toArray();
        }
        return [
            'anio' => $this->anio,
            'estado' => $this->estado,
            'paises' => $paises,
            'decisiones' => $decisiones,
            'eventoPendiente' => $this->eventoPendiente,
            'historial' => $this->historial,
            'ultimo' => $this->ultimo,
            'inicio' => $this->inicio,
        ];
    }

    public static function fromArray($datos)
    {
        $partida = new Partida();
        $partida->anio = $datos['anio'];
        $partida->estado = $datos['estado'];
        foreach ($datos['paises'] as $codigo => $p) {
            $partida->paises[$codigo] = Pais::fromArray($p);
        }
        foreach ($datos['decisiones'] as $codigo => $d) {
            $partida->decisiones[$codigo] = Decision::fromArray($d);
        }
        $partida->eventoPendiente = $datos['eventoPendiente'];
        $partida->historial = $datos['historial'];
        $partida->ultimo = $datos['ultimo'];
        $partida->inicio = $datos['inicio'];
        return $partida;
    }
}

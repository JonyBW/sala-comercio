<?php
// Guarda y carga la partida en datos/partida.json.
// Usa bloqueo (flock) para que dos países que confirman a la vez no se pisen.
class Almacen
{
    // Solo lectura. Devuelve la Partida o null si no hay ninguna.
    public static function leer()
    {
        if (!is_file(ARCHIVO_PARTIDA)) {
            return null;
        }
        $fp = fopen(ARCHIVO_PARTIDA, 'r');
        flock($fp, LOCK_SH);
        $datos = json_decode(stream_get_contents($fp), true);
        flock($fp, LOCK_UN);
        fclose($fp);
        return (is_array($datos) && $datos) ? Partida::fromArray($datos) : null;
    }

    // Lee, deja que $cambio modifique la partida y la vuelve a guardar, todo bloqueado.
    // $cambio recibe la Partida (o null). Si devuelve una Partida, esa sustituye a la guardada.
    // Devuelve lo mismo que devuelva $cambio.
    public static function modificar(callable $cambio)
    {
        $carpeta = dirname(ARCHIVO_PARTIDA);
        if (!is_dir($carpeta)) {
            mkdir($carpeta, 0775, true);
        }
        $fp = fopen(ARCHIVO_PARTIDA, 'c+');
        flock($fp, LOCK_EX);

        $datos = json_decode(stream_get_contents($fp), true);
        $partida = (is_array($datos) && $datos) ? Partida::fromArray($datos) : null;

        $resultado = $cambio($partida);
        if ($resultado instanceof Partida) {
            $partida = $resultado;
        }

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, $partida ? json_encode($partida->toArray(), JSON_UNESCAPED_UNICODE) : '{}');
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        return $resultado;
    }
}

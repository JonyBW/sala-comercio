<?php
// =====================================================================
//  CONFIGURACIÓN DEL JUEGO "COMERCIO MUNDIAL"
//  Todo lo que se puede ajustar sin tocar la lógica está aquí.
// =====================================================================

// ---- General ----
const PASSWORD_PROFE  = 'Irakasle';          // <-- CAMBIA ESTA CONTRASEÑA
const REFRESCO        = 3;                   // segundos entre recargas de las pantallas
const ARCHIVO_PARTIDA = __DIR__ . '/datos/partida.json';
define('ANIO_INICIAL', (int) date('Y'));     // la partida empieza en el año actual

// ---- Sectores ----
// precio = lo que vale cada unidad (en miles de millones de €)
const SECTORES = [
    'alimentos'  => ['nombre' => 'Alimentos',  'icono' => '🌾', 'precio' => 2],
    'energia'    => ['nombre' => 'Energía',    'icono' => '⚡', 'precio' => 3],
    'fabricas'   => ['nombre' => 'Fábricas',   'icono' => '🏭', 'precio' => 4],
    'tecnologia' => ['nombre' => 'Tecnología', 'icono' => '💻', 'precio' => 5],
];

// ---- Países ----
// produccion = lo que fabrica cada año | necesidad = lo que consume su población
// Si produce más de lo que necesita, le sobra y lo exporta.
// Si produce menos, tiene que importar.
const PAISES = [
    'es' => [
        'nombre'     => 'España',
        'produccion' => ['alimentos' => 37, 'energia' => 8,  'fabricas' => 15, 'tecnologia' => 6],
        'necesidad'  => ['alimentos' => 20, 'energia' => 20, 'fabricas' => 18, 'tecnologia' => 12],
    ],
    'us' => [
        'nombre'     => 'EE. UU.',
        'produccion' => ['alimentos' => 30, 'energia' => 28, 'fabricas' => 15, 'tecnologia' => 36],
        'necesidad'  => ['alimentos' => 35, 'energia' => 30, 'fabricas' => 30, 'tecnologia' => 22],
    ],
    'cn' => [
        'nombre'     => 'China',
        'produccion' => ['alimentos' => 30, 'energia' => 15, 'fabricas' => 54, 'tecnologia' => 20],
        'necesidad'  => ['alimentos' => 35, 'energia' => 32, 'fabricas' => 25, 'tecnologia' => 20],
    ],
    'sa' => [
        'nombre'     => 'Arabia Saudí',
        'produccion' => ['alimentos' => 5,  'energia' => 41, 'fabricas' => 4,  'tecnologia' => 2],
        'necesidad'  => ['alimentos' => 12, 'energia' => 10, 'fabricas' => 15, 'tecnologia' => 10],
    ],
];

// ---- Reglas de la simulación ----
const EFECTO_ARANCEL   = 1.5;   // con un arancel del 50 % se compra un 75 % menos
const ARANCEL_MAXIMO   = 50;
const INVERSION_MAXIMA = 10;
const CRECIMIENTO      = 0.16;  // lo que puede crecer un sector si recibe toda la inversión
const DESGASTE         = 0.02;  // lo que pierde un sector cada año si no se invierte en él
const CRECIMIENTO_DEMANDA = 0.02; // la población consume un 2 % más cada año
const PROTECCION      = 0.1;   // un arancel del 50 % hace crecer un 5 % a los productores del país

// ---- Eventos ----
// Solo duran el año en el que se lanzan. Multiplicadores: 1 = sin cambio, 0.8 = -20 %, 1.3 = +30 %
//   precio:     cambia el precio mundial de un sector
//   produccion: cambia lo que produce cada país en un sector
//   necesidad:  cambia lo que necesita cada país en un sector ('*' = todos los países)
//   comercio:   cuánto comercio se puede hacer entre países
//   textos:     lo que lee cada país en su informe [texto, 'buena' | 'mala']
const EVENTOS = [
    'petroleo' => [
        'nombre' => 'Crisis del petróleo', 'icono' => '🛢️',
        'descripcion' => 'Arabia Saudí recorta su producción y la energía se encarece en todo el mundo.',
        'precio' => ['energia' => 1.8],
        'produccion' => ['sa' => ['energia' => 0.9]],
        'necesidad' => [],
        'comercio' => 1,
        'textos' => [
            'es' => ['España compra casi toda su energía fuera: la luz y la gasolina se disparan.', 'mala'],
            'us' => ['EE. UU. produce casi toda su energía: le afecta poco.', 'mala'],
            'cn' => ['Las fábricas chinas necesitan mucha energía de fuera y ahora pagan mucho más.', 'mala'],
            'sa' => ['Arabia Saudí vende su petróleo mucho más caro: entra muchísimo dinero.', 'buena'],
        ],
    ],
    'sequia' => [
        'nombre' => 'Sequía', 'icono' => '🌵',
        'descripcion' => 'Una gran sequía hace que las cosechas caigan en todo el mundo.',
        'precio' => ['alimentos' => 1.3],
        'produccion' => [
            'es' => ['alimentos' => 0.6], 'us' => ['alimentos' => 0.9],
            'cn' => ['alimentos' => 0.8], 'sa' => ['alimentos' => 0.9],
        ],
        'necesidad' => [],
        'comercio' => 1,
        'textos' => [
            'es' => ['El campo español pierde gran parte de la cosecha: hay menos para vender fuera.', 'mala'],
            'us' => ['Las cosechas de EE. UU. bajan un poco y la comida de fuera es más cara.', 'mala'],
            'cn' => ['China cosecha menos y tiene que comprar más comida, que ahora es más cara.', 'mala'],
            'sa' => ['Arabia Saudí compra casi toda su comida fuera: los precios suben mucho.', 'mala'],
        ],
    ],
    'puertos' => [
        'nombre' => 'Puertos cerrados', 'icono' => '🚢',
        'descripcion' => 'Una pandemia obliga a cerrar puertos: el comercio entre países cae un 30 %.',
        'precio' => [],
        'produccion' => [],
        'necesidad' => [],
        'comercio' => 0.7,
        'textos' => [
            'es' => ['España no puede vender tanta comida fuera ni comprar toda la energía que necesita.', 'mala'],
            'us' => ['EE. UU. depende menos del comercio, pero le faltan productos de fábrica.', 'mala'],
            'cn' => ['China vive de exportar: sus fábricas se quedan con la mercancía en el puerto.', 'mala'],
            'sa' => ['Arabia Saudí no puede vender su petróleo y le falta casi de todo.', 'mala'],
        ],
    ],
    'tecnologia' => [
        'nombre' => 'Avance tecnológico', 'icono' => '💡',
        'descripcion' => 'Un gran invento hace la tecnología más barata y todo el mundo quiere más.',
        'precio' => ['tecnologia' => 0.85],
        'produccion' => [
            'es' => ['tecnologia' => 1.05], 'us' => ['tecnologia' => 1.3],
            'cn' => ['tecnologia' => 1.15], 'sa' => ['tecnologia' => 1.05],
        ],
        'necesidad' => ['*' => ['tecnologia' => 1.2]],
        'comercio' => 1,
        'textos' => [
            'es' => ['La tecnología que compra España es más barata.', 'buena'],
            'us' => ['Las empresas tecnológicas de EE. UU. producen y venden mucho más.', 'buena'],
            'cn' => ['China aprovecha el invento y fabrica más tecnología.', 'buena'],
            'sa' => ['La tecnología que compra Arabia Saudí es más barata.', 'buena'],
        ],
    ],
    'chips' => [
        'nombre' => 'Escasez de chips', 'icono' => '🔌',
        'descripcion' => 'Faltan chips en todo el mundo: las fábricas y la tecnología producen menos.',
        'precio' => [],
        'produccion' => [
            'es' => ['fabricas' => 0.9,  'tecnologia' => 0.9],
            'us' => ['fabricas' => 0.85, 'tecnologia' => 0.9],
            'cn' => ['fabricas' => 0.7,  'tecnologia' => 0.9],
            'sa' => ['fabricas' => 0.95, 'tecnologia' => 0.9],
        ],
        'necesidad' => [],
        'comercio' => 1,
        'textos' => [
            'es' => ['Algunas fábricas de coches en España paran por falta de chips.', 'mala'],
            'us' => ['EE. UU. fabrica menos y le llegan menos productos de China.', 'mala'],
            'cn' => ['Las fábricas chinas, las más grandes del mundo, son las que más paran.', 'mala'],
            'sa' => ['A Arabia Saudí le llegan menos productos de fábrica.', 'mala'],
        ],
    ],
    'renovables' => [
        'nombre' => 'Boom de las renovables', 'icono' => '☀️',
        'descripcion' => 'Se instalan miles de placas solares y molinos: el mundo necesita menos petróleo.',
        'precio' => ['energia' => 0.85],
        'produccion' => ['es' => ['energia' => 1.4], 'us' => ['energia' => 1.05], 'cn' => ['energia' => 1.15]],
        'necesidad' => ['*' => ['energia' => 0.75]],
        'comercio' => 1,
        'textos' => [
            'es' => ['España tiene mucho sol: produce más energía propia y compra menos fuera.', 'buena'],
            'us' => ['EE. UU. necesita menos petróleo y produce algo más de energía.', 'buena'],
            'cn' => ['China instala muchas placas y depende menos del petróleo de fuera.', 'buena'],
            'sa' => ['Nadie quiere tanto petróleo: Arabia Saudí vende mucho menos.', 'mala'],
        ],
    ],
];

// ---- Carga automática de las clases (carpeta clases/) ----
spl_autoload_register(function ($clase) {
    $archivo = __DIR__ . '/clases/' . $clase . '.php';
    if (is_file($archivo)) {
        require $archivo;
    }
});

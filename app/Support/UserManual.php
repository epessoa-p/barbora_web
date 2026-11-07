<?php

namespace App\Support;

use App\Models\Company;
use App\Models\User;

/**
 * El manual de usuario: un solo contenido para la web y para la app, para que
 * no se desincronicen. Al cambiar una pantalla, se actualiza aquí.
 *
 * Cada sección dice dónde está en cada sistema («where») y qué hace falta para
 * verla (los mismos módulos y permisos del menú): cada usuario lee solo lo que
 * puede usar. Un paso puede ser un texto común, o ['web' => …, 'app' => …] si
 * en cada sistema se hace distinto; un tema con 'only' sale en uno solo.
 */
class UserManual
{
    /**
     * El manual para un sistema («web» o «app»), con lo que este usuario puede usar.
     *
     * @return array<int, array{key: string, title: string, intro: string, where: ?string, topics: array}>
     */
    public static function for(string $surface, User $user, ?Company $company): array
    {
        return collect(self::sections())
            ->filter(fn (array $s) => self::allowed($s, $user, $company))
            ->map(function (array $s) use ($surface) {
                $topics = collect($s['topics'])
                    ->filter(fn (array $t) => ! isset($t['only']) || $t['only'] === $surface)
                    ->map(fn (array $t) => [
                        'title' => $t['title'],
                        'steps' => collect($t['steps'])
                            ->map(fn ($step) => is_array($step) ? ($step[$surface] ?? null) : $step)
                            ->filter()->values()->all(),
                        'note' => $t['note'] ?? null,
                    ])
                    ->values()->all();

                return [
                    'key' => $s['key'],
                    'title' => $s['title'],
                    'intro' => $s['intro'],
                    'where' => $s['where'][$surface] ?? null,
                    'topics' => $topics,
                ];
            })
            ->filter(fn (array $s) => $s['topics'] !== [])
            ->values()->all();
    }

    /** Como el menú: algún módulo del plan (si pide) y algún permiso (si pide). */
    protected static function allowed(array $section, User $user, ?Company $company): bool
    {
        if ($user->is_super_admin) {
            return true;
        }

        $modules = $section['modules'] ?? [];
        if ($modules !== [] && ! collect($modules)->contains(fn ($m) => (bool) $company?->planAllows($m))) {
            return false;
        }

        $permissions = $section['permissions'] ?? [];

        return $permissions === []
            || collect($permissions)->contains(fn ($p) => $user->hasPermissionInCompany($p, $company));
    }

    protected static function sections(): array
    {
        return [
            [
                'key' => 'inicio',
                'title' => 'Primeros pasos',
                'intro' => 'Cómo entrar y moverte por el sistema. Cada persona ve solo lo que su plan y sus permisos le permiten.',
                'where' => ['web' => 'Menú lateral, a la izquierda', 'app' => 'Menú lateral (☰), arriba a la izquierda'],
                'topics' => [
                    [
                        'title' => 'Entrar y salir',
                        'steps' => [
                            'Entra con tu usuario y contraseña.',
                            ['web' => 'Si trabajas en varias barberías, cámbiala con el botón «Empresa» de la barra superior.', 'app' => 'Si trabajas en varias barberías, elige una al entrar; luego se cambia en el menú, con «Cambiar de barbería».'],
                            ['web' => 'Para salir, usa «Cerrar sesión», arriba a la derecha.', 'app' => 'Para salir, abre el menú y toca «Cerrar sesión».'],
                        ],
                        'note' => 'Si no ves una sección que necesitas, puede que tu plan no la incluya o que tu usuario no tenga permiso: consúltalo con el administrador.',
                    ],
                    [
                        'title' => 'Por dónde empezar (administrador)',
                        'steps' => [
                            'Completa los datos del negocio en «Mi empresa» y revisa tus «Sucursales».',
                            'Carga los «Métodos de pago», las «Cajas» y al «Personal», con sus horarios.',
                            'Crea los «Servicios» y, si vendes productos, los «Productos» y su stock inicial.',
                            'Si pagas comisiones, crea las «Reglas» de comisión antes de empezar a cobrar.',
                        ],
                    ],
                ],
            ],
            [
                'key' => 'agenda',
                'title' => 'Agenda y citas',
                'intro' => 'Reservar, confirmar, reprogramar y cobrar las citas.',
                'modules' => ['agenda'],
                'permissions' => ['appointments.view'],
                'where' => ['web' => 'Agenda › Calendario / Citas del día / Nueva cita', 'app' => 'Agenda (vistas Día, Semana y Mes); «Nueva cita» en Acciones rápidas del menú'],
                'topics' => [
                    [
                        'title' => 'Crear una cita',
                        'steps' => [
                            ['web' => 'Entra a Agenda › Nueva cita.', 'app' => 'En el menú, toca «Nueva cita».'],
                            'Elige el cliente. Si es nuevo, puedes crearlo ahí mismo sin salir de la cita.',
                            'Elige los servicios y quién atiende.',
                            'Elige un horario libre y guarda. Solo se ofrecen los huecos dentro del horario de esa persona.',
                        ],
                    ],
                    [
                        'title' => 'Manejar una cita',
                        'steps' => [
                            'Abre la cita desde la agenda para ver su detalle.',
                            'Desde ahí puedes confirmarla con el cliente, reprogramarla, marcar que no vino o cancelarla.',
                            '«Cobrar esta cita» abre el cobro con sus servicios ya cargados; al cobrar, queda como atendida.',
                        ],
                        'note' => 'Una cita cancelada o en la que el cliente no vino libera el horario.',
                    ],
                    [
                        'title' => 'Bloquear horarios',
                        'steps' => [
                            ['web' => 'Entra a Agenda › Bloqueos.', 'app' => 'En Agenda, abre la pestaña de abajo «Bloqueos».'],
                            'Registra vacaciones, feriados o descansos: de una persona o de todos.',
                            'En esas horas no se podrán reservar citas.',
                        ],
                    ],
                ],
            ],
            [
                'key' => 'clientes',
                'title' => 'Clientes',
                'intro' => 'La ficha de cada cliente: contacto, preferencias, alergias e historial.',
                'modules' => ['clientes'],
                'permissions' => ['clients.view'],
                'where' => ['web' => 'Clientes', 'app' => 'Clientes'],
                'topics' => [
                    [
                        'title' => 'Registrar y buscar',
                        'steps' => [
                            'Busca por nombre o por teléfono.',
                            'Crea un cliente con su nombre; los demás datos son opcionales.',
                            'Anota sus preferencias y alergias: las verás al atenderlo.',
                        ],
                    ],
                ],
            ],
            [
                'key' => 'ventas',
                'title' => 'Cobrar (ventas)',
                'intro' => 'Registrar lo que se vende: servicios y productos, y cómo se pagó.',
                'modules' => ['pos'],
                'permissions' => ['sales.create', 'sales.view'],
                'where' => ['web' => 'Ventas › Nueva venta / Historial', 'app' => '«Cobrar» en Acciones rápidas del menú; historial en Ventas'],
                'topics' => [
                    [
                        'title' => 'Hacer un cobro',
                        'steps' => [
                            'Si usas caja, primero debe estar abierto el turno: sin turno no se puede cobrar.',
                            ['web' => 'Entra a Ventas › Nueva venta.', 'app' => 'En el menú, toca «Cobrar».'],
                            'Agrega los servicios y productos, y elige el cliente y quién atendió.',
                            'Ajusta la cantidad (siempre en números enteros) y, si corresponde, el descuento y la propina.',
                            'Elige el método de pago y confirma. Se genera el comprobante.',
                        ],
                    ],
                    [
                        'title' => 'Pago mixto',
                        'steps' => [
                            'Activa «Pago mixto» para cobrar con más de un método (parte en efectivo, parte por QR).',
                            'Selecciona los métodos y escribe cuánto va en cada uno; la suma debe cubrir el total.',
                        ],
                    ],
                    [
                        'title' => 'Venta rápida',
                        'steps' => [
                            'Sirve para vender un producto que no está cargado en el inventario, sin perder tiempo registrándolo.',
                            'Toca «Venta rápida» y escribe el nombre, el precio y la cantidad.',
                        ],
                        'note' => 'No descuenta stock ni tiene costo conocido, así que el reporte de Ganancias la deja aparte, salvo que actives «Unir ventas rápidas».',
                    ],
                    [
                        'title' => 'Historial y anulaciones',
                        'steps' => [
                            'Cada venta lleva la etiqueta «Servicio», «Producto» o ambas.',
                            ['web' => 'Filtra por fechas, sucursal, barbero, estado o tipo (con servicios / con productos).', 'app' => 'Filtra por Hoy, Ayer, 7 días, Mes o «Rango», para elegir las fechas.'],
                            'Abre una venta para ver su comprobante y, con permiso, anularla.',
                        ],
                        'note' => 'Anular devuelve el stock y registra la salida del dinero en la caja abierta. No se borra: queda en el historial.',
                    ],
                ],
            ],
            [
                'key' => 'caja',
                'title' => 'Caja',
                'intro' => 'El cajón del mostrador: abrir turno, registrar movimientos y cerrar con el arqueo.',
                'modules' => ['caja'],
                'permissions' => ['cash.view'],
                'where' => ['web' => 'Botón «Caja» de la barra superior; Caja › Caja actual / Movimientos / Turnos', 'app' => 'Caja; botón de estado en la barra superior'],
                'topics' => [
                    [
                        'title' => 'Cómo funciona',
                        'steps' => [
                            'Cada caja tiene su propio turno: una puede estar abierta y otra cerrada.',
                            'Primero ves la caja asignada a tu personal; si no tienes una, la primera de tu sucursal. Por eso dos usuarios pueden ver estados distintos.',
                            'Solo el efectivo está en el cajón y entra en el arqueo. QR y tarjeta se muestran aparte.',
                        ],
                    ],
                    [
                        'title' => 'Abrir el turno',
                        'steps' => [
                            ['web' => 'Toca el botón «Caja» de la barra superior: el modal muestra todas tus cajas con su estado.', 'app' => 'Entra a Caja y elige la caja.'],
                            'Escribe el fondo inicial (el efectivo con el que empieza el cajón) y toca «Abrir turno».',
                        ],
                    ],
                    [
                        'title' => 'Registrar un movimiento',
                        'steps' => [
                            'Usa «Movimiento» para dinero que entra o sale sin ser una venta (por ejemplo, cambio o un retiro).',
                            'Indica el tipo, el concepto y el importe.',
                        ],
                    ],
                    [
                        'title' => 'Cerrar el turno',
                        'steps' => [
                            'Toca «Cerrar turno» y cuenta el efectivo. Según la configuración, se cuenta el total o billete por billete.',
                            'El sistema compara lo contado con lo esperado y marca la diferencia: cuadrada, sobrante o faltante.',
                            ['web' => 'Los turnos anteriores están en Caja › Turnos.', 'app' => 'Los turnos anteriores están en «Turnos anteriores».'],
                        ],
                    ],
                ],
            ],
            [
                'key' => 'servicios',
                'title' => 'Servicios y catálogo público',
                'intro' => 'Lo que ofrece la barbería, con fotos de los cortes, y el catálogo para compartir con los clientes.',
                'permissions' => ['services.view', 'settings.view'],
                'where' => ['web' => 'Catálogo › Servicios / Categorías / Catálogo público', 'app' => 'Servicios (pestañas Servicios, Categorías y Catálogo)'],
                'topics' => [
                    [
                        'title' => 'Crear un servicio',
                        'steps' => [
                            'Indica nombre, categoría, duración y precio.',
                            'La duración es el tiempo que reserva en la agenda.',
                            'Un servicio inactivo no aparece al reservar ni al cobrar.',
                        ],
                    ],
                    [
                        'title' => 'Fotos de cortes',
                        'steps' => [
                            'Cada servicio admite hasta 30 fotos, cada una con su comentario (por ejemplo, «Corte XXTT»). Pesan hasta 2 MB cada una.',
                            ['web' => 'En «Fotos de cortes», elige varias a la vez y escribe el comentario de cada una. Ordénalas con las flechas y quítalas con la papelera.', 'app' => 'Toca «Agregar» para elegirlas de la galería o tomar una foto. Toca una foto para escribir su comentario, hacerla portada, moverla o quitarla.'],
                            'La primera foto es la portada. Los cambios se guardan al guardar el servicio.',
                        ],
                    ],
                    [
                        'title' => 'Catálogo público',
                        'steps' => [
                            'Actívalo con «Publicar el catálogo» y elige si muestra también los productos.',
                            'Comparte el enlace o imprime el código QR y el afiche.',
                            'El cliente ve los precios y puede deslizar las fotos de cada servicio y abrirlas en grande.',
                        ],
                    ],
                ],
            ],
            [
                'key' => 'inventario',
                'title' => 'Inventario',
                'intro' => 'Los productos y cuánto hay en cada almacén.',
                'modules' => ['inventario'],
                'permissions' => ['products.view', 'stock.view'],
                'where' => ['web' => 'Inventario › Existencias / Productos / Categorías / Movimientos / Almacenes', 'app' => 'Inventario'],
                'topics' => [
                    [
                        'title' => 'Productos y stock',
                        'steps' => [
                            'Crea el producto con su precio de compra y de venta, y una foto si quieres.',
                            'Define el stock mínimo: al llegar a él, aparece en «Stock bajo» del reporte de Productos.',
                            'Registra entradas, salidas o ajustes con un movimiento de stock; cada cambio queda en el historial.',
                        ],
                        'note' => 'Al vender se descuenta solo, y al registrar una compra entra solo.',
                    ],
                ],
            ],
            [
                'key' => 'compras',
                'title' => 'Compras y proveedores',
                'intro' => 'Lo que se compra para el negocio: al contado o a crédito.',
                'modules' => ['inventario', 'finanzas'],
                'permissions' => ['purchases.view', 'suppliers.view'],
                'where' => ['web' => 'Compras › Compras / Proveedores', 'app' => 'Compras (pestañas Compras y Proveedores)'],
                'topics' => [
                    [
                        'title' => 'Registrar una compra',
                        'steps' => [
                            'Elige el proveedor y el almacén, y agrega los productos con su cantidad y costo.',
                            'Al contado: elige de dónde sale el dinero (una cuenta o la caja).',
                            'A crédito: queda como deuda «Por pagar». Registra cada pago desde la compra.',
                            'El stock entra al guardar la compra.',
                        ],
                    ],
                ],
            ],
            [
                'key' => 'gastos',
                'title' => 'Gastos',
                'intro' => 'Todo lo que se paga, separado por tipo. Cada gasto sale de una cuenta o de la caja abierta.',
                'modules' => ['finanzas'],
                'permissions' => ['expenses.view'],
                'where' => ['web' => 'Finanzas › Gastos (pestañas Generales, Proveedores, Personal, Servicios básicos y Todos)', 'app' => 'Gastos (pestañas Gastos, Proveedores, Personal y Servicios)'],
                'topics' => [
                    [
                        'title' => 'Registrar un gasto',
                        'steps' => [
                            'Entra a la pestaña que corresponde y usa su botón de alta: el formulario pide lo que hace falta.',
                            'General (comida, pasajes, limpieza…): categoría y descripción.',
                            'Proveedores y Personal: eliges al proveedor o a la persona; no hace falta elegir categoría.',
                            'Servicios: eliges el servicio (luz, agua…).',
                            'Indica el importe, la fecha y de dónde sale el dinero: una cuenta o la caja abierta.',
                        ],
                        'note' => 'Un gasto no se edita: se anula y el dinero vuelve a donde salió. Luego se registra de nuevo.',
                    ],
                    [
                        'title' => 'Servicios básicos del mes',
                        'steps' => [
                            'La pestaña de servicios muestra cada servicio del mes como «Pagado» o «Pendiente».',
                            'En uno pendiente, toca «Registrar»: se abre el pago con el servicio y el último importe ya puestos.',
                            'Un servicio mensual queda pagado si hubo un pago en el mes. Uno anual (como el hosting) queda pagado por un año desde el último pago.',
                            ['web' => 'Para crear otro, usa Finanzas › Categorías de gasto, con el tipo «Servicio básico», y elige Mensual o Anual.', 'app' => 'Para crear otro, toca «Nuevo servicio» y elige Mensual o Anual.'],
                        ],
                    ],
                    [
                        'title' => 'Categorías de gasto',
                        'steps' => [
                            ['web' => 'Se administran en Finanzas › Categorías de gasto.', 'app' => 'Se administran en Finanzas › Categorías.'],
                            'El tipo decide en qué pestaña sale: Gasto general, Servicio básico, Pago a proveedor o Pago a personal.',
                            'Si una categoría ya tiene gastos, al quitarla solo se da de baja, para conservar el historial.',
                        ],
                    ],
                ],
            ],
            [
                'key' => 'cuentas',
                'title' => 'Cuentas (tesorería)',
                'intro' => 'El dinero fuera del cajón: capital, caja chica, banco, billetera digital.',
                'modules' => ['finanzas'],
                'permissions' => ['accounts.view'],
                'where' => ['web' => 'Finanzas › Cuentas', 'app' => 'Finanzas › Cuentas'],
                'topics' => [
                    [
                        'title' => 'Usar las cuentas',
                        'steps' => [
                            'Crea cada cuenta con su tipo y su saldo inicial.',
                            'Abre una cuenta para ver su saldo y su extracto.',
                            'Registra un ingreso o egreso, o transfiere dinero a otra cuenta.',
                            'Los gastos y compras pagados desde una cuenta aparecen solos en su extracto.',
                        ],
                        'note' => 'Nada se borra: anular escribe el movimiento contrario, para que el saldo y el historial siempre cuadren.',
                    ],
                ],
            ],
            [
                'key' => 'comisiones',
                'title' => 'Comisiones y liquidaciones',
                'intro' => 'La comisión es lo que gana una persona por cada servicio o venta. La liquidación es pagarle lo acumulado.',
                'modules' => ['comisiones'],
                'where' => ['web' => 'Comisiones › Resumen / Liquidaciones / Reglas; también Finanzas › Gastos › Personal', 'app' => 'Gastos › Personal para pagar; Comisiones para ver las tuyas'],
                'topics' => [
                    [
                        'title' => 'Crear las reglas',
                        'only' => 'web',
                        'steps' => [
                            'En Comisiones › Reglas, define el porcentaje o el monto fijo, para servicios o productos.',
                            'Sin reglas no se generan comisiones.',
                            'Gana siempre la regla más específica: por persona y servicio antes que la general.',
                        ],
                        'note' => 'Cambiar una regla no altera lo ya ganado: cada comisión se calcula con la regla vigente al cobrar.',
                    ],
                    [
                        'title' => 'Pagar una liquidación',
                        'steps' => [
                            ['web' => 'En Comisiones › Resumen (o en Gastos › Personal), el recuadro «Pendiente de pagar» muestra lo que se debe de cualquier fecha.', 'app' => 'En Gastos › Personal, la tarjeta «Comisiones por pagar» muestra lo que se debe de cualquier fecha.'],
                            'Toca «Pagar» en la persona.',
                            'Decide si incluyes las propinas y elige de dónde sale el dinero.',
                            'Al pagar queda un comprobante y un gasto «COMISIONES» a su nombre.',
                        ],
                    ],
                    [
                        'title' => 'Ver mis comisiones',
                        'only' => 'app',
                        'steps' => [
                            'En la sección Comisiones ves lo que llevas ganado y lo que todavía te deben, por periodo.',
                        ],
                    ],
                ],
            ],
            [
                'key' => 'reportes',
                'title' => 'Reportes',
                'intro' => 'Cómo va el negocio. Se filtran por periodo, salvo Análisis, que compara solo contra el periodo anterior.',
                'modules' => ['estadisticas'],
                'permissions' => ['reports.view'],
                'where' => ['web' => 'Reportes', 'app' => 'Reportes, abajo en el menú'],
                'topics' => [
                    [
                        'title' => 'Qué muestra cada uno',
                        'steps' => [
                            'Ganancias: lo vendido contra su costo y comisiones, separado en Ventas y Servicios, por día, por producto y por venta.',
                            'Balance: el dinero que entró y salió. Resultados (utilidad), Movimientos (caja, tesorería o todo) y Cierres de caja.',
                            'Análisis: semana y mes contra el mismo tramo anterior, tendencia de 8 semanas y 6 meses, y el top del mes.',
                            'Productos: el valor del inventario actual, la rotación y el margen.',
                            'Ventas, Servicios, Barberos y Clientes: el detalle de cada área.',
                        ],
                        'note' => 'Ganancias compara lo vendido con su costo; Balance cuenta el dinero. Una compra resta en Balance el día que se paga y en Ganancias cuando se vende.',
                    ],
                ],
            ],
            [
                'key' => 'personal',
                'title' => 'Personal y horarios',
                'intro' => 'Las personas que trabajan en el negocio y su jornada.',
                'permissions' => ['personal.view', 'schedules.view'],
                'where' => ['web' => 'Administración › Personal / Horarios / Cargos', 'app' => 'Administración › Personal'],
                'topics' => [
                    [
                        'title' => 'Personal',
                        'steps' => [
                            'Registra nombre, cargo, teléfono y sucursal.',
                            'Activa «Atiende citas» solo para quien recibe clientes en la agenda.',
                            'A un administrador o gerente se le puede asignar más de una sucursal.',
                        ],
                    ],
                    [
                        'title' => 'Horarios',
                        'steps' => [
                            ['web' => 'Entra a Administración › Horarios y elige a la persona.', 'app' => 'Abre a la persona y toca «Horario», arriba.'],
                            'Agrega una franja: elige varios días a la vez, la hora de entrada y la de salida.',
                            'Quita una franja cuando ya no corresponda. Dos franjas del mismo día no pueden pisarse.',
                        ],
                        'note' => 'Cualquiera puede tener horario, aunque no atienda citas (por ejemplo, la recepción). Para quien atiende citas, el horario define los huecos de la agenda.',
                    ],
                ],
            ],
            [
                'key' => 'configuracion',
                'title' => 'Configuración del negocio',
                'intro' => 'Los datos generales del negocio.',
                'permissions' => ['settings.view', 'branches.view', 'cajas.view'],
                'where' => ['web' => 'Administración › Mi empresa / Sucursales / Cajas / Métodos de pago', 'app' => 'Administración, abajo en el menú'],
                'topics' => [
                    [
                        'title' => 'Mi empresa',
                        'steps' => [
                            'Carga el logo, el nombre, el contacto y el pie del comprobante.',
                        ],
                    ],
                    [
                        'title' => 'Sucursales y cajas',
                        'steps' => [
                            'Crea un registro por cada local y por cada cajón.',
                            'Una caja se puede asignar a una persona: será la que vea primero.',
                        ],
                    ],
                    [
                        'title' => 'Métodos de pago',
                        'steps' => [
                            'Define cómo cobras: efectivo, QR, tarjeta, una billetera…',
                            'Marca como efectivo solo lo que entra al cajón: es lo único que se cuenta en el arqueo.',
                        ],
                    ],
                ],
            ],
        ];
    }
}

<?php
declare(strict_types=1);

/**
 * ====================================================================
 * KONTIFY APP — PRACTICE MANAGEMENT CONTROLLER
 * Subsistema de Gestión de Despacho, Rentabilidad y Blindaje Jurídico
 * Normativa: FCCPV (SEC-7), IFAC (NIA 210), VEN-NIF PYMES, Cód. Comercio VE
 * ====================================================================
 */

class DespachoController
{
    private PDO $db;
    private int $empresaId;
    private float $tasaBcvDefault;

    public function __construct(PDO $db, int $empresaId = 1, float $tasaBcvDefault = 65.50)
    {
        $this->db = $db;
        $this->empresaId = $empresaId;
        $this->tasaBcvDefault = $tasaBcvDefault;
    }

    /**
     * Calcula la rentabilidad y margen de contribución real de un cliente en un período dado.
     * Fórmula: Margen = [Honorarios Cobrados] - [Costo HH Asignado + Overhead Prorrateado + Gastos Directos]
     *
     * @param int $clienteId ID del cliente
     * @param int $mes Mes a evaluar (1-12)
     * @param int $año Año a evaluar (ej: 2026)
     * @return array Resumen financiero, desglose por jerarquía, KPIs de rentabilidad y alertas
     */
    public function calcularRentabilidadCliente(int $clienteId, int $mes, int $año): array
    {
        $mesStr = sprintf('%04d-%02d', $año, $mes);
        $fechaInicio = "{$mesStr}-01";
        $fechaFin = date('Y-m-t', strtotime($fechaInicio));

        // 1. Obtener datos del cliente
        $stmtCli = $this->db->prepare("
            SELECT id, razon_social, rif, tipo_contribuyente, honorarios_usd 
            FROM clientes 
            WHERE id = ?
        ");
        $stmtCli->execute([$clienteId]);
        $cliente = $stmtCli->fetch(PDO::FETCH_ASSOC);

        if (!$cliente) {
            throw new InvalidArgumentException("El cliente con ID {$clienteId} no existe.");
        }

        // 2. Determinar Honorarios Cobrados / Facturados en el período
        // Busca en tabla de ingresos o pagos_honorarios; si no hay registro específico toma honorarios_usd base
        $stmtIng = $this->db->prepare("
            SELECT COALESCE(SUM(monto_usd), 0) AS total_ingresos
            FROM ingresos
            WHERE cliente_id = ? 
              AND estado = 'cobrado' 
              AND fecha BETWEEN ? AND ?
        ");
        $stmtIng->execute([$clienteId, $fechaInicio, $fechaFin]);
        $totalCobrado = (float)$stmtIng->fetchColumn();

        // Si no se han registrado ingresos específicos en el mes, cotejamos con honorarios_usd contractuales
        $honorariosPactados = (float)($cliente['honorarios_usd'] ?? 0.0);
        $honorariosReales = $totalCobrado > 0 ? $totalCobrado : $honorariosPactados;

        // 3. Obtener el Overhead Base Operativo por hora para este mes
        $overheadHora = $this->calcularOverheadHoraOperativa($mes, $año);

        // 4. Detalle de Horas Invertidas por el equipo en el cliente
        $stmtHoras = $this->db->prepare("
            SELECT 
                h.id,
                h.usuario_id,
                u.name AS nombre_usuario,
                j.rol_nombre,
                j.nivel_jerarquico,
                h.fecha,
                h.horas,
                h.tipo_tarea,
                h.sujeta_contrato,
                h.es_facturable,
                h.facturado,
                h.tarifa_costo_aplicada_usd,
                h.tarifa_cobro_aplicada_usd,
                h.gastos_directos_usd,
                h.descripcion
            FROM despacho_horas h
            JOIN despacho_jerarquias j ON h.jerarquia_id = j.id
            LEFT JOIN users u ON h.usuario_id = u.id
            WHERE h.empresa_id = ?
              AND h.cliente_id = ?
              AND h.fecha BETWEEN ? AND ?
            ORDER BY h.fecha ASC
        ");
        $stmtHoras->execute([$this->empresaId, $clienteId, $fechaInicio, $fechaFin]);
        $registrosHoras = $stmtHoras->fetchAll(PDO::FETCH_ASSOC);

        // 5. Procesar acumuladores de costeo
        $totalHoras = 0.0;
        $totalHorasOrdinarias = 0.0;
        $totalHorasExtraordinarias = 0.0;
        $totalHorasFacturables = 0.0;
        $totalHorasNoFacturables = 0.0;

        $costoDirectoLaboralUSD = 0.0;
        $costoOverheadUSD = 0.0;
        $gastosDirectosUSD = 0.0;
        $valorFacturableExtraordinarioUSD = 0.0;

        $desglosePorJerarquia = [];

        foreach ($registrosHoras as $reg) {
            $h = (float)$reg['horas'];
            $costoHora = (float)$reg['tarifa_costo_aplicada_usd'];
            $cobroHora = (float)$reg['tarifa_cobro_aplicada_usd'];
            $gastoDir = (float)$reg['gastos_directos_usd'];
            $rol = $reg['rol_nombre'];

            $totalHoras += $h;
            $costoDirectoLaboralUSD += ($h * $costoHora);
            $costoOverheadUSD += ($h * $overheadHora);
            $gastosDirectosUSD += $gastoDir;

            if ($reg['tipo_tarea'] === 'Ordinaria') {
                $totalHorasOrdinarias += $h;
            } else {
                $totalHorasExtraordinarias += $h;
                if ((int)$reg['sujeta_contrato'] === 0 && (int)$reg['es_facturable'] === 1) {
                    $valorFacturableExtraordinarioUSD += ($h * $cobroHora);
                }
            }

            if ((int)$reg['es_facturable'] === 1) {
                $totalHorasFacturables += $h;
            } else {
                $totalHorasNoFacturables += $h;
            }

            if (!isset($desglosePorJerarquia[$rol])) {
                $desglosePorJerarquia[$rol] = [
                    'rol' => $rol,
                    'nivel' => (int)$reg['nivel_jerarquico'],
                    'horas_totales' => 0.0,
                    'costo_total_usd' => 0.0,
                    'cobro_potencial_usd' => 0.0
                ];
            }
            $desglosePorJerarquia[$rol]['horas_totales'] += $h;
            $desglosePorJerarquia[$rol]['costo_total_usd'] += ($h * $costoHora);
            $desglosePorJerarquia[$rol]['cobro_potencial_usd'] += ($h * $cobroHora);
        }

        // 6. Costo Operativo Total y Margen de Contribución
        $costoOperativoTotalUSD = $costoDirectoLaboralUSD + $costoOverheadUSD + $gastosDirectosUSD;
        $margenContribucionUSD = $honorariosReales - $costoOperativoTotalUSD;
        $porcentajeMargen = $honorariosReales > 0
            ? ($margenContribucionUSD / $honorariosReales) * 100
            : ($costoOperativoTotalUSD > 0 ? -100.0 : 0.0);

        // 7. Diagnóstico de Salud Financiera y Alertas de Gestión
        $diagnostico = match (true) {
            $porcentajeMargen >= 50.0 => [
                'estado' => 'Óptimo / Alta Rentabilidad',
                'badge_color' => 'emerald',
                'icono' => 'fa-arrow-trend-up',
                'mensaje' => 'La cuenta tiene un retorno excelente. El fee mensual absorbe holgadamente el costo operativo y genera flujo positivo.'
            ],
            $porcentajeMargen >= 30.0 => [
                'estado' => 'Saludable',
                'badge_color' => 'blue',
                'icono' => 'fa-check-circle',
                'mensaje' => 'Rango operativo estándar de despachos contables de alto desempeño (30% - 50%).'
            ],
            $porcentajeMargen >= 10.0 => [
                'estado' => 'Bajo Margen / En Riesgo',
                'badge_color' => 'amber',
                'icono' => 'fa-triangle-exclamation',
                'mensaje' => 'Alerta de erosión de margen. El volumen de horas invertidas está comprometiendo la rentabilidad de la firma.'
            ],
            default => [
                'estado' => 'Déficit / A Pérdida',
                'badge_color' => 'rose',
                'icono' => 'fa-circle-xmark',
                'mensaje' => '¡Atención Socio Director! El cliente genera pérdidas netas. El costo de HH y gastos directos supera los honorarios percibidos.'
            ]
        };

        // 8. Recomendaciones Accionables para el Socio / Gerente
        $recomendaciones = [];
        if ($totalHorasExtraordinarias > 0 && $valorFacturableExtraordinarioUSD > 0) {
            $recomendaciones[] = "Existen {$totalHorasExtraordinarias} horas de tareas extraordinarias no cubiertas en contrato. Emitir de inmediato anexo de facturación por \${$valorFacturableExtraordinarioUSD} USD.";
        }
        if ($totalHorasNoFacturables > ($totalHoras * 0.25)) {
            $recomendaciones[] = "El " . round(($totalHorasNoFacturables / max($totalHoras, 1)) * 100, 1) . "% de las horas no fueron facturables (reprocesos o subsanaciones). Revisar procesos del auxiliar o documentación tardía del cliente.";
        }
        if ($porcentajeMargen < 25.0) {
            $feeSugerido = round($costoOperativoTotalUSD / 0.60, 2); // Busca un 40% de margen mínimo
            $recomendaciones[] = "Reajuste contractual recomendado: Elevar honorarios mensuales a \${$feeSugerido} USD para restituir el margen objetivo del 40%.";
        }

        return [
            'periodo' => [
                'mes' => $mes,
                'año' => $año,
                'cadena' => $mesStr,
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin
            ],
            'cliente' => $cliente,
            'kpis' => [
                'honorarios_cobrados_usd' => round($honorariosReales, 2),
                'costo_directo_laboral_usd' => round($costoDirectoLaboralUSD, 2),
                'costo_overhead_prorrateado_usd' => round($costoOverheadUSD, 2),
                'gastos_directos_usd' => round($gastosDirectosUSD, 2),
                'costo_operativo_total_usd' => round($costoOperativoTotalUSD, 2),
                'margen_contribucion_usd' => round($margenContribucionUSD, 2),
                'margen_contribucion_porcentaje' => round($porcentajeMargen, 2),
                'overhead_hora_tasa_usd' => round($overheadHora, 2),
                'diagnostico' => $diagnostico
            ],
            'resumen_horas' => [
                'total_horas' => $totalHoras,
                'horas_ordinarias' => $totalHorasOrdinarias,
                'horas_extraordinarias' => $totalHorasExtraordinarias,
                'horas_facturables' => $totalHorasFacturables,
                'horas_no_facturables' => $totalHorasNoFacturables,
                'monto_extraordinario_facturable_usd' => round($valorFacturableExtraordinarioUSD, 2)
            ],
            'desglose_jerarquia' => array_values($desglosePorJerarquia),
            'detalle_registros' => $registrosHoras,
            'recomendaciones' => $recomendaciones
        ];
    }

    /**
     * Genera el reporte formal de horas facturables vs no facturables,
     * estructurado como Anexo de Detalle de Horas listo para adjuntar a la factura de honorarios.
     *
     * @param int $clienteId
     * @param array $rangoFechas ['inicio' => 'YYYY-MM-DD', 'fin' => 'YYYY-MM-DD']
     * @return array Estructura completa con encabezados, ítems listos para facturación y totales
     */
    public function generarReporteHorasFacturables(int $clienteId, array $rangoFechas): array
    {
        $inicio = $rangoFechas['inicio'] ?? date('Y-m-01');
        $fin = $rangoFechas['fin'] ?? date('Y-m-t');

        // Datos del Despacho y Cliente
        $stmtConf = $this->db->prepare("SELECT * FROM configuracion_despacho WHERE id = 1");
        $stmtConf->execute();
        $despacho = $stmtConf->fetch(PDO::FETCH_ASSOC) ?: [
            'nombre_despacho' => 'Kontify CPA Despacho Contable',
            'cpc_numero' => 'CPC-000000',
            'rif' => 'J-00000000-0'
        ];

        $stmtCli = $this->db->prepare("SELECT * FROM clientes WHERE id = ?");
        $stmtCli->execute([$clienteId]);
        $cliente = $stmtCli->fetch(PDO::FETCH_ASSOC);

        if (!$cliente) {
            throw new InvalidArgumentException("Cliente no encontrado.");
        }

        // Obtener todas las horas del período
        $stmt = $this->db->prepare("
            SELECT 
                h.id,
                h.fecha,
                h.horas,
                h.tipo_tarea,
                h.sujeta_contrato,
                h.es_facturable,
                h.facturado,
                h.tarifa_cobro_aplicada_usd,
                h.gastos_directos_usd,
                h.descripcion,
                j.rol_nombre,
                u.name AS colaborador_nombre
            FROM despacho_horas h
            JOIN despacho_jerarquias j ON h.jerarquia_id = j.id
            LEFT JOIN users u ON h.usuario_id = u.id
            WHERE h.empresa_id = ?
              AND h.cliente_id = ?
              AND h.fecha BETWEEN ? AND ?
            ORDER BY h.fecha ASC, h.id ASC
        ");
        $stmt->execute([$this->empresaId, $clienteId, $inicio, $fin]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $anexoItems = [];
        $totalHorasOrdinarias = 0.0;
        $totalHorasExtraordinarias = 0.0;
        $totalHorasFacturables = 0.0;
        $totalHorasNoFacturables = 0.0;
        $subtotalServiciosUSD = 0.0;
        $subtotalGastosReembolsablesUSD = 0.0;

        foreach ($filas as $fila) {
            $h = (float)$fila['horas'];
            $tarifa = (float)$fila['tarifa_cobro_aplicada_usd'];
            $gasto = (float)$fila['gastos_directos_usd'];
            $esFacturable = (bool)$fila['es_facturable'];
            $sujetaContrato = (bool)$fila['sujeta_contrato'];
            $subtotalFila = 0.0;

            if ($fila['tipo_tarea'] === 'Ordinaria') {
                $totalHorasOrdinarias += $h;
            } else {
                $totalHorasExtraordinarias += $h;
            }

            if ($esFacturable) {
                $totalHorasFacturables += $h;
                // Si es extraordinaria o no está sujeta a contrato mensual, se cobra
                if (!$sujetaContrato || $fila['tipo_tarea'] === 'Extraordinaria') {
                    $subtotalFila = $h * $tarifa;
                    $subtotalServiciosUSD += $subtotalFila;
                }
            } else {
                $totalHorasNoFacturables += $h;
            }

            $subtotalGastosReembolsablesUSD += $gasto;

            $anexoItems[] = [
                'id' => (int)$fila['id'],
                'fecha' => $fila['fecha'],
                'profesional' => $fila['colaborador_nombre'] ?: 'Equipo Técnico',
                'rol' => $fila['rol_nombre'],
                'descripcion' => $fila['descripcion'],
                'tipo_tarea' => $fila['tipo_tarea'],
                'condicion' => $sujetaContrato ? 'Incluida en Contrato' : 'Extraordinaria / Por Cobrar',
                'es_facturable' => $esFacturable,
                'horas' => $h,
                'tarifa_hora_usd' => $tarifa,
                'subtotal_usd' => round($subtotalFila, 2),
                'gasto_directo_usd' => round($gasto, 2),
                'estado_facturado' => (bool)$fila['facturado']
            ];
        }

        $totalGeneralUSD = $subtotalServiciosUSD + $subtotalGastosReembolsablesUSD;
        $totalGeneralBs = $totalGeneralUSD * $this->tasaBcvDefault;

        return [
            'anexo_numero' => 'ANX-' . date('Ym', strtotime($inicio)) . '-' . str_pad((string)$clienteId, 4, '0', STR_PAD_LEFT),
            'fecha_emision' => date('Y-m-d'),
            'rango' => [
                'desde' => $inicio,
                'hasta' => $fin
            ],
            'despacho' => $despacho,
            'cliente' => $cliente,
            'totales' => [
                'horas_totales' => round($totalHorasOrdinarias + $totalHorasExtraordinarias, 2),
                'horas_ordinarias_contrato' => round($totalHorasOrdinarias, 2),
                'horas_extraordinarias' => round($totalHorasExtraordinarias, 2),
                'horas_facturables' => round($totalHorasFacturables, 2),
                'horas_no_facturables' => round($totalHorasNoFacturables, 2),
                'subtotal_honorarios_adicionales_usd' => round($subtotalServiciosUSD, 2),
                'gastos_reembolsables_usd' => round($subtotalGastosReembolsablesUSD, 2),
                'total_a_facturar_usd' => round($totalGeneralUSD, 2),
                'tasa_bcv' => $this->tasaBcvDefault,
                'total_a_facturar_bs' => round($totalGeneralBs, 2)
            ],
            'items' => $anexoItems
        ];
    }

    /**
     * Calcula la tasa de Overhead Operativo por hora ($/HH) del despacho.
     * Prorratea los costos fijos mensuales (alquiler, luz, fibra, licencias, nómina administrativa)
     * entre la capacidad instalada de horas productivas del equipo contable.
     */
    public function calcularOverheadHoraOperativa(int $mes, int $año): float
    {
        // 1. Totalizar costos fijos activos mensuales
        $stmtCF = $this->db->prepare("
            SELECT COALESCE(SUM(
                CASE 
                    WHEN frecuencia = 'Mensual' THEN monto_mensual_usd
                    WHEN frecuencia = 'Trimestral' THEN (monto_mensual_usd / 3)
                    WHEN frecuencia = 'Semestral' THEN (monto_mensual_usd / 6)
                    WHEN frecuencia = 'Anual' THEN (monto_mensual_usd / 12)
                    ELSE monto_mensual_usd
                END
            ), 0) AS total_fijo_mes
            FROM despacho_costos_fijos
            WHERE empresa_id = ? AND activo = 1
        ");
        $stmtCF->execute([$this->empresaId]);
        $totalCostosFijosMes = (float)$stmtCF->fetchColumn();

        if ($totalCostosFijosMes <= 0.0) {
            return 2.50; // Tasa mínima de contingencia por defecto ($2.50/h)
        }

        // 2. Determinar capacidad instalada de horas del equipo
        // Por estándar de firmas contables: personal técnico x 160 horas laborales mensuales x 75% eficiencia
        $stmtPers = $this->db->prepare("
            SELECT COUNT(*) AS total_staff 
            FROM despacho_jerarquias 
            WHERE empresa_id = ? AND activo = 1
        ");
        $stmtPers->execute([$this->empresaId]);
        $staffCount = max((int)$stmtPers->fetchColumn(), 1);

        // Capacidad base: 120 horas billables por persona/mes
        $capacidadHorasTotales = $staffCount * 120.0;

        return round($totalCostosFijosMes / $capacidadHorasTotales, 2);
    }

    /**
     * Generador de Cartas de Encargo y Contratos con Normativa Venezolana
     * (SEC-7 FCCPV, NIA 210, Sección 23 VEN-NIF PYMES, Comisario Mercantil)
     */
    public function generarPlantillaContrato(string $tipoPlantilla, int $clienteId, array $parametros = []): array
    {
        $stmtCli = $this->db->prepare("SELECT * FROM clientes WHERE id = ?");
        $stmtCli->execute([$clienteId]);
        $cliente = $stmtCli->fetch(PDO::FETCH_ASSOC);

        if (!$cliente) {
            throw new InvalidArgumentException("Cliente inválido.");
        }

        $stmtConf = $this->db->prepare("SELECT * FROM configuracion_despacho WHERE id = 1");
        $stmtConf->execute();
        $conf = $stmtConf->fetch(PDO::FETCH_ASSOC) ?: [];

        $nombreFirma = $conf['nombre_despacho'] ?? 'Nexus & Asociados Contadores Públicos';
        $cpcFirma = $conf['cpc_numero'] ?? 'CPC-123456';
        $rifFirma = $conf['rif'] ?? 'J-12345678-0';
        $fechaHoy = date('d/m/Y');
        $honorariosUSD = number_format((float)($parametros['honorarios_usd'] ?? $cliente['honorarios_usd'] ?? 150), 2);
        $horasIncluidas = $parametros['horas_incluidas'] ?? '25';
        $tarifaExcedente = $parametros['tarifa_excedente_usd'] ?? '35.00';

        $contenido = '';
        $titulo = '';
        $normativa = '';

        switch ($tipoPlantilla) {
            case 'SEC_7_RECURRENTE':
                $titulo = "CONTRATO DE PRESTACIÓN DE SERVICIOS CONTABLES Y FISCALES RECURRENTES";
                $normativa = "Declaración de Normas y Procedimientos de Auditoría N° 7 (SEC-7) - FCCPV";
                $contenido = <<<TEXT
ENTRE: Por una parte, {$nombreFirma}, inscrita bajo el RIF {$rifFirma}, representada en este acto por su Socio Director, Lcdo. [Nombre del Contador], CPC N° {$cpcFirma}, en adelante denominada "EL DESPACHO"; y por la otra, {$cliente['razon_social']}, RIF {$cliente['rif']}, representada por su Administrador(a) en funciones, en adelante denominada "EL CLIENTE". Se ha convenido celebrar el presente contrato sujeto a las siguientes cláusulas conforme a la normativa profesional SEC-7 de la FCCPV:

PRIMERA (OBJETO Y ALCANCE): EL DESPACHO prestará servicios de compilación contable, revisión de libros fiscales de compras y ventas de conformidad con la Ley de IVA, preparación y presentación de declaraciones tributarias quincenales/mensuales en el portal del SENIAT, y emisión del balance de comprobación mensual bajo VEN-NIF PYMES.
SEGUNDA (DESLINDE DE RESPONSABILIDAD PENAL Y TRIBUTARIA - SEC 7): Conforme a la Norma SEC-7, los servicios prestados no constituyen una auditoría de estados financieros. EL CLIENTE es el único y exclusivo responsable legal de la exactitud, legitimidad y procedencia de las facturas, soportes, deducciones y documentación fiscal suministrada. EL DESPACHO no asume responsabilidad patrimonial ni penal por defraudación, sanciones tributarias o multas causadas por omisión, demora en la entrega de soportes o falsedad imputable a EL CLIENTE.
TERCERA (HONORARIOS Y HORAS BASE): EL CLIENTE conviene pagar la cantidad de {$honorariosUSD} USD mensuales (pagaderos al tipo de cambio oficial publicado por el BCV a la fecha valor del pago). El abono cubre hasta {$horasIncluidas} horas-hombre operativas mensuales. Cualquier requerimiento extraordinario (inspecciones del SENIAT, reconstrucción de libros anteriores, certificaciones especiales) se facturará a razón de \${$tarifaExcedente} USD por hora adicional según anexo de horas registrado en la plataforma Kontify.
CUARTA (OBLIGACIONES DEL CLIENTE): Entregar los soportes y estados de cuenta bancarios dentro de los primeros cinco (5) días hábiles posteriores al cierre de cada quincena fiscal. El incumplimiento exime a EL DESPACHO de los recargos por presentación extemporánea.
QUINTA (RESCISIÓN): Cualquiera de las partes podrá dar por terminado el contrato mediante notificación escrita con treinta (30) días de anticipación, previa cancelación total de los honorarios devengados hasta la fecha.
TEXT;
                break;

            case 'NIA_210_AUDITORIA':
                $titulo = "CARTA DE ENCARGO DE AUDITORÍA DE ESTADOS FINANCIEROS (BAJO NIA 210)";
                $normativa = "Norma Internacional de Auditoría 210 (NIA 210) - IFAC / FCCPV";
                $contenido = <<<TEXT
A la Junta Directiva y Accionistas de: {$cliente['razon_social']} (RIF: {$cliente['rif']})
Fecha: {$fechaHoy}

De nuestra mayor consideración:
Ustedes nos han solicitado que auditemos los estados financieros de {$cliente['razon_social']}, que comprenden el estado de situación financiera al 31 de diciembre, el estado de resultados integrales, el estado de cambios en el patrimonio y el estado de flujos de efectivo correspondientes al ejercicio económico finalizado en dicha fecha, así como las notas explicativas que resumen las políticas contables significativas preparadas de conformidad con VEN-NIF PYMES.

Nos complace confirmarles mediante esta carta de encargo que aceptamos dicho encargo de auditoría bajo las siguientes condiciones:
1. RESPONSABILIDAD DE LOS AUDITORES: Efectuaremos nuestra auditoría de conformidad con las Normas Internacionales de Auditoría (NIA). Dichas normas exigen que cumplamos con los requerimientos de ética y que planifiquemos y ejecutemos la auditoría con el fin de obtener una seguridad razonable de que los estados financieros están libres de incorrección material debida a fraude o error.
2. LIMITACIONES INHERENTES: Debido a las limitaciones inherentes a la auditoría y al control interno, existe un riesgo inevitable de que no se detecten algunas incorrecciones materiales, aun cuando la auditoría se haya planificado y ejecutado de conformidad con las NIA.
3. RESPONSABILIDAD DE LA DIRECCIÓN: Nuestra auditoría se realizará sobre la base de que la dirección y el gobierno corporativo de {$cliente['razon_social']} reconocen y comprenden sus responsabilidades relativas a:
   a) La preparación y presentación fiel de los estados financieros con arreglo a VEN-NIF.
   b) El diseño, implementación y mantenimiento del control interno necesario para la prevención de fraudes y errores.
   c) Proporcionarnos acceso irrestricto a toda la información, libros societarios, registros y correspondencia fiscal.
4. HONORARIOS: Los honorarios profesionales por el encargo de auditoría se estiman en \${$honorariosUSD} USD, devengados bajo el avance del trabajo de campo e informes preliminares.
TEXT;
                break;

            case 'NIIF_PYMES_SECCION23':
                $titulo = "CONTRATO DE PRESTACIÓN DE SERVICIOS CON MODELO DE RECONOCIMIENTO DE INGRESOS (SECCIÓN 23 VEN-NIF PYMES)";
                $normativa = "VEN-NIF PYMES Sección 23 (Ingresos de Actividades Ordinarias) / NIIF 15 Principios";
                $contenido = <<<TEXT
CONTRATO OPERATIVO DE SERVICIOS PROFESIONALES CON IDENTIFICACIÓN DE OBLIGACIONES DE DESEMPEÑO
Entre {$nombreFirma} (EL PROVEEDOR) y {$cliente['razon_social']} (EL ADQUIRIENTE).

Las partes acuerdan estructurar el presente contrato bajo las directrices de reconocimiento de ingresos de la Sección 23 de VEN-NIF PYMES conforme a las etapas operativas:
PASO 1. IDENTIFICACIÓN DEL CONTRATO: El presente acuerdo formaliza el alcance convenido con aprobación comercial y compromisos de pago bilaterales.
PASO 2. IDENTIFICACIÓN DE OBLIGACIONES DE DESEMPEÑO SEPARADAS:
   a) Obligación A (Recurrente): Cumplimiento tributario quincenal (IVA, Retenciones ISLR y libros de compra/venta). Satisfacción a lo largo del tiempo.
   b) Obligación B (Hito Específico): Conciliación anual de rentas y emisión de Balance General con notas VEN-NIF. Satisfacción en un momento determinado.
PASO 3. DETERMINACIÓN DEL PRECIO DE LA TRANSACCIÓN: Los honorarios se fijan en \${$honorariosUSD} USD más los impuestos y aranceles a que hubiere lugar.
PASO 4. ASIGNACIÓN DEL PRECIO: 70% imputable al servicio tributario continuo mensual; 30% imputable a la entrega del dictamen financiero anual.
PASO 5. RECONOCIMIENTO DEL INGRESO: Se reconocerá conforme al método de grado de avance o insumo (horas-hombre incurridas registradas en el módulo de Practice Management).
TEXT;
                break;

            case 'COMISARIO_MERCANTIL':
                $titulo = "CARTA DE ACEPTACIÓN DEL CARGO DE COMISARIO MERCANTIL";
                $normativa = "Código de Comercio de la República Bolivariana de Venezuela (Artículos 287, 309 al 311)";
                $contenido = <<<TEXT
Ciudadanos Accionistas de:
{$cliente['razon_social']}
Presente.-

Yo, [Nombre del Contador Colegiado], Contador Público Colegiado bajo el N° CPC {$cpcFirma}, titular de la Cédula de Identidad N° V-[Número], por medio de la presente hago constar mi formal ACEPTACIÓN a la designación como COMISARIO PRINCIPAL de la sociedad mercantil {$cliente['razon_social']}, RIF {$cliente['rif']}, designación efectuada por la Asamblea General de Accionistas celebrada en fecha [Fecha de Asamblea].

A tales efectos, declaro bajo fe de juramento:
1. No tener impedimento legal, incompatibilidad ni vinculación de parentesco dentro del cuarto grado de consanguinidad o segundo de afinidad con los Administradores de la compañía, de conformidad con el Artículo 287 del Código de Comercio de Venezuela.
2. Conocer las atribuciones, deberes y responsabilidades inherentes al cargo estipuladas en el Artículo 309 y subsiguientes del Código de Comercio, comprometiéndome a revisar los libros societarios, contables y comprobantes, y a emitir el correspondiente INFORME DEL COMISARIO MERCANTIL con dictamen sobre el Balance General y el Estado de Resultados para la Asamblea Ordinaria Anual.
3. Se fijan como honorarios por la función de supervisión y emisión del dictamen anual la cantidad de \${$honorariosUSD} USD o su equivalente en bolívares a la tasa oficial del Banco Central de Venezuela.

En Caracas, a la fecha de su presentación.
________________________________________
Lcdo. [Nombre del Contador]
Contador Público Colegiado (CPC-{$cpcFirma})
TEXT;
                break;

            default:
                throw new InvalidArgumentException("Tipo de plantilla no soportada: {$tipoPlantilla}");
        }

        return [
            'tipo_plantilla' => $tipoPlantilla,
            'titulo' => $titulo,
            'normativa_referencia' => $normativa,
            'cliente' => $cliente,
            'fecha_generacion' => $fechaHoy,
            'contenido_contrato' => $contenido
        ];
    }

    /**
     * Registra un bloque de tiempo (desde el cronómetro o ingreso manual)
     * calculando y almacenando los snapshots de costo y cobro del rol.
     */
    public function registrarTiempo(array $datos): int
    {
        $clienteId = (int)($datos['cliente_id'] ?? 0);
        $usuarioId = (int)($datos['usuario_id'] ?? 1);
        $jerarquiaId = (int)($datos['jerarquia_id'] ?? 1);
        $horas = (float)($datos['horas'] ?? 1.0);
        $tipoTarea = in_array($datos['tipo_tarea'] ?? '', ['Ordinaria', 'Extraordinaria'], true) ? $datos['tipo_tarea'] : 'Ordinaria';
        $sujetaContrato = isset($datos['sujeta_contrato']) ? (int)$datos['sujeta_contrato'] : 1;
        $esFacturable = isset($datos['es_facturable']) ? (int)$datos['es_facturable'] : 1;
        $fecha = !empty($datos['fecha']) ? $datos['fecha'] : date('Y-m-d');
        $descripcion = trim($datos['descripcion'] ?? 'Gestión contable operativa');
        $gastosDirectos = (float)($datos['gastos_directos_usd'] ?? 0.0);
        $origen = in_array($datos['origen_registro'] ?? '', ['Cronometro', 'Manual'], true) ? $datos['origen_registro'] : 'Manual';

        if ($clienteId <= 0 || $horas <= 0.0) {
            throw new InvalidArgumentException("Cliente u horas inválidas.");
        }

        // Obtener tarifas vigentes de la jerarquía para el snapshot
        $stmtJ = $this->db->prepare("SELECT tarifa_costo_hora_usd, tarifa_cobro_hora_usd FROM despacho_jerarquias WHERE id = ?");
        $stmtJ->execute([$jerarquiaId]);
        $jer = $stmtJ->fetch(PDO::FETCH_ASSOC);

        $tarifaCosto = (float)($jer['tarifa_costo_hora_usd'] ?? 4.50);
        $tarifaCobro = (float)($jer['tarifa_cobro_hora_usd'] ?? 15.00);

        // Overhead actual
        $mes = (int)date('m', strtotime($fecha));
        $año = (int)date('Y', strtotime($fecha));
        $overheadHora = $this->calcularOverheadHoraOperativa($mes, $año);

        $stmt = $this->db->prepare("
            INSERT INTO despacho_horas (
                empresa_id, cliente_id, usuario_id, jerarquia_id, fecha,
                horas, tipo_tarea, sujeta_contrato, es_facturable, facturado,
                tarifa_costo_aplicada_usd, tarifa_cobro_aplicada_usd, costo_overhead_aplicado_usd,
                gastos_directos_usd, descripcion, origen_registro
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, 0,
                ?, ?, ?,
                ?, ?, ?
            )
        ");

        $stmt->execute([
            $this->empresaId,
            $clienteId,
            $usuarioId,
            $jerarquiaId,
            $fecha,
            $horas,
            $tipoTarea,
            $sujetaContrato,
            $esFacturable,
            $tarifaCosto,
            $tarifaCobro,
            $overheadHora,
            $gastosDirectos,
            $descripcion,
            $origen
        ]);

        return (int)$this->db->lastInsertId();
    }
}

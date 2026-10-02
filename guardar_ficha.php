<?php
require_once 'config.php';

// Seguridad: Solo usuarios logueados pueden guardar clientes
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['status' => 'error', 'message' => 'No autorizado. Inicie sesión.']));
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $usuario_id = $_SESSION['user_id']; // Extraemos al contador dueño de esta ficha
        
        // Conversión de datos
        $servicios = isset($_POST['servicios']) ? json_encode($_POST['servicios']) : '[]';
        $agrega_valor = isset($_POST['agrega_valor']) ? 1 : 0;
        $es_foraneo = isset($_POST['es_foraneo']) ? 1 : 0;
        $afiliado_rrss = isset($_POST['afiliado_rrss']) ? 1 : 0;

        $dedicacion_horas = !empty($_POST['dedicacion_horas']) ? $_POST['dedicacion_horas'] : 0;
        $dedicacion_dias_sem = !empty($_POST['dedicacion_dias_sem']) ? $_POST['dedicacion_dias_sem'] : 0;
        $dedicacion_dias_mes = !empty($_POST['dedicacion_dias_mes']) ? $_POST['dedicacion_dias_mes'] : 0;
        $antiguedad_dias = !empty($_POST['antiguedad_dias']) ? $_POST['antiguedad_dias'] : 0;
        $perdida_valor = !empty($_POST['perdida_valor']) ? $_POST['perdida_valor'] : 0.00;

        $sql = "INSERT INTO clientes_ficha (
            usuario_id, nombre_razon_social, actividad_mercantil, condicion_laboral, tipo_agente, estatus_cliente, 
            dedicacion_horas, dedicacion_dias_sem, dedicacion_dias_mes, servicios_prestados, 
            agrega_valor, es_foraneo, afiliado_rrss, antiguedad_dias, perdida_valor
        ) VALUES (
            :uid, :nombre, :actividad, :laboral, :agente, :estatus, 
            :horas, :dias_sem, :dias_mes, :servicios, 
            :agrega, :foraneo, :rrss, :antiguedad, :perdida
        )";

        $stmt = $pdo->prepare($sql);
        
        $stmt->execute([
            ':uid' => $usuario_id, // Vinculación SaaS
            ':nombre' => $_POST['nombre_razon_social'] ?? 'Cliente Sin Nombre',
            ':actividad' => $_POST['actividad_mercantil'] ?? '',
            ':laboral' => $_POST['condicion_laboral'] ?? '',
            ':agente' => $_POST['tipo_agente'] ?? '',
            ':estatus' => $_POST['estatus_cliente'] ?? '',
            ':horas' => $dedicacion_horas,
            ':dias_sem' => $dedicacion_dias_sem,
            ':dias_mes' => $dedicacion_dias_mes,
            ':servicios' => $servicios,
            ':agrega' => $agrega_valor,
            ':foraneo' => $es_foraneo,
            ':rrss' => $afiliado_rrss,
            ':antiguedad' => $antiguedad_dias,
            ':perdida' => $perdida_valor
        ]);

        // Arquitectura Híbrida: Responder según el cliente (Web vs APK)
        if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            // Respuesta para la APK
            echo json_encode(['status' => 'success', 'message' => 'Ficha creada', 'id' => $pdo->lastInsertId()]);
        } else {
            // Respuesta para la Web
            header("Location: ficha_cliente.php?status=success");
        }
        exit;

    } catch (PDOException $e) {
        if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        } else {
            die("Error de Base de Datos: " . $e->getMessage());
        }
    }
}
?>
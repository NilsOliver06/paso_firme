<?php
// ==============================================================================
// ARCHIVO: includes/conexion.php
// PROYECTO: PasoFirme - Sistema de Calzados
// MATERIA: Tecnología Web I
// DESCRIPCIÓN: Conexión procedural a la base de datos MySQL usando mysqli.
// ==============================================================================

// 1. Definición de los parámetros de conexión al servidor de base de datos
$servidor   = "localhost";      // Dirección del servidor MySQL (local en Laragon)
$usuario    = "root";           // Usuario administrador por defecto de MySQL
$password   = "";               // Contraseña vacía (configuración predeterminada en Laragon)
$base_datos = "paso_firme_db";   // Nombre de la base de datos del sistema

// 2. Bloque de conexión con control y manejo de errores (try - catch)
try {
    // Configura mysqli para que lance excepciones automáticas si ocurre un error (estándar en PHP 8.1+)
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    // Función procedural para abrir una nueva conexión al servidor MySQL
    $conexion = mysqli_connect($servidor, $usuario, $password, $base_datos);

    // Verificación manual adicional del estado de la conexión mediante mysqli_connect_error()
    if (!$conexion) {
        throw new Exception("Error al conectar: " . mysqli_connect_error());
    }

    // Establece el conjunto de caracteres a utf8mb4 para admitir tildes, 'ñ' y caracteres especiales
    mysqli_set_charset($conexion, "utf8mb4");

    // NOTA PARA LA EXPOSICIÓN:
    // La variable $conexion queda disponible globalmente para ser incluida en otros archivos
    // mediante: require_once 'includes/conexion.php'; o require_once '../includes/conexion.php';

} catch (mysqli_sql_exception $e) {
    // Captura errores específicos arrojados por el controlador mysqli (ej. servidor apagado, BD no existe)
    die("<h3>[Error de Conexión MySQL]</h3> No se pudo conectar a la base de datos: " . $e->getMessage());

} catch (Exception $e) {
    // Captura cualquier otra excepción general durante el proceso
    die("<h3>[Error General]</h3> Ocurrió un error inesperado: " . $e->getMessage());
}

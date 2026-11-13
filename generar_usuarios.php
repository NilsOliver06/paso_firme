<?php
// ==============================================================================
// ARCHIVO: generar_usuarios.php
// DESCRIPCIÓN: Script inicializador (seeder) para limpiar la tabla 'usuarios',
//              asegurar los roles base e insertar un Administrador y un Vendedor
//              con contraseñas encriptadas mediante bcrypt.
// ==============================================================================

// 1. Incluimos el archivo de conexión a la base de datos (nos provee $conexion)
require_once 'includes/conexion.php';

try {
    // 2. Nos aseguramos de que existan los roles 1 (Administrador) y 2 (Vendedor)
    //    usando INSERT IGNORE por si la tabla 'roles' aún estuviera vacía.
    mysqli_query($conexion, "INSERT IGNORE INTO roles (id_rol, nombre_rol) VALUES (1, 'Administrador'), (2, 'Vendedor')");

    // 3. Eliminamos todos los registros actuales de la tabla 'usuarios'.
    //    Usamos DELETE en lugar de TRUNCATE porque 'usuarios' es referenciada por
    //    llaves foráneas (en 'ventas' y 'arqueo_caja'), y MySQL bloquea TRUNCATE en ese caso.
    mysqli_query($conexion, "DELETE FROM usuarios");

    // Reiniciamos el contador AUTO_INCREMENT a 1 para que los IDs comiencen limpios
    mysqli_query($conexion, "ALTER TABLE usuarios AUTO_INCREMENT = 1");

    // 4. Encriptamos la contraseña '123456' usando el algoritmo BCRYPT
    $password_hash = password_hash('123456', PASSWORD_BCRYPT);

    // 5. Preparamos la sentencia INSERT para registrar usuarios de forma segura
    $sql = "INSERT INTO usuarios (nombres, correo, password, id_rol) VALUES (?, ?, ?, ?)";
    $stmt = mysqli_prepare($conexion, $sql);

    // --------------------------------------------------------------------------
    // 6. Primera inserción: Usuario Administrador (id_rol: 1)
    // --------------------------------------------------------------------------
    $nombres_admin = 'Armando Mendoza';
    $correo_admin  = 'admin@pasofirme.com';
    $rol_admin     = 1;

    // "sssi" indica los tipos de datos: string, string, string, integer
    mysqli_stmt_bind_param($stmt, "sssi", $nombres_admin, $correo_admin, $password_hash, $rol_admin);
    mysqli_stmt_execute($stmt);

    // --------------------------------------------------------------------------
    // 7. Segunda inserción: Usuario Vendedor (id_rol: 2)
    // --------------------------------------------------------------------------
    $nombres_vendedor = 'Nils Oliver';
    $correo_vendedor  = 'ventas@pasofirme.com';
    $rol_vendedor     = 2;

    mysqli_stmt_bind_param($stmt, "sssi", $nombres_vendedor, $correo_vendedor, $password_hash, $rol_vendedor);
    mysqli_stmt_execute($stmt);

    // 8. Cerramos la sentencia preparada para liberar recursos
    mysqli_stmt_close($stmt);

    // 9. Imprimimos el mensaje de confirmación en pantalla
    echo "Usuarios generados correctamente";

} catch (mysqli_sql_exception $e) {
    die("Error al generar los usuarios: " . $e->getMessage());
}

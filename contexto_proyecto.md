# Contexto del Proyecto: PasoFirme - Sistema de Calzados
**Materia:** Tecnología Web I
**Stack Tecnológico:** PHP (Procedural con mysqli), MySQL/MariaDB (phpMyAdmin / Laragon), Tailwind CSS.
**Base de Datos:** `paso_firme_db` (Tablas: roles, usuarios, clientes, categorias, modelos, inventario, arqueo_caja, ventas, detalle_venta, configuracion).

## Script de creación de la base de datos (Actualizado al Sprint 4)
```sql
-- 1. Crear la base de datos y seleccionarla
CREATE DATABASE IF NOT EXISTS paso_firme_db CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci;
USE paso_firme_db;

-- 2. Tabla de Roles (Administrador, Vendedor)
CREATE TABLE roles (
    id_rol INT AUTO_INCREMENT PRIMARY KEY,
    nombre_rol VARCHAR(50) NOT NULL UNIQUE
);

-- 3. Tabla de Usuarios (Personal de la tienda)
CREATE TABLE usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    ci VARCHAR(20) NULL UNIQUE,          -- Carnet de identidad (solo números)
    nombres VARCHAR(100) NOT NULL,
    correo VARCHAR(100) NOT NULL UNIQUE, -- Generado automáticamente: inicial_rol.ci@pasofirme.com
    password VARCHAR(255) NOT NULL,
    id_rol INT NOT NULL,
    estado_activo TINYINT(1) NOT NULL DEFAULT 1, -- 1 = Activo, 0 = Inactivo (baja lógica)
    FOREIGN KEY (id_rol) REFERENCES roles(id_rol) ON DELETE RESTRICT ON UPDATE CASCADE
);

-- 4. Tabla de Clientes (Opcional para ventas)
CREATE TABLE clientes (
    id_cliente INT AUTO_INCREMENT PRIMARY KEY,
    nombre_completo VARCHAR(150) NOT NULL,
    telefono VARCHAR(20) NULL,
    nit_ci VARCHAR(30) NULL
);

-- 5. Tabla de Categorías (Deportiva, Casual, Escolar, etc.)
CREATE TABLE categorias (
    id_categoria INT AUTO_INCREMENT PRIMARY KEY,
    nombre_categoria VARCHAR(100) NOT NULL,
    publico VARCHAR(50) NOT NULL -- Damas, Varones, Niños
);

-- 6. Tabla de Modelos (Catálogo Base)
CREATE TABLE modelos (
    id_modelo INT AUTO_INCREMENT PRIMARY KEY,
    codigo_modelo VARCHAR(50) NOT NULL UNIQUE,
    nombre VARCHAR(150) NOT NULL,
    precio_venta DECIMAL(10,2) NOT NULL, -- Precio base para cálculos
    url_imagen VARCHAR(255) NULL,        -- Enlace a la fotografía del calzado
    estado_activo TINYINT(1) DEFAULT 1,  -- 1 = Activo, 0 = Oculto/Descontinuado
    id_categoria INT NOT NULL,
    FOREIGN KEY (id_categoria) REFERENCES categorias(id_categoria) ON DELETE RESTRICT ON UPDATE CASCADE
);

-- 7. Tabla de Inventario (Control detallado por Talla y Color)
CREATE TABLE inventario (
    id_inventario INT AUTO_INCREMENT PRIMARY KEY,
    id_modelo INT NOT NULL,
    talla VARCHAR(10) NOT NULL,          -- Solo números positivos
    color VARCHAR(50) NOT NULL,          -- Solo letras, espacios y "/"
    stock_actual INT NOT NULL CHECK (stock_actual >= 0),
    estado_activo TINYINT(1) DEFAULT 1,  -- 1 = Activo, 0 = Defectuoso/Baja
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_modelo) REFERENCES modelos(id_modelo) ON DELETE CASCADE ON UPDATE CASCADE
);

-- 8. Tabla de Arqueo de Caja (Control de turnos y cierre administrativo)
CREATE TABLE arqueo_caja (
    id_arqueo INT AUTO_INCREMENT PRIMARY KEY,
    estado ENUM('ABIERTA', 'CERRADA') NOT NULL DEFAULT 'ABIERTA',
    fecha DATE NOT NULL,
    fecha_apertura DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_cierre DATETIME NULL,
    monto_apertura DECIMAL(10,2) NOT NULL,
    monto_sistema DECIMAL(10,2) DEFAULT 0.00, -- Apertura + ventas en efectivo
    monto_fisico DECIMAL(10,2) DEFAULT 0.00,  -- Dinero real contado al cierre
    descuadre DECIMAL(10,2) DEFAULT 0.00,     -- monto_fisico - monto_sistema
    id_usuario INT NOT NULL,                  -- Usuario responsable del turno
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT ON UPDATE CASCADE
);

-- 9. Tabla de Ventas (Comprobantes digitales - Inmutables por auditoría)
CREATE TABLE ventas (
    id_venta INT AUTO_INCREMENT PRIMARY KEY,
    fecha_hora DATETIME DEFAULT CURRENT_TIMESTAMP,
    total DECIMAL(10,2) NOT NULL,
    metodo_pago VARCHAR(50) NOT NULL,    -- Efectivo, Transferencia QR
    nro_comprobante VARCHAR(100) NULL,   -- Opcional para pagos QR
    id_usuario INT NOT NULL,             -- Vendedor/Admin que procesó la venta
    id_cliente INT NULL,                 -- NULL si es cliente al paso
    id_arqueo INT NULL,                  -- Turno de caja activo al momento de la venta
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (id_cliente) REFERENCES clientes(id_cliente) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (id_arqueo) REFERENCES arqueo_caja(id_arqueo) ON DELETE RESTRICT ON UPDATE CASCADE
);

-- 10. Tabla de Detalle de Venta (Líneas del comprobante)
CREATE TABLE detalle_venta (
    id_detalle INT AUTO_INCREMENT PRIMARY KEY,
    id_venta INT NOT NULL,
    id_inventario INT NOT NULL,
    cantidad INT NOT NULL CHECK (cantidad > 0),
    precio_unitario DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (id_venta) REFERENCES ventas(id_venta) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_inventario) REFERENCES inventario(id_inventario) ON DELETE RESTRICT ON UPDATE CASCADE
);

-- 11. Tabla de Configuración (URL del QR de pago editable solo por Admin)
CREATE TABLE configuracion (
    clave VARCHAR(50) PRIMARY KEY,
    valor TEXT NOT NULL
);
```

## Reglas de Negocio y Desarrollo

### Generación Automática de Correo Institucional
- **Formato:** `letra_inicial_del_rol.ci@pasofirme.com` en minúsculas (ej. `a.1234567@pasofirme.com` para Administrador o `v.1234567@pasofirme.com` para Vendedor).
- El campo `ci` solo acepta números enteros.
- El campo `correo` se genera en tiempo real vía JavaScript como previsualización de solo lectura y se valida/recalcula estrictamente en el backend en PHP antes del `INSERT` o `UPDATE`.
- Tanto el `ci` como el `correo` son únicos en la tabla `usuarios`.

### Políticas de Caja y Ventas
- Para realizar una venta en `ventas/crear.php`, es obligatorio que exista un turno activo con `estado = 'ABIERTA'` en `arqueo_caja`.
- Las ventas registradas son inmutables por políticas de auditoría (no se permite edición ni eliminación).
- Al eliminar un cliente, su histórico de ventas se conserva gracias a la restricción `ON DELETE SET NULL`.

### Baja Lógica de Usuarios
- Para preservar la integridad referencial y auditoría histórica de ventas y arqueos de caja, los usuarios no se eliminan físicamente de la base de datos (`usuarios/eliminar.php`).
- Se utiliza el campo `estado_activo` (`1 = Activo`, `0 = Inactivo`), permitiendo alternar el estado del usuario sin romper los comprobantes emitidos ni los turnos de caja registrados.
- Un administrador no puede desactivar su propia cuenta en sesión activa.

## Estado de Sprints

- **Sprint 1 (Completado):** Configuración de entorno, conexión a `paso_firme_db`, autenticación de usuarios (`login.php`, `auth.php`), control de sesiones y roles (`Administrador` y `Vendedor`).
- **Sprint 2 (Completado):** Módulo de inventario con validaciones por talla y color, catálogo visual de modelos de calzado con CRUD, gestión de usuarios (`usuarios/`).
- **Sprint 3 (Completado):** Control y arqueo de caja por turnos (`caja/`), punto de venta transaccional con control de stock y soporte para transferencias QR con comprobante configurable (`ventas/crear.php`), historial inmutable de ventas (`ventas/index.php`) y recibo formal imprimible (`ventas/recibo.php`).
- **Sprint 4 (Completado):** CRUD completo de Clientes (`clientes/`), generación automática de correos institucionales en `usuarios/` a partir del CI y rol (`letra_inicial_del_rol.ci@pasofirme.com`), implementación de baja lógica de usuarios (`estado_activo`) para salvaguardar la auditoría, módulo gerencial de Balance General y Reportes (`reportes/index.php`) con resumen financiero imprimible en formato formal (KPIs, desglose efectivo/QR, arqueos de caja, top modelos vendidos, ventas detalladas por vendedor y alertas de reposición de stock bajo/agotado), y rediseño integral del Dashboard (`index.php`) con estética minimalista estilo Nike (hero banner institucional, indicadores KPIs en tiempo real y tarjetas de acceso Bento con SVG).

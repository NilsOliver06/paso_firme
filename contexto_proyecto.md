# Contexto del Proyecto: PasoFirme - Sistema de Calzados
**Materia:** Tecnología Web I
**Stack Tecnológico:** PHP (Procedural con mysqli), MySQL/MariaDB (phpMyAdmin / Laragon), Tailwind CSS.
**Base de Datos:** `paso_firme_db` (Tablas: roles, usuarios, clientes, categorias, modelos, inventario, arqueo_caja, ventas, detalle_venta, configuracion).

## Script de creación de la base de datos (Actualizado al Sprint 3)
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
    nombres VARCHAR(100) NOT NULL,
    correo VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    id_rol INT NOT NULL,
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
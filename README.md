# PasoFirme - Sistema de Gestión de Calzados

Proyecto académico desarrollado para la materia de **Tecnología Web I** de la Universidad Privada Domingo Savio (**UPDS - Cochabamba**).

---

## 📝 Descripción del Proyecto

**PasoFirme** es una aplicación web diseñada para automatizar y centralizar la operativa comercial de una tienda minorista de calzados en Cochabamba: control de inventarios detallado por tallas y colores, catálogo visual de modelos, gestión de personal con correos institucionales automatizados, control y arqueo de turnos de caja, punto de venta ágil con soporte para pagos en efectivo y transferencias QR, emisión de comprobantes digitales de venta y administración integral de clientes.

Su interfaz sigue una línea estética moderna y minimalista inspirada en el diseño de **Nike** (tonos monocromáticos, tipografía legible y componentes responsivos).

---

## 🛠️ Stack Tecnológico

- **Backend:** PHP 8.x (Enfoque Procedural con sentencias preparadas `mysqli`).
- **Base de Datos:** MySQL / MariaDB (`paso_firme_db`).
- **Frontend:** HTML5, CSS3, Tailwind CSS (Vía CDN) y JavaScript vainilla.
- **Entorno Local:** Laragon (Apache, MySQL, PHP).

---

## 👥 Equipo de Desarrollo (Grupo 5)

- Nils Oliver Machaca Mamani
- Gabriel Fidel Thokokary Jaldin
- Jorge Mercado Calcina
- Santiago Castellon Arcos

---

## 🚀 Estado de Avances del Proyecto (Sprints 1 al 4)

### ✅ Sprint 1: Autenticación, Sesiones y Control de Acceso
- **Base de Datos:** Creación del esquema inicial de `paso_firme_db` con tablas `roles` y `usuarios`.
- **Conexión Segura:** Módulo `includes/conexion.php` centralizado con manejo de excepciones y codificación UTF-8 (`utf8mb4`).
- **Inicio de Sesión:** Pantalla `login.php` con validación de credenciales y hashing seguro de contraseñas con `bcrypt` (`auth.php`).
- **Control de Acceso (RBAC):** Redirección y restricción de navegación según rol (`1: Administrador`, `2: Vendedor`) con cierre de sesión en `logout.php`.

### ✅ Sprint 2: Catálogo de Modelos, Inventario y Gestión de Usuarios
- **Inventario Detallado (`inventario/`):** Control de existencias por modelo, talla (solo valores numéricos válidos) y color (letras, espacios y diagonales), cálculo automático de stock y bajas lógicas.
- **Catálogo de Modelos (`modelos/`):** CRUD completo con código único de calzado, nombre, categoría/público, precio unitario de venta y URL de imagen fotográfica.
- **Gestión de Personal (`usuarios/`):** CRUD para el Administrador para crear, listar, editar contraseñas y desactivar cuentas de vendedores.

### ✅ Sprint 3: Control de Caja por Turnos, Punto de Venta y Recibos
- **Control y Arqueo de Caja (`caja/`):**
  - Apertura obligatoria de turno de caja ingresando el monto inicial.
  - Panel en tiempo real de ingresos discriminados entre **Efectivo** y **Transferencia QR**.
  - Cierre y arqueo de turno calculando dinero esperado en sistema vs. monto físico contado, registrando descuadres y auditoría de turnos cerrados.
- **Punto de Venta (`ventas/crear.php`):**
  - Validación estricta de turno de caja abierto antes de operar.
  - Catálogo de calzados con verificación de stock en tiempo real en frontend y backend.
  - Carrito de compras interactivo con desglose de subtotales y total general.
  - Transacciones atómicas (`START TRANSACTION` / `COMMIT` / `ROLLBACK`) para descontar stock e insertar la venta y su detalle.
  - Modal para configuración dinámica del QR de pago (exclusivo para Administrador) almacenado en la tabla `configuracion`.
- **Historial de Ventas (`ventas/index.php`):** Listado inmutable con filtros rápidos por fecha o texto (cumple auditoría sin opción de editar o borrar ventas).
- **Recibo Digital Imprimible (`ventas/recibo.php`):** Comprobante formal con datos de la tienda, desglose de calzados y optimización con reglas `print:hidden` para impresión limpia en un clic.

### ✅ Sprint 4: CRUD de Clientes, Correo Institucional por CI y Rediseño de Dashboard
- **Módulo de Clientes (`clientes/`):**
  - CRUD completo (`index.php`, `registrar.php`, `editar.php`, `eliminar.php`) accesible para Administrador y Vendedor.
  - Validaciones estrictas: nombre completo (solo letras y espacios), teléfono (numérico opcional) y NIT/CI (numérico opcional).
  - Integración de acceso rápido "+ Nuevo Cliente" dentro del Punto de Venta (`ventas/crear.php`).
  - Integridad referencial protegida: al eliminar un cliente, su historial de ventas permanece intacto (`ON DELETE SET NULL`).
- **Automatización de Credenciales en Usuarios (`usuarios/`):**
  - Incorporación del Carnet de Identidad (`ci`) obligatorio y único.
  - Generación automática del correo institucional con formato: `inicial_del_rol.ci@pasofirme.com` (ej. `a.1234567@pasofirme.com` para Administrador o `v.1234567@pasofirme.com` para Vendedor).
  - Previsualización dinámica en JavaScript (`readonly`) y validación/recalculación obligatoria en servidor en PHP.
- **Rediseño Integral del Dashboard (`index.php`):**
  - Estética moderna estilo Nike en blanco, negro y grises neutros.
  - Hero banner superior oscuro institucional con saludo personalizado según rol y acceso directo al Punto de Venta.
  - Métricas e indicadores clave (KPIs) calculados en tiempo real con SQL: estado de caja, ventas de hoy, productos con stock bajo y total de clientes.
  - Tarjetas de navegación Bento con iconografía SVG vectorial limpia.
- **Navegación Unificada (`includes/navbar.php`):** Menú adaptable con enlaces directos a Clientes, Ventas y Caja para ambos roles.

---

## 📁 Estructura del Proyecto

```text
paso_firme/
│
├── auth.php                  # Validación de credenciales e inicio de sesión
├── contexto_proyecto.md      # Documentación técnica, esquema SQL y reglas de negocio
├── generar_usuarios.php      # Script inicializador (seeder) de roles y usuarios
├── index.php                 # Panel principal (Dashboard con KPIs y accesos Bento)
├── login.php                 # Formulario de inicio de sesión
├── logout.php                # Destrucción segura de sesión
├── README.md                 # Documentación general del repositorio
│
├── caja/
│   └── index.php             # Control de turnos, apertura, balance y arqueo de caja
│
├── clientes/
│   ├── editar.php            # Edición de datos de clientes
│   ├── eliminar.php          # Baja de cliente (preserva histórico con SET NULL)
│   ├── index.php             # Listado de clientes con buscador en tiempo real
│   └── registrar.php         # Registro de nuevos clientes con validaciones
│
├── includes/
│   ├── conexion.php          # Instancia mysqli centralizada
│   └── navbar.php            # Barra de navegación compartida con control de roles
│
├── inventario/
│   ├── detalle.php           # Detalle de existencias de un modelo
│   ├── editar.php            # Modificación de talla, color y stock
│   ├── eliminar.php          # Baja lógica de registro de inventario
│   ├── index.php             # Listado de inventario con filtros por modelo/categoría
│   └── registrar.php         # Ingreso de nuevo stock por talla y color
│
├── modelos/
│   ├── editar.php            # Actualización de datos del modelo
│   ├── eliminar.php          # Eliminación de modelo de calzado
│   ├── index.php             # Catálogo visual de calzados con imágenes
│   └── registrar.php         # Alta de nuevos modelos con código único
│
├── usuarios/
│   ├── editar.php            # Edición de usuario y recalculo automático de correo
│   ├── eliminar.php          # Eliminación segura de usuario
│   ├── index.php             # Listado de usuarios con CI, correo y rol
│   └── registrar.php         # Registro con CI y correo autogenerado
│
└── ventas/
    ├── crear.php             # Punto de Venta (POS), carrito, control de stock y QR
    ├── index.php             # Historial inmutable de ventas realizadas
    └── recibo.php            # Comprobante digital listo para impresión
```

---

## ⚙️ Instrucciones de Instalación

1. **Clonar el repositorio** dentro de la carpeta `www` de Laragon (`C:\laragon\www`):
   ```bash
   cd C:\laragon\www
   git clone https://github.com/NilsOliver06/paso_firme.git
   ```

2. **Iniciar servicios:** Abrir Laragon y presionar **"Iniciar todo"** (Apache y MySQL).

3. **Configurar la Base de Datos:**
   - Abrir phpMyAdmin o HeidiSQL.
   - Crear la base de datos `paso_firme_db`.
   - Ejecutar el script SQL contenido en [contexto_proyecto.md](contexto_proyecto.md).

4. **Sembrar usuarios iniciales (opcional):**
   - Ejecutar en el navegador o terminal el archivo `generar_usuarios.php` para cargar los usuarios iniciales con contraseña encriptada.

5. **Acceder a la aplicación:**
   - Ingresar a: `http://localhost/paso_firme/login.php`

---

## 🔑 Credenciales de Prueba

| Rol | Correo / Identificador | Contraseña | Acceso |
| :--- | :--- | :--- | :--- |
| **Administrador** | `admin@pasofirme.com` *(o institucional `a.<ci>@pasofirme.com`)* | `123456` | Acceso total al sistema |
| **Vendedor** | `ventas@pasofirme.com` *(o institucional `v.<ci>@pasofirme.com`)* | `123456` | Ventas, Caja, Clientes, Inventario y Catálogo |

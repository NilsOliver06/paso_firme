<?php
// Inicia la sesión para poder leer si el usuario ya está autenticado
session_start();

// Si ya hay un usuario logueado, no tiene sentido mostrar el login: se redirige al inicio
if (isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit;
}

// auth.php enviará ?error=1 (credenciales inválidas) o ?error=2 (campos vacíos) cuando algo falle
$error = isset($_GET['error']) ? $_GET['error'] : '';
$mensaje_error = '';
if ($error === '1') {
    $mensaje_error = 'Correo o contraseña incorrectos.';
} elseif ($error === '2') {
    $mensaje_error = 'Por favor completa todos los campos.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <!-- viewport: imprescindible para que Tailwind sea responsivo en celulares -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PasoFirme | Iniciar sesión</title>

    <!-- Tailwind CSS vía CDN: permite usar clases de utilidad sin instalar nada -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<!--
  BODY:
  - min-h-screen    : altura mínima = 100% de la pantalla
  - flex            : activa Flexbox
  - items-center    : centra verticalmente
  - justify-center  : centra horizontalmente
  - bg-neutral-100  : fondo gris muy suave
  - px-4            : margen lateral en móviles para que la tarjeta no toque los bordes
  - font-sans       : tipografía sin serifas, limpia
-->
<body class="min-h-screen flex items-center justify-center bg-neutral-100 px-4 font-sans">

    <!--
      TARJETA:
      - w-full max-w-md : ocupa todo el ancho en móvil pero nunca más de 28rem (responsivo)
      - bg-white        : fondo blanco
      - p-8 sm:p-10     : relleno de 2rem; desde pantallas "sm" (>=640px) sube a 2.5rem
      - rounded-2xl     : esquinas muy redondeadas
      - shadow-sm       : sombra sutil
      - border          : borde fino, con border-neutral-200 (gris claro)
    -->
    <main class="w-full max-w-md bg-white p-8 sm:p-10 rounded-2xl shadow-sm border border-neutral-200">

        <!-- Encabezado: marca y subtítulo -->
        <header class="mb-8 text-center">
            <!-- text-3xl: tamaño grande | font-black: peso máximo | tracking-tighter: letras juntas | uppercase: mayúsculas -->
            <h1 class="text-3xl font-black tracking-tighter uppercase text-black">PasoFirme</h1>
            <!-- text-sm: pequeño | text-neutral-500: gris medio | mt-2: margen superior -->
            <p class="text-sm text-neutral-500 mt-2">Ingresa a tu cuenta para continuar</p>
        </header>

        <?php if ($mensaje_error !== ''): ?>
            <!-- Alerta de error en escala de grises: fondo gris claro, borde y texto oscuros -->
            <div class="mb-6 rounded-lg border border-neutral-300 bg-neutral-100 px-4 py-3 text-sm text-neutral-800" role="alert">
                <?php echo htmlspecialchars($mensaje_error); ?>
            </div>
        <?php endif; ?>

        <!--
          FORMULARIO:
          - action="auth.php" : archivo que procesará los datos
          - method="POST"     : los datos viajan en el cuerpo de la petición (no en la URL)
          - space-y-5         : separación vertical uniforme de 1.25rem entre hijos
        -->
        <form action="auth.php" method="POST" class="space-y-5">

            <!-- Campo: correo -->
            <div>
                <!-- block: ocupa su propia línea | text-xs uppercase tracking-wide: etiqueta pequeña estilo editorial -->
                <label for="correo" class="block text-xs font-semibold uppercase tracking-wide text-neutral-700 mb-2">
                    Correo electrónico
                </label>
                <!--
                  INPUT:
                  - w-full                 : ancho completo
                  - px-4 py-3              : relleno horizontal y vertical
                  - border-neutral-300     : borde gris sutil
                  - rounded-lg             : esquinas redondeadas
                  - placeholder-neutral-400: texto de ayuda en gris claro
                  - focus:outline-none focus:border-black focus:ring-1 focus:ring-black :
                      al hacer clic, quita el contorno azul del navegador y resalta en negro
                  - transition             : animación suave del cambio
                -->
                <input type="email" id="correo" name="correo" required autofocus
                       placeholder="usuario@correo.com"
                       class="w-full px-4 py-3 border border-neutral-300 rounded-lg text-neutral-900 placeholder-neutral-400 focus:outline-none focus:border-black focus:ring-1 focus:ring-black transition">
            </div>

            <!-- Campo: password -->
            <div>
                <label for="password" class="block text-xs font-semibold uppercase tracking-wide text-neutral-700 mb-2">
                    Contraseña
                </label>
                <input type="password" id="password" name="password" required
                       placeholder="••••••••"
                       class="w-full px-4 py-3 border border-neutral-300 rounded-lg text-neutral-900 placeholder-neutral-400 focus:outline-none focus:border-black focus:ring-1 focus:ring-black transition">
            </div>

            <!--
              BOTÓN:
              - w-full                   : ancho completo
              - bg-black text-white      : fondo negro, texto blanco
              - py-3.5                   : alto cómodo para tocar
              - rounded-full             : forma de píldora (estilo Nike)
              - font-semibold uppercase tracking-wide : texto en mayúsculas con espaciado
              - hover:bg-neutral-800     : al pasar el mouse se aclara a gris oscuro
              - active:scale-[0.98]      : se "hunde" ligeramente al presionar
              - transition               : cambios animados
            -->
            <button type="submit"
                    class="w-full bg-black text-white py-3.5 rounded-full font-semibold uppercase tracking-wide text-sm hover:bg-neutral-800 active:scale-[0.98] transition">
                Ingresar
            </button>
        </form>

        <!-- Pie: text-xs text-neutral-400 = texto diminuto en gris claro -->
        <footer class="mt-8 text-center text-xs text-neutral-400">
            &copy; <?php echo date('Y'); ?> PasoFirme · Sistema de Calzados
        </footer>
    </main>

</body>
</html>


<?php
/**
 * SOLUFEED - Crear Nuevo Lote
 */

require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Simular sesión
$_SESSION['usuario_id'] = 1;
$_SESSION['nombre'] = 'Administrador';
$_SESSION['tipo'] = 'ADMIN';

// Obtener campos disponibles
$query_campos = "SELECT id_campo, nombre FROM campo WHERE activo = 1 ORDER BY nombre ASC";
$campos_disponibles = ejecutarConsulta($query_campos);

// Obtener dietas disponibles
$query_dietas = "SELECT id_dieta, nombre FROM dieta WHERE activo = 1 ORDER BY nombre ASC";
$dietas_disponibles = ejecutarConsulta($query_dietas);

// Procesar formulario si se envió
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Recibir datos del formulario
    $nombre = limpiarDato($_POST['nombre']);
    $id_campo = (int) $_POST['id_campo'];
    $categoria = limpiarDato($_POST['categoria']);
    $fecha_inicio = limpiarDato($_POST['fecha_inicio']);
    $cantidad_inicial = (int) $_POST['cantidad_inicial'];
    $id_dieta = !empty($_POST['id_dieta']) ? (int) $_POST['id_dieta'] : null;
    $activo = isset($_POST['activo']) ? 1 : 0;
    
    // Validaciones
    $errores = [];
    
    if (empty($nombre)) {
        $errores[] = "El nombre del lote es obligatorio.";
    }
    
    if ($id_campo <= 0) {
        $errores[] = "Debés seleccionar un campo.";
    }
    
    if (empty($fecha_inicio)) {
        $errores[] = "La fecha de inicio es obligatoria.";
    }
    
    if ($cantidad_inicial <= 0) {
        $errores[] = "La cantidad inicial de animales debe ser mayor a 0.";
    }
    
    // Si no hay errores, crear el lote
    if (empty($errores)) {
        
        // Insertar lote
        $query_lote = "
            INSERT INTO tropa (nombre, id_campo, categoria, fecha_inicio, cantidad_inicial, activo, fecha_creacion)
            VALUES ('$nombre', $id_campo, '$categoria', '$fecha_inicio', $cantidad_inicial, $activo, NOW())
        ";
        
        if (ejecutarConsulta($query_lote)) {
            
            $id_lote_nuevo = mysqli_insert_id($conn);
            
            // Si se seleccionó una dieta, asignarla
            if ($id_dieta !== null && $id_dieta > 0) {
                $query_dieta = "
                    INSERT INTO tropa_dieta_asignada (id_tropa, id_dieta, fecha_desde, fecha_hasta)
                    VALUES ($id_lote_nuevo, $id_dieta, '$fecha_inicio', NULL)
                ";
                ejecutarConsulta($query_dieta);
            }
            
            $exito = "✓ Lote creado exitosamente.";
            header("refresh:2;url=ver.php?id=$id_lote_nuevo");
            
        } else {
            $errores[] = "Error al crear el lote.";
        }
    }
}

include '../../includes/header.php';
?>

<h1 class="tarjeta-titulo">🐮 Crear Nuevo Lote</h1>

<div class="tarjeta">
    
    <?php if (isset($exito)): ?>
        <div class="mensaje mensaje-exito"><?php echo $exito; ?></div>
    <?php endif; ?>
    
    <?php if (!empty($errores)): ?>
        <div class="mensaje mensaje-error">
            <strong>Se encontraron los siguientes errores:</strong>
            <ul class="mensaje-lista">
                <?php foreach ($errores as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <form method="POST" class="formulario">
        
        <!-- Nombre del lote -->
        <div class="form-grupo">
            <label for="nombre">Nombre del Lote *</label>
            <input 
                type="text" 
                id="nombre" 
                name="nombre" 
                required 
                placeholder="Ej: Novillos Lote 1, Terneros Recría, etc."
                value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : ''; ?>"
            >
            <small>El nombre debe ser descriptivo y único para identificar el lote fácilmente.</small>
        </div>
        
        <!-- Campo -->
        <div class="form-grupo">
            <label for="id_campo">Campo *</label>
            <select id="id_campo" name="id_campo" required>
                <option value="">-- Seleccioná un campo --</option>
                <?php while ($campo = mysqli_fetch_assoc($campos_disponibles)): ?>
                    <option value="<?php echo $campo['id_campo']; ?>"
                        <?php echo (isset($_POST['id_campo']) && $_POST['id_campo'] == $campo['id_campo']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($campo['nombre']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <small>Ubicación física donde se encuentra el lote.</small>
        </div>
        
        <!-- Categoría -->
        <div class="form-grupo">
            <label for="categoria">Categoría</label>
            <input 
                type="text" 
                id="categoria" 
                name="categoria" 
                placeholder="Ej: Novillos 350-400kg, Terneros 180-220kg"
                value="<?php echo isset($_POST['categoria']) ? htmlspecialchars($_POST['categoria']) : ''; ?>"
            >
            <small>Describí la categoría y/o peso promedio inicial de los animales (opcional).</small>
        </div>
        
        <!-- Fecha de inicio -->
        <div class="form-grupo">
            <label for="fecha_inicio">Fecha de Inicio *</label>
            <input 
                type="date" 
                id="fecha_inicio" 
                name="fecha_inicio" 
                required 
                class="input-mediano"
                value="<?php echo isset($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : date('Y-m-d'); ?>"
            >
            <small>Fecha en que ingresaron los animales al feedlot.</small>
        </div>
        
        <!-- Cantidad inicial -->
        <div class="form-grupo">
            <label for="cantidad_inicial">Cantidad Inicial de Animales *</label>
            <input 
                type="number" 
                id="cantidad_inicial" 
                name="cantidad_inicial" 
                required 
                min="1"
                class="input-pequeno"
                placeholder="Ej: 50"
                value="<?php echo isset($_POST['cantidad_inicial']) ? $_POST['cantidad_inicial'] : ''; ?>"
            >
            <small>Cantidad de animales con la que comienza el lote.</small>
        </div>
        
        <hr class="separador-horizontal">

        <h3 class="seccion-titulo">📋 Asignación de Dieta (Opcional)</h3>

        <div class="mensaje mensaje-info mb-1">
            ℹ️ Podés asignar una dieta ahora o hacerlo más tarde.
            La dieta se asignará desde la fecha de inicio del lote.
        </div>
        
        <!-- Dieta -->
        <div class="form-grupo">
            <label for="id_dieta">Dieta a Asignar</label>
            <select id="id_dieta" name="id_dieta">
                <option value="">-- Sin dieta por ahora --</option>
                <?php 
                mysqli_data_seek($dietas_disponibles, 0); // Resetear puntero
                while ($dieta = mysqli_fetch_assoc($dietas_disponibles)): 
                ?>
                    <option value="<?php echo $dieta['id_dieta']; ?>"
                        <?php echo (isset($_POST['id_dieta']) && $_POST['id_dieta'] == $dieta['id_dieta']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($dieta['nombre']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <small>La dieta teórica que se usará para este lote. Podés cambiarla después.</small>
        </div>
        
        <?php if (mysqli_num_rows($dietas_disponibles) == 0): ?>
            <div class="mensaje mensaje-error">
                ⚠️ No hay dietas activas disponibles. 
                <a href="../dietas/crear.php" target="_blank">Creá al menos una dieta</a> para poder asignarla al lote.
            </div>
        <?php endif; ?>

        <hr class="separador-horizontal">

        <!-- Estado activo -->
        <div class="form-grupo">
            <label>
                <input 
                    type="checkbox" 
                    name="activo" 
                    value="1" 
                    <?php echo (!isset($_POST['activo']) || $_POST['activo']) ? 'checked' : ''; ?>
                >
                Lote activo
            </label>
            <small>Los lotes inactivos no aparecen en las opciones de registro de alimentación/pesadas.</small>
        </div>
        
        <!-- Botones -->
        <div class="btn-grupo">
            <button type="submit" class="btn btn-primario">💾 Crear Lote</button>
            <a href="listar.php" class="btn btn-secundario">❌ Cancelar</a>
        </div>
        
    </form>
    
</div>

<?php include '../../includes/footer.php'; ?>
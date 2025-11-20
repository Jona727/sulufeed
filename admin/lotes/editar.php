<?php
/**
 * SOLUFEED - Editar Lote
 */

require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Simular sesión
$_SESSION['usuario_id'] = 1;
$_SESSION['nombre'] = 'Administrador';
$_SESSION['tipo'] = 'ADMIN';

// Verificar que se recibió el ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: listar.php');
    exit();
}

$id_tropa = (int) $_GET['id'];

// Obtener datos del lote
$query_lote = "
    SELECT t.*, c.nombre as campo_nombre
    FROM tropa t
    INNER JOIN campo c ON t.id_campo = c.id_campo
    WHERE t.id_tropa = $id_tropa
";
$resultado_lote = ejecutarConsulta($query_lote);

if (mysqli_num_rows($resultado_lote) === 0) {
    header('Location: listar.php');
    exit();
}

$lote = mysqli_fetch_assoc($resultado_lote);

// Obtener dieta vigente actual
$dieta_vigente = obtenerDietaVigente($id_tropa);
$id_dieta_actual = $dieta_vigente ? $dieta_vigente['id_dieta'] : null;

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
    $id_dieta_nueva = !empty($_POST['id_dieta']) ? (int) $_POST['id_dieta'] : null;
    $fecha_cambio_dieta = !empty($_POST['fecha_cambio_dieta']) ? limpiarDato($_POST['fecha_cambio_dieta']) : date('Y-m-d');
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
    
    // Si no hay errores, actualizar el lote
    if (empty($errores)) {
        
        // Actualizar lote
        $query_update = "
            UPDATE tropa SET
                nombre = '$nombre',
                id_campo = $id_campo,
                categoria = '$categoria',
                fecha_inicio = '$fecha_inicio',
                cantidad_inicial = $cantidad_inicial,
                activo = $activo,
                fecha_actualizacion = NOW()
            WHERE id_tropa = $id_tropa
        ";
        
        if (ejecutarConsulta($query_update)) {
            
            // Verificar si cambió la dieta
            $dieta_cambio = false;
            
            if ($id_dieta_nueva !== $id_dieta_actual) {
                
                // Si había una dieta asignada, cerrarla
                if ($id_dieta_actual !== null) {
                    $query_cerrar = "
                        UPDATE tropa_dieta_asignada 
                        SET fecha_hasta = '$fecha_cambio_dieta'
                        WHERE id_tropa = $id_tropa
                        AND fecha_hasta IS NULL
                    ";
                    ejecutarConsulta($query_cerrar);
                }
                
                // Si hay una nueva dieta seleccionada, asignarla
                if ($id_dieta_nueva !== null && $id_dieta_nueva > 0) {
                    $query_nueva_dieta = "
                        INSERT INTO tropa_dieta_asignada (id_tropa, id_dieta, fecha_desde, fecha_hasta)
                        VALUES ($id_tropa, $id_dieta_nueva, '$fecha_cambio_dieta', NULL)
                    ";
                    ejecutarConsulta($query_nueva_dieta);
                }
                
                $dieta_cambio = true;
            }
            
            $exito = "✓ Lote actualizado exitosamente.";
            if ($dieta_cambio) {
                $exito .= " La dieta fue modificada.";
            }
            
            header("refresh:2;url=ver.php?id=$id_tropa");
            
            // Actualizar datos para mostrar en el formulario
            $lote['nombre'] = $nombre;
            $lote['id_campo'] = $id_campo;
            $lote['categoria'] = $categoria;
            $lote['fecha_inicio'] = $fecha_inicio;
            $lote['cantidad_inicial'] = $cantidad_inicial;
            $lote['activo'] = $activo;
            
        } else {
            $errores[] = "Error al actualizar el lote.";
        }
    }
}

include '../../includes/header.php';
?>

<h1 class="tarjeta-titulo">✏️ Editar Lote</h1>

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

    <div class="mensaje mensaje-info mb-1">
        ℹ️ <strong>Atención:</strong> Los cambios en la cantidad inicial no afectan los movimientos
        ya registrados. Para ajustar animales presentes, usá el módulo de movimientos.
    </div>
    
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
                value="<?php echo htmlspecialchars($lote['nombre']); ?>"
            >
            <small>El nombre debe ser descriptivo y único para identificar el lote fácilmente.</small>
        </div>
        
        <!-- Campo -->
        <div class="form-grupo">
            <label for="id_campo">Campo *</label>
            <select id="id_campo" name="id_campo" required>
                <option value="">-- Seleccioná un campo --</option>
                <?php 
                mysqli_data_seek($campos_disponibles, 0); // Resetear puntero
                while ($campo = mysqli_fetch_assoc($campos_disponibles)): 
                ?>
                    <option value="<?php echo $campo['id_campo']; ?>"
                        <?php echo ($lote['id_campo'] == $campo['id_campo']) ? 'selected' : ''; ?>>
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
                value="<?php echo htmlspecialchars($lote['categoria']); ?>"
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
                value="<?php echo $lote['fecha_inicio']; ?>"
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
                value="<?php echo $lote['cantidad_inicial']; ?>"
            >
            <small>Cantidad de animales con la que comenzó el lote.</small>
        </div>
        
        <!-- Información adicional -->
        <div class="form-grupo">
            <small class="etiqueta-resumen">
                <strong>Creado:</strong> <?php echo formatearFecha($lote['fecha_creacion']); ?>
                <?php if ($lote['fecha_actualizacion']): ?>
                    | <strong>Última actualización:</strong> <?php echo formatearFecha($lote['fecha_actualizacion']); ?>
                <?php endif; ?>
            </small>
        </div>

        <hr class="separador-horizontal">

        <h3 class="seccion-titulo">📋 Gestión de Dieta</h3>

        <?php if ($dieta_vigente): ?>
            <div class="mensaje mensaje-info mb-1">
                📌 <strong>Dieta actual:</strong> <?php echo htmlspecialchars($dieta_vigente['dieta_nombre']); ?>
                <br>Asignada desde: <?php echo formatearFecha($dieta_vigente['fecha_desde']); ?>
            </div>
        <?php else: ?>
            <div class="mensaje mensaje-info mb-1">
                ⚠️ Este lote no tiene dieta asignada actualmente.
            </div>
        <?php endif; ?>
        
        <!-- Dieta -->
        <div class="form-grupo">
            <label for="id_dieta">
                <?php echo $dieta_vigente ? 'Cambiar a otra Dieta' : 'Asignar Dieta'; ?>
            </label>
            <select id="id_dieta" name="id_dieta">
                <option value="">-- <?php echo $dieta_vigente ? 'Mantener actual' : 'Sin dieta'; ?> --</option>
                <?php 
                mysqli_data_seek($dietas_disponibles, 0); // Resetear puntero
                while ($dieta = mysqli_fetch_assoc($dietas_disponibles)): 
                    // Si es la dieta actual, marcarla
                    $es_actual = ($id_dieta_actual && $dieta['id_dieta'] == $id_dieta_actual);
                ?>
                    <option value="<?php echo $dieta['id_dieta']; ?>"
                        <?php echo $es_actual ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($dieta['nombre']); ?>
                        <?php echo $es_actual ? ' (actual)' : ''; ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <small>
                <?php if ($dieta_vigente): ?>
                    Si seleccionás otra dieta, la actual se cerrará y se asignará la nueva.
                <?php else: ?>
                    Seleccioná la dieta teórica que se usará para este lote.
                <?php endif; ?>
            </small>
        </div>
        
        <!-- Fecha de cambio de dieta -->
        <div class="form-grupo">
            <label for="fecha_cambio_dieta">Fecha del Cambio de Dieta</label>
            <input 
                type="date" 
                id="fecha_cambio_dieta" 
                name="fecha_cambio_dieta" 
                class="input-mediano"
                value="<?php echo date('Y-m-d'); ?>"
            >
            <small>Fecha desde la cual la nueva dieta estará vigente (por defecto: hoy).</small>
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
                    <?php echo $lote['activo'] ? 'checked' : ''; ?>
                >
                Lote activo
            </label>
            <small>Los lotes inactivos no aparecen en las opciones de registro de alimentación/pesadas.</small>
        </div>
        
        <!-- Botones -->
        <div class="btn-grupo">
            <button type="submit" class="btn btn-primario">💾 Guardar Cambios</button>
            <a href="ver.php?id=<?php echo $id_tropa; ?>" class="btn btn-secundario">❌ Cancelar</a>
        </div>
        
    </form>
    
</div>

<!-- Historial de cambios de dieta -->
<?php
$query_historial = "
    SELECT 
        tda.fecha_desde,
        tda.fecha_hasta,
        d.nombre as dieta_nombre
    FROM tropa_dieta_asignada tda
    INNER JOIN dieta d ON tda.id_dieta = d.id_dieta
    WHERE tda.id_tropa = $id_tropa
    ORDER BY tda.fecha_desde DESC
";
$historial_dietas = ejecutarConsulta($query_historial);
?>

<?php if (mysqli_num_rows($historial_dietas) > 0): ?>
<div class="tarjeta">
    <h2 class="tarjeta-titulo">📜 Historial de Dietas Asignadas</h2>
    
    <div class="tabla-responsive">
        <table>
            <thead>
                <tr>
                    <th>Dieta</th>
                    <th>Fecha Desde</th>
                    <th>Fecha Hasta</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($hist = mysqli_fetch_assoc($historial_dietas)): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($hist['dieta_nombre']); ?></strong></td>
                        <td><?php echo formatearFecha($hist['fecha_desde']); ?></td>
                        <td>
                            <?php
                            if ($hist['fecha_hasta']) {
                                echo formatearFecha($hist['fecha_hasta']);
                            } else {
                                echo '<span class="texto-vigente">Vigente</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <?php if (!$hist['fecha_hasta']): ?>
                                <span class="estado estado-activo">Actual</span>
                            <?php else: ?>
                                <span class="estado estado-inactivo">Finalizada</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
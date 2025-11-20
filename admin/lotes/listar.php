<?php
/**
 * SOLUFEED - Listar Lotes/Tropas
 */

require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Simular sesión
$_SESSION['usuario_id'] = 1;
$_SESSION['nombre'] = 'Administrador';
$_SESSION['tipo'] = 'ADMIN';

include '../../includes/header.php';

// Obtener todos los lotes con información relacionada
$query = "
    SELECT 
        t.id_tropa,
        t.nombre,
        t.categoria,
        t.fecha_inicio,
        t.cantidad_inicial,
        t.activo,
        c.nombre as campo_nombre,
        d.nombre as dieta_nombre,
        (SELECT MAX(p.fecha) FROM pesada p WHERE p.id_tropa = t.id_tropa) as ultima_pesada,
        (SELECT MAX(cl.fecha) FROM consumo_lote cl WHERE cl.id_tropa = t.id_tropa) as ultima_alimentacion,
        (SELECT COUNT(*) FROM consumo_lote cl WHERE cl.id_tropa = t.id_tropa) as total_alimentaciones,
        (SELECT COUNT(*) FROM pesada p WHERE p.id_tropa = t.id_tropa) as total_pesadas
    FROM tropa t
    INNER JOIN campo c ON t.id_campo = c.id_campo
    LEFT JOIN tropa_dieta_asignada tda ON t.id_tropa = tda.id_tropa 
        AND tda.fecha_desde <= CURDATE() 
        AND (tda.fecha_hasta IS NULL OR tda.fecha_hasta >= CURDATE())
    LEFT JOIN dieta d ON tda.id_dieta = d.id_dieta
    ORDER BY t.activo DESC, t.fecha_inicio DESC
";

$resultado = ejecutarConsulta($query);
?>

<h1 class="tarjeta-titulo">🐮 Gestión de Lotes</h1>

<div class="tarjeta">

    <div class="seccion-header">
        <p>Administrá los lotes/tropas de animales en el feedlot.</p>
        <a href="crear.php" class="btn btn-primario">➕ Crear Nuevo Lote</a>
    </div>
    
    <?php if (mysqli_num_rows($resultado) > 0): ?>
        
        <div class="tabla-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Lote</th>
                        <th>Campo</th>
                        <th>Categoría</th>
                        <th>Animales</th>
                        <th>Dieta Actual</th>
                        <th>Fecha Inicio</th>
                        <th>Días</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($lote = mysqli_fetch_assoc($resultado)): ?>
                        <?php 
                        // Calcular animales presentes
                        $animales_presentes = obtenerAnimalesPresentes($lote['id_tropa']);
                        
                        // Calcular días desde el inicio
                        $fecha_inicio = new DateTime($lote['fecha_inicio']);
                        $fecha_hoy = new DateTime();
                        $dias_engorde = $fecha_inicio->diff($fecha_hoy)->days;
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($lote['nombre']); ?></strong></td>
                            <td><?php echo htmlspecialchars($lote['campo_nombre']); ?></td>
                            <td><?php echo htmlspecialchars($lote['categoria']); ?></td>
                            <td>
                                <strong class="texto-destacado-lote">
                                    <?php echo $animales_presentes; ?>
                                </strong>
                                <?php if ($animales_presentes != $lote['cantidad_inicial']): ?>
                                    <small class="etiqueta-resumen">
                                        (inicial: <?php echo $lote['cantidad_inicial']; ?>)
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($lote['dieta_nombre']): ?>
                                    <span class="texto-exito">✓ <?php echo htmlspecialchars($lote['dieta_nombre']); ?></span>
                                <?php else: ?>
                                    <span class="texto-peligro">⚠ Sin dieta</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo formatearFecha($lote['fecha_inicio']); ?></td>
                            <td>
                                <span class="badge-dias">
                                    <?php echo $dias_engorde; ?> días
                                </span>
                            </td>
                            <td>
                                <?php if ($lote['activo']): ?>
                                    <span class="estado estado-activo">Activo</span>
                                <?php else: ?>
                                    <span class="estado estado-inactivo">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="tabla-acciones">
                                    <a href="ver.php?id=<?php echo $lote['id_tropa']; ?>" 
                                       class="btn btn-secundario btn-pequeno">👁️ Ver</a>
                                    <a href="editar.php?id=<?php echo $lote['id_tropa']; ?>" 
                                       class="btn btn-secundario btn-pequeno">✏️ Editar</a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        
    <?php else: ?>
        
        <div class="sin-datos">
            <p>No hay lotes registrados todavía.</p>
            <a href="crear.php" class="btn btn-primario">Crear Primer Lote</a>
        </div>
        
    <?php endif; ?>
    
</div>

<!-- Resumen estadístico -->
<?php if (mysqli_num_rows($resultado) > 0): ?>
    <?php
    // Calcular totales
    mysqli_data_seek($resultado, 0); // Resetear puntero
    $total_animales = 0;
    $lotes_activos = 0;
    $lotes_sin_dieta = 0;
    
    while ($lote = mysqli_fetch_assoc($resultado)) {
        if ($lote['activo']) {
            $lotes_activos++;
            $total_animales += obtenerAnimalesPresentes($lote['id_tropa']);
            if (empty($lote['dieta_nombre'])) {
                $lotes_sin_dieta++;
            }
        }
    }
    ?>
    
    <div class="tarjeta tarjeta-resumen">
        <h2 class="tarjeta-titulo">📊 Resumen</h2>

        <div class="resumen-grid">

            <div class="resumen-item">
                <div class="valor-resumen">
                    <?php echo $lotes_activos; ?>
                </div>
                <div class="etiqueta-resumen">Lotes Activos</div>
            </div>

            <div class="resumen-item">
                <div class="valor-resumen">
                    <?php echo $total_animales; ?>
                </div>
                <div class="etiqueta-resumen">Animales en Feedlot</div>
            </div>

            <?php if ($lotes_sin_dieta > 0): ?>
            <div class="resumen-item">
                <div class="valor-resumen-peligro">
                    <?php echo $lotes_sin_dieta; ?>
                </div>
                <div class="etiqueta-resumen">⚠ Lotes sin Dieta</div>
            </div>
            <?php endif; ?>

        </div>
    </div>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
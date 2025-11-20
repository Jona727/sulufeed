<?php
// admin/dashboard.php - VERSIÓN FINAL CON NOMBRES CORRECTOS
require_once '../config/database.php';
require_once '../includes/functions.php';

$page_title = "Dashboard - Panel de Control";
$db = getConnection();

$hoy = date('Y-m-d');

// ===========================================
// INDICADORES PRINCIPALES
// ===========================================

// Lotes activos
$stmt = $db->query("SELECT COUNT(*) as total FROM tropa WHERE activo = 1");
$total_lotes = $stmt->fetch()['total'];

// Total de animales (suma de cantidad_inicial de lotes activos)
$stmt = $db->query("SELECT SUM(cantidad_inicial) as total FROM tropa WHERE activo = 1");
$total_animales = $stmt->fetch()['total'] ?? 0;

// Total de insumos
$stmt = $db->query("SELECT COUNT(*) as total FROM insumo WHERE activo = 1");
$total_insumos = $stmt->fetch()['total'];

// Total de dietas
$stmt = $db->query("SELECT COUNT(*) as total FROM dieta WHERE activo = 1");
$total_dietas = $stmt->fetch()['total'];

// Alimentaciones hoy
$stmt = $db->prepare("SELECT COUNT(*) as total FROM consumo_lote WHERE DATE(fecha) = ?");
$stmt->execute([$hoy]);
$alimentaciones_hoy = $stmt->fetch()['total'];

// Kg totales entregados hoy
$stmt = $db->prepare("SELECT COALESCE(SUM(kg_totales_tirados), 0) as total FROM consumo_lote WHERE DATE(fecha) = ?");
$stmt->execute([$hoy]);
$kg_hoy = $stmt->fetch()['total'];

// ADPV Promedio (últimos 30 días)
$stmt = $db->query("
    SELECT 
        AVG(
            (p2.peso_promedio - p1.peso_promedio) / 
            DATEDIFF(p2.fecha, p1.fecha)
        ) as adpv_promedio
    FROM pesada p1
    INNER JOIN pesada p2 ON p1.id_tropa = p2.id_tropa 
        AND p2.fecha > p1.fecha
        AND p2.fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    WHERE NOT EXISTS (
        SELECT 1 FROM pesada p3 
        WHERE p3.id_tropa = p1.id_tropa 
        AND p3.fecha > p1.fecha 
        AND p3.fecha < p2.fecha
    )
");
$adpv_promedio = $stmt->fetch()['adpv_promedio'] ?? 0;

// CMS Promedio (últimos 7 días)
$stmt = $db->query("
    SELECT 
        AVG(
            (SELECT SUM(kg_ms)
             FROM consumo_lote_detalle cld
             WHERE cld.id_consumo = cl.id_consumo) / cl.animales_presentes
        ) as cms_promedio
    FROM consumo_lote cl
    WHERE cl.fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    AND cl.animales_presentes > 0
");
$cms_promedio = $stmt->fetch()['cms_promedio'] ?? 0;

// ===========================================
// ALERTAS
// ===========================================

// Lotes sin dieta asignada
$stmt = $db->query("
    SELECT COUNT(*) as total
    FROM tropa t
    LEFT JOIN tropa_dieta_asignada tda ON t.id_tropa = tda.id_tropa AND tda.fecha_hasta IS NULL
    WHERE t.activo = 1 AND tda.id_tropa_dieta IS NULL
");
$lotes_sin_dieta = $stmt->fetch()['total'];

// Ajustes de animales pendientes
$stmt = $db->query("
    SELECT COUNT(*) as total
    FROM ajuste_animales_pendiente
    WHERE estado = 'PENDIENTE'
");
$ajustes_pendientes = $stmt->fetch()['total'];

// Lotes sin alimentar hoy
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT t.id_tropa) as total
    FROM tropa t
    LEFT JOIN consumo_lote cl ON t.id_tropa = cl.id_tropa AND DATE(cl.fecha) = ?
    WHERE t.activo = 1 AND cl.id_consumo IS NULL
");
$stmt->execute([$hoy]);
$lotes_sin_alimentar = $stmt->fetch()['total'];

// ===========================================
// LOTES ACTIVOS (Top 5)
// ===========================================
$stmt = $db->query("
    SELECT 
        t.id_tropa,
        t.nombre,
        c.nombre as campo,
        t.cantidad_inicial as animales,
        d.nombre as dieta,
        (SELECT peso_promedio FROM pesada WHERE id_tropa = t.id_tropa ORDER BY fecha DESC LIMIT 1) as ultimo_peso,
        (SELECT DATE(fecha) FROM pesada WHERE id_tropa = t.id_tropa ORDER BY fecha DESC LIMIT 1) as fecha_peso,
        DATEDIFF(CURDATE(), t.fecha_inicio) as dias_feedlot
    FROM tropa t
    LEFT JOIN campo c ON t.id_campo = c.id_campo
    LEFT JOIN tropa_dieta_asignada tda ON t.id_tropa = tda.id_tropa AND tda.fecha_hasta IS NULL
    LEFT JOIN dieta d ON tda.id_dieta = d.id_dieta
    WHERE t.activo = 1
    ORDER BY t.fecha_inicio DESC
    LIMIT 5
");
$lotes_activos = $stmt->fetchAll();

// ===========================================
// ÚLTIMAS ALIMENTACIONES (5)
// ===========================================
$stmt = $db->query("
    SELECT 
        cl.fecha,
        cl.hora,
        t.nombre as lote,
        cl.kg_totales_tirados,
        cl.animales_presentes,
        u.nombre as operario
    FROM consumo_lote cl
    INNER JOIN tropa t ON cl.id_tropa = t.id_tropa
    LEFT JOIN usuario u ON cl.id_usuario = u.id_usuario
    ORDER BY cl.fecha DESC, cl.hora DESC
    LIMIT 5
");
$ultimas_alimentaciones = $stmt->fetchAll();

// ===========================================
// DATOS PARA GRÁFICOS
// ===========================================

// Gráfico 1: Evolución de peso (últimos 30 días)
$stmt = $db->query("
    SELECT 
        DATE(p.fecha) as fecha,
        AVG(p.peso_promedio) as peso_promedio
    FROM pesada p
    INNER JOIN tropa t ON p.id_tropa = t.id_tropa
    WHERE t.activo = 1
    AND p.fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(p.fecha)
    ORDER BY fecha ASC
");
$datos_peso = $stmt->fetchAll();

// Gráfico 2: Consumo de MS últimos 7 días
$stmt = $db->query("
    SELECT 
        DATE(cl.fecha) as fecha,
        SUM(
            (SELECT SUM(cld.kg_ms)
             FROM consumo_lote_detalle cld
             WHERE cld.id_consumo = cl.id_consumo)
        ) as ms_total
    FROM consumo_lote cl
    WHERE cl.fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(cl.fecha)
    ORDER BY fecha ASC
");
$datos_ms = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<div class="dashboard-container">
    <h1 class="titulo-dashboard">📊 Dashboard - Panel de Control</h1>

    <!-- ALERTAS -->
    <?php if ($lotes_sin_dieta > 0 || $ajustes_pendientes > 0 || $lotes_sin_alimentar > 0): ?>
        <div class="alertas-container">
            <?php if ($lotes_sin_alimentar > 0): ?>
                <div class="alerta">
                    <div class="icono">⚠️</div>
                    <div class="contenido">
                        <strong><?php echo $lotes_sin_alimentar; ?> lote(s) sin alimentar hoy</strong>
                        Hay lotes que aún no recibieron alimentación.
                        <a href="lotes/listar.php">Ver lotes →</a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($lotes_sin_dieta > 0): ?>
                <div class="alerta">
                    <div class="icono">📋</div>
                    <div class="contenido">
                        <strong><?php echo $lotes_sin_dieta; ?> lote(s) sin dieta asignada</strong>
                        Algunos lotes no tienen dieta vigente.
                        <a href="lotes/listar.php">Asignar dietas →</a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($ajustes_pendientes > 0): ?>
                <div class="alerta critica">
                    <div class="icono">🔔</div>
                    <div class="contenido">
                        <strong><?php echo $ajustes_pendientes; ?> ajuste(s) de animales pendiente(s)</strong>
                        Hay diferencias detectadas en pesadas que requieren revisión.
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- INDICADORES PRINCIPALES -->
    <div class="indicadores">
        <div class="indicador">
            <div class="indicador-icono">🐮</div>
            <div class="indicador-valor"><?php echo $total_lotes; ?></div>
            <div class="indicador-label">Lotes Activos</div>
        </div>

        <div class="indicador">
            <div class="indicador-icono">🐄</div>
            <div class="indicador-valor"><?php echo number_format($total_animales); ?></div>
            <div class="indicador-label">Animales en Feedlot</div>
        </div>

        <div class="indicador">
            <div class="indicador-icono">🍽️</div>
            <div class="indicador-valor"><?php echo $alimentaciones_hoy; ?></div>
            <div class="indicador-label">Alimentaciones Hoy</div>
        </div>

        <div class="indicador">
            <div class="indicador-icono">⚖️</div>
            <div class="indicador-valor"><?php echo number_format($kg_hoy, 0); ?></div>
            <div class="indicador-label">Kg Entregados Hoy</div>
        </div>

        <div class="indicador">
            <div class="indicador-icono">📈</div>
            <div class="indicador-valor"><?php echo number_format($adpv_promedio, 2); ?></div>
            <div class="indicador-label">ADPV Promedio (kg/día)</div>
        </div>

        <div class="indicador">
            <div class="indicador-icono">🌾</div>
            <div class="indicador-valor"><?php echo number_format($cms_promedio, 2); ?></div>
            <div class="indicador-label">CMS Promedio (kg/día)</div>
        </div>
    </div>

    <!-- GRÁFICOS -->
    <div class="graficos">
        <div class="grafico-card">
            <h3>📊 Evolución de Peso (últimos 30 días)</h3>
            <canvas id="graficoPeso" height="200"></canvas>
        </div>

        <div class="grafico-card">
            <h3>🌾 Consumo de MS (últimos 7 días)</h3>
            <canvas id="graficoMS" height="200"></canvas>
        </div>
    </div>

    <!-- TABLA: LOTES ACTIVOS -->
    <div class="tabla-card">
        <h3>🐮 Lotes Activos</h3>
        <?php if (count($lotes_activos) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Lote</th>
                        <th>Campo</th>
                        <th>Animales</th>
                        <th>Dieta</th>
                        <th>Último Peso</th>
                        <th>Días en Feedlot</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lotes_activos as $lote): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($lote['nombre']); ?></strong></td>
                            <td><?php echo htmlspecialchars($lote['campo'] ?? '-'); ?></td>
                            <td><?php echo $lote['animales']; ?></td>
                            <td>
                                <?php if ($lote['dieta']): ?>
                                    <span class="badge badge-success"><?php echo htmlspecialchars($lote['dieta']); ?></span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Sin dieta</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($lote['ultimo_peso']): ?>
                                    <?php echo number_format($lote['ultimo_peso'], 0); ?> kg
                                    <small class="texto-secundario">(<?php echo date('d/m', strtotime($lote['fecha_peso'])); ?>)</small>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?php echo $lote['dias_feedlot']; ?> días</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="sin-datos">
                No hay lotes activos en este momento.
            </p>
        <?php endif; ?>
    </div>

    <!-- TABLA: ÚLTIMAS ALIMENTACIONES -->
    <div class="tabla-card">
        <h3>🍽️ Últimas Alimentaciones</h3>
        <?php if (count($ultimas_alimentaciones) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>Lote</th>
                        <th>Kg Totales</th>
                        <th>Animales</th>
                        <th>Kg/Animal</th>
                        <th>Operario</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ultimas_alimentaciones as $alim): ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($alim['fecha'])); ?></td>
                            <td><?php echo date('H:i', strtotime($alim['hora'])); ?></td>
                            <td><strong><?php echo htmlspecialchars($alim['lote']); ?></strong></td>
                            <td><?php echo number_format($alim['kg_totales_tirados'], 0); ?> kg</td>
                            <td><?php echo $alim['animales_presentes']; ?></td>
                            <td><?php echo number_format($alim['kg_totales_tirados'] / $alim['animales_presentes'], 2); ?> kg</td>
                            <td><?php echo htmlspecialchars($alim['operario'] ?? 'Sistema'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="sin-datos">
                No hay alimentaciones registradas recientemente.
            </p>
        <?php endif; ?>
    </div>
</div>

<!-- Scripts para gráficos -->
<script>
// Gráfico de Evolución de Peso
const ctxPeso = document.getElementById('graficoPeso').getContext('2d');
const graficoPeso = new Chart(ctxPeso, {
    type: 'line',
    data: {
        labels: [
            <?php 
            foreach ($datos_peso as $dato) {
                echo "'" . date('d/m', strtotime($dato['fecha'])) . "',";
            }
            ?>
        ],
        datasets: [{
            label: 'Peso Promedio (kg)',
            data: [
                <?php 
                foreach ($datos_peso as $dato) {
                    echo $dato['peso_promedio'] . ",";
                }
                ?>
            ],
            borderColor: '#2c5530',
            backgroundColor: 'rgba(44, 85, 48, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: false
            }
        }
    }
});

// Gráfico de Consumo de MS
const ctxMS = document.getElementById('graficoMS').getContext('2d');
const graficoMS = new Chart(ctxMS, {
    type: 'bar',
    data: {
        labels: [
            <?php 
            foreach ($datos_ms as $dato) {
                echo "'" . date('d/m', strtotime($dato['fecha'])) . "',";
            }
            ?>
        ],
        datasets: [{
            label: 'MS Total (kg)',
            data: [
                <?php 
                foreach ($datos_ms as $dato) {
                    echo $dato['ms_total'] . ",";
                }
                ?>
            ],
            backgroundColor: '#2c5530',
            borderRadius: 5
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>

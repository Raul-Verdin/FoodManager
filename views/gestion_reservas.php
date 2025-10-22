<?php
include '../includes/header.php'; 

$permission_needed = 'gestion_mesas'; 

if (!checkPermission($permission_needed)) {
    logActivity($_SESSION['user_id'], 'ACCESS DENIED', "Intento de acceso al CRUD de Reservas.");
    echo '<h1><i class="fas fa-lock"></i> Acceso Denegado</h1><p>Tu rol no puede acceder al CRUD de reservas.</p>';
    include '../includes/footer.php'; 
    exit;
}

$message = '';
$error_message = '';
$restaurante_id = $_SESSION['restaurante_actual_id'] ?? null;
$usuario_id = $_SESSION['user_id'];

if (!$restaurante_id) {
    echo '<h1>Gestión de Reservas</h1><p class="alert alert-warning">Por favor, selecciona un restaurante para gestionar las reservas.</p>';
    include '../includes/footer.php';
    exit;
}

// ----------------------------------------------------------------------
// Lógica de Procesamiento (CREATE & UPDATE RESERVAS)
// ----------------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';
    
    // --- CREAR NUEVA RESERVA ---
    if ($action == 'create_reserva') {
        $nombre = trim($_POST['nombre_cliente']);
        $tel = trim($_POST['telefono_cliente']);
        $fecha = trim($_POST['fecha_reserva']);
        $hora = trim($_POST['hora_reserva']);
        $personas = (int)$_POST['numero_personas'];

        if (empty($nombre) || empty($fecha) || empty($hora) || $personas <= 0) {
            $error_message = "Faltan campos obligatorios para crear la reserva.";
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO reservas (restaurante_id, nombre_cliente, telefono_cliente, fecha_reserva, hora_reserva, numero_personas)
                    VALUES (:rid, :nombre, :tel, :fecha, :hora, :personas)
                ");
                $stmt->execute([
                    ':rid' => $restaurante_id,
                    ':nombre' => $nombre,
                    ':tel' => $tel,
                    ':fecha' => $fecha,
                    ':hora' => $hora,
                    ':personas' => $personas
                ]);
                logActivity($usuario_id, 'RESERVA CREATE', "Reserva creada para {$nombre} el {$fecha} a las {$hora}");
                $message = "✅ Reserva creada exitosamente para **{$nombre}**.";
            } catch (PDOException $e) {
                $error_message = "Error al crear la reserva: " . $e->getMessage();
            }
        }
    }

    // --- ACTUALIZAR ESTADO DE RESERVA ---
    if ($action == 'update_reserva_status') {
        $reserva_id = (int)$_POST['reserva_id'];
        $nuevo_estado = trim($_POST['nuevo_estado']);

        if (!in_array($nuevo_estado, ['CONFIRMADA', 'SENTADA', 'CANCELADA', 'EXPIRADA'])) {
            $error_message = 'Estado de reserva inválido.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE reservas SET estado = :estado WHERE id = :rid AND restaurante_id = :restid
                ");
                $stmt->execute([
                    ':estado' => $nuevo_estado,
                    ':rid' => $reserva_id,
                    ':restid' => $restaurante_id
                ]);

                // Lógica de asignación/liberación de mesa al "SENTAR"
                if ($nuevo_estado === 'SENTADA') {
                    $mesa_id = (int)$_POST['mesa_id'] ?? null;
                    if ($mesa_id) {
                         // 1. Asignar la mesa a la reserva
                        $stmt_assign = $pdo->prepare("UPDATE reservas SET mesa_id = :mid WHERE id = :rid");
                        $stmt_assign->execute([':mid' => $mesa_id, ':rid' => $reserva_id]);
                        
                         // 2. Marcar la mesa como OCUPADA
                        $stmt_table = $pdo->prepare("UPDATE mesas SET estado = 'OCUPADA' WHERE id = :mid AND restaurante_id = :restid");
                        $stmt_table->execute([':mid' => $mesa_id, ':restid' => $restaurante_id]);
                    }
                }
                
                logActivity($usuario_id, 'RESERVA UPDATE', "Reserva ID: {$reserva_id} cambiada a {$nuevo_estado}");
                $message = "✅ Reserva #{$reserva_id} actualizada a {$nuevo_estado}.";

            } catch (PDOException $e) {
                $error_message = "Error al actualizar estado: " . $e->getMessage();
            }
        }
    }
}

// ----------------------------------------------------------------------
// Lógica de Lectura (READ: Reservas Futuras)
// ----------------------------------------------------------------------
$reservas_futuras = [];
try {
    $hoy = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT 
            r.*, m.numero AS mesa_numero 
        FROM reservas r
        LEFT JOIN mesas m ON r.mesa_id = m.id
        WHERE r.restaurante_id = :rid AND r.fecha_reserva >= :hoy
        ORDER BY r.fecha_reserva ASC, r.hora_reserva ASC
    ");
    $stmt->execute([':rid' => $restaurante_id, ':hoy' => $hoy]);
    $reservas_futuras = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = "Error al cargar la lista de reservas: " . $e->getMessage();
}

// Obtener mesas libres para el modal de 'Sentar'
$mesas_libres = [];
try {
    $stmt_mesas = $pdo->prepare("SELECT id, numero, capacidad FROM mesas WHERE restaurante_id = :rid AND estado = 'LIBRE' ORDER BY capacidad DESC, numero ASC");
    $stmt_mesas->execute([':rid' => $restaurante_id]);
    $mesas_libres = $stmt_mesas->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Manejo de error de mesas
}

?>

<h1>Gestión de Reservas - <?php echo getRestaurantName($restaurante_id); ?></h1>

<div class="messages">
    <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error_message): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>
</div>

<button type="button" class="btn btn-success mb-4" data-toggle="modal" data-target="#newReservaModal">
  <i class="fas fa-plus"></i> Nueva Reserva
</button>

<h2 class="mt-4">Reservas Futuras y de Hoy (Pendientes/Confirmadas)</h2>

<div class="table-responsive">
    <table class="table table-striped table-hover">
        <thead>
            <tr>
                <th>ID</th>
                <th>Cliente</th>
                <th>Fecha/Hora</th>
                <th>Personas</th>
                <th>Mesa Asignada</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($reservas_futuras)): ?>
                <tr>
                    <td colspan="7" class="text-center">No hay reservas futuras registradas.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($reservas_futuras as $reserva): ?>
                    <tr class="reserva-status-<?php echo strtolower($reserva['estado']); ?>">
                        <td><?php echo $reserva['id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($reserva['nombre_cliente']); ?></strong>
                            <small class="d-block text-muted"><?php echo htmlspecialchars($reserva['telefono_cliente']); ?></small>
                        </td>
                        <td><?php echo date('d/M/Y', strtotime($reserva['fecha_reserva'])) . ' a las ' . date('H:i', strtotime($reserva['hora_reserva'])); ?></td>
                        <td><?php echo $reserva['numero_personas']; ?></td>
                        <td><?php echo $reserva['mesa_numero'] ? 'Mesa ' . $reserva['mesa_numero'] : 'N/A'; ?></td>
                        <td><span class="badge badge-<?php echo get_reserva_badge_class($reserva['estado']); ?>"><?php echo str_replace('_', ' ', $reserva['estado']); ?></span></td>
                        <td>
                            <?php if ($reserva['estado'] === 'PENDIENTE'): ?>
                                <form action="" method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="update_reserva_status">
                                    <input type="hidden" name="reserva_id" value="<?php echo $reserva['id']; ?>">
                                    <input type="hidden" name="nuevo_estado" value="CONFIRMADA">
                                    <button type="submit" class="btn btn-sm btn-info" title="Confirmar Reserva"><i class="fas fa-check"></i></button>
                                </form>
                            <?php endif; ?>
                            
                            <?php if (in_array($reserva['estado'], ['PENDIENTE', 'CONFIRMADA']) && $reserva['fecha_reserva'] == $hoy): ?>
                                <button type="button" class="btn btn-sm btn-success" data-toggle="modal" data-target="#seatReservaModal" 
                                    data-reserva-id="<?php echo $reserva['id']; ?>" 
                                    data-num-personas="<?php echo $reserva['numero_personas']; ?>"
                                    title="Sentar Cliente">
                                    <i class="fas fa-chair"></i> Sentar
                                </button>
                            <?php endif; ?>

                            <?php if (in_array($reserva['estado'], ['PENDIENTE', 'CONFIRMADA'])): ?>
                                <form action="" method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="update_reserva_status">
                                    <input type="hidden" name="reserva_id" value="<?php echo $reserva['id']; ?>">
                                    <input type="hidden" name="nuevo_estado" value="CANCELADA">
                                    <button type="submit" class="btn btn-sm btn-danger" title="Cancelar Reserva"><i class="fas fa-times"></i></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="modal fade" id="newReservaModal" tabindex="-1" role="dialog" aria-labelledby="newReservaModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="newReservaModalLabel">Crear Nueva Reserva</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form method="POST" action="">
        <div class="modal-body">
            <input type="hidden" name="action" value="create_reserva">
            
            <div class="form-group">
                <label for="nombre_cliente">Nombre Cliente</label>
                <input type="text" class="form-control" name="nombre_cliente" required>
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="fecha_reserva">Fecha</label>
                    <input type="date" class="form-control" name="fecha_reserva" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group col-md-6">
                    <label for="hora_reserva">Hora</label>
                    <input type="time" class="form-control" name="hora_reserva" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="numero_personas">Número de Personas</label>
                    <input type="number" class="form-control" name="numero_personas" required min="1">
                </div>
                <div class="form-group col-md-6">
                    <label for="telefono_cliente">Teléfono (Opcional)</label>
                    <input type="text" class="form-control" name="telefono_cliente">
                </div>
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
          <button type="submit" class="btn btn-success">Guardar Reserva</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="seatReservaModal" tabindex="-1" role="dialog" aria-labelledby="seatReservaModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="seatReservaModalLabel">Sentar Cliente</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form method="POST" action="">
        <div class="modal-body">
            <input type="hidden" name="action" value="update_reserva_status">
            <input type="hidden" name="nuevo_estado" value="SENTADA">
            <input type="hidden" name="reserva_id" id="seat_reserva_id">
            <p>Clientes de **<span id="seat_num_personas"></span>** personas.</p>
            
            <div class="form-group">
                <label for="mesa_id">Seleccionar Mesa Libre</label>
                <select class="form-control" name="mesa_id" required>
                    <?php if (empty($mesas_libres)): ?>
                        <option value="">-- No hay mesas libres disponibles --</option>
                    <?php else: ?>
                        <option value="">-- Seleccione una mesa --</option>
                        <?php foreach ($mesas_libres as $mesa): ?>
                            <option value="<?php echo $mesa['id']; ?>">Mesa #<?php echo $mesa['numero']; ?> (Cap: <?php echo $mesa['capacidad']; ?>)</option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
          <button type="submit" class="btn btn-success" <?php echo empty($mesas_libres) ? 'disabled' : ''; ?>>Sentar y Ocupar Mesa</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
    // Script para pasar datos al modal de Sentar
    $('#seatReservaModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget); 
        var reservaId = button.data('reserva-id'); 
        var numPersonas = button.data('num-personas');
        
        var modal = $(this);
        modal.find('#seat_reserva_id').val(reservaId);
        modal.find('#seat_num_personas').text(numPersonas);
        
        // Opcional: auto-seleccionar la mejor mesa libre por capacidad
        // Implementación requeriría más lógica de JS/AJAX para ser perfecta
    });
</script>

<?php 
function get_reserva_badge_class($estado) {
    switch ($estado) {
        case 'PENDIENTE':
            return 'primary';
        case 'CONFIRMADA':
            return 'info';
        case 'SENTADA':
            return 'success';
        case 'CANCELADA':
            return 'danger';
        case 'EXPIRADA':
            return 'secondary';
        default:
            return 'dark';
    }
}
?>

<style>
.reserva-status-pendiente { background-color: #f0f8ff; }
.reserva-status-confirmada { background-color: #fcf8e3; }
.reserva-status-sentada { background-color: #dff0d8; }
.reserva-status-cancelada, .reserva-status-expirada { text-decoration: line-through; color: #999; }
</style>

<?php include '../includes/footer.php'; ?>
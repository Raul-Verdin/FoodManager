<?php
require_once '../includes/auth.php';
requireLogin(); // Solo pedimos que esté logueado
include('../includes/header.php');
// ControlMesas.php
// Página única que pregunta cuántas mesas y cuantos cupos por mesa y genera una vista visual
// Guarda los datos en sesión temporal para mantener la vista entre recargas (no persistente)
session_start();

// Manejo del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    if ($action === 'create') {
        $numMesas = max(1, intval($_POST['numMesas'] ?? 0));
        $cupos = max(1, intval($_POST['cupos'] ?? 1));
        // Crear estructura básica de mesas (ninguna ocupada inicialmente)
        $mesas = [];
        for ($i = 1; $i <= $numMesas; $i++) {
            $mesas[] = [
                'id' => $i,
                'cupos' => $cupos,
                'ocupados' => 0
            ];
        }
        $_SESSION['mesas'] = $mesas;
    } elseif ($action === 'reset') {
        unset($_SESSION['mesas']);
    } elseif ($action === 'saveState') {
        // Guardar estado enviado por AJAX (JSON)
        $payload = json_decode(file_get_contents('php://input'), true);
        if (isset($payload['mesas'])) {
            $_SESSION['mesas'] = $payload['mesas'];
            header('Content-Type: application/json');
            echo json_encode(['status' => 'ok']);
            exit;
        }
    }
}

$mesas = $_SESSION['mesas'] ?? null;
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Control de Mesas - Restaurante</title>
  <style>
    :root{--bg:#f5f7fb;--card:#ffffff;--accent:#2b8cff;--busy:#ff6b6b;--ok:#4caf50}
    body{font-family:Inter, system-ui, Arial, sans-serif;background:var(--bg);margin:0;padding:24px;color:#222}
    .container{max-width:1100px;margin:0 auto}
    .card{background:var(--card);padding:18px;border-radius:12px;box-shadow:0 6px 18px rgba(20,30,50,0.06)}
    h1{margin:0 0 12px;font-size:20px}
    form.row{display:flex;gap:12px;flex-wrap:wrap;align-items:end}
    label{font-size:14px}
    input[type=number]{padding:8px;border-radius:8px;border:1px solid #ddd;width:120px}
    button{background:var(--accent);color:#fff;border:none;padding:10px 14px;border-radius:8px;cursor:pointer}
    button.secondary{background:#6c757d}

    /* Vista de mesas */
    .layout{margin-top:18px;display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:14px}
    .mesa{background:linear-gradient(180deg,#fff,#f8fbff);border-radius:12px;padding:12px;text-align:center;position:relative;box-shadow:0 6px 14px rgba(30,40,60,0.06);cursor:pointer;transition:transform .14s}
    .mesa:hover{transform:translateY(-6px)}
    .mesa .circle{width:64px;height:64px;border-radius:50%;margin:0 auto 8px;background:radial-gradient(circle at 30% 20%, #fff, #e6f0ff);display:flex;align-items:center;justify-content:center;font-weight:700;color:#123}
    .mesa.busy .circle{background:linear-gradient(180deg,var(--busy),#ff4a4a);color:#fff}
    .mesa .meta{font-size:13px;color:#444}
    .badge{position:absolute;top:10px;right:10px;background:var(--accent);color:#fff;padding:6px 8px;border-radius:999px;font-size:12px}
    .badge.small{padding:4px 6px;font-size:11px;background:#999}
    .controls{display:flex;gap:8px;justify-content:flex-end;margin-top:12px}

    /* Panel derecho resumen */
    .grid{display:grid;grid-template-columns:1fr 320px;gap:18px;margin-top:18px}
    .summary{padding:12px;border-radius:10px;background:#fff}
    .summary h3{margin:0 0 8px}
    .list{margin:8px 0;padding:0;list-style:none}
    .list li{padding:6px 0;border-bottom:1px dashed #eee}

    footer{margin-top:18px;text-align:center;color:#666;font-size:13px}
    @media (max-width:900px){.grid{grid-template-columns:1fr}}
  </style>
</head>
<body>
  <div class="container">
    <div class="card">
      <h1>Control de Mesas - Restaurante</h1>
      <p style="margin:0 0 12px;color:#555">Ingrese la cantidad de mesas y los cupos por mesa. Se generará una vista visual donde puede marcar asientos ocupados.</p>

      <?php if (!$mesas): ?>
        <form method="post" class="row card-form">
          <input type="hidden" name="action" value="create">
          <label>
            <div>Cantidad de mesas</div>
            <input type="number" name="numMesas" min="1" value="6" required>
          </label>
          <label>
            <div>Cupos por mesa</div>
            <input type="number" name="cupos" min="1" value="4" required>
          </label>
          <div style="display:flex;gap:8px;align-items:center">
            <button type="submit">Generar vista</button>
            <button type="submit" formaction="?" formmethod="post" name="action" value="reset" class="secondary">Reset</button>
          </div>
        </form>
      <?php else: ?>
        <div class="grid">
          <div>
            <div style="display:flex;justify-content:space-between;align-items:center">
              <h2 style="margin:0">Mesas generadas (<?php echo count($mesas); ?>)</h2>
              <div>
                <form method="post" style="display:inline">
                  <input type="hidden" name="action" value="reset">
                  <button class="secondary">Nueva configuración</button>
                </form>
              </div>
            </div>

            <div class="layout" id="layout">
              <?php foreach ($mesas as $m): ?>
                <div class="mesa" data-id="<?php echo $m['id']; ?>" data-cupos="<?php echo $m['cupos']; ?>">
                  <div class="circle">T<?php echo $m['id']; ?></div>
                  <div class="meta">Cupos: <strong><?php echo $m['cupos']; ?></strong></div>
                  <div class="meta">Ocupados: <span class="ocupados"><?php echo $m['ocupados']; ?></span></div>
                  <div class="badge small">Ver</div>
                </div>
              <?php endforeach; ?>
            </div>

            <div style="margin-top:12px;display:flex;gap:8px">
              <button id="toggleSeats">Ver/Asignar Asientos</button>
              <button id="saveState" class="secondary">Guardar estado</button>
            </div>
          </div>

          <aside class="summary card">
            <h3>Resumen</h3>
            <p id="resumenTotal">Mesas: <?php echo count($mesas); ?> · Total cupos: <span id="totalCupos">0</span> · Total ocupados: <span id="totalOcupados">0</span></p>
            <h4 style="margin-top:12px;margin-bottom:6px">Detalle de mesas</h4>
            <ul id="detalle" class="list"></ul>
          </aside>
        </div>

        <!-- Modal/Panel seats (dinámico) -->
        <div id="seatPanel" style="display:none;padding:12px;background:#fff;border-radius:10px;margin-top:12px;box-shadow:0 8px 20px rgba(10,20,30,0.06)"></div>

      <?php endif; ?>
    </div>

    <footer>Desarrollado - Interfaz simple para control visual de mesas. (Archivo PHP único)</footer>
  </div>

<script>
// Lógica en cliente para interactividad
(function(){
  const layout = document.getElementById('layout');
  if (!layout) return;

  // Construir estado inicial a partir del DOM
  function leerMesasDesdeDOM(){
    const nodes = [...layout.querySelectorAll('.mesa')];
    return nodes.map(node => ({
      id: parseInt(node.dataset.id,10),
      cupos: parseInt(node.dataset.cupos,10),
      ocupados: parseInt(node.querySelector('.ocupados').textContent,10) || 0
    }));
  }

  let mesas = leerMesasDesdeDOM();
  let asignarAsientosMode = false;

  function renderResumen(){
    const totalCupos = mesas.reduce((s,m)=> s + m.cupos,0);
    const totalOcupados = mesas.reduce((s,m)=> s + m.ocupados,0);
    document.getElementById('totalCupos').textContent = totalCupos;
    document.getElementById('totalOcupados').textContent = totalOcupados;

    const detalle = document.getElementById('detalle');
    detalle.innerHTML = '';
    mesas.forEach(m => {
      const li = document.createElement('li');
      li.textContent = 'Mesa ' + m.id + ': ' + m.ocupados + ' / ' + m.cupos + ' ocupados';
      detalle.appendChild(li);

      // actualizar DOM de la mesa
      const node = layout.querySelector('.mesa[data-id="'+m.id+'"]');
      if(node){
        node.querySelector('.ocupados').textContent = m.ocupados;
        if(m.ocupados > 0) node.classList.add('busy'); else node.classList.remove('busy');
      }
    });
  }

  // Al hacer click en una mesa
  layout.addEventListener('click', function(ev){
    const mesaNode = ev.target.closest('.mesa');
    if(!mesaNode) return;
    const id = parseInt(mesaNode.dataset.id,10);
    const mesa = mesas.find(x=>x.id===id);
    if(!mesa) return;

    if(asignarAsientosMode){
      // abrir panel de asientos
      abrirPanelAsignacion(mesa);
    } else {
      // Alternar marca ocupada si hay al menos 1 ocupado posible
      mesa.ocupados = (mesa.ocupados === mesa.cupos) ? 0 : mesa.cupos;
      renderResumen();
    }
  });

  function abrirPanelAsignacion(mesa){
    const panel = document.getElementById('seatPanel');
    panel.style.display = 'block';
    panel.innerHTML = '';
    const title = document.createElement('h3');
    title.textContent = 'Mesa ' + mesa.id + ' - Asignación de asientos';
    panel.appendChild(title);

    const info = document.createElement('p');
    info.textContent = 'Cupos: ' + mesa.cupos + ' · Ocupados: ' + mesa.ocupados;
    panel.appendChild(info);

    const seats = document.createElement('div');
    seats.style.display='flex';seats.style.gap='8px';seats.style.flexWrap='wrap';seats.style.marginTop='8px';

    for(let s=1; s<=mesa.cupos; s++){
      const btn = document.createElement('button');
      btn.textContent = s;
      btn.style.width='44px';btn.style.height='44px';btn.style.borderRadius='8px';btn.style.border='1px solid #ddd';
      btn.style.cursor='pointer';
      if(s <= mesa.ocupados) { btn.style.backgroundColor='var(--busy)'; btn.style.color='#fff'; }
      btn.addEventListener('click', ()=>{
        // togglear este asiento: si s <= ocupados -> liberar, sino asignar (asignamos secuencialmente)
        if(s <= mesa.ocupados){
          // liberar uno (disminuir ocupados en 1)
          mesa.ocupados = Math.max(0, mesa.ocupados - 1);
        } else {
          // asignar uno más
          mesa.ocupados = Math.min(mesa.cupos, mesa.ocupados + 1);
        }
        abrirPanelAsignacion(mesa); // re-render panel
        renderResumen();
      });
      seats.appendChild(btn);
    }

    panel.appendChild(seats);

    const close = document.createElement('div');
    close.style.marginTop='10px';
    const done = document.createElement('button'); done.textContent='Cerrar'; done.style.marginRight='8px';
    done.addEventListener('click', ()=>{ panel.style.display='none'; });
    close.appendChild(done);

    const reset = document.createElement('button'); reset.textContent='Liberar mesa'; reset.className='secondary';
    reset.addEventListener('click', ()=>{ mesa.ocupados = 0; renderResumen(); panel.style.display='none'; });
    close.appendChild(reset);

    panel.appendChild(close);
  }

  document.getElementById('toggleSeats').addEventListener('click', function(){
    asignarAsientosMode = !asignarAsientosMode;
    this.textContent = asignarAsientosMode ? 'Modo: Asignar asientos (ON)' : 'Ver/Asignar Asientos';
  });

  document.getElementById('saveState').addEventListener('click', function(){
    fetch(window.location.href, {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ action: 'saveState', mesas: mesas })
    }).then(r=>r.json()).then(j=>{
      alert('Estado guardado en sesión');
    }).catch(e=>{ alert('Error al guardar'); });
  });

  renderResumen();
})();
</script>
</body>
</html>

<?php include('../includes/footer.php'); ?>

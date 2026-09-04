<?php
$pageTitle = "Dashboard Operativo";
ob_start();
?>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="container my-4">

  <!-- ✅ KPIs Cards -->
  <div class="row text-center mb-4" id="home-kpis">
    <!-- Se genera dinámicamente -->
  </div>

  <!-- ✅ Chart Avance Diario -->
  <div class="card mb-4">
    <div class="card-body">
      <h5 class="card-title text-primary">Avance Diario (Distribuido vs Armado)</h5>
      <canvas id="avanceDiarioChart" height="100"></canvas>
    </div>
  </div>

  <!-- ✅ Chart Avance por Jefatura -->
  <div class="card mb-4">
    <div class="card-body">
      <h5 class="card-title text-primary">Armado por Jefatura</h5>
      <canvas id="avanceJefaturaChart" height="100"></canvas>
    </div>
  </div>

  <!-- ✅ Tabla de Reportados recientes -->
  <div class="card mb-4">
    <div class="card-body">
      <h5 class="card-title text-danger">Últimos Registros Reportados</h5>
      <div class="table-responsive">
        <table class="table table-bordered">
          <thead><tr><th>Táctico</th><th>Gestor</th><th>PDV</th><th>Motivo</th><th>Fecha</th></tr></thead>
          <tbody id="tablaReportados"></tbody>
        </table>
      </div>
    </div>
  </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  cargarDashboardHome();
});

async function cargarDashboardHome() {
  const mes = new Date().getMonth() + 1;
  const anio = new Date().getFullYear();
  const tipo = "false"; // Puedes adaptarlo si quieres selector de tipo
  
  const avanceResp = await fetch(`../get_avances.php?es_adicional=${tipo}&mes=${mes}&anio=${anio}`);
  const avanceData = await avanceResp.json();

  const inspeccionResp = await fetch(`../get_inspeccion_data_new.php?es_adicional=${tipo}`);
  const inspeccionData = await inspeccionResp.json();

  renderKPIsHome(avanceData.data, inspeccionData);
  renderAvanceDiarioChart(avanceData.data);
  renderAvanceJefaturaChart(avanceData.data);
  renderTablaReportados(inspeccionData.reportes);
}

function renderKPIsHome(avances, inspeccion) {
  let distrib = 0, armado = 0, recursos = 0;
  for (const tipo in avances) {
    avances[tipo].forEach(r => {
      distrib += parseFloat(r.cantidad_distribuida || 0);
      armado += parseFloat(r.cantidad_armada || 0);
    });
  }
  const avanceGlobal = distrib === 0 ? 0 : ((armado / distrib) * 100).toFixed(2);
  const kpis = `
    <div class="col-md-2 mb-3"><div class="card p-3 shadow-sm border-0"><h6>Distribuido</h6><h3 class="text-primary">${distrib}</h3></div></div>
    <div class="col-md-2 mb-3"><div class="card p-3 shadow-sm border-0"><h6>Armado</h6><h3 class="text-success">${armado}</h3></div></div>
    <div class="col-md-2 mb-3"><div class="card p-3 shadow-sm border-0"><h6>Avance (%)</h6><h3 class="text-warning">${avanceGlobal}%</h3></div></div>
    <div class="col-md-2 mb-3"><div class="card p-3 shadow-sm border-0"><h6>Reportados</h6><h3 class="text-danger">${inspeccion.reportes.length}</h3></div></div>
    <div class="col-md-2 mb-3"><div class="card p-3 shadow-sm border-0"><h6>Validados</h6><h3 class="text-success">${inspeccion.validaciones.length}</h3></div></div>
    <div class="col-md-2 mb-3"><div class="card p-3 shadow-sm border-0"><h6>Corregidos</h6><h3 class="text-info">${inspeccion.correjidos.length}</h3></div></div>
  `;
  document.getElementById('home-kpis').innerHTML = kpis;
}

function renderAvanceDiarioChart(avances) {
  const dias = {};
  for (const tipo in avances) {
    avances[tipo].forEach(row => {
      const dia = row.fecha ? row.fecha.split('-')[2] : '1';
      if (!dias[dia]) dias[dia] = { distrib: 0, armado: 0 };
      dias[dia].distrib += parseFloat(row.cantidad_distribuida || 0);
      dias[dia].armado += parseFloat(row.cantidad_armada || 0);
    });
  }
  const labels = Object.keys(dias).sort((a,b) => a-b);
  const distribuidos = labels.map(d => dias[d].distrib);
  const armados = labels.map(d => dias[d].armado);

  new Chart(document.getElementById('avanceDiarioChart'), {
    type: 'line',
    data: {
      labels,
      datasets: [
        { label: "Distribuido", data: distribuidos, borderColor: "#5a3d8a", backgroundColor: "#5a3d8a44", tension: 0.4, fill: true },
        { label: "Armado", data: armados, borderColor: "#28a745", backgroundColor: "#28a74544", tension: 0.4, fill: true }
      ]
    },
    options: {
      responsive: true,
      plugins: { legend: { position: 'top' } },
      interaction: { mode: 'index', intersect: false },
      scales: { y: { beginAtZero: true } }
    }
  });
}

function renderAvanceJefaturaChart(avances) {
  const jefaturas = {};
  if (avances.ejecutivo) {
    avances.ejecutivo.forEach(row => {
      const j = row.jefatura || 'Sin Jefatura';
      if (!jefaturas[j]) jefaturas[j] = 0;
      jefaturas[j] += parseFloat(row.cantidad_armada || 0);
    });
  }
  const labels = Object.keys(jefaturas);
  const data = labels.map(l => jefaturas[l]);

  new Chart(document.getElementById('avanceJefaturaChart'), {
    type: 'bar',
    data: {
      labels,
      datasets: [{
        label: "Armado",
        data,
        backgroundColor: labels.map(() => "#5a3d8a88"),
        borderColor: labels.map(() => "#5a3d8a"),
        borderWidth: 1
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true } }
    }
  });
}

function renderTablaReportados(reportes) {
  const tabla = reportes.map(r =>
    `<tr><td>${r.tactico}</td><td>${r.gestor}</td><td>${r.pdv}</td><td>${r.motivo}</td><td>${r.fecha_creacion}</td></tr>`
  ).join('');
  document.getElementById('tablaReportados').innerHTML = tabla || `<tr><td colspan="5" class="text-center">Sin datos</td></tr>`;
}
</script>

<?php
$content = ob_get_clean();
include '../layout.php';
?>

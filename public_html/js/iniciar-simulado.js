function iniciarSimulado() {
  const jsonSimulado = {
    "tipo_reforma": "cambio_bañera_por_plato_ducha",
    "medidas_bañera": {
      "largo_cm": 140,
      "ancho_cm": 70
    },
    "entre_paredes": true,
    "zona_azulejos": {
      "derribo": true,
      "altura_reforma": "1m",
      "reponer_azulejos": "buscar_similar"
    },
    "griferia": {
      "mantener_grifo_actual": true,
      "instalar_barra_ducha": false
    },
    "mampara": {
      "tipo": "fijo_mas_corredera"
    }
  };

  document.getElementById('selector-reforma').style.display = 'none';
  document.getElementById('chat-box').style.display = 'block';
  chatStarted = true;

  appendMessage("Has elegido cambiar la bañera por un plato de ducha.", 'system');

  const jsonStr = JSON.stringify(jsonSimulado, null, 2);
  chatHistory = [
    { role: 'system', content: 'Simulación de presupuesto de ducha' },
    { role: 'assistant', content: jsonStr }
  ];
  appendMessage(`<pre>${jsonStr}</pre>`, 'ai');

  window.ultimoPresupuestoJson = jsonSimulado;

  fetch(RUTA_CALCULAR, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(jsonSimulado)
  })
  .then(res => res.json())
  .then(presupuesto => {
    window.ultimoPresupuestoJson = {
      ...jsonSimulado,
      ...presupuesto
    };

    const texto = `
💼 <strong>Presupuesto estimado</strong><br>
- Mano de obra: <strong>${presupuesto.mano_obra} €</strong><br>
- Materiales: <strong>${presupuesto.materiales} €</strong><br>
- Total estimado: <strong>${presupuesto.total_estimado_min} €</strong> a <strong>${presupuesto.total_estimado_max} €</strong><br><br>
📄 <a href="#" onclick="descargarPdfPresupuesto()" class="btn btn-sm btn-outline-primary">Descargar PDF</a>
    `;
    appendMessage(texto, 'ai');
  })
  .catch(() => {
    appendMessage("❌ Error al calcular el presupuesto.", 'ai');
  });
}
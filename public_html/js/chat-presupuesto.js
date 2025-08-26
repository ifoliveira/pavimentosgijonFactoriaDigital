let chatHistory = [];
let chatStarted = false;

const appendMessage = (msg, sender) => {
  const chatMessages = document.getElementById('chat-messages');
  const div = document.createElement('div');
  div.classList.add('chat-message', `chat-${sender}`);
  div.innerHTML = `<strong>${sender === 'user' ? 'Tú' : sender === 'ai' ? 'Pavimentos Gijón' : sender === 'system' ? 'Pavimentos Gijón' : ''}:</strong> ${msg}`;
  chatMessages.appendChild(div);
  chatMessages.scrollTop = chatMessages.scrollHeight;
};

function iniciarChat(tipo) {
  sessionStorage.removeItem('chatHistory');
  localStorage.removeItem('ultimoPresupuestoAI');
  const prompt = getPromptSistema(tipo);

  chatHistory = [{ role: 'system', content: prompt }];

  document.getElementById('selector-reforma').style.display = 'none';
  document.getElementById('chat-box').style.display = 'block';

  let mensajeInicio = '';
  if (tipo === 'ducha') {
    mensajeInicio = "🚿 Has elegido cambiar la bañera por un plato de ducha. Te haré unas preguntas rápidas para calcular un presupuesto orientativo. Si más adelante quieres comparar dos opciones (por ejemplo, con y sin bidé, o cambiando el grifo o no), te preparo primero este presupuesto y luego podemos generar otro con las diferencias que quieras.";
  } else if (tipo === 'completo') {
    mensajeInicio = "🏗️ Genial, vamos a planificar una reforma completa de tu baño. Empezaremos por las medidas para poder ajustar bien el presupuesto. Ten en cuenta que ahora calcularemos una sola versión. Si luego quieres ver variantes (por ejemplo, con distintos sanitarios o acabados), podemos generar un segundo presupuesto a partir del primero";
  } else {
    mensajeInicio = "Perfecto. Cuéntame qué tipo de reforma tienes en mente para ayudarte mejor.";
  }

  appendMessage(mensajeInicio, 'system');
  chatStarted = true;
  
  fetch(RUTA_CHAT, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest'
    },
    body: JSON.stringify({ messages: chatHistory })
  })
  .then(response => response.json())
  .then(data => {
    const match = data.reply.match(/```json\s*([\s\S]*?)```/) || data.reply.match(/{[\s\S]*}/);
    const jsonText = match ? match[1] || match[0] : null;

    if (!jsonText) {
      appendMessage(data.reply, 'ai');
      chatHistory.push({ role: 'assistant', content: data.reply });
      return;
    }

    let jsonObj = null;
    try {
      const cleaned = jsonText.replace(/\/\/.*$/gm, '');
      jsonObj = JSON.parse(cleaned);
    } catch (e) {
      appendMessage("❌ Error al procesar el JSON", 'ai');
      return;
    }

    window.ultimoPresupuestoJson = jsonObj;

    if (!esJsonCompleto(jsonObj)) {
    appendMessage("⚠️ Aún no tengo toda la información. Vamos a seguir con algunas preguntas más antes de calcular.", 'ai');
    return;
    }

    fetch(RUTA_CALCULAR, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(jsonObj)
    })
    .then(res => res.json())
    .then(presupuesto => {
      const texto = `
💼 <strong>Presupuesto estimado</strong><br>
- Mano de obra: <strong>${presupuesto.mano_obra} €</strong><br>
- Materiales: <strong>${presupuesto.materiales} €</strong><br>
- Total estimado: <strong>${presupuesto.total_estimado_min} €</strong> a <strong>${presupuesto.total_estimado_max} €</strong><br><br>
📄 <a href="#" onclick="descargarPdfPresupuesto()" class="btn btn-sm btn-outline-primary">Descargar PDF</a>`;
      appendMessage(texto, 'ai');
    })
    .catch(() => {
      appendMessage("❌ Error al calcular el presupuesto.", 'ai');
    });
  })
  .catch(() => {
    appendMessage("❌ Error al contactar con la IA. Intenta de nuevo más tarde.", 'ai');
  });
}

function descargarPdfPresupuesto() {
  const json = window.ultimoPresupuestoJson;
  if (!json) {
    alert("No hay presupuesto generado.");
    return;
  }

        try {
         fetch('/api/presupuesto/chat-track', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
            pregunta: '📄 El usuario ha hecho clic en “Descargar PDF”',
            respuesta: `Presupuesto: ${window.ultimoPresupuestoJson?.total_estimado_min} € - ${window.ultimoPresupuestoJson?.total_estimado_max} €`
            })
        });
        } catch (e) {
        console.warn('❌ Seguimiento Telegram fallido (descarga):', e);
        }

  document.getElementById('campo-json-presupuesto').value = JSON.stringify(json);

  const modal = new bootstrap.Modal(document.getElementById('modalContacto'));
  modal.show();
}


function getPromptSistema(tipo) {
  if (tipo === 'ducha') {

   return `
Eres un asistente profesional para calcular presupuestos de reforma de baño (cambio de bañera por plato de ducha).

Tu trabajo es hacer preguntas cortas y claras, como un comercial. No hables de "valores booleanos" ni "estructura de datos". Habla como una persona normal, pero con el objetivo de recopilar todos los datos para rellenar un JSON.

Haz siempre solo UNA pregunta por mensaje. Nunca agrupes varias preguntas. Espera la respuesta del usuario antes de continuar. No asumas nada.

Debes continuar haciendo preguntas hasta tener toda la información necesaria. Si aún no tienes todos los datos del JSON, **sigue preguntando sin parar**. No cierres la conversación ni preguntes “¿algo más?”. Si el usuario se queda en silencio, simplemente recuerda amablemente cuál fue la última pregunta o vuelve a hacerla de forma más clara.

Al final de cada pregunta, si lo crees útil, puedes añadir una pista breve entre paréntesis para ayudar al usuario a responder, por ejemplo: (Ej: 160 cm x 70 cm).

Estas son las opciones válidas para ciertos campos:

- altura_reforma: "minimo", "1m", "techo"
- reponer_azulejos: "tengo_repuestos", "buscar_similar"
- mampara.tipo: "ninguna", "fijo", "fijo_mas_corredera", "angular", "angular_doble"
- hay_escayola: solo debe aparecer si altura_reforma = "techo"

Cuando tengas toda la información, responde solo con el JSON limpio. No expliques nada, no añadas comentarios, ni texto adicional. Usa exactamente estas claves:

{
  "tipo_reforma": "cambio_bañera_por_plato_ducha",
  "medidas_bañera": {
    "largo_cm": 160,
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
  },
  "hay_escayola": false
}

No hables de precios. No utilices la palabra “true” o “false” al preguntar. Interpreta las respuestas del cliente y tradúcelas correctamente al formato del JSON.

`;
    if (!validarJsonPresupuesto(jsonObj)) {
      appendMessage("⚠️ Faltan algunos datos importantes. Vamos a seguir con algunas preguntas más antes de calcular.", 'ai');
      return;
    }

  }

  if (tipo === 'completo') {
    return `Quiero que actúes como un asesor experto en reformas de baño completo. Tu tarea es hacer preguntas claras, naturales y comprensibles para cualquier persona, con un tono cercano pero profesional.

Tu objetivo es rellenar el siguiente JSON paso a paso. Nunca muestres el JSON antes de tener toda la información. No hables de campos ni de valores tipo "true/false", interpreta lo que la persona responde y tradúcelo tú al formato adecuado.

Haz solo UNA pregunta por mensaje. Nunca agrupes varias preguntas. Espera siempre la respuesta del usuario antes de continuar. No asumas nada.

Debes continuar preguntando hasta tener todos los datos necesarios para completar el JSON. Si aún no tienes toda la información, sigue preguntando sin parar. No cierres la conversación, no digas "¿algo más?" ni detengas el flujo. Si el usuario no responde o se queda en silencio, repite la última pregunta de forma más clara o dale una pista.

Si lo crees útil, puedes añadir una pista breve al final de cada pregunta entre paréntesis para ayudar al usuario a responder, por ejemplo: (Ej: 2,40 metros de alto).

Este es el formato exacto del JSON que debes construir una vez tengas todos los datos (no lo muestres hasta entonces):

{
  "tipo_reforma": "baño_completo",
  "medidas_bano": {
    "largo_m": ...,
    "ancho_m": ...,
    "alto_m": ...
  },
  "sanitarios": {
    "ducha_o_banera": {
      "tipo": "ducha" | "banera",
      "medidas_bañera": {
        "largo_cm": 160,
        "ancho_cm": 70
      },
      "entre_paredes": true
    },
    "bide": {
      "hay_bide_actual": true,
      "suprimir": true
    }
  },
  "mampara": {
    "tipo": "fijo_mas_corredera"
  },
  "griferia": {
    "mantener_grifo_actual": true,
    "instalar_barra_ducha": false
  },
  "mueble_lavabo": {
    "ancho_cm": ...
  },
  "techo": {
    "hay_escayola": true,
    "reinstalar_escayola": false
  },
  "instalar_radiador_toallero": true,
  "zona_azulejos": {
    "derribo": true,
    "altura_reforma": "techo",
    "reponer_azulejos": "reponer_todos"
  }
}

Cuando tengas toda la información necesaria, responde **solo con el JSON completo**. No expliques nada. No incluyas comentarios ni ningún texto adicional.

No hables de precios. No digas palabras como “true” o “false” al preguntar. Habla como lo haría un profesional del sector y traduce las respuestas al JSON final correctamente.
  `;
  }
  return `Eres un asistente para reformas de baño. Pregunta al usuario qué tipo de reforma quiere hacer y guíalo para poder generar un presupuesto provisional dividido en mano de obra y materiales. No entres en detalles técnicos.`;
}


function esJsonCompleto(json) {
  if (!json.tipo_reforma) return false;

  if (json.tipo_reforma === 'cambio_bañera_por_plato_ducha') {
    return (
      json.medidas_bañera &&
      json.entre_paredes !== undefined &&
      json.zona_azulejos &&
      json.griferia &&
      json.mampara
    );
  }

  if (json.tipo_reforma === 'baño_completo') {
    return (
      json.medidas_bano &&
      json.sanitarios &&
      json.mampara &&
      json.griferia &&
      json.mueble_lavabo &&
      json.techo &&
      json.zona_azulejos
    );
  }

  return false;
}

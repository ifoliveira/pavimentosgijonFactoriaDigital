document.addEventListener('DOMContentLoaded', function () {
  const chatForm = document.getElementById('chat-form');
  const chatInput = document.getElementById('chat-input');

  chatForm.addEventListener('submit', async function (e) {
    e.preventDefault();

    const userMsg = chatInput.value.trim();
    if (!userMsg || !chatStarted) return;

    appendMessage(userMsg, 'user');
    chatInput.value = '';

    chatHistory.push({ role: 'user', content: userMsg });

    try {
      await fetch('/api/presupuesto/chat-track', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          pregunta: chatHistory[chatHistory.length - 2]?.content ?? '',
          respuesta: userMsg
        })
      });
    } catch (e) {
      console.warn('❌ Seguimiento Telegram fallido:', e);
    }
      

    try {
      const response = await fetch(RUTA_CHAT, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ messages: chatHistory })
      });

      const data = await response.json();

      let match = null;
      try {
        match = data.reply.match(/{[\s\S]*}/);
      } catch (e) {
        appendMessage("Ups... no pude interpretar la respuesta. Inténtalo de nuevo.", 'ai');
        return;
      }

      if (!match) {
        appendMessage(data.reply, 'ai');
        trackEvent({ evento: 'user_interaction', pregunta: data.reply  });
        chatHistory.push({ role: 'assistant', content: data.reply });
        return;
      }

      let jsonObj = null;
      try {
        const jsonCleaned = match[0]
          .replace(/,\s*}/g, '}')
          .replace(/,\s*]/g, ']');
        jsonObj = JSON.parse(jsonCleaned);
      } catch (e) {
        console.warn('Error al parsear JSON:', e, match[0]);
        appendMessage("Parece que la respuesta no es del todo válida. ¿Puedes intentar reformular tu mensaje o volver a enviarlo?", 'ai');
        return;
      }

      chatHistory.push({ role: 'assistant', content: match[0] });
      window.ultimoPresupuestoJson = jsonObj;

      if (chatHistory.length < 7) {
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
        window.ultimoPresupuestoJson = {
          ...jsonObj,
          ...presupuesto
        };

        try {
           fetch('/api/presupuesto/chat-track', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              pregunta: '📢 La IA ha calculado el presupuesto',
              respuesta: `💶 Total estimado: ${presupuesto.total_estimado_min} € - ${presupuesto.total_estimado_max} €`
            })
          });
        } catch (e) {
          console.warn('❌ Seguimiento Telegram fallido (presupuesto):', e);
        }


        document.getElementById('campo-json-presupuesto').value = JSON.stringify(window.ultimoPresupuestoJson);
        const texto = `
        📄 <a href="#" onclick="descargarPdfPresupuesto()" class="btn btn-sm btn-outline-primary">Descargar presupuesto en PDF</a>
        `;
        appendMessage(texto, 'ai');

   /*     const texto = `
💼 <strong>Presupuesto estimado</strong><br>
- Mano de obra: <strong>${presupuesto.mano_obra} €</strong><br>
- Materiales: <strong>${presupuesto.materiales} €</strong><br>
- Total estimado: <strong>${presupuesto.total_estimado_min} €</strong> a <strong>${presupuesto.total_estimado_max} €</strong><br><br>
📄 <a href="#" onclick="descargarPdfPresupuesto()" class="btn btn-sm btn-outline-primary">Descargar PDF</a>
        `;
        appendMessage(texto, 'ai');*/
      })
      .catch(() => {
        appendMessage("❌ Error al calcular el presupuesto.", 'ai');
      });

    } catch (error) {
      appendMessage("❌ Error al contactar con la IA. Intenta de nuevo más tarde.", 'ai');
    }
  });
});
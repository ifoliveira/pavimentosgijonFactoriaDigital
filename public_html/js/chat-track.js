async function trackUserResponse(pregunta, respuesta) {
  try {
    await fetch('/api/presupuesto/chat-track', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ pregunta, respuesta })
    });
  } catch (e) {
    console.warn('No se pudo enviar el seguimiento a Telegram.');
  }
}
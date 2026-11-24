document.addEventListener("DOMContentLoaded", function () {
  // Consolidar el saldo de cierre: los vencidos se sitúan en el primer día.
  const saldosPorDia = new Map();
  const inicio = gastosAcumulados[0]?.x;
  forecast.forEach(point => {
    const dia = point.x < inicio ? inicio : point.x;
    saldosPorDia.set(dia, point.y);
  });
  let saldo = forecast[0]?.y ?? bancoTotal;
  const saldoDiario = gastosAcumulados.map(point => {
    if (saldosPorDia.has(point.x)) saldo = saldosPorDia.get(point.x);
    return { x: point.x, y: saldo };
  });
  const referencias = [
    { nombre: "Efectivo", saldo: efectivoTotal, color: "#e7b3d1" },
    { nombre: "Banco", saldo: bancoTotal, color: "#8fb8df" },
    { nombre: "Banco + efectivo", saldo: bancoTotal + efectivoTotal, color: "#f2c48f" }
  ];
  const cruces = document.querySelector("#cruces-gasto");
  referencias.forEach(ref => {
    const cruce = gastosAcumulados.find(point => point.y >= ref.saldo);
    const texto = document.createElement("span");
    const fecha = cruce ? new Date(cruce.x + "T00:00:00").toLocaleDateString("es-ES") : null;
    texto.textContent = `${ref.nombre}: ${fecha ? "alcanzado el " + fecha : "no se alcanza en el período"}`;
    cruces.appendChild(texto);
  });

  const chart = new ApexCharts(document.querySelector("#Website-Visit"), {

    series: [
      {
        name: "Saldo banco previsto",
        type: "area",
        data: saldoDiario
      },
      {
        name: "Saldo Banco",
        type: "area",
        data: bancoLine
      },
      {
        name: "Saldo Efectivo",
        type: "area",
        data: saldoefectivoLine
      },
      {
        name: "Saldo Banco + Efectivo",
        type: "area",
        data: bancoCajaLine
      },
      {
        name: "Gasto acumulado",
        type: "line",
        data: gastosAcumulados
      }
    ],
    colors: ["#8fd3b5", "#8fb8df", "#e7b3d1", "#f2c48f", "#dc5960"],
    chart: {
      fontFamily: "Montserrat,sans-serif",
      height: 400,
      type: "area",
      toolbar: { show: false }
    },
    dataLabels: {
      enabled: false // ❌ esto es lo que apaga los valores encima de los puntos
    },
    xaxis: {
      type: 'datetime',
      tickAmount: 10, // controla el número de etiquetas visibles
      labels: {
        style: {
          colors: "#add9bb"
        },
        formatter: function (value, timestamp) {
          const date = new Date(timestamp);
          return date.toLocaleDateString("es-ES", {
            day: "2-digit",
            month: "short"
          }); // ej: "18 ene"
        }
      }
    },
    yaxis: {
        labels: {
            style: { colors: "#add9bb" },
            formatter: function (value) {
                return new Intl.NumberFormat('es-ES', {
                    style: 'currency',
                    currency: 'EUR',
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0
                }).format(value);
            }
        }
    },
    stroke: {
      curve: "smooth",
      colors: ["#8fd3b5", "#8fb8df", "#e7b3d1", "#f2c48f", "#dc5960"],
      width: [3, 2, 2, 2, 3]
    },
    markers: {
      size: 0,
      strokeColors: "#fff",
      hover: { size: 6 }
    },
    fill: {
      type: ["gradient", "gradient", "gradient", "gradient", "solid"],
      opacity: 1,
      gradient: {
        shade: "light",
        type: "vertical",
        shadeIntensity: 0,
        inverseColors: false,
        opacityFrom: 0.6,
        opacityTo: 0,
        stops: [0, 50, 100],
      },
    },
  annotations: {
    yaxis: [{
      y: 0,
      borderColor: '#c58b8b',
      strokeDashArray: 4,
      label: { text: 'Saldo cero', style: { background: '#c58b8b', color: '#fff' } }
    }]
  },
  tooltip: {
      shared: true,
      intersect: false,
      x: { format: "dd/MM/yy" },
      theme: "dark",
      y: {
          formatter: function (value) {
              return new Intl.NumberFormat('es-ES', {
                  style: 'currency',
                  currency: 'EUR',
                  minimumFractionDigits: 2,
                  maximumFractionDigits: 2
              }).format(value);
          }
      }
  },

  });

  chart.render();
});

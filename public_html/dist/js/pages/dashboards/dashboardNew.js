document.addEventListener("DOMContentLoaded", function () {
  
  // -----------------------------------------------------------------------
  // Sales overview
  // -----------------------------------------------------------------------




  const chart = new ApexCharts(document.querySelector("#Website-Visit"), {

    series: [
      {
        name: "Saldo banco previsto",
        data: forecast
      },
      {
        name: "Saldo Banco",
        data: bancoLine
      },
      {
        name: "Saldo Efectivo",
        data: saldoefectivoLine
      },      
      {
        name: "Saldo Banco + Efectivo",
        data: bancoCajaLine
      }
    ],
    colors: ["#8fd3b5", "#8fb8df", "#e7b3d1", "#f2c48f"],
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
      curve: "stepline",
      width: [3, 1, 1, 1]
    },
    markers: {
      size: 4,
      strokeColors: "#fff",
      hover: { size: 6 }
    },
    fill: {
      type: "solid",
      gradient: {
        shade: "light",
        type: "vertical",
        shadeIntensity: 0.5,
        opacityFrom: 0.15,
        opacityTo: 0.05,
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

document.addEventListener("DOMContentLoaded", function () {
  
  // -----------------------------------------------------------------------
  // Sales overview
  // -----------------------------------------------------------------------




  const chart = new ApexCharts(document.querySelector("#Website-Visit"), {

    series: [
      {
        name: "Gasto acumulado",
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
    colors: ["#06d55c", "#1e88e5", "#fe88e5", "#f39c12"],
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
      labels: { style: { colors: "#add9bb" } }
    },
    stroke: {
      curve: "smooth",
      width: 2
    },
    markers: {
      size: 0,
      strokeColors: "transparent"
    },
    fill: {
      type: "gradient",
      gradient: {
        shade: "light",
        type: "vertical",
        shadeIntensity: 0.5,
        opacityFrom: 0.5,
        opacityTo: 0.3,
        stops: [0, 50, 100],
      },
    },
    tooltip: {
      x: { format: "dd/MM/yy" },
      theme: "dark"
    },

  });

  chart.render();
});

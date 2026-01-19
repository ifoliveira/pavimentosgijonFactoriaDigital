// -------------------------------------------------------------------------------------------------------------------------------------------
// Dashboard 1 : Chart Init Js
// -------------------------------------------------------------------------------------------------------------------------------------------
$(function () {
  "use strict";
  var i = 1;
  var tarjeta = [0,0,0,0,0,0,0,0,0,0,0,0];
  var resta =0;
  var total = [0,0,0,0,0,0,0,0,0,0,0,0];
  var fecha = [];
  var obra = [0,0,0,0,0,0,0,0,0,0,0,0];
  var forecast = [];
  var forecastBank = [];
  var fechaBank =[];
  var caja = [];
  var banco = [];
  do {
    var selector = document.querySelector("#datosmes"+i);
    if (selector !== null) {
      tarjeta[i-1] = selector.dataset.venta;
      i++;
    }else{
      i=13;
    }
  } while (i<13)
  i = 1;
  do {
    var selector = document.querySelector("#efectivo"+i);
    if (selector !== null) {
      total[i-1] = parseFloat(selector.dataset.venta);

      i++;
    }else{
      i=13;
    }
  } while (i<13)

  i = 0;
  do {
    var selector = document.querySelector("#fechfore"+i);

    if (selector !== null) {	
      if (selector.classList.contains( 'Banco' )){
      fechaBank [i] = selector.dataset.venta;
      } 
    
      fecha[i] = selector.dataset.venta;
      i++;
    }else{
      i=42;
    }
  } while (i<42)

  i = 0;
  do {
    var selector = document.querySelector("#forecast"+i);
    if (selector !== null) {
      if (selector.classList.contains( 'Banco' )){
        forecastBank [i] = parseFloat(selector.dataset.venta - resta).toFixed(2);
      } else {
        resta = resta + (selector.dataset.venta - forecast[i-1]) ;
      }
      forecast[i] = parseFloat(selector.dataset.venta).toFixed(2);
      i++;
    }else{
      i=42;
    }
  } while (i<42)
  var selector = document.querySelector("#cajatotal");
  i = 0;
  do {
    caja[i] = parseFloat(selector.dataset.caja).toFixed(2);
    i++;
  } while (i<42)

  var selector = document.querySelector("#bancototal");
  i = 0;
  do {
    banco[i] = parseFloat(selector.dataset.banco).toFixed(2);
    i++;
  } while (i<42);


  // -----------------------------------------------------------------------
  // Sales overview
  // -----------------------------------------------------------------------

  var options_Sales_Overview = {
    series: [
      {
        name: "Efectivo",
        data: [total[0],total[1],total[2],total[3],total[4],total[5],total[6],total[7],total[8],total[9],total[10],total[11]],
      },
      {
        name: "Tarjeta",
        data: [tarjeta[0],tarjeta[1],tarjeta[2],tarjeta[3],tarjeta[4],tarjeta[5],tarjeta[6],tarjeta[7],tarjeta[8],tarjeta[9],tarjeta[10],tarjeta[11]],
      },
      {
        name: "Sales ",
        data: [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
      },
    ],
    chart: {
      height: 345,
      type: "area",
      stacked: true,
      fontFamily: "Montserrat,sans-serif",
      zoom: {
        enabled: false,
      },
      toolbar: {
        show: false,
      },
    },
    colors: ["#e9edf2", "#398bf7", "#7460ee"],
    dataLabels: {
      enabled: false,
    },
    stroke: {
      show: false,
    },
    fill: {
      type: "solid",
      colors: ["#e9edf2", "#398bf7", "#7460ee"],
      opacity: 1,
    },
    markers: {
      size: 3,
      strokeColors: "#fff",
      strokeWidth: 0,
      colors: ["#e9edf2", "#398bf7", "#7460ee"],
    },
    grid: {
      borderColor: "rgba(0,0,0,.1)",
    },
    xaxis: {
      categories: [
        "Ene",
        "Feb",
        "Mar",
        "Abr",
        "May",
        "Jun",
        "Jul",
        "Ago",
        "Sep",
        "Oct",
        "Nov",
        "Dic",
      ],
      labels: {
        style: {
          colors: [
            "#a1aab2",
            "#a1aab2",
            "#a1aab2",
            "#a1aab2",
            "#a1aab2",
            "#a1aab2",
            "#a1aab2",
            "#a1aab2",
            "#a1aab2",
            "#a1aab2",
            "#a1aab2",
            "#a1aab2",
            "#a1aab2",
          ],
        },
      },
    },
    yaxis: {
      labels: {
        style: {
          colors: [
            "#a1aab2",
            "#a1aab2",
            "#a1aab2",
            "#a1aab2",
            "#a1aab2",
            "#a1aab2",
            "#a1aab2",
          ],
        },
      },
    },
    legend: {
      show: false,
    },
    tooltip: {
      theme: "dark",
      marker: {
        show: true,
      },
    },
  };

  var chart_line_overview = new ApexCharts(
    document.querySelector("#Sales-Overview"),
    options_Sales_Overview
  );
  chart_line_overview.render();



  // -----------------------------------------------------------------------
  // Website Visitor
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

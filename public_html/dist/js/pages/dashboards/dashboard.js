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
  // Visitor
  // -----------------------------------------------------------------------

  var option_Visit_Separation = {
    series: [50, 40, 30, 10],
    labels: ["Mobile", "Tablet", "Other", "Desktop"],
    chart: {
      type: "donut",
      fontFamily: "Montserrat,sans-serif",
      height: 225,
      offsetY: 30,
      width: "100%",
    },
    dataLabels: {
      enabled: false,
    },
    stroke: {
      width: 0,
    },
    plotOptions: {
      pie: {
        expandOnClick: true,
        donut: {
          size: "80",
          labels: {
            show: true,
            name: {
              show: true,
              offsetY: 10,
            },
            value: {
              show: false,
            },
            total: {
              show: true,
              color: "#000",
              fontFamily: "Montserrat,sans-serif",
              fontSize: "26px",
              fontWeight: 600,
              label: "Visits",
            },
          },
        },
      },
    },
    colors: ["#1e88e5", "#26c6da", "#eceff1", "#745af2"],
    tooltip: {
      show: true,
      fillSeriesColor: false,
    },
    legend: {
      show: false,
    },
    responsive: [
      {
        breakpoint: 1025,
        options: {
          chart: {
            height: 220,
            width: 220,
          },
        },
      },
      {
        breakpoint: 769,
        options: {
          chart: {
            height: 250,
            width: 250,
          },
        },
      },
    ],
  };

  var chart_pie_donut_status = new ApexCharts(
    document.querySelector("#Visit-Separation"),
    option_Visit_Separation
  );
  chart_pie_donut_status.render();

  // -----------------------------------------------------------------------
  // Website Visitor
  // -----------------------------------------------------------------------

  var option_Website_visit = {
    series: [
      {
        name: "Series A View ",
        data: forecast,
      },
      {
        name: "Series B View ",
        data: banco,
      },
    ],
    chart: {
      fontFamily: "Montserrat,sans-serif",
      height: 400,
      type: "area",
      toolbar: {
        show: false,
      },
    },
    grid: {
      show: true,
      borderColor: "rgba(0,0,0,.1)",
      xaxis: {
        lines: {
          show: true,
        },
      },
      yaxis: {
        lines: {
          show: true,
        },
      },
    },
    colors: ["#06d79c", "#398bf7"],
    fill: {
      type: "gradient",
      opacity: 0.5,
      gradient: {
        shade: "light",
        type: "vertical",
        shadeIntensity: 0.5,
        gradientToColors: undefined, // optional, if not defined - uses the shades of same color in series
        inverseColors: true,
        opacityFrom: 0.5,
        opacityTo: 0.3,
        stops: [0, 50, 100],
        colorStops: [],
      },
    },
    dataLabels: {
      enabled: false,
    },
    stroke: {
      curve: "smooth",
      width: 2,
    },
    markers: {
      size: 3,
      strokeColors: "transparent",
    },
    xaxis: {
      categories: [fecha[0], fecha[1],fecha[2],fecha[3],fecha[4],fecha[5],fecha[6],fecha[7],fecha[8],fecha[9],
      fecha[10], fecha[11],fecha[12],fecha[13],fecha[14],fecha[15],fecha[16],fecha[17],fecha[18],fecha[19],
      fecha[20], fecha[21],fecha[22],fecha[23],fecha[24],fecha[25],fecha[26],fecha[27],fecha[28],fecha[29],
      fecha[30], fecha[31],fecha[32],fecha[33],fecha[34],fecha[35],fecha[36],fecha[37],fecha[38],fecha[39]],
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
          ],
        },
      },
      axisBorder: {
        color: "rgba(0,0,0,0.5)",
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
    tooltip: {
      x: {
        format: "dd/MM/yy HH:mm",
      },
      theme: "dark",
    },
    legend: {
      show: false,
    },
  };

  var chart_area_spline = new ApexCharts(
    document.querySelector("#Website-Visit"),
    option_Website_visit
  );
  chart_area_spline.render();
});

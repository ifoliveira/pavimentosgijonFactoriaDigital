/*
Template Name: Admin Pro Admin
Author: Themedesigner
Email: niravjoshi87@gmail.com
File: js
*/
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
				total[i-1] = parseFloat(selector.dataset.venta) + parseFloat(tarjeta[i-1]);

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
		} while (i<42)



		// ============================================================== 
		// Sales overview
		// ============================================================== 
		 new Chartist.Line('#sales-overview', {

			
				labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
				, series: [
				  {meta:"Venta total (€)", data: total }
				, {meta:"Tarjeta (€)", data: tarjeta}
				, {meta:"Obra (€)" , data: obra}
			
			]
		}, {
				low: 0
				, high:20000
				, showArea: true
				, fullWidth: true
				, showLine: false
				, chartPadding: 10
				, axisX: {
						showLabel: true
						, divisor: 1
						, showGrid: false
						, offset: 50
				}
				, plugins: [
						Chartist.plugins.tooltip()
					], 
					// As this is axis specific we need to tell Chartist to use whole numbers only on the concerned axis
				axisY: {
						onlyInteger: true
						, showLabel: true
						, scaleMinSpace: 50
						, showGrid: true
						, offset: 10,
						labelInterpolationFnc: function(value) {
							return (value / 100) + 'k'
						},
				}
		});

		// ============================================================== 
		// Website Visitor
		// ============================================================== 

		var chart = new Chartist.Line('.website-visitor', {
					labels: [fecha[0], fecha[1],fecha[2],fecha[3],fecha[4],fecha[5],fecha[6],fecha[7],fecha[8],fecha[9],
					         fecha[10], fecha[11],fecha[12],fecha[13],fecha[14],fecha[15],fecha[16],fecha[17],fecha[18],fecha[19],
							 fecha[20], fecha[21],fecha[22],fecha[23],fecha[24],fecha[25],fecha[26],fecha[27],fecha[28],fecha[29],
							 fecha[30], fecha[31],fecha[32],fecha[33],fecha[34],fecha[35],fecha[36],fecha[37],fecha[38],fecha[39],],
					series: [forecast
						, caja , forecastBank, banco,
					]}, {
					low: 0,
					high: 15000,
					showArea: true,
					fullWidth: true,
					plugins: [
						Chartist.plugins.tooltip()
					],
						axisY: {
						onlyInteger: true
						, scaleMinSpace: 40    
						, offset: 30
						, labelInterpolationFnc: function (value) {
								return (value / 1000) + 'k';
						}
				},
				});
				// Offset x1 a tiny amount so that the straight stroke gets a bounding box
				// Straight lines don't get a bounding box 
				// Last remark on -> http://www.w3.org/TR/SVG11/coords.html#ObjectBoundingBox
				chart.on('draw', function(ctx) {  
					if(ctx.type === 'area') {    
						ctx.element.attr({
							x1: ctx.x1 + 0.001
						});
					}
				});

				// Create the gradient definition on created event (always after chart re-render)
				chart.on('created', function(ctx) {
					var defs = ctx.svg.elem('defs');
					defs.elem('linearGradient', {
						id: 'gradient',
						x1: 0,
						y1: 1,
						x2: 0,
						y2: 0
					}).elem('stop', {
						offset: 0,
						'stop-color': 'rgba(255, 255, 255, 1)'
					}).parent().elem('stop', {
						offset: 1,
						'stop-color': 'rgba(38, 198, 218, 1)'
					});
				});
				
});

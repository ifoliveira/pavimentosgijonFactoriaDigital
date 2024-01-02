const $estado = document.querySelector("#estado"),
$listaDeImpresoras = document.querySelector("#listaDeImpresoras"),
$btnLimpiarLog = document.querySelector("#btnLimpiarLog"),
$btnImprimir = document.querySelector("#btnImprimir");

function ticket_Epson(tipopago, importesnal) 
{   

    const Price = new Intl.NumberFormat('de-DE',
                {minimumFractionDigits: 2 });
        // Select elements by their data attribute
        const entryInfoElements =
            document.querySelectorAll('[data-entry-info]');

        // Map over each element and extract the data value
        const entryInfoObjects =
                Array.from(entryInfoElements).map(
                item => JSON.parse(item.dataset.entryInfo)
            );
        let total = 0;

        let iva = 0;
    let descripcion='';
    let now = new Date();
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    const loguear = texto => $estado.textContent += (new Date()).toLocaleString() + " " + texto + "\n";


    const nombreImpresora = "EPSONTicket"; // Puede ser obtenida de la lista de impresoras o puedes escribirlo si lo conoces
    const conector = new ConectorPluginV3();

    conector.Iniciar();
    conector.EstablecerTamañoFuente(1, 1);
    conector.DeshabilitarElModoDeCaracteresChinos();
     conector.EstablecerAlineacion(ConectorPluginV3.ALINEACION_CENTRO);
    conector.Corte(1);
    conector.Feed(1);
    conector.EscribirTexto("P A V I M E N T O S    G I J O N\n");
    conector.Feed(1);
    conector.EscribirTexto("C.I.F. 53543499-M \n");
    conector.Feed(1);
    conector.EscribirTexto("Avenida Schultz Nº 28 Bajo\n");
    conector.Feed(1);
    conector.EscribirTexto("Telefono: 985-391-326\n");
    conector.EscribirTexto("Whatsapp: 684-608-811\n");
    conector.EscribirTexto("Tambien en https://www.pavimentosgijon.es   \n");
    conector.Feed(1);
    conector.EscribirTexto("-----------------------------------------------\n");
    conector.EscribirTexto("FACTURA SIMPLIFICADA\n");
    conector.EscribirTexto("-----------------------------------------------\n");
    conector.Feed(1);
        entryInfoObjects[0].forEach(function(cantidad,index) {


            cantidad.forEach(function(element,index) {
               
               parcial = (element.cantidad * element.precio)
               total = total + parcial
               cantidad = element.cantidad > 9 ? "" + element.cantidad: "0" + element.cantidad;
               descripcion = element.descripcion.substr(0,28);
                
               $linea = '  ' + cantidad + '  ' + descripcion.padEnd(28) + ' ' + parcial.toLocaleString('de-DE', {minimumFractionDigits: 2, maximumFractionDigits: 2}).padStart(9) + ' EUR' + "\n";
               conector.EscribirTexto($linea);
            });

    iva = (total * 0.21 )/1,21
    conector.EscribirTexto("-----------------------------------------------\n");
    $linea = '  IVA 21%:                         ' + iva.toLocaleString('de-DE', {minimumFractionDigits: 2, maximumFractionDigits: 2}).padStart(9) + ' EUR' + "\n";	
    conector.EscribirTexto($linea);
    conector.EstablecerEnfatizado(true);
    $linea = '  TOTAL :                          ' + total.toLocaleString('de-DE', {minimumFractionDigits: 2, maximumFractionDigits: 2}).padStart(9) + ' EUR' + "\n";	
    conector.EscribirTexto($linea);
        });
    conector.EstablecerEnfatizado(false);
    conector.EstablecerAlineacion(ConectorPluginV3.ALINEACION_CENTRO);
    conector.EscribirTexto("-----------------------------------------------\n");
    conector.Feed(1);
    conector.ImprimirCodigoQr("https://www.pavimentosgijon.es", 160, ConectorPluginV3.RECUPERACION_QR_MEJOR, ConectorPluginV3.TAMAÑO_IMAGEN_NORMAL);
    conector.Feed(1);
    conector.EstablecerEnfatizado(true);
    conector.EscribirTexto("***Gracias por su compra***");
    conector.Feed(4);
    conector.Corte(1);
    
    conector.imprimirEn(nombreImpresora);

};  
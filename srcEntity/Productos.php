<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Productos
 *
 * @ORM\Table(name="productos", indexes={@ORM\Index(name="tipoprod", columns={"Tipo_Pd"})})
 * @ORM\Entity
 */
class Productos
{
    /**
     * @var int
     *
     * @ORM\Column(name="id_Pd", type="integer", nullable=false, options={"comment"="Es el identificador del producto, tendra un incremental a medida que los vayamos dando de alta en la BBDD"})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $idPd;

    /**
     * @var string
     *
     * @ORM\Column(name="Descripcion_Pd", type="string", length=250, nullable=false, options={"comment"="Descripción del producto, texto identificatiivo de lo que es el producto"})
     */
    private $descripcionPd;

    /**
     * @var string
     *
     * @ORM\Column(name="Precio_Pd", type="decimal", precision=10, scale=2, nullable=false, options={"comment"="Precio de adquisición, coste para la empresa incluido el IVA"})
     */
    private $precioPd;

    /**
     * @var float
     *
     * @ORM\Column(name="PVP_Pd", type="float", precision=10, scale=2, nullable=false, options={"comment"="Precio de venta al público IVA incluido"})
     */
    private $pvpPd;

    /**
     * @var int
     *
     * @ORM\Column(name="Stock_Pd", type="integer", nullable=false, options={"comment"="Numero de elementos que tenemos en Stock, se tendrá que ir refrescando a medida que hacemos facturas o se compran productos"})
     */
    private $stockPd = '0';

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="FechaAlta_Pd", type="datetime", nullable=true, options={"default"="CURRENT_TIMESTAMP","comment"="Timestamp de alta en la tabla"})
     */
    private $fechaaltaPd = 'CURRENT_TIMESTAMP';

}

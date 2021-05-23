<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Banco
 *
 * @ORM\Table(name="banco")
 * @ORM\Entity
 */
class Banco
{
    /**
     * @var int
     *
     * @ORM\Column(name="id_bn", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $idBn;

    /**
     * @var string
     *
     * @ORM\Column(name="categoria_bn", type="text", length=0, nullable=false)
     */
    private $categoriaBn;

    /**
     * @var float
     *
     * @ORM\Column(name="importe_bn", type="float", precision=10, scale=0, nullable=false)
     */
    private $importeBn;

    /**
     * @var string
     *
     * @ORM\Column(name="concepto_bn", type="text", length=0, nullable=false)
     */
    private $conceptoBn;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="fecha_bn", type="date", nullable=false)
     */
    private $fechaBn;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="timestamp_bn", type="datetime", nullable=false, options={"default"="CURRENT_TIMESTAMP"})
     */
    private $timestampBn = 'CURRENT_TIMESTAMP';


}

<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * ENTIDAD MANOOBRATEXTOSELECCIONADO
 * ==================================
 * Representa la selección de un texto de catálogo (TextoManoObra)
 * dentro de una categoría de mano de obra (ManoObra) para un documento.
 *
 * PROPÓSITO:
 * ----------
 * Permite almacenar de forma estructurada qué textos del catálogo han sido
 * seleccionados por el usuario en el constructor visual de mano de obra.
 *
 * De esta forma:
 * - Se evita depender de texto concatenado en ManoObra.textoMo
 * - Se puede reconstruir fácilmente la selección en la interfaz (precarga)
 * - Se mantiene consistencia incluso si cambia el texto manual
 *
 * RELACIONES:
 * -----------
 * - ManoObra: representa la categoría dentro del documento (Albañilería, etc.)
 * - TextoManoObra: representa cada texto seleccionable del catálogo
 *
 * ORDEN:
 * ------
 * El campo "orden" permite controlar el orden en que se mostrarán los textos
 * seleccionados al imprimir o visualizar el documento.
 *
 * RESTRICCIONES:
 * --------------
 * Se recomienda una restricción única (mano_obra_id + texto_mano_obra_id)
 * para evitar duplicados dentro de una misma categoría.
 *
 * EJEMPLO:
 * --------
 * Documento → ManoObra (Albañilería)
 *              ├─ Texto: "Desmontar loza..."
 *              ├─ Texto: "Preparar paredes..."
 *              └─ Texto: "Colocar plato ducha"
 *
 * TEXTO FINAL:
 * ------------
 * El texto final del documento se construye dinámicamente:
 * - Textos seleccionados (ordenados)
 * - + texto manual adicional (ManoObra.textoMo)
 */
#[ORM\Entity]
#[ORM\Table(
    name: 'mano_obra_texto_seleccionado',
    uniqueConstraints: [
        new ORM\UniqueConstraint(
            name: 'uniq_mano_obra_texto',
            columns: ['mano_obra_id', 'texto_mano_obra_id']
        )
    ]
)]
class ManoObraTextoSeleccionado
{
    // -------------------------------------------------------------------------
    // IDENTIFICACIÓN
    // -------------------------------------------------------------------------

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    // -------------------------------------------------------------------------
    // RELACIÓN CON MANO DE OBRA (CATEGORÍA EN DOCUMENTO)
    // -------------------------------------------------------------------------

    /**
     * Categoría de mano de obra dentro de un documento.
     * Ejemplo: Albañilería, Fontanería, Electricidad...
     */
    #[ORM\ManyToOne(
        targetEntity: ManoObra::class,
        inversedBy: 'seleccionesTexto'
    )]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ManoObra $manoObra = null;

    // -------------------------------------------------------------------------
    // RELACIÓN CON TEXTO DE CATÁLOGO
    // -------------------------------------------------------------------------

    /**
     * Texto de catálogo seleccionado.
     * Ejemplo: "Desmontar loza y derribo de bañera..."
     */
    #[ORM\ManyToOne(targetEntity: TextoManoObra::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?TextoManoObra $textoManoObra = null;

    // -------------------------------------------------------------------------
    // ORDEN
    // -------------------------------------------------------------------------

    /**
     * Orden de aparición del texto dentro de la categoría.
     * Permite mantener coherencia visual en impresión o interfaz.
     */
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $orden = null;

    // -------------------------------------------------------------------------
    // GETTERS / SETTERS
    // -------------------------------------------------------------------------

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getManoObra(): ?ManoObra
    {
        return $this->manoObra;
    }

    public function setManoObra(?ManoObra $manoObra): self
    {
        $this->manoObra = $manoObra;
        return $this;
    }

    public function getTextoManoObra(): ?TextoManoObra
    {
        return $this->textoManoObra;
    }

    public function setTextoManoObra(?TextoManoObra $textoManoObra): self
    {
        $this->textoManoObra = $textoManoObra;
        return $this;
    }

    public function getOrden(): ?int
    {
        return $this->orden;
    }

    public function setOrden(?int $orden): self
    {
        $this->orden = $orden;
        return $this;
    }
}
<?php

namespace App\Form;

use App\Entity\Proyecto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Edición de la información descriptiva del proyecto.
 *
 * El cliente se asigna mediante su flujo específico para mantener sincronizados
 * los presupuestos que se hayan creado sin cliente.
 */
class ProyectoInformacionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nombre', TextType::class, [
                'label' => 'Descripción del proyecto',
                'required' => true,
                'help' => 'Usa una referencia clara: obra, dirección o cualquier dato que permita identificarla.',
                'attr' => [
                    'placeholder' => 'Ej.: Reforma del piso de la calle Corrida 12',
                ],
            ])
            ->add('notas', TextareaType::class, [
                'label' => 'Notas',
                'required' => false,
                'help' => 'Observaciones internas que necesites conservar para esta obra.',
                'attr' => [
                    'rows' => 7,
                    'placeholder' => 'Añade observaciones, medidas pendientes, acuerdos o cualquier detalle útil...',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Proyecto::class,
        ]);
    }
}

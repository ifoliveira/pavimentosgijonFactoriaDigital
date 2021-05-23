<?php

namespace App\Form;

use App\Entity\Cestas;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\DateType;

class CestasType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('fechaCs', DateType::class, array(
                'widget' => 'single_text',
                'html5' => false,
                'label' => 'Fecha del ticket'))
            ->add('importeTotCs',null ,  array(
                'label' => 'Importe' ))
            ->add('descuentoCs',null ,  array(
                'label' => 'Descuento' ))
            ->add('tipopagoCs',null ,  array(
                'label' => 'Tipo pago' ))
            ->add('estadoCs',null ,  array(
                'label' => 'Estado' ))
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Cestas::class,
        ]);
    }
}

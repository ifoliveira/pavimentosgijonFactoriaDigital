<?php

namespace App\Form;

use App\Entity\Forecast;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\DateType;

class ForecastType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('conceptoFr',null ,  array(
                'label' => 'Concepto' ))
            ->add('fechaFr', DateType::class, array(
                'widget' => 'single_text',
                'input' => 'datetime',
                'format' => 'yyyy-MM-dd',
                'html5' => false,
                'label' => 'Fecha del movimiento'))
            ->add('importeFr',null ,  array(
                'label' => 'Importe' ))
            ->add('origenFr',null ,  array(
                'label' => 'Efectivo  / Banco' ))
            ->add('tipoFr',null ,  array(
                'label' => 'Tipo ' ))
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Forecast::class,
        ]);
    }
}

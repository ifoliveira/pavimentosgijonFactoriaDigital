<?php

namespace App\Form;

use App\Entity\Efectivo;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class EfectivoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('tipoEf',null ,  array(
                'label' => 'Tipo de movimiento' ))
            ->add('conceptoEf',null ,  array(
                'label' => 'Concepto' ))
            ->add('fechaEf', DateType::class, array(
                'widget' => 'single_text',
                'html5' => false,
                'label' => 'Fecha del movimiento'))
            ->add('importeEf',null ,  array(
                'label' => 'Importe' ))

        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Efectivo::class,
        ]);
    }
}

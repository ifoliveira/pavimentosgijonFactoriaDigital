<?php

namespace App\Form;

use App\Entity\Presupuestos;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\PercentType;

class PresupuestosType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('estadoPe',null ,  array(
                'label' => 'Estado del presupuesto' ))
            ->add('manoobraPe',null ,  array(
                  'label' => 'Descripción del presupuesto' ,
                  'attr' => ['placeholder' => 'Bañera por plato / Baño Completo / Mueble ....']))
            ->add('importetotPe', MoneyType::class ,  array(
                   'label' => 'Importe Total' ))
            ->add('descuaetoPe', PercentType::class ,  array(
                    'label' => 'Descuento' ))
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Presupuestos::class,
        ]);
    }

    public function __construct()
    {
        return 'presupuestos';
    }

}

<?php

namespace App\Form;

use App\Entity\Presupuestos;
use App\Entity\Clientes;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
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
            ->add('manoobraPe',TextType::class ,  array(
             'label' => 'Descripción  ...',
                  ))
            ->add('importetotPe', MoneyType::class ,  array(
                   'label' => 'Importe Total' ))
            ->add('importemanoobra', MoneyType::class ,  array(
                    'label' => 'Importe Mano Obra' ))
            ->add('descuaetoPe', PercentType::class ,  array(
                    'label' => 'Descuento' ))
            ->add('clientePe', EntityType::class ,  array(
                        'class' => Clientes::class,
                        'label' => 'Cliente' ))

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

//            ->add('manoobraPe',null ,  array(
//    'label' => 'Descripción del presupuesto' ,
//    'attr' => ['placeholder' => 'Bañera por plato / Baño Completo / Mueble ....']))
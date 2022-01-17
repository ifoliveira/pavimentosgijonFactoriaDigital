<?php

namespace App\Form;

use App\Entity\Presupuestos;
use App\Form\ManoObraType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\PercentType;

class PresupuestosManoObraType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('manoObra', CollectionType::class, ['entry_type' => ManoObraType::class,]);
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
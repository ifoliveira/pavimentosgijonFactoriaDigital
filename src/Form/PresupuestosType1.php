<?php

namespace App\Form;

use App\Entity\Presupuestos;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PresupuestosType1 extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('estadoPe',null ,  array(
                  'label' => 'Estado' ,))
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
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

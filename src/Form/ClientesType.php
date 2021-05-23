<?php

namespace App\Form;

use App\Entity\Clientes;
use App\Entity\Presupuestos;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use App\Form\PresupuestosType;
use Symfony\Component\Form\Extension\Core\Type\DateType;


class ClientesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('nombreCl',null ,  array(
                'label' => 'Nombre' ))
            ->add('apellidosCl',null ,  array(
                'label' => 'Apellidos' ))
            ->add('ciudadCl',null ,  array(
                'label' => 'Ciudad' ))
            ->add('direccionCl',null ,  array(
                'label' => 'Dirección' ))
            ->add('telefono1Cl',null ,  array(
                'label' => 'Telefono' ))
            ->add('telefono2Cl')
            ->add('emailCl')
        ;

        $builder->add('presupuestosCl', CollectionType::class, ['entry_type' => PresupuestosType::class,]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Clientes::class,
        ]);
    }

    public function __construct()
    {
        return 1;
    }
}

<?php

namespace App\Form;

use App\Entity\Clientes;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


class ClientesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nombreCl', TextType::class, [
                'label' => 'Nombre *',
                'attr' => ['class' => 'form-control', 'autocomplete' => 'given-name'],
            ])
            ->add('apellidosCl', TextType::class, [
                'label' => 'Apellidos',
                'required' => false,
                'attr' => ['class' => 'form-control', 'autocomplete' => 'family-name'],
            ])
            ->add('dni', TextType::class, [
                'label' => 'NIF / DNI',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('telefono1Cl', TelType::class, [
                'label' => 'Teléfono principal',
                'required' => false,
                'attr' => ['class' => 'form-control', 'autocomplete' => 'tel'],
            ])
            ->add('telefono2Cl', TelType::class, [
                'label' => 'Teléfono secundario',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('emailCl', EmailType::class, [
                'label' => 'Correo electrónico',
                'required' => false,
                'attr' => ['class' => 'form-control', 'autocomplete' => 'email'],
            ])
            ->add('ciudadCl', TextType::class, [
                'label' => 'Ciudad',
                'required' => false,
                'attr' => ['class' => 'form-control', 'autocomplete' => 'address-level2'],
            ])
            ->add('direccionCl', TextType::class, [
                'label' => 'Dirección',
                'required' => false,
                'attr' => ['class' => 'form-control', 'autocomplete' => 'street-address'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Clientes::class,
        ]);
    }
}

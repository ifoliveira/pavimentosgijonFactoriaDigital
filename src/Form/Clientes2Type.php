<?php
namespace App\Form;

use App\Entity\Clientes;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Clientes2Type extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nombreCl', TextType::class, [
                'label' => 'Nombre *',
                'required' => true,
                'attr' => ['placeholder' => 'Nombre'],
            ])
            ->add('apellidosCl', TextType::class, [
                'label' => 'Apellidos',
                'required' => false,
                'attr' => ['placeholder' => 'Apellidos'],
            ])
            ->add('dni', TextType::class, [
                'label' => 'D.N.I.',
                'required' => false,
                'attr' => ['placeholder' => 'D.N.I.'],
            ])
            ->add('ciudadCl', TextType::class, [
                'label' => 'Ciudad',
                'required' => false,
                'attr' => ['placeholder' => 'Ciudad'],
            ])
            ->add('direccionCl', TextType::class, [
                'label' => 'Dirección',
                'required' => false,
                'attr' => ['placeholder' => 'Dirección'],
            ])
            ->add('telefono1Cl', TelType::class, [
                'label' => 'Teléfono principal',
                'required' => false,
                'attr' => ['placeholder' => '600000000'],
            ])
            ->add('telefono2Cl', TelType::class, [
                'label' => 'Teléfono secundario',
                'required' => false,
                'attr' => ['placeholder' => '600000000'],
            ])
            ->add('emailCl', EmailType::class, [
                'label' => 'Email',
                'required' => false,
            ])
        ;
        
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Clientes::class]);
    }
}
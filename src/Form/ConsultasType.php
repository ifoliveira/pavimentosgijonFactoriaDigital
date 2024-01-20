<?php

namespace App\Form;

use App\Entity\Consultas;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class ConsultasType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nombre', TextType::class, 
                           ['label' => 'Nombre'])
            ->add('email', TextType::class, 
            ['label' => 'Email'])
            ->add('telefono', TextType::class, 
            ['label' => 'Teléfono'])
            ->add('pregunta', TextType::class, 
            ['label' => 'Comentario'])
             
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Consultas::class,
        ]);
    }
}

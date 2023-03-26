<?php

namespace App\Form;

use App\Entity\Economicpresu;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EconomicpresuType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('conceptoEco')
            ->add('importeEco')
            ->add('debehaberEco')
            ->add('aplicaEco')
            ->add('estadoEco')
            ->add('idpresuEco')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Economicpresu::class,
        ]);
    }
}

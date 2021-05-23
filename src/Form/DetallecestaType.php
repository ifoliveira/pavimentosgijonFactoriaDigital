<?php

namespace App\Form;

use App\Entity\Detallecesta;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DetallecestaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('cantidadDc')
            ->add('pvpDc')
            ->add('descuentoDc')
            ->add('cestaDc')
            ->add('productoDc')
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Detallecesta::class,
        ]);
    }
}

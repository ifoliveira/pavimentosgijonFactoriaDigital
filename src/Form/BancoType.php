<?php

namespace App\Form;

use App\Entity\Banco;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BancoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('id_Bn')
            ->add('categoria_Bn')
            ->add('importe_Bn')
            ->add('concepto_Bn')
            ->add('fecha_Bn')
            ->add('timestamp_Bn')
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Banco::class,
        ]);
    }
}

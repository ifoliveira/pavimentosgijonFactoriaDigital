<?php

namespace App\Form;


use App\Entity\Banco;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\DateType;


class Banco1Type extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('categoria_Bn',null ,  array(
                'label' => 'Categoria' ))
            ->add('importe_Bn',null ,  array(
                'label' => 'Importe' ))
            ->add('concepto_Bn',null ,  array(
                'label' => 'Concepto' ))
              
            ->add('fecha_Bn', DateType::class, array(
                'widget' => 'single_text',
                'html5' => false,
                'label' => 'Fecha del ticket'))
            ->add('conciliado',null ,  array(
                    'label' => 'Conciliado' ))                  
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Banco::class,
        ]);
    }
}

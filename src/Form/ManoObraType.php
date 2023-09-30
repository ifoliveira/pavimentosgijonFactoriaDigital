<?php

namespace App\Form;

use App\Entity\ManoObra;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Form\Type\DoctrineType;
use App\Entity\Presupuestos;

class ManoObraType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {

            $builder
            ->add('presupuestoMo')
            ->add('categoriaMo')
            ->add('tipoMo')
            ->add('textoMo', null, array(
                'label' => 'Texto Descripción', 
                'attr' => array('style' => 'height: 200px')
               ))
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => ManoObra::class,
       ]);
    }
}

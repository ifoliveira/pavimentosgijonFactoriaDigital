<?php

namespace App\Form;

use App\Entity\ManoObra;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Form\Type\DoctrineType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use App\Entity\Presupuestos;

class ManoObraType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {

            $builder
            ->add('categoriaMo', null, array(
                'label' => 'Categoria' ))
            ->add('tipoMo', null, array(
                'label' => 'Tipo' ))
            ->add('coste',  MoneyType::class, array(
                    'label' => 'Coste' ))                
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

<?php

namespace App\Form;

use App\Entity\Productos;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProductosType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('descripcion_Pd',null ,  array(
                'label' => 'Descripción' ))
            ->add('precio_Pd',null ,  array(
                'label' => 'Precio' ))
            ->add('pvp_Pd',null ,  array(
                'label' => 'PVP' ))
            ->add('stock_Pd',null ,  array(
                'label' => 'Stock' ))
            ->add('tipo_pd_id',null ,  array(
                'label' => 'Tipo de producto' ));
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Productos::class,
        ]);
    }
}

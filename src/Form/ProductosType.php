<?php

namespace App\Form;

use App\Entity\Productos;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

class ProductosType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('incluir', CheckboxType::class, [
                'mapped' => false, // 👈 clave: este campo NO está en la entidad
                'required' => false,
                'label' => 'Incluir',
                'data' => true, 
            ])

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

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Productos::class,
        ]);
    }
}

<?php

namespace App\Form;

use App\Entity\CatalogoProducto;
use App\Entity\CatalogoProductoConfiguracion;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CatalogoProductoConfiguracionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('producto', EntityType::class, [
                'class' => CatalogoProducto::class,
                'choice_label' => 'nombre',
                'placeholder' => 'Selecciona producto',
            ])
            ->add('configuradorCodigo', TextType::class, [
                'label' => 'Configurador',
            ])
            ->add('uso', TextType::class, [
                'label' => 'Uso',
            ])
            ->add('tipo', TextType::class, [
                'required' => false,
            ])
            ->add('anchoMin', NumberType::class, [
                'required' => false,
                'label' => 'Ancho mínimo',
            ])
            ->add('anchoMax', NumberType::class, [
                'required' => false,
                'label' => 'Ancho máximo',
            ])
            ->add('largoMin', NumberType::class, [
                'required' => false,
                'label' => 'Largo mínimo',
            ])
            ->add('largoMax', NumberType::class, [
                'required' => false,
                'label' => 'Largo máximo',
            ])
            ->add('altoMin', NumberType::class, [
                'required' => false,
                'label' => 'Alto mínimo',
            ])  
            ->add('altoMax', NumberType::class, [
                'required' => false,
                'label' => 'Alto máximo',
            ])
            ->add('prioridad', IntegerType::class)
            ->add('recomendado', CheckboxType::class, [
                'required' => false,
            ])
            ->add('activo', CheckboxType::class, [
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CatalogoProductoConfiguracion::class,
        ]);
    }
}